<?php
// Réseau Synapse : l'accompagnement de l'offre agents.
//   - coaching des 90 premiers jours (étapes cochées automatiquement d'après l'activité réelle, notes du coach) ;
//   - formation obligatoire loi ALUR (14 h par an, 42 h sur 3 ans dont éthique et non-discrimination) ;
//   - question au juriste avec le dossier joint ;
//   - contacts partagés entre agents (un acquéreur hors secteur est transmis à l'agent du secteur) ;
//   - vue tête de réseau (administrateur) : activité, ventes, palier de chaque agent.

function etapes_coaching(array $agent): array
{
    $dossiers = dossiers($agent);
    $prosp = collection($agent, 'prospection');
    $courriers = count(array_filter($prosp['cibles'] ?? [], fn ($c) => $c['statut'] !== 'nouveau'));
    $u = user_by_id($agent['id']) ?? $agent;
    $debut = strtotime($u['cree_le'] ?? 'now');
    $quand = function (callable $test) use ($dossiers): ?string {
        $dates = [];
        foreach ($dossiers as $v) if ($d = $test($v)) $dates[] = $d;
        return $dates ? min($dates) : null;
    };
    $e = [
        ['Profil complet (e-mail, téléphone)', 3, !empty($u['email']) && !empty($u['telephone']) ? ($u['cree_le'] ?? date('c')) : null],
        ['Première visite enregistrée', 7, $quand(fn ($v) => $v['genere_le'] ?? null)],
        ['Secteur de prospection et 20 courriers', 14, $courriers >= 20 ? date('c') : null],
        ['Premier avis de valeur envoyé au vendeur', 21, $quand(fn ($v) => !empty($v['avis_valeur']) ? ($v['envoi_vendeur']['date'] ?? null) : null)],
        ['Premier mandat signé', 30, $quand(fn ($v) => $v['mandat']['signe_le'] ?? null)],
        ['Premier bien publié', 35, $quand(fn ($v) => $v['vitrine']['publiee_le'] ?? null)],
        ['Première offre acceptée', 60, $quand(fn ($v) => $v['vente']['debut'] ?? null)],
        ['Premier compromis signé', 90, $quand(fn ($v) => $v['vente']['compromis_le'] ?? null)],
    ];
    return array_map(fn ($x) => ['libelle' => $x[0], 'objectif' => date('c', $debut + $x[1] * 86400), 'jour' => $x[1], 'fait_le' => $x[2],
        'en_retard' => !$x[2] && time() > $debut + $x[1] * 86400], $e);
}

function heures_formation(array $agent): array
{
    $f = collection($agent, 'formations');
    $an = date('Y');
    $annee = array_sum(array_map(fn ($x) => (float) $x['heures'], array_filter($f, fn ($x) => substr($x['date'], 0, 4) === $an)));
    $trois = array_sum(array_map(fn ($x) => (float) $x['heures'], array_filter($f, fn ($x) => $x['date'] >= date('Y-m-d', strtotime('-3 years')))));
    return ['annee' => $annee, 'trois_ans' => $trois, 'objectif_annee' => 14, 'objectif_trois_ans' => 42, 'liste' => array_reverse($f)];
}

function stats_agent(array $u): array
{
    $d = dossiers($u);
    $etapes = array_count_values(array_map('etape_dossier', $d));
    $ca = ca_12_mois($u['id'], time() + 1);
    $acte = 0;
    foreach ($d as $v) if (!empty($v['vente']['acte_le'])) $acte++;
    return [
        'id' => $u['id'], 'nom' => $u['nom'], 'role' => $u['role'], 'depuis' => $u['cree_le'] ?? null,
        'dossiers' => count($d), 'mandats' => count(array_filter($d, fn ($v) => !empty($v['mandat']['signe_le']))),
        'en_vente' => ($etapes['en_vente'] ?? 0), 'en_cours' => ($etapes['offre'] ?? 0) + ($etapes['compromis'] ?? 0), 'ventes' => $acte,
        'ca' => $ca, 'taux' => taux_agent($ca)['taux'], 'acquereurs' => count(collection($u, 'acquereurs')),
        'formation' => heures_formation($u)['annee'],
        'coaching' => count(array_filter(etapes_coaching($u), fn ($e) => $e['fait_le'])) . '/' . count(etapes_coaching($u)),
        'derniere_activite' => $d ? max(array_map(fn ($v) => $v['modifie_le'] ?? $v['cree_le'], $d)) : null,
    ];
}

route('GET reseau', function () {
    $me = require_user();
    $u = user_by_id($me['id']);
    $out = [
        'coaching' => etapes_coaching($me), 'notes_coach' => $u['notes_coach'] ?? [],
        'formation' => heures_formation($me),
        'collegues' => array_values(array_map(fn ($x) => ['id' => $x['id'], 'nom' => $x['nom']], array_filter(users(), fn ($x) => $x['id'] !== $me['id']))),
        'recus' => array_values(array_filter(acquereurs($me), fn ($a) => !empty($a['transmis_par']) && $a['statut'] === 'nouveau')),
        'juriste' => email_configure() && !empty($GLOBALS['CONFIG']['juriste_email']),
        'questions' => array_reverse(collection($me, 'questions')),
    ];
    if ($me['role'] === 'admin') $out['agents'] = array_map('stats_agent', users());
    send_json($out);
});

