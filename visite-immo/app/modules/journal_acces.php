<?php
// Journal des accès (RGPD, sécurité) : qui a consulté quel dossier, quel document, quelle pièce d'identité, et quand.
//
//  - Côté agents : consultation d'un dossier (une ligne par quart d'heure et par dossier), PDF ouverts, pièces du
//    vendeur, écoute de l'audio, exports (CRM, données d'une personne), dépôt d'une pièce d'identité (LCB-FT).
//  - Côté clients (liens personnels) : ouverture de l'espace vendeur / acquéreur / notaire, documents et pièces.
//  - Rangé par mois dans data/acces/AAAA-MM.jsonl (une ligne JSON par accès, ajout seul), effacé automatiquement
//    après la durée choisie (réglage « conservation_journal_mois », 12 mois par défaut).
//  - Consultable et exportable (CSV) par un administrateur dans Paramètres ; inclus dans l'export des données
//    d'une personne (droit d'accès).

const ACCES_DIR = '/acces';
/** PDF qui contiennent des données sensibles (identité, situation, LCB-FT). */
const PDF_SENSIBLES = ['vigilance', 'notaire', 'dossier', 'mandat'];

/** Ajoute une ligne au journal. $qui : ['id', 'nom', 'role'] ; $dossier : identifiant du dossier ou null. */
function acces_noter(array $qui, string $action, ?string $dossier = null, string $objet = '', bool $sensible = false, ?string $bien = null): void
{
    $dir = DATA_DIR . ACCES_DIR;
    if (!is_dir($dir)) @mkdir($dir, 0770, true);
    $ligne = [
        'date' => date('c'),
        'qui' => (string) ($qui['nom'] ?? ''),
        'qui_id' => (string) ($qui['id'] ?? ''),
        'role' => (string) ($qui['role'] ?? ''),
        'action' => $action,
        'dossier' => $dossier,
        'bien' => $bien,
        'objet' => mb_substr($objet, 0, 160),
        'sensible' => $sensible,
        'ip' => (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
    ];
    @file_put_contents("$dir/" . date('Y-m') . '.jsonl', json_encode($ligne, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND | LOCK_EX);
}

/** Titre du bien pour le journal (lu une fois, pour que le journal reste lisible même si le dossier est supprimé). */
function acces_bien(array $agent, string $id): ?string
{
    if (!preg_match('/^[\w-]{6,40}$/', $id) || !is_file(visit_dir($agent, $id) . '/visite.json')) return null;
    try {
        return titre_bien(load_visit($agent, $id));
    } catch (Throwable) {
        return null;
    }
}

/** Appelé par l'API avant chaque route : note les accès aux données personnelles. */
function acces_api(string $methode, string $route, string $id): void
{
    $regles = [
        'GET visit' => ['Consultation du dossier', false, true],
        'GET dossier' => ['Consultation du dossier', false, true],
        'GET audio' => ["Écoute de l'audio de la visite", false, true],
        'GET pdf' => ['Document PDF', null, false],
        'GET piece' => ['Pièce du dossier', true, false],
        'GET crm_export' => ['Export de la fiche (CRM)', true, false],
        'GET rgpd_export' => ["Export des données d'une personne", true, false],
        'POST lcbft' => ["Pièce d'identité déposée (LCB-FT)", true, false],
    ];
    $r = $regles["$methode $route"] ?? null;
    if (!$r) return;
    $me = current_user();
    if (!$me) return;
    [$action, $sensible, $regroupe] = $r;
    $objet = '';
    if ($route === 'pdf') {
        $doc = (string) ($_GET['doc'] ?? '');
        $objet = (string) (pdf_docs()[$doc] ?? $doc);
        $sensible = in_array($doc, PDF_SENSIBLES, true);
        if ($doc === 'vigilance') $action = 'Fiche de vigilance LCB-FT (identité)';
    }
    if ($route === 'piece') $objet = (string) ($_GET['f'] ?? '');
    if ($route === 'rgpd_export') $objet = (string) ($_GET['q'] ?? '');
    if ($route === 'lcbft') {
        $objet = (string) ($_POST['partie'] ?? '');
        if (empty($_FILES['piece']['tmp_name'])) $action = 'Contrôle LCB-FT complété';
    }
    $dossier = $id !== '' ? $id : null;
    $bien = $dossier ? acces_bien($me, $dossier) : null;
    if ($dossier && $bien === null) return; // dossier inexistant : rien n'a été consulté
    // Les écrans rafraîchissent le dossier souvent : une seule ligne par quart d'heure et par dossier
    if ($regroupe && session_status() === PHP_SESSION_ACTIVE) {
        $cle = "$route|$dossier";
        if (time() - ($_SESSION['acces_vus'][$cle] ?? 0) < 900) return;
        $_SESSION['acces_vus'][$cle] = time();
    }
    acces_noter(['id' => $me['id'], 'nom' => $me['nom'], 'role' => $me['role'] === 'admin' ? 'administrateur' : 'agent'], $action, $dossier, $objet, (bool) $sensible, $bien);
}

/** Accès par un lien client (espace vendeur, acquéreur, notaire). */
function acces_lien(array $lien, array $v, string $action, string $objet = '', bool $sensible = false): void
{
    $role = (string) $lien['role'];
    $nom = ['vendeur' => trim(champ($v, 'prenom_vendeur') . ' ' . champ($v, 'nom_vendeur')), 'acquereur' => (string) ($v['vente']['acquereur_nom'] ?? ''), 'notaire' => ''][$role] ?? '';
    acces_noter(['id' => 'lien', 'nom' => $nom ?: ucfirst($role), 'role' => "$role (lien personnel)"], $action, $v['id'], $objet, $sensible, titre_bien($v));
}

/** Lignes du journal, les plus récentes d'abord, filtrées (texte libre, dossier, sensibles seulement). */
function acces_lire(array $filtre = [], int $max = 500): array
{
    $fichiers = glob(DATA_DIR . ACCES_DIR . '/*.jsonl') ?: [];
    rsort($fichiers);
    $q = mb_strtolower(trim((string) ($filtre['q'] ?? '')));
    $out = [];
    foreach ($fichiers as $f) {
        $lignes = array_reverse(file($f, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: []);
        foreach ($lignes as $l) {
            $a = json_decode($l, true);
            if (!$a) continue;
            if (!empty($filtre['dossiers']) && !in_array($a['dossier'], $filtre['dossiers'], true)) continue;
            if (!empty($filtre['sensible']) && empty($a['sensible'])) continue;
            if ($q !== '' && !str_contains(mb_strtolower($a['qui'] . ' ' . $a['action'] . ' ' . $a['bien'] . ' ' . $a['objet'] . ' ' . $a['role']), $q)) continue;
            $out[] = $a;
            if (count($out) >= $max) return $out;
        }
    }
    return $out;
}

// Effacement automatique des mois trop anciens
tache_cron('rgpd_journal_acces', function (array $agent, array $dossiers): int {
    global $CONFIG;
    static $fait = false; // la tâche tourne pour chaque agent ; le journal est commun
    if ($fait) return 0;
    $fait = true;
    $mois = max(1, (int) ($CONFIG['conservation_journal_mois'] ?? 12));
    $limite = date('Y-m', strtotime("-$mois months", maintenant()));
    $n = 0;
    foreach (glob(DATA_DIR . ACCES_DIR . '/*.jsonl') ?: [] as $f) {
        if (basename($f, '.jsonl') < $limite) { @unlink($f); $n++; }
    }
    return $n;
});

route('GET acces', function () {
    require_admin();
    send_json(acces_lire(['q' => $_GET['q'] ?? '', 'sensible' => !empty($_GET['sensible'])], 200));
});

route('GET acces_csv', function () {
    $me = require_admin();
    $lignes = acces_lire(['q' => $_GET['q'] ?? '', 'sensible' => !empty($_GET['sensible'])], 100000);
    acces_noter(['id' => $me['id'], 'nom' => $me['nom'], 'role' => 'administrateur'], 'Export du journal des accès', null, (string) ($_GET['q'] ?? ''), true);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="journal-acces-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // accents corrects dans Excel
    fputcsv($out, ['Date', 'Qui', 'Rôle', 'Action', 'Bien', 'Objet', 'Sensible', 'Adresse IP'], ';');
    foreach ($lignes as $a) fputcsv($out, [date('d/m/Y H:i:s', strtotime($a['date'])), $a['qui'], $a['role'], $a['action'], $a['bien'] ?? '', $a['objet'], $a['sensible'] ? 'oui' : '', $a['ip']], ';');
    exit;
});
