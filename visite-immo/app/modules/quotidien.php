<?php
// Au quotidien : tâches, bilan d'appel dicté, commande vocale, briefing du matin à écouter,
// tableau de bord (honoraires sur 12 mois, palier, temps gagné).

// ---------- Tâches ----------

function taches(array $agent): array
{
    $l = collection($agent, 'taches');
    usort($l, fn ($a, $b) => strcmp($a['echeance'] ?? '9', $b['echeance'] ?? '9'));
    return $l;
}

function tache_ajouter(array $agent, string $titre, ?string $echeance = null, ?string $dossier = null, ?string $acquereur = null, string $source = 'agent'): array
{
    // Références vérifiées : elles servent à construire des liens dans l'appli
    if ($dossier !== null && !valid_id($dossier)) $dossier = null;
    if ($acquereur !== null && !preg_match('/^a\w{6,30}$/', $acquereur)) $acquereur = null;
    return collection_enregistrer($agent, 'taches', array_filter(['titre' => mb_substr(trim($titre), 0, 200), 'echeance' => $echeance ? date_iso($echeance) ?: substr($echeance, 0, 10) : null,
        'dossier' => $dossier, 'acquereur' => $acquereur, 'source' => $source, 'fait' => false], fn ($x) => $x !== null) + ['fait' => false]);
}

a_faire('taches', function (array $agent): array {
    $auj = date('Y-m-d', maintenant());
    $items = [];
    foreach (taches($agent) as $t) {
        if (!empty($t['fait']) || (($t['echeance'] ?? '') !== '' && $t['echeance'] > $auj)) continue;
        $items[] = ['type' => 'tache', 'titre' => $t['titre'], 'detail' => ($t['echeance'] ?? '') && $t['echeance'] < $auj ? 'En retard depuis le ' . date_jj($t['echeance']) : ($t['source'] === 'appel' ? 'Suite à un appel' : 'Aujourd\'hui'),
            'lien' => !empty($t['dossier']) ? "#/visite/{$t['dossier']}/resume" : (!empty($t['acquereur']) ? "#/acquereur/{$t['acquereur']}" : '#/'), 'priorite' => 1, 'tache' => $t['id'], 'date' => $t['echeance'] ?? ''];
    }
    return $items;
});

route('POST tache', function ($id) {
    $me = require_user();
    $in = json_input();
    if ($id !== '' && isset($in['fait'])) {
        $t = collection_trouver($me, 'taches', $id) ?? fail(404, 'Tâche introuvable.');
        send_json(collection_enregistrer($me, 'taches', ['fait' => (bool) $in['fait'], 'fait_le' => date('c')] + $t));
    }
    send_json(tache_ajouter($me, (string) ($in['titre'] ?? ''), $in['echeance'] ?? null, $in['dossier'] ?? null, $in['acquereur'] ?? null));
});

// ---------- Retrouver le dossier ou l'acquéreur dont parle l'agent ----------

function trouver_dossier_cite(array $agent, string $ref): ?array
{
    $ref = normaliser_nom($ref);
    if ($ref === '') return null;
    $meilleur = null;
    $score = 0;
    foreach (dossiers($agent) as $v) {
        $cible = normaliser_nom(titre_bien($v) . ' ' . champ($v, 'nom_vendeur') . ' ' . champ($v, 'ville') . ' ' . ($v['titre_annonce'] ?? ''));
        $s = 0;
        foreach (array_filter(explode(' ', $ref), fn ($m) => strlen($m) > 2) as $mot) if (str_contains($cible, $mot)) $s++;
        if ($s > $score) { $score = $s; $meilleur = $v; }
    }
    return $meilleur;
}

function trouver_acquereur_cite(array $agent, string $ref): ?array
{
    $ref = normaliser_nom($ref);
    if ($ref === '') return null;
    foreach (acquereurs($agent) as $a) {
        $n = normaliser_nom(nom_acquereur($a));
        if ($n !== '' && (str_contains($ref, normaliser_nom($a['nom'] ?? '~')) || str_contains($n, $ref))) return $a;
    }
    return null;
}

