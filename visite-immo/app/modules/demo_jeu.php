<?php
// Jeu de démonstration : remplit le compte de l'agent avec des biens à toutes les étapes (visite, dossier,
// signature, en vente, offre, compromis, vendu), des photos dessinées, des acquéreurs, des rendez-vous, des
// visites avec retours, une prospection et une formation. Pour présenter l'outil en 10 minutes.

const DEMO_BIENS = [
    ['etape' => 'visite', 'titre' => '8 impasse des Lilas, Lougres', 'type' => 'Maison', 'surface' => '96', 'prix' => '289000', 'ville' => '25260 Lougres'],
    ['etape' => 'preparation', 'titre' => '22 rue Pasteur, Montbéliard', 'type' => 'Appartement', 'surface' => '74', 'prix' => '168000', 'ville' => '25200 Montbéliard', 'copro' => true],
    ['etape' => 'en_vente', 'titre' => '11 rue du Chênois, Lougres', 'type' => 'Maison', 'surface' => '125', 'prix' => '420000', 'ville' => '25260 Lougres'],
    ['etape' => 'offre', 'titre' => '5 chemin des Vignes, Audincourt', 'type' => 'Maison', 'surface' => '142', 'prix' => '365000', 'ville' => '25400 Audincourt'],
    ['etape' => 'compromis', 'titre' => '3 place de la Mairie, Valentigney', 'type' => 'Maison', 'surface' => '110', 'prix' => '255000', 'ville' => '25700 Valentigney'],
    ['etape' => 'vendu', 'titre' => '17 rue des Écoles, Bethoncourt', 'type' => 'Maison', 'surface' => '101', 'prix' => '238000', 'ville' => '25200 Bethoncourt'],
];

/** Photo de maison dessinée (ciel, façade, toit, fenêtres, jardin), légèrement différente à chaque appel. */
function demo_photo(int $graine, string $piece = 'Façade'): string
{
    mt_srand($graine);
    $w = 1600; $h = 1200;
    $img = imagecreatetruecolor($w, $h);
    for ($y = 0; $y < $h; $y++) {
        $t = $y / $h;
        imageline($img, 0, $y, $w, $y, imagecolorallocate($img, (int) (150 + 80 * $t), (int) (190 + 50 * $t), (int) (235 - 20 * $t)));
    }
    $vert = imagecolorallocate($img, 90 + mt_rand(0, 40), 150 + mt_rand(0, 30), 70);
    imagefilledrectangle($img, 0, 860, $w, $h, $vert);
    if ($piece === 'Façade' || $piece === 'Jardin') {
        $mur = [imagecolorallocate($img, 236, 226, 205), imagecolorallocate($img, 245, 240, 228), imagecolorallocate($img, 222, 205, 180)][mt_rand(0, 2)];
        $x0 = 300 + mt_rand(-80, 80);
        imagefilledrectangle($img, $x0, 480, $x0 + 900, 900, $mur);
        imagefilledpolygon($img, [$x0 - 60, 490, $x0 + 450, 230 + mt_rand(-30, 30), $x0 + 960, 490], imagecolorallocate($img, 150 + mt_rand(0, 40), 70, 50));
        $vitre = imagecolorallocate($img, 120, 160, 190);
        $cadre = imagecolorallocate($img, 255, 255, 255);
        foreach ([[$x0 + 90, 560], [$x0 + 330, 560], [$x0 + 640, 560], [$x0 + 90, 720], [$x0 + 640, 720]] as [$fx, $fy]) {
            imagefilledrectangle($img, $fx - 6, $fy - 6, $fx + 146, $fy + 116, $cadre);
            imagefilledrectangle($img, $fx, $fy, $fx + 140, $fy + 110, $vitre);
        }
        imagefilledrectangle($img, $x0 + 380, 700, $x0 + 520, 900, imagecolorallocate($img, 120, 80, 50));
        for ($i = 0; $i < 6; $i++) imagefilledellipse($img, mt_rand(50, 1550), mt_rand(880, 1150), mt_rand(120, 260), mt_rand(90, 160), imagecolorallocate($img, 60 + mt_rand(0, 40), 120 + mt_rand(0, 40), 50));
    } else {
        // Intérieur : murs, sol parquet, fenêtre lumineuse, quelques meubles
        imagefilledrectangle($img, 0, 0, $w, 800, imagecolorallocate($img, 240, 236, 228));
        for ($x = 0; $x < $w; $x += 120) imagefilledrectangle($img, $x, 800, $x + 116, $h, imagecolorallocate($img, 190 + mt_rand(-10, 10), 150 + mt_rand(-10, 10), 110));
        imagefilledrectangle($img, 980, 140, 1420, 640, imagecolorallocate($img, 255, 255, 255));
        imagefilledrectangle($img, 1000, 160, 1400, 620, imagecolorallocate($img, 190, 220, 245));
        imagefilledrectangle($img, 180, 600, 820, 840, imagecolorallocate($img, 90 + mt_rand(0, 60), 110, 120 + mt_rand(0, 60)));
        imagefilledrectangle($img, 540, 780, 900, 900, imagecolorallocate($img, 120, 85, 55));
    }
    $chemin = sys_get_temp_dir() . "/demo-$graine.jpg";
    imagejpeg($img, $chemin, 88);
    return $chemin;
}

