<?php
// Stockage commun à toutes les fonctions : dossiers (un dossier = un bien, né d'une visite), fichiers par agent
// (acquéreurs, agenda, tâches…), liens sécurisés pour les clients, journal des actions automatiques.
// Tout reste en fichiers JSON ; ces fonctions sont le seul endroit à réécrire pour passer à une base de données.

// ---------- Agents ----------

function user_by_id(string $id): ?array
{
    foreach (users() as $u) if ($u['id'] === $id) return $u;
    return null;
}

// ---------- Dossiers ----------

/** Tous les dossiers d'un agent (contenu complet), du plus récent au plus ancien. */
function dossiers(array $user): array
{
    $list = [];
    foreach (glob(visits_dir($user) . '/*/visite.json') ?: [] as $file) {
        $v = read_json($file, null);
        if ($v) $list[] = $v;
    }
    usort($list, fn ($a, $b) => strcmp($b['cree_le'], $a['cree_le']));
    return $list;
}

/** Retrouve un dossier sans connaître son agent (liens clients, tâches automatiques) : [agent, dossier]. */
function chercher_dossier(string $id): ?array
{
    if (!valid_id($id)) return null;
    foreach (users() as $u) {
        $file = visits_dir($u) . "/$id/visite.json";
        if (is_file($file)) return [$u, read_json($file)];
    }
    return null;
}

/** Ajoute une ligne au journal du dossier (actions automatiques et envois, visibles dans l'appli). */
function journal_ajout(array &$v, string $type, string $texte): void
{
    $v['journal'][] = ['date' => date('c'), 'type' => $type, 'texte' => $texte];
    if (count($v['journal']) > 300) $v['journal'] = array_slice($v['journal'], -300);
}

function journaliser(array $user, string $id, string $type, string $texte): void
{
    try {
        update_visit($user, $id, function (array $v) use ($type, $texte) {
            journal_ajout($v, $type, $texte);
            return $v;
        });
    } catch (Throwable) {
        // dossier supprimé entre-temps
    }
}

function champ(array $visit, string $cle): string
{
    return trim((string) (((array) ($visit['fiche']['champs'] ?? []))[$cle]['valeur'] ?? ''));
}

// ---------- Fichiers par agent (acquéreurs, agenda, tâches, messages…) ----------

function agent_file(array $user, string $nom): string
{
    if (!preg_match('/^[a-z_]{2,30}$/', $nom)) fail(400, 'Collection inconnue.');
    return DATA_DIR . '/agents/' . $user['id'] . "/$nom.json";
}

function collection(array $user, string $nom): array
{
    return read_json(agent_file($user, $nom), []);
}

function collection_maj(array $user, string $nom, callable $fn): array
{
    return update_json(agent_file($user, $nom), $fn);
}

/** Ajoute ou remplace un élément (par son id) dans une collection et le renvoie. */
function collection_enregistrer(array $user, string $nom, array $item): array
{
    $item['id'] ??= nouvel_id(substr($nom, 0, 1));
    $item['modifie_le'] = date('c');
    $item['cree_le'] ??= date('c');
    collection_maj($user, $nom, function (array $list) use ($item) {
        foreach ($list as $i => $x) {
            if (($x['id'] ?? '') === $item['id']) {
                $list[$i] = $item;
                return $list;
            }
        }
        $list[] = $item;
        return $list;
    });
    return $item;
}

function collection_trouver(array $user, string $nom, string $id): ?array
{
    foreach (collection($user, $nom) as $x) if (($x['id'] ?? '') === $id) return $x;
    return null;
}

function collection_supprimer(array $user, string $nom, string $id): void
{
    collection_maj($user, $nom, fn (array $list) => array_values(array_filter($list, fn ($x) => ($x['id'] ?? '') !== $id)));
}

function nouvel_id(string $prefixe = 'x'): string
{
    return $prefixe . date('ymd') . bin2hex(random_bytes(4));
}

// ---------- Liens sécurisés pour les clients (vendeur, acquéreur, notaire) ----------

/**
 * Crée un lien personnel sans mot de passe vers un dossier. $role : vendeur, acquereur, notaire, signature…
 * Le jeton est long et aléatoire ; il expire et peut être révoqué.
 */
function lien_creer(array $user, string $dossierId, string $role, array $extra = [], int $jours = 90): string
{
    $token = rtrim(strtr(base64_encode(random_bytes(24)), '+/', '-_'), '=');
    update_json(DATA_DIR . '/liens.json', function (array $liens) use ($token, $user, $dossierId, $role, $extra, $jours) {
        $now = time();
        $liens = array_filter($liens, fn ($l) => ($l['expire'] ?? 0) > $now); // ménage
        $liens[$token] = ['agent' => $user['id'], 'dossier' => $dossierId, 'role' => $role, 'cree' => $now, 'expire' => $now + $jours * 86400] + $extra;
        return $liens;
    });
    return $token;
}