function contexte_agent(array $agent): string
{
    $biens = array_map(fn ($v) => '- ' . titre_bien($v) . ' (vendeur ' . (champ($v, 'nom_vendeur') ?: '?') . ', ' . (ETAPES[etape_dossier($v)][0] ?? '') . ')', array_slice(dossiers($agent), 0, 30));
    $acq = array_map(fn ($a) => '- ' . nom_acquereur($a), array_slice(acquereurs($agent), 0, 40));
    return "Biens de l'agent :\n" . implode("\n", $biens) . "\nAcquéreurs :\n" . implode("\n", $acq);
}

// ---------- Bilan d'appel dicté ----------

type_dictee('appel',
    s_obj(['interlocuteur' => s_txt(), 'bien' => s_txt(), 'acquereur' => s_txt(), 'resume' => s_txt(), 'nouveau_prix' => s_num(), 'rappel_date' => s_txt(), 'rappel_heure' => s_txt(), 'taches' => s_liste(s_txt())], ['resume', 'taches']),
    "L'agent fait le bilan d'un appel téléphonique. Identifie l'interlocuteur, le bien concerné (tel que nommé dans la liste fournie), l'acquéreur concerné s'il y en a un, résume l'appel en une phrase, relève un éventuel nouveau prix accepté par un vendeur, la date et l'heure de rappel convenues, et les tâches à faire (courtes, à l'infinitif).",
    fn (string $t) => ['interlocuteur' => 'Mme Martin', 'bien' => 'Chênois', 'acquereur' => '', 'resume' => 'La vendeuse accepte de baisser le prix à 399 000 € si aucune offre d\'ici 15 jours.', 'nouveau_prix' => 399000, 'rappel_date' => date('Y-m-d', strtotime('next thursday')), 'rappel_heure' => '10:00', 'taches' => ['Préparer l\'avenant de baisse de prix']],
    fn (array $agent) => contexte_agent($agent),
);

apres_dictee('appel', function (array $agent, array $d, array $in, string $transcription) {
    $v = trouver_dossier_cite($agent, (string) ($d['bien'] ?? ''));
    $a = trouver_acquereur_cite($agent, (string) ($d['acquereur'] ?? ''));
    $faits = [];
    if ($v) {
        update_visit($agent, $v['id'], function (array $x) use ($d) {
            journal_ajout($x, 'appel', 'Appel avec ' . ($d['interlocuteur'] ?: 'un contact') . ' : ' . $d['resume']);
            if (!empty($d['nouveau_prix'])) $x['prix_propose_vendeur'] = ['prix' => (int) $d['nouveau_prix'], 'le' => date('c')];
            return $x;
        });
        $faits[] = 'Noté dans le dossier ' . titre_bien($v);
        if (!empty($d['nouveau_prix'])) {
            tache_ajouter($agent, 'Faire signer l\'avenant au mandat (nouveau prix : ' . number_format((float) $d['nouveau_prix'], 0, ',', ' ') . ' €)', date('Y-m-d'), $v['id'], null, 'appel');
            $faits[] = 'Avenant de prix à faire signer (le prix affiché ne change qu\'avec l\'avenant)';
        }
    }
    if ($a) {
        $a['historique'][] = ['date' => date('c'), 'texte' => 'Appel : ' . $d['resume']];
        collection_enregistrer($agent, 'acquereurs', $a);
        $faits[] = 'Noté dans la fiche de ' . nom_acquereur($a);
    }
    foreach ($d['taches'] ?? [] as $t) {
        tache_ajouter($agent, $t, $d['rappel_date'] ?: date('Y-m-d'), $v['id'] ?? null, $a['id'] ?? null, 'appel');
        $faits[] = "Tâche : $t";
    }
    if (!empty($d['rappel_date'])) {
        rdv_enregistrer($agent, ['type' => 'appel', 'debut' => $d['rappel_date'] . ' ' . ($d['rappel_heure'] ?: '09:00'), 'duree' => 15, 'titre' => 'Rappeler ' . ($d['interlocuteur'] ?: ''), 'dossier' => $v['id'] ?? null, 'acquereur' => $a['id'] ?? null, 'notes' => $d['resume']]);
        $faits[] = 'Rappel ajouté à l\'agenda le ' . date_jj($d['rappel_date']) . ($d['rappel_heure'] ? " à {$d['rappel_heure']}" : '');
    }
    return ['faits' => $faits, 'dossier' => $v['id'] ?? null];
});

