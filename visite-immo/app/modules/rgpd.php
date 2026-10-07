<?php
// Données personnelles (RGPD) : durées de conservation appliquées automatiquement, export des données
// d'une personne (droit d'accès), effacement de l'audio des visites, registre des traitements simplifié.

/** Suppression automatique de l'audio des visites après N jours (réglage « conservation_audio_jours », 0 = jamais). */
tache_cron('rgpd_audio', function (array $agent, array $dossiers): int {
    global $CONFIG;
    $jours = (int) ($CONFIG['conservation_audio_jours'] ?? 0);
    if ($jours <= 0) return 0;
    $n = 0;
    foreach ($dossiers as $v) {
        if (!empty($v['audio_supprime']) || empty($v['morceaux']) || empty($v['genere_le'])) continue;
        if (maintenant() < strtotime($v['genere_le']) + $jours * 86400) continue;
        rrmdir(visit_dir($agent, $v['id']) . '/audio');
        update_visit($agent, $v['id'], function (array $x) use ($jours) {
            $x['audio_supprime'] = true;
            journal_ajout($x, 'rgpd', "Audio de la visite effacé automatiquement ($jours jours après la création du dossier). La transcription est conservée.");
            return $x;
        });
        $n++;
    }
    return $n;
});

/** Toutes les données qui concernent une personne (nom ou e-mail), dans les dossiers et fiches de tous les agents. */
function donnees_personne(string $recherche): array
{
    $r = normaliser_nom($recherche);
    $email = strtolower(trim($recherche));
    if (mb_strlen($r) < 3) fail(400, 'Recherche trop courte.');
    $trouve = ['recherche' => $recherche, 'export_le' => date('c'), 'dossiers' => [], 'acquereurs' => [], 'contacts' => []];
    $correspond = fn (string $txt) => $txt !== '' && (str_contains(normaliser_nom($txt), $r) || strtolower($txt) === $email);
    foreach (users() as $u) {
        foreach (dossiers($u) as $v) {
            $champs = array_map(fn ($c) => $c['valeur'], (array) $v['fiche']['champs']);
            $vendeur = array_filter($champs, fn ($k) => str_contains($k, 'vendeur'), ARRAY_FILTER_USE_KEY);
            if (array_filter($vendeur, fn ($x) => $correspond((string) $x))) $trouve['dossiers'][] = ['agent' => $u['nom'], 'bien' => titre_bien($v), 'id' => $v['id'], 'donnees_vendeur' => $vendeur, 'signatures' => array_map(fn ($d) => array_map(fn ($s) => array_diff_key($s, ['code' => 1]), $d['signataires']), $v['signatures'] ?? []), 'lcbft' => $v['lcbft'] ?? null];
            foreach ($v['contacts'] ?? [] as $c) if ($correspond($c['nom'])) $trouve['contacts'][] = ['agent' => $u['nom'], 'bien' => titre_bien($v)] + $c;
        }
        foreach (acquereurs($u) as $a) if ($correspond(nom_acquereur($a)) || $correspond($a['email'] ?? '')) $trouve['acquereurs'][] = ['agent' => $u['nom']] + $a;
    }
    // Qui a consulté ses dossiers (journal des accès)
    if (function_exists('acces_lire') && $trouve['dossiers']) {
        $trouve['acces'] = array_map(fn ($a) => array_diff_key($a, ['qui_id' => 1]), acces_lire(['dossiers' => array_column($trouve['dossiers'], 'id')], 2000));
    }
    return $trouve;
}

route('GET rgpd_export', function () {
    require_admin();
    $d = donnees_personne((string) ($_GET['q'] ?? ''));
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="donnees-' . slug((string) ($_GET['q'] ?? 'personne'), 40) . '.json"');
    echo json_encode($d, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
});

route('GET rgpd_recherche', function () {
    require_admin();
    $d = donnees_personne((string) ($_GET['q'] ?? ''));
    send_json(['dossiers' => count($d['dossiers']), 'acquereurs' => count($d['acquereurs']), 'contacts' => count($d['contacts'])]);
});

/** Registre simplifié des traitements, affiché dans les Paramètres (à compléter par le responsable). */
const REGISTRE_TRAITEMENTS = [
    ['Enregistrement et transcription des visites', 'Accord du vendeur recueilli avant chaque enregistrement', 'Audio : selon le réglage ; transcription : durée du mandat + 5 ans'],
    ['Dossier vendeur et mandat', 'Exécution du mandat (loi Hoguet)', 'Durée du mandat + 5 ans ; registre des mandats : 10 ans'],
    ['Fiches acquéreurs et assistant en ligne', 'Mesures précontractuelles, intérêt légitime', '3 ans après le dernier contact'],
    ['Contrôle anti-blanchiment (LCB-FT)', 'Obligation légale (code monétaire et financier)', '5 ans après la fin de la relation d\'affaires'],
    ['Prospection par courrier', 'Intérêt légitime, données publiques (ADEME, DVF)', '3 ans ; opposition possible à tout moment'],
];