/** Lien existant et encore valide pour ce dossier et ce rôle (évite d'en créer un nouveau à chaque e-mail). */
function lien_pour(array $user, string $dossierId, string $role, array $extra = []): string
{
    $now = time();
    foreach (read_json(DATA_DIR . '/liens.json', []) as $t => $l) {
        if ($l['dossier'] === $dossierId && $l['role'] === $role && $l['agent'] === $user['id'] && $l['expire'] > $now + 7 * 86400
            && array_intersect_assoc($extra, $l) == $extra) return $t;
    }
    return lien_creer($user, $dossierId, $role, $extra);
}

function lien_lire(string $token): ?array
{
    if (!preg_match('/^[A-Za-z0-9_-]{20,64}$/', $token)) return null;
    $l = read_json(DATA_DIR . '/liens.json', [])[$token] ?? null;
    if (!$l || $l['expire'] < time()) return null;
    return $l;
}

function lien_revoquer(string $token): void
{
    update_json(DATA_DIR . '/liens.json', function (array $liens) use ($token) {
        unset($liens[$token]);
        return $liens;
    });
}

/** Adresse publique du site (pour les liens envoyés par e-mail, y compris depuis les tâches automatiques). */
function url_publique(string $chemin = ''): string
{
    global $CONFIG;
    $base = rtrim((string) ($CONFIG['url_publique'] ?? ''), '/');
    if ($base === '' && !empty($_SERVER['HTTP_HOST'])) $base = base_requete();
    return $base . '/' . ltrim($chemin, '/');
}

function base_requete(): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
    $dir = preg_replace('#/(api|v|espace|ics)$#', '', rtrim($dir, '/')); // racine de public/
    return ($https ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . rtrim($dir, '/');
}

/** Mémorise l'adresse du site vue lors d'une visite (utile aux tâches automatiques lancées par cron). */
function memoriser_url_publique(): void
{
    global $CONFIG;
    if (!empty($CONFIG['url_publique']) || empty($_SERVER['HTTP_HOST']) || !is_writable(dirname(SETTINGS_FILE))) return;
    if (preg_match('/^(127\.|localhost)/', $_SERVER['HTTP_HOST'])) return; // poste de développement
    $s = read_json(SETTINGS_FILE, []);
    $s['url_publique'] = base_requete();
    write_json(SETTINGS_FILE, $s);
    $CONFIG['url_publique'] = $s['url_publique'];
}

// ---------- Appels HTTP vers les services publics ----------

/** GET JSON (ou texte) avec délai court ; renvoie null si le service ne répond pas. */
function http_get(string $url, int $timeout = 12, bool $json = true, array $headers = []): mixed
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 6,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTPHEADER     => array_merge(['Accept: application/json', 'User-Agent: VisiteImmo-Synapse/1.0'], $headers),
    ]);
    $raw = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($raw === false || $status >= 400) return null;
    return $json ? json_decode((string) $raw, true) : (string) $raw;
}

function http_post_json(string $url, array $body, array $headers = [], int $timeout = 20): ?array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_HTTPHEADER     => array_merge(['Content-Type: application/json', 'Accept: application/json'], $headers),
    ]);
    $raw = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($raw === false || $status >= 400) return null;
    return json_decode((string) $raw, true) ?: [];
}

// ---------- Dates ----------

/** Heure courante (simulable pour les tests des tâches automatiques : cron.php --maintenant=…). */
function maintenant(): int
{
    return defined('CRON_MAINTENANT') ? CRON_MAINTENANT : time();
}

/** JJ/MM/AAAA → AAAA-MM-JJ (ou '' si invalide). */
function date_iso(string $jjmmaaaa): string
{
    if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', trim($jjmmaaaa), $m) && checkdate((int) $m[2], (int) $m[1], (int) $m[3])) {
        return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
    }
    return preg_match('/^\d{4}-\d{2}-\d{2}/', $jjmmaaaa) ? substr($jjmmaaaa, 0, 10) : '';
}

function date_jj(string $iso): string
{
    $t = strtotime($iso);
    return $t ? date('d/m/Y', $t) : '';
}

function slug(string $texte, int $max = 60): string
{
    $s = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower(iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texte) ?: '')), '-');
    return substr($s ?: 'document', 0, $max);
}