// ---------- Commande vocale ----------

const INTENTIONS = ['rdv' => 'Ajouter un rendez-vous', 'tache' => 'Créer une tâche', 'appel' => "Faire le bilan d'un appel", 'acquereur' => 'Créer un acquéreur', 'relancer_vendeur' => 'Relancer le vendeur pour ses pièces',
    'point_vendeur' => 'Envoyer le point de la semaine au vendeur', 'ouvrir' => 'Ouvrir un dossier', 'briefing' => 'Lire le briefing', 'inconnu' => 'Je n\'ai pas compris'];

type_dictee('commande',
    s_obj(['intention' => s_enum(array_keys(INTENTIONS)), 'confirmation' => s_txt(), 'bien' => s_txt(), 'date' => s_txt(), 'heure' => s_txt(), 'titre' => s_txt(), 'texte' => s_txt()], ['intention', 'confirmation']),
    "L'agent donne un ordre à son assistant. Choisis l'intention, reformule en une phrase ce que tu vas faire (« confirmation »), et remplis les paramètres utiles : bien concerné (tel que nommé dans la liste), date AAAA-MM-JJ, heure HH:MM, titre court ; « texte » reprend la demande telle quelle pour les intentions appel et acquereur.",
    function (string $t) {
        $t = mb_strtolower($t);
        if (str_contains($t, 'relance')) return ['intention' => 'relancer_vendeur', 'confirmation' => 'Je relance la vendeuse du Chênois pour ses documents.', 'bien' => 'Chênois', 'date' => '', 'heure' => '', 'titre' => '', 'texte' => $t];
        if (str_contains($t, 'briefing')) return ['intention' => 'briefing', 'confirmation' => 'Je vous lis le briefing.', 'bien' => '', 'date' => '', 'heure' => '', 'titre' => '', 'texte' => $t];
        return ['intention' => 'rdv', 'confirmation' => 'J\'ajoute une estimation jeudi à 14 h 30 chez M. Bernard.', 'bien' => '', 'date' => date('Y-m-d', strtotime('next thursday')), 'heure' => '14:30', 'titre' => 'Estimation chez M. Bernard', 'texte' => $t];
    },
    fn (array $agent) => contexte_agent($agent),
);

apres_dictee('commande', function (array $agent, array $d) {
    $v = !empty($d['bien']) ? trouver_dossier_cite($agent, $d['bien']) : null;
    return ['intention' => $d['intention'], 'libelle' => INTENTIONS[$d['intention']] ?? '', 'confirmation' => $d['confirmation'], 'dossier' => $v['id'] ?? null, 'bien' => $v ? titre_bien($v) : null, 'params' => $d];
});