route('POST note_coach', function () {
    $me = require_admin();
    $in = json_input();
    $texte = mb_substr(trim((string) ($in['texte'] ?? '')), 0, 1000);
    if ($texte === '') fail(400, 'Note vide.');
    update_json(USERS_FILE, fn (array $users) => array_map(fn ($u) => $u['id'] === ($in['agent'] ?? '') ? ['notes_coach' => array_merge($u['notes_coach'] ?? [], [['date' => date('c'), 'par' => $me['nom'], 'texte' => $texte]])] + $u : $u, $users));
    if ($cible = user_by_id((string) ($in['agent'] ?? ''))) notifier($cible, 'Message de votre coach', mb_strimwidth($texte, 0, 120, '…'), '#/reseau');
    send_json(['ok' => true]);
});

route('POST formation', function () {
    $me = require_user();
    $in = json_input();
    $h = (float) str_replace(',', '.', (string) ($in['heures'] ?? 0));
    if ($h <= 0 || $h > 100) fail(400, 'Nombre d\'heures invalide.');
    collection_enregistrer($me, 'formations', ['date' => date_iso((string) ($in['date'] ?? '')) ?: date('Y-m-d'), 'intitule' => mb_substr(trim((string) ($in['intitule'] ?? '')), 0, 160) ?: 'Formation',
        'heures' => $h, 'organisme' => mb_substr(trim((string) ($in['organisme'] ?? '')), 0, 120), 'theme' => in_array($in['theme'] ?? '', ['ethique', 'discrimination', 'autre'], true) ? $in['theme'] : 'autre']);
    send_json(heures_formation($me));
});

route('POST question_juriste', function () {
    global $CONFIG;
    $me = require_user();
    $in = json_input();
    $q = trim((string) ($in['question'] ?? ''));
    if (mb_strlen($q) < 10) fail(400, 'Précisez votre question.');
    if (empty($CONFIG['juriste_email'])) fail(400, "L'e-mail du juriste n'est pas renseigné (Paramètres).");
    $pj = [];
    $titre = '';
    if (!empty($in['dossier'])) {
        $v = load_visit($me, (string) $in['dossier']);
        $titre = titre_bien($v);
        [$bin, $nom] = build_pdf('dossier', $v, $me);
        $pj[] = [$nom, $bin, 'application/pdf'];
    }
    envoyer_mail_agent($me, $CONFIG['juriste_email'], 'Question juridique · ' . ($in['sujet'] ?? 'Sans sujet') . ($titre ? " · $titre" : ''), "Bonjour,\n\n" . $q . "\n\n" . ($titre ? "Dossier concerné : $titre (PDF joint).\n\n" : '') . "Merci,\n" . signature_agent($me), $pj, true);
    collection_enregistrer($me, 'questions', ['sujet' => mb_substr((string) ($in['sujet'] ?? ''), 0, 120), 'question' => mb_substr($q, 0, 3000), 'dossier' => $in['dossier'] ?? null]);
    send_json(['ok' => true]);
});

/** Transmet un acquéreur à un collègue (hors secteur, spécialité…) : copie chez lui, trace chez soi, notification. */
route('POST partager_acquereur', function () {
    $me = require_user();
    $in = json_input();
    $a = collection_trouver($me, 'acquereurs', (string) ($in['acquereur'] ?? '')) ?? fail(404, 'Acquéreur introuvable.');
    $dest = user_by_id((string) ($in['agent'] ?? '')) ?? fail(404, 'Collègue introuvable.');
    $copie = $a;
    unset($copie['id'], $copie['propositions']);
    $copie['statut'] = 'nouveau';
    $copie['transmis_par'] = $me['nom'];
    $copie['historique'] = [['date' => date('c'), 'texte' => "Transmis par {$me['nom']}" . (!empty($in['message']) ? ' : « ' . mb_substr((string) $in['message'], 0, 300) . ' »' : '')]];
    $copie = collection_enregistrer($dest, 'acquereurs', $copie);
    $a['historique'][] = ['date' => date('c'), 'texte' => "Transmis à {$dest['nom']}."];
    collection_enregistrer($me, 'acquereurs', $a);
    notifier($dest, 'Contact transmis par ' . $me['nom'], nom_acquereur($copie), '#/acquereur/' . $copie['id']);
    try {
        if (valid_email($dest['email'] ?? '')) envoyer_mail_agent($me, $dest['email'], 'Un contact pour vous · ' . nom_acquereur($copie), "Bonjour {$dest['nom']},\n\nJe te transmets " . nom_acquereur($copie) . ' (' . implode(' · ', array_filter([$copie['telephone'] ?? '', $copie['email'] ?? ''])) . ").\n" . ($in['message'] ?? '') . "\n\nSa fiche est dans ton appli : " . url_publique('#/acquereur/' . $copie['id']) . "\n\n" . $me['nom']);
    } catch (Throwable) {
    }
    send_json(['ok' => true]);
});

a_faire('reseau', function (array $agent): array {
    $items = [];
    foreach (etapes_coaching($agent) as $e) {
        if (!$e['fait_le'] && $e['en_retard'] && $e['jour'] <= 90) {
            $items[] = ['type' => 'info', 'titre' => "Coaching : {$e['libelle']}", 'detail' => 'Objectif du jour ' . $e['jour'] . ' de votre démarrage', 'lien' => '#/reseau', 'priorite' => 3];
            break;
        }
    }
    $f = heures_formation($agent);
    if ((int) date('n') >= 9 && $f['annee'] < 14) $items[] = ['type' => 'info', 'titre' => 'Formation loi ALUR : ' . $f['annee'] . ' h sur 14 h cette année', 'detail' => 'Obligatoire pour le renouvellement de la carte', 'lien' => '#/reseau', 'priorite' => 3];
    return $items;
});