function demo_signer(array $agent, string $id, string $cle): void
{
    $img = imagecreatetruecolor(400, 120);
    imagesavealpha($img, true);
    imagefill($img, 0, 0, imagecolorallocatealpha($img, 0, 0, 0, 127));
    imagesetthickness($img, 3);
    $n = imagecolorallocate($img, 17, 17, 20);
    for ($x = 10; $x < 390; $x += 4) imageline($img, $x, 60 + (int) (35 * sin($x / 17)), $x + 4, 60 + (int) (35 * sin(($x + 4) / 17)), $n);
    ob_start();
    imagepng($img);
    $png = 'data:image/png;base64,' . base64_encode((string) ob_get_clean());
    $v = load_visit($agent, $id);
    foreach ($v['signatures'][$cle]['signataires'] as $s) if (!$s['signe_le']) enregistrer_signature($agent, $id, $cle, $s['id'], $png, 'sur_place');
}

function charger_demo(array $agent): array
{
    global $CONFIG, $APRES_DICTEE;
    // Mentions de l'agence nécessaires au mandat : valeurs de démonstration si rien n'est renseigné
    $s = read_json(SETTINGS_FILE, []);
    foreach (['raison_sociale' => 'Synapse Démo SAS', 'siege' => '1 rue de la Démo, 25000 Besançon', 'siret' => '000 000 000 00000', 'carte_numero' => 'CPI DEMO 0000', 'carte_delivree_par' => 'CCI (démo)', 'garant' => 'Garant (démo)', 'rcp' => 'Assureur (démo)'] as $k => $val) {
        if (empty($CONFIG[$k])) { $s[$k] = $val; $CONFIG[$k] = $val; }
    }
    write_json(SETTINGS_FILE, $s);

    $prenoms = [['Madame', 'Claire', 'Martin'], ['Monsieur', 'Paul', 'Girard'], ['Madame', 'Sophie', 'Lambert'], ['Monsieur', 'Marc', 'Petit'], ['Madame', 'Inès', 'Roux'], ['Monsieur', 'Luc', 'Fontaine']];
    // Acquéreurs
    $acq = [];
    foreach ([['Julien', 'Moreau', 430000, 'Maison', 'Lougres, Audincourt', 'Accord de principe obtenu'], ['Nina', 'Roy', 380000, 'Maison', 'Audincourt', 'En cours'], ['Karim', 'Benali', 200000, 'Appartement', 'Montbéliard', 'Accord de principe obtenu'], ['Emma', 'Leroy', 290000, 'Maison', 'Valentigney, Lougres', 'Pas encore']] as $i => [$p, $n, $b, $t, $vil, $acc]) {
        $acq[] = collection_enregistrer($agent, 'acquereurs', normaliser_acquereur(['prenom' => $p, 'nom' => $n, 'telephone' => '06 ' . (20 + $i) . ' 33 44 55', 'email' => strtolower("$p.$n") . '@exemple.fr', 'source' => ['Appel entrant', 'Page du bien', 'Leboncoin', 'Recommandation'][$i],
            'criteres' => ['type' => $t, 'budget_max' => $b, 'villes' => $vil, 'chambres_min' => 3], 'financement' => ['apport' => 50000 + $i * 20000, 'accord_banque' => $acc], 'delai' => 'Dans les 6 mois', 'statut' => $i === 3 ? 'nouveau' : 'qualifie']) + ['historique' => [['date' => date('c'), 'texte' => 'Fiche créée (démonstration).']]]);
    }

    $n = 0;
    foreach (DEMO_BIENS as $i => $b) {
        $id = date('Ymd-His') . '-d' . $i . bin2hex(random_bytes(2));
        [$civ, $pre, $nom] = $prenoms[$i];
        $visite = ['id' => $id, 'agent' => $agent['id'], 'titre' => $b['titre'], 'statut' => 'enregistre', 'cree_le' => date('c', strtotime('-' . (40 - $i * 6) . ' days')), 'modifie_le' => date('c'),
            'consentement_le' => date('c'), 'morceaux' => array_map(fn ($k) => ['n' => $k, 'fichier' => '', 'mime' => 'audio/webm', 'taille' => 0, 'duree' => 170, 'transcription' => demo_transcript_chunk($k), 'statut' => 'transcrit', 'erreur' => null], [0, 1, 2]),
            'audio_supprime' => true, 'fiche' => ['champs' => new stdClass()], 'titre_annonce' => '', 'annonce' => '', 'rapport_agent' => '', 'rapport_vendeur' => '', 'genere_le' => null, 'erreur' => null, 'demo' => true];
        write_json(visit_dir($agent, $id) . '/visite.json', $visite);
        $n++;
        if ($b['etape'] === 'visite') continue;

        // Documents rédigés (contenu de démonstration, adapté au bien)
        $g = demo_generation($agent);
        $remplace = fn ($t) => str_replace(['Madame Martin', 'Mme Martin', '125 m²', '420 000'], ["$civ $nom", ($civ === 'Madame' ? 'Mme ' : 'M. ') . $nom, $b['surface'] . ' m²', number_format((float) $b['prix'], 0, ',', ' ')], $t);
        update_visit($agent, $id, function (array $v) use ($g, $b, $remplace, $civ, $pre, $nom, $i) {
            $champs = [];
            foreach ($g['champs'] as $c) $champs[$c['cle']] = ['valeur' => $remplace($c['valeur']), 'citation' => $c['citation'], 'source' => 'ia'];
            $set = function ($k, $val, $src = 'dialogue') use (&$champs) {
                $champs[$k] = ['valeur' => $val, 'citation' => '', 'source' => $src];
            };
            foreach (['type_bien' => $b['type'], 'surface_habitable' => $b['surface'], 'prix_souhaite' => $b['prix'], 'ville' => $b['ville'], 'adresse' => explode(',', $b['titre'])[0], 'nom_vendeur' => $nom] as $k => $val) $set($k, $val, 'ia');
            if (!empty($b['copro'])) { $set('copropriete', 'oui', 'ia'); $set('charges_mensuelles', '140', 'ia'); unset($champs['surface_terrain']); }
            if ($b['etape'] !== 'preparation') {
                foreach (['civilite_vendeur' => $civ, 'prenom_vendeur' => $pre, 'naissance_date_vendeur' => '12/04/196' . $i, 'naissance_lieu_vendeur' => 'Besançon', 'telephone_vendeur' => '06 12 34 56 7' . $i,
                    'email_vendeur' => strtolower("$pre.$nom") . '@exemple.fr', 'situation_vendeur' => 'Veuf / veuve', 'nb_pieces' => '5', 'origine_propriete' => 'Acquisition en 2005', 'copropriete' => 'non',
                    'occupation' => 'Occupé par le propriétaire', 'mandat_type' => $i % 2 ? 'Exclusif' : 'Simple', 'mandat_lieu' => 'En agence', 'mandat_prix' => (string) round((float) $b['prix'] * 0.98, -3),
                    'mandat_honoraires' => '5 %', 'mandat_honoraires_charge' => "L'acquéreur", 'mandat_duree' => '3', 'mandat_date' => date('d/m/Y')] as $k => $val) $set($k, $val);
            }
            $v['fiche']['champs'] = $champs;
            foreach (['titre_annonce', 'annonce', 'rapport_agent', 'rapport_vendeur'] as $k) $v[$k] = $remplace($g[$k]);
            $v['points_forts'] = $g['points_forts'];
            $v['plan'] = ['pieces' => $g['pieces_plan']];
            $v['posts'] = $g['posts'];
            $v['statut'] = 'pret';
            $v['genere_le'] = date('c', strtotime($v['cree_le']) + 600);
            journal_ajout($v, 'auto', 'Fiche, annonce, rapports, publications et croquis de plan rédigés par l\'IA.');
            return $v;
        });
        preparer_dossier($agent, $id);
        if ($b['etape'] === 'preparation') continue;

        // Photos, mandat signé, mise en vente
        foreach (['Façade', 'Séjour', 'Jardin', 'Chambre'] as $k => $piece) {
            $f = demo_photo($i * 10 + $k, $piece);
            try {
                ajouter_photo($agent, $id, ['error' => UPLOAD_ERR_OK, 'size' => filesize($f), 'tmp_name' => $f, 'name' => "$piece.jpg"]);
            } catch (Throwable) {
            }
            @unlink($f);
        }
        update_visit($agent, $id, function (array $v) {
            foreach ($v['photos'] ?? [] as $k => $p) $v['photos'][$k]['piece'] = ['Façade', 'Séjour', 'Jardin', 'Chambre'][$k] ?? $p['piece'];
            $v['envoi_vendeur'] = ['date' => date('c', strtotime('-20 days')), 'a' => champ($v, 'email_vendeur'), 'docs' => ['vendeur', 'avis'], 'mandat' => true];
            return $v;
        });
        preparer_mandat_signature($agent, $id);
        demander_signature($agent, $id, 'mandat', false);
        demo_signer($agent, $id, 'mandat');
        publier($agent, $id);

        // Visites d'acquéreurs avec retours, contacts reçus, point de la semaine
        foreach (array_slice($acq, 0, 2) as $k => $a) {
            $r = rdv_enregistrer($agent, ['type' => 'visite', 'debut' => date('Y-m-d', strtotime('-' . (8 - $k * 3) . ' days')) . ' 1' . (4 + $k) . ':00', 'dossier' => $id, 'acquereur' => $a['id'], 'titre' => 'Visite · ' . nom_acquereur($a)]);
            $v = load_visit($agent, $id);
            $va = end($v['visites_acq']);
            $retour = $k === 0
                ? ['interet' => 4, 'positifs' => ['Jardin et exposition', 'Volumes'], 'negatifs' => ['Cuisine à refaire'], 'prix_percu' => 'Un peu élevé vu la cuisine', 'suite' => 'deuxieme_visite', 'resume_vendeur' => 'Visiteurs séduits par le jardin et les volumes ; la cuisine à rafraîchir est le principal frein. Ils souhaitent revenir avec un artisan.', 'notes_internes' => 'Budget confortable, prêt accordé.']
                : ['interet' => 2, 'positifs' => ['Luminosité'], 'negatifs' => ['Pas de garage', 'Route passante'], 'prix_percu' => 'Correct', 'suite' => 'pas_interesse', 'resume_vendeur' => 'Visiteurs sensibles à la luminosité, mais le bien ne correspond pas à leur besoin de stationnement.', 'notes_internes' => ''];
            $APRES_DICTEE['retour_visite']($agent, $retour, ['dossier' => $id, 'visite' => $va['id']], 'démo');
        }
        update_visit($agent, $id, function (array $v) use ($acq, $i) {
            for ($d = 1; $d <= 14; $d++) $v['vitrine']['vues'][date('Y-m-d', strtotime("-$d days"))] = 12 + ($d * 7) % 23;
            $a = $acq[($i + 1) % 4];
            $v['contacts'][] = ['date' => date('c', strtotime('-1 day')), 'acquereur' => $a['id'], 'nom' => nom_acquereur($a), 'canal' => 'Assistant de la page du bien', 'message' => 'Le garage est-il assez grand pour deux voitures ?', 'rdv' => null, 'conversation' => null];
            return $v;
        });
        compte_rendu_hebdo($agent, $id, false);
        if ($b['etape'] === 'en_vente') continue;

        // Offre signée et acceptée
        $o = $APRES_DICTEE['offre']($agent, ['acquereur_nom' => nom_acquereur($acq[$i % 4]), 'montant' => (int) round((float) $b['prix'] * 0.95, -3), 'financement' => 'pret', 'apport' => 60000, 'pret_montant' => (int) round((float) $b['prix'] * 0.8, -3), 'pret_duree_ans' => 25, 'pret_taux_max' => 3.9, 'validite_jours' => 10], ['dossier' => $id, 'acquereur' => $acq[$i % 4]['id']])['offre'];
        demander_signature($agent, $id, 'offre:' . $o['id'], false);
        demo_signer($agent, $id, 'offre:' . $o['id']);
        demo_signer($agent, $id, 'acceptation:' . $o['id']);
        if ($b['etape'] === 'offre') continue;

        // Compromis, contrôles, notaires, puis acte
        update_visit($agent, $id, function (array $v) {
            $c = date('Y-m-d', strtotime('-12 days'));
            $v['vente'] += ['compromis_le' => $c, 'notification_sru' => $c, 'pret_depot_limite' => date('Y-m-d', strtotime("$c +10 days")), 'pret_depose_le' => date('Y-m-d', strtotime("$c +6 days")), 'pret_limite' => date('Y-m-d', strtotime("$c +45 days")), 'acte_prevu' => date('Y-m-d', strtotime("$c +80 days")),
                'financement' => 'pret', 'notaire_vendeur' => ['nom' => 'Me Lefèvre', 'email' => 'lefevre@notaires-demo.fr'], 'notaire_acquereur' => ['nom' => 'Me Garnier', 'email' => 'garnier@notaires-demo.fr'], 'courtier' => ['nom' => 'Prêt+', 'email' => 'courtier@demo.fr'], 'notaires_envoye_le' => date('c', strtotime('-10 days'))];
            foreach (['vendeur', 'acquereur'] as $p) $v['lcbft'][$p] = ['identite' => ['nom' => mb_strtoupper(champ($v, 'nom_vendeur')), 'prenoms' => '', 'type' => "Carte nationale d'identité"], 'ppe' => false, 'pays' => 'France', 'origine' => $p === 'vendeur' ? 'epargne' : 'pret',
                'gels' => ['statut' => 'aucune', 'detail' => 'Aucune correspondance au registre national des gels.', 'le' => date('c')], 'niveau' => 'simplifiée', 'raisons' => ['Aucun facteur de risque identifié'], 'le' => date('c')];
            journal_ajout($v, 'etape', 'Compromis signé. Échéancier calculé, relances automatiques activées.');
            return $v;
        });
        if ($b['etape'] === 'compromis') continue;
        enregistrer_acte($agent, $id, date('Y-m-d', strtotime('-3 days')));
    }

    // Agenda du jour, tâche, formation, prospection
    rdv_enregistrer($agent, ['type' => 'estimation', 'debut' => date('Y-m-d') . ' 10:30', 'titre' => 'Estimation chez M. Bernard', 'lieu' => '4 rue des Lilas, Lougres']);
    rdv_enregistrer($agent, ['type' => 'visite', 'debut' => date('Y-m-d') . ' 15:00', 'titre' => 'Visite · ' . nom_acquereur($acq[1])]);
    tache_ajouter($agent, 'Rappeler Mme Martin pour le point de la semaine', date('Y-m-d'));
    collection_enregistrer($agent, 'formations', ['date' => date('Y-m-d', strtotime('-60 days')), 'intitule' => 'Déontologie et non-discrimination', 'heures' => 4, 'organisme' => 'Synapse Académie', 'theme' => 'ethique']);
    try {
        $c = chercher_commune('Lougres')[0] ?? null;
        if ($c) analyser_secteur($agent, $c);
    } catch (Throwable) {
    }
    return ['biens' => $n, 'acquereurs' => count($acq)];
}

route('POST demo', function () {
    $me = require_user();
    set_time_limit(600);
    send_json(charger_demo($me));
});