route('POST commande', function () {
    $me = require_user();
    $in = json_input();
    $p = (array) ($in['params'] ?? []);
    $id = (string) ($in['dossier'] ?? '');
    switch ((string) ($in['intention'] ?? '')) {
        case 'rdv':
            $r = rdv_enregistrer($me, ['type' => preg_match('/estimation/i', $p['titre'] ?? '') ? 'estimation' : (preg_match('/visite/i', $p['titre'] ?? '') ? 'visite' : 'autre'), 'debut' => ($p['date'] ?? date('Y-m-d')) . ' ' . ($p['heure'] ?: '09:00'), 'titre' => $p['titre'] ?? 'Rendez-vous', 'dossier' => $id ?: null]);
            send_json(['message' => 'Rendez-vous ajouté : ' . libelle_creneau($r['debut']), 'lien' => '#/agenda']);
        case 'tache':
            tache_ajouter($me, $p['titre'] ?: ($p['texte'] ?? 'Tâche'), $p['date'] ?: null, $id ?: null);
            send_json(['message' => 'Tâche ajoutée.', 'lien' => '#/']);
        case 'appel':
            [, $d] = traiter_dictee($me, 'appel', null, (string) ($p['texte'] ?? ''));
            global $APRES_DICTEE;
            $r = $APRES_DICTEE['appel']($me, $d, [], (string) ($p['texte'] ?? ''));
            send_json(['message' => implode(' · ', $r['faits']) ?: 'Appel noté.', 'lien' => $r['dossier'] ? "#/visite/{$r['dossier']}/resume" : '#/']);
        case 'acquereur':
            [, $d] = traiter_dictee($me, 'acquereur', null, (string) ($p['texte'] ?? ''));
            global $APRES_DICTEE;
            $r = $APRES_DICTEE['acquereur']($me, $d, []);
            send_json(['message' => 'Fiche acquéreur créée.', 'lien' => '#/acquereur/' . $r['acquereur']['id']]);
        case 'relancer_vendeur':
            if (!$id) fail(400, 'De quel bien parlez-vous ?');
            $v = load_visit($me, $id);
            $manque = pieces_manquantes(actualiser_pieces($v));
            if (!$manque) send_json(['message' => 'Toutes les pièces sont déjà reçues.', 'lien' => "#/visite/$id/pieces"]);
            $lien = url_publique('espace/?t=' . lien_pour($me, $id, 'vendeur'));
            envoyer_mail_agent($me, champ($v, 'email_vendeur'), 'Documents pour la vente de votre bien', "Bonjour,\n\nPour avancer, il me manque encore :\n" . implode("\n", array_map(fn ($p) => '- ' . $p['label'], $manque)) . "\n\nVous pouvez les déposer ici en photo : $lien\n\nMerci,\n" . signature_agent($me), [], true);
            journaliser($me, $id, 'relance', 'Relance du vendeur (commande vocale).');
            send_json(['message' => 'Vendeur relancé pour ' . count($manque) . ' pièce(s).', 'lien' => "#/visite/$id/pieces"]);
        case 'point_vendeur':
            if (!$id) fail(400, 'De quel bien parlez-vous ?');
            compte_rendu_hebdo($me, $id, true);
            send_json(['message' => 'Point de la semaine envoyé au vendeur.', 'lien' => "#/visite/$id/vente"]);
        case 'ouvrir':
            send_json(['message' => '', 'lien' => $id ? "#/visite/$id/resume" : '#/biens']);
        case 'briefing':
            send_json(['message' => '', 'lien' => '#/', 'briefing' => true]);
    }
    fail(400, "Je n'ai pas compris la demande.");
});

// ---------- Briefing du matin ----------