/** Envoie un e-mail à la charte Synapse au nom d'un agent ; renvoie false (sans erreur) si l'envoi n'est pas configuré. */
function envoyer_mail_agent(array $agent, string $to, string $sujet, string $texte, array $pieces = [], bool $exiger = false): bool
{
    if (!email_configure() || !valid_email($to)) {
        if ($exiger) throw new RuntimeException(email_configure() ? "Adresse e-mail invalide : $to" : "L'envoi d'e-mails n'est pas configuré (Paramètres).");
        return false;
    }
    [$html, $images] = email_html($texte, $agent);
    send_email(['to' => $to, 'reply_to' => $agent['email'] ?? '', 'sujet' => $sujet, 'texte' => $texte, 'html' => $html, 'images' => $images, 'pieces' => $pieces]);
    return true;
}

function signature_agent(array $agent): string
{
    global $CONFIG;
    return trim($agent['nom'] . "\n" . $CONFIG['agence'] . (($agent['telephone'] ?? '') !== '' ? "\n" . $agent['telephone'] : ''));
}

// ---------- Enregistrement des fonctions par module ----------
// Chaque fichier de app/modules/ déclare ses routes d'API, ses tâches automatiques (cron) et ce qu'il ajoute
// à l'écran « Aujourd'hui ». Ajouter une fonction = ajouter un module, sans toucher au reste.

$ROUTES = [];
$TACHES_CRON = [];
$A_FAIRE = [];

/** Route d'API : route('GET acquereurs', fn () => …) → /api/?r=acquereurs */
function route(string $cle, callable $fn): void
{
    global $ROUTES;
    $ROUTES[$cle] = $fn;
}

/** Tâche automatique lancée par cron.php pour chaque agent : fn (array $agent, array $dossiers): void */
function tache_cron(string $nom, callable $fn): void
{
    global $TACHES_CRON;
    $TACHES_CRON[$nom] = $fn;
}

/**
 * Fournisseur d'éléments pour l'écran « Aujourd'hui » : fn (array $agent, array $dossiers): array d'éléments
 * [type, titre, detail, lien, priorite (1 urgent → 3), date?]
 */
function a_faire(string $nom, callable $fn): void
{
    global $A_FAIRE;
    $A_FAIRE[$nom] = $fn;
}

// ---------- Réglages ajoutés par les modules (Paramètres) ----------

/** Réglages simples enregistrés tels quels : clé => [type (texte, url, nombre, choix:a|b, secret), valeur par défaut]. */
const REGLAGES_MODULES = [
    'url_publique'             => ['url', ''],
    'signature_mode'           => ['choix:interne|firma|boldsign|api', 'interne'],
    'signature_api_url'        => ['url', ''],
    'signature_api_cle'        => ['secret', ''],
    'signature_webhook_secret' => ['secret', ''],
    'signature_otp'            => ['choix:0|1', '0'],
    'modele_image'             => ['texte', 'gemini-2.5-flash-image'],
    'lien_avis_google'         => ['url', ''],
    'taux_palier1'             => ['nombre', 80],
    'seuil_palier2'            => ['nombre', 40000],
    'taux_palier2'             => ['nombre', 90],
    'seuil_palier3'            => ['nombre', 80000],
    'taux_palier3'             => ['nombre', 95],
    'juriste_email'            => ['email', ''],
    'conservation_audio_jours' => ['nombre', 0],
    'vapid_sujet'              => ['texte', ''],
];

function reglages_modules_vue(): array
{
    global $CONFIG;
    $out = [];
    foreach (REGLAGES_MODULES as $k => [$type, $defaut]) {
        $out[$k] = $type === 'secret' ? (!empty($CONFIG[$k]) ? '••••••' : '') : ($CONFIG[$k] ?? $defaut);
    }
    return $out;
}

function reglages_modules_valider(array $in, array $settings): array
{
    foreach (REGLAGES_MODULES as $k => [$type]) {
        if (!array_key_exists($k, $in)) continue;
        $v = trim((string) $in[$k]);
        if ($type === 'secret') { if ($v !== '' && $v !== '••••••') $settings[$k] = $v; continue; }
        if ($type === 'url' && $v !== '' && !preg_match('#^https?://#', $v)) fail(400, "Adresse invalide pour $k (elle doit commencer par https://).");
        if ($type === 'email' && $v !== '' && !valid_email($v)) fail(400, "E-mail invalide pour $k.");
        if ($type === 'nombre') $v = (float) str_replace(',', '.', $v);
        if (str_starts_with($type, 'choix:') && !in_array($v, explode('|', substr($type, 6)), true)) continue;
        $settings[$k] = $type === 'url' ? rtrim($v, '/') : $v;
    }
    return $settings;
}