function briefing(array $agent): string
{
    global $CONFIG;
    $prenom = explode(' ', $agent['nom'])[0];
    $auj = date('Y-m-d', maintenant());
    $rdv = array_values(array_filter(agenda($agent), fn ($r) => substr($r['debut'], 0, 10) === $auj));
    $elements = array_filter(elements_du_jour($agent), fn ($e) => $e['type'] !== 'rdv');
    $urgents = array_filter($elements, fn ($e) => $e['priorite'] === 1);
    $jours = ['', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche'];
    $lignes = ["Bonjour $prenom, nous sommes " . $jours[(int) date('N', maintenant())] . '.'];
    if ($rdv) {
        $lignes[] = 'Vous avez ' . count($rdv) . ' rendez-vous aujourd\'hui.';
        foreach ($rdv as $r) $lignes[] = 'À ' . str_replace(':', ' heures ', date('H:i', strtotime($r['debut']))) . ' : ' . TYPES_RDV[$r['type']] . ', ' . $r['titre'] . '.';
    } else {
        $lignes[] = 'Aucun rendez-vous aujourd\'hui : une bonne journée pour prospecter.';
    }
    if ($urgents) {
        $lignes[] = count($urgents) . ' chose' . (count($urgents) > 1 ? 's' : '') . ' en priorité.';
        foreach (array_slice($urgents, 0, 5) as $e) $lignes[] = $e['titre'] . '.';
    }
    $auto = 0;
    foreach (dossiers($agent) as $v) foreach ($v['journal'] ?? [] as $j) if (in_array($j['type'], ['relance', 'envoi', 'auto', 'contact'], true) && strtotime($j['date']) > maintenant() - 86400) $auto++;
    if ($auto) $lignes[] = "Depuis hier, l'assistant a fait $auto action" . ($auto > 1 ? 's' : '') . " pour vous : relances, envois et réponses aux acquéreurs.";
    $t = taux_agent(ca_12_mois($agent['id']));
    if ($t['prochain_seuil']) $lignes[] = 'Il vous reste ' . number_format(max(0, $t['prochain_seuil'] - ca_12_mois($agent['id'])), 0, ',', ' ') . ' euros d\'honoraires pour passer à ' . $t['prochain_taux'] . ' pour cent.';
    $lignes[] = 'Belle journée !';
    $texte = implode("\n", $lignes);
    if (empty($CONFIG['gemini_api_key'])) return $texte;
    try {
        return trim(gemini_generate($CONFIG['modele_analyse'], [
            'systemInstruction' => ['parts' => [['text' => "Réécris ce briefing du matin pour qu'il soit écouté (voix de synthèse) en moins d'une minute : phrases courtes et naturelles, chaleureux, aucun symbole, aucune liste, heures écrites en toutes lettres. Ne rajoute aucune information."]]],
            'contents' => [['role' => 'user', 'parts' => [['text' => $texte]]]],
        ], 30)) ?: $texte;
    } catch (Throwable) {
        return $texte;
    }
}

route('GET briefing', function () {
    $me = require_user();
    $t = briefing($me);
    flush_usage($me, null, 'analyse');
    send_json(['texte' => $t]);
});

// Briefing prêt chaque matin : notification à 7 h 30 (jours ouvrés)
tache_cron('briefing', function (array $agent): int {
    $h = (int) date('Gi', maintenant());
    if ($h < 730 || $h > 900 || date('N', maintenant()) == 7 || empty($agent['push'])) return 0;
    $etat = read_json(DATA_DIR . '/agents/' . $agent['id'] . '/briefing.json', []);
    if (($etat['dernier'] ?? '') === date('Y-m-d', maintenant())) return 0;
    write_json(DATA_DIR . '/agents/' . $agent['id'] . '/briefing.json', ['dernier' => date('Y-m-d', maintenant())]);
    $n = count(elements_du_jour($agent));
    return notifier($agent, 'Votre briefing du jour', $n ? "$n chose(s) à voir aujourd'hui. Touchez pour écouter." : 'Rien d\'urgent aujourd\'hui. Touchez pour écouter.', '#/?briefing=1');
});

// ---------- Tableau de bord ----------

/** Temps habituellement passé à la main pour chaque action automatisée (minutes), d'après l'infographie de l'offre. */
const TEMPS_REFERENCE = [
    'generation' => [95, 'Fiche, annonce et compte rendu rédigés'],
    'avis'       => [30, 'Avis de valeur'],
    'technique'  => [20, 'Dossier technique (cadastre, risques, DPE)'],
    'mandat'     => [40, 'Mandat rédigé et signé'],
    'piece'      => [6, 'Pièces lues et rangées'],
    'relance'    => [5, 'Relances automatiques'],
    'contact'    => [12, 'Contacts traités par l\'assistant'],
    'visite'     => [8, 'Bons de visite et retours'],
    'envoi'      => [6, 'Envois de documents'],
    'notaire'    => [45, 'Dossiers notaire'],
    'lcbft'      => [20, 'Contrôles anti-blanchiment'],
    'facture'    => [15, 'Factures et commissions'],
];

function temps_gagne(array $agent, int $depuis): array
{
    $compte = array_fill_keys(array_keys(TEMPS_REFERENCE), 0);
    foreach (dossiers($agent) as $v) {
        if (!empty($v['genere_le']) && strtotime($v['genere_le']) >= $depuis) $compte['generation']++;
        if (!empty($v['avis_valeur']['calcule_le']) && strtotime($v['avis_valeur']['calcule_le']) >= $depuis) $compte['avis']++;
        if (!empty($v['public']['maj_le']) && strtotime($v['public']['maj_le']) >= $depuis) $compte['technique']++;
        if (!empty($v['mandat']['signe_le']) && strtotime($v['mandat']['signe_le']) >= $depuis) $compte['mandat']++;
        if (!empty($v['vente']['notaires_envoye_le']) && strtotime($v['vente']['notaires_envoye_le']) >= $depuis) $compte['notaire']++;
        if (!empty($v['vente']['acte_le']) && strtotime($v['vente']['acte_le']) >= $depuis) $compte['facture']++;
        foreach ($v['lcbft'] ?? [] as $c) if (strtotime($c['le']) >= $depuis) $compte['lcbft']++;
        foreach ($v['journal'] ?? [] as $j) {
            if (strtotime($j['date']) < $depuis) continue;
            if (in_array($j['type'], ['piece', 'relance', 'contact', 'visite', 'envoi'], true)) $compte[$j['type']]++;
        }
    }
    $minutes = 0;
    $detail = [];
    foreach ($compte as $k => $n) {
        if (!$n) continue;
        $minutes += $n * TEMPS_REFERENCE[$k][0];
        $detail[] = ['libelle' => TEMPS_REFERENCE[$k][1], 'nombre' => $n, 'minutes' => $n * TEMPS_REFERENCE[$k][0]];
    }
    usort($detail, fn ($a, $b) => $b['minutes'] <=> $a['minutes']);
    return ['minutes' => $minutes, 'detail' => $detail];
}

route('GET tableau', function () {
    $me = require_user();
    $ca = ca_12_mois($me['id'], maintenant() + 1);
    $t = taux_agent($ca);
    $etapes = array_fill_keys(array_keys(ETAPES), 0);
    $potentiel = 0;
    foreach (dossiers($me) as $v) {
        $e = etape_dossier($v);
        $etapes[$e]++;
        if (in_array($e, ['offre', 'compromis'], true)) $potentiel += montant_honoraires_ttc($v) / 1.2;
    }
    $factures = array_values(array_filter(read_json(DATA_DIR . '/factures.json', [])['factures'] ?? [], fn ($f) => $f['agent'] === $me['id'] && strtotime($f['date']) > strtotime('-12 months')));
    send_json([
        'ca_12_mois' => $ca, 'taux' => $t, 'paliers' => paliers(), 'potentiel_ht' => round($potentiel),
        'part_agent_12_mois' => round(array_sum(array_map(fn ($f) => $f['ht'], $factures)) * $t['taux'] / 100),
        'etapes' => $etapes, 'libelles' => array_map(fn ($e) => $e[0], ETAPES),
        'temps_mois' => temps_gagne($me, strtotime('first day of this month 00:00', maintenant())),
        'temps_total' => temps_gagne($me, 0),
        'ventes' => count($factures),
    ]);
});
