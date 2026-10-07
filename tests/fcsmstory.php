<?php
/**
 * Reprise des années 1928-1969 depuis fcsmstory.com (App\Services\FcsmStory, App\Services\FcsmImport) :
 * découpage d'une page de saison (en-têtes et variantes, match en double fusionné, deux équipes le même
 * jour), classement des pages, plan sans doublon (match déjà au musée, joueur déjà présent),
 * réécriture simulée, mesure de ressemblance (texte copié refusé), création des fiches publiées
 * (faits, composition, buteurs, sources, rubriques), relance sans doublon. Gemini est simulé ; tout
 * ce qui est créé est supprimé à la fin. Usage : php tests/fcsmstory.php
 */
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Data\Categories;
use App\Data\Fiches;
use App\Data\Index;
use App\Services\AiCosts;
use App\Services\FcsmImport;
use App\Services\FcsmStory;

$tmp = sys_get_temp_dir() . '/fcsm-test-' . bin2hex(random_bytes(4));
mkdir($tmp, 0775, true);
FcsmStory::$dir = $tmp;
AiCosts::$dir = "$tmp/ia";
$catsBefore = file_get_contents(DATA_PATH . '/categories.json');
$mediaBefore = file_get_contents(DATA_PATH . '/media.json');
$fail = 0;
$eq = function (string $label, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    if (!$ok) {
        $fail++;
    }
    echo ($ok ? 'OK   ' : 'FAIL ') . $label . ($ok ? '' : ' => ' . json_encode($got, JSON_UNESCAPED_UNICODE) . ' attendu ' . json_encode($exp, JSON_UNESCAPED_UNICODE)) . "\n";
};

// 1. Page de saison : calendrier (lignes courtes) puis récits détaillés.
$report = '<p>Le FC Chalon reçoit le leader sur un terrain lourd. Dès la dixième minute, Kenner ouvre la marque d’un tir croisé imparable. '
    . 'Les visiteurs dominent ensuite largement et Laurent double la mise avant la pause. En seconde période, Chalon ne trouve jamais la faille.</p>';
$season = '<p>La Saison 1931 / 1932</p><p>L’équipe prépare son entrée dans le professionnalisme.</p>'
    . '<p>Le 17 Octobre 1931 à Chalon sur Saône, victoire 8 à 0 FC Chalon</p><p>Championnat</p>'
    . '<p>Le 25 Octobre 1931 à Amiens, victoire 1 à 0 Amiens (amical)</p><p>Match amical</p>'
    . '<p>Le 17 Octobre 1931 à Chalon sur Saône, victoire 8 à 0 FC Chalon</p>'
    . '<p>FC Sochaux Montbéliard: Lozes; Wartel, Mattler; J Laurent, Galland, Boguet; De James, Cottin, Kenner, L Laurent, Rougeot</p>'
    . '<p>Buts FC Sochaux Montbéliard: Kenner (4), J Laurent, De James, L Laurent (2)</p><p>résumé</p>' . $report
    . '<figure><img src="https://fcsmstory.com/wp-content/uploads/2024/03/1931-10-18-LAuto-velo-Chalon-Sochaux-1024x700.jpg" alt="x"><figcaption>Le compte rendu du lendemain</figcaption></figure>'
    . '<figure><img src="https://fcsmstory.com/wp-content/uploads/2024/03/1931-10-18-photo-equipe-Chalon-ia1.jpg" alt=""></figure>'
    . '<figure><img src="https://fcsmstory.com/wp-content/uploads/2024/03/1931-10-18-Football-croque-Kenner.png" alt=""></figure>'
    . '<p>Le 25 Octobre 1931 à Amiens, victoire 1 à 0 Amiens (amical)</p><p>Match amical</p>'
    . '<p>FC Sochaux Montbéliard: Lozes; Wartel, Mattler; J Laurent, Kenner, Galland; Cottin, Hillier, Maschinot, L Laurent, Leslie Miller</p>'
    . '<p>Buts FC Sochaux Montbéliard: Cottin 59′</p>'
    . '<p>Le 25 Octobre 1931 à Colombier-Fontaine, victoire 3 à 2</p><p>FC Sochaux Montbéliard: Lowi; Emonot, Boguet</p>'
    . '<p>Le 01 Novembre 1931 à Nîmes, défaite 3 à 2 Nîmes (amical)</p><p>Coupe du Président de la République</p>'
    . '<p>Le 08 Novembre 1931 à Paris, stade Buffalo, victoire 9 à 2 Club Français</p><p>Championnat</p>'
    . '<p>Le 15 Novembre 1931 à Montbéliard, victoire 3 0 à Bousbotte</p><p>Championnat</p>'
    . '<p>Le 22 Novembre 1931 à Montbéliard, victoire sur tapis vert RCFC</p><p>Championnat</p><p>*Défaite 3 à 2 mais Mykowski n’était pas qualifié</p>'
    . '<p>Bilan de la saison</p><p>Sochaux termine premier de sa poule.</p>';
$p = FcsmStory::season($season, '1931-1932');
$eq('en-têtes lus (le doublon du calendrier reste à fusionner)', count($p['matches']), 9);
$eq('bilan de fin de saison séparé du dernier match', $p['outro'], "Bilan de la saison\nSochaux termine premier de sa poule.");
$byDate = [];
foreach ($p['matches'] as $m) {
    $byDate[$m['date'] . '|' . $m['opponent']][] = $m;
}
$chalon = $byDate['1931-10-17|FC Chalon'][1];
$eq('score, lieu extérieur ; compétition sur la ligne du calendrier', [$chalon['us'], $chalon['them'], $chalon['result'], $chalon['home'], $byDate['1931-10-17|FC Chalon'][0]['competition']], [8, 0, 'V', false, 'Championnat']);
$eq('composition 2-3-5 (postes déduits de l\'ordre)', array_map(fn ($r) => $r['position'], $chalon['lineup']), ['G', 'D', 'D', 'M', 'M', 'M', 'A', 'A', 'A', 'A', 'A']);
$eq('buteurs et nombre de buts', array_map(fn ($s) => [$s['name'], $s['goals']], $chalon['scorers']), [['Kenner', 4], ['J Laurent', 1], ['De James', 1], ['L Laurent', 2]]);
$eq('récit gardé', str_contains($chalon['report'], 'Kenner ouvre la marque'), true);
$nimes = $byDate['1931-11-01|Nîmes'][0];
$eq('défaite : score remis dans le bon sens', [$nimes['us'], $nimes['them'], $nimes['result'], $nimes['amical']], [2, 3, 'D', true]);
$buff = $byDate['1931-11-08|Club Français'][0];
$eq('stade précisé avant le résultat', [$buff['stadium'], $buff['us'], $buff['them']], ['stade Buffalo', 9, 2]);
$eq('variante « victoire 3 0 à Bousbotte »', [$byDate['1931-11-15|Bousbotte'][0]['us'], $byDate['1931-11-15|Bousbotte'][0]['home']], [3, true]);
$tv = $byDate['1931-11-22|RCFC'][0];
$eq('tapis vert : victoire sans score, note gardée', [$tv['forfeit'], $tv['result'], $tv['us'], str_contains($tv['note'], 'Mykowski')], [true, 'V', null, true]);
$eq('deuxième équipe le même jour : adversaire = lieu du match', isset($byDate['1931-10-25|Colombier-Fontaine']), true);

// 2. Classement des pages et analyse complète (fusion des doublons, page de match à part).
$raw = [
    ['kind' => 'page', 'id' => 1, 'slug' => 'saison-1931-1932-2', 'link' => 'https://fcsmstory.com/saison-1931-1932-2/', 'title' => 'Saison 1931 1932', 'html' => $season, 'modified' => ''],
    ['kind' => 'page', 'id' => 2, 'slug' => '17-octobre-1931-chalon-sochaux', 'link' => 'https://fcsmstory.com/17-octobre-1931-chalon-sochaux/', 'title' => '17 Octobre 1931 Chalon Sochaux', 'html' => '<p>Le grand récit à part du match de Chalon, avec la presse.</p>', 'modified' => ''],
    ['kind' => 'page', 'id' => 3, 'slug' => '13-mai-1934-nimes-sochaux', 'link' => 'https://fcsmstory.com/13-mai-1934-nimes-sochaux/', 'title' => '13 Mai 1934 Nîmes Sochaux', 'html' => '<p>' . str_repeat('Récit du match de Nîmes. ', 20) . '</p>', 'modified' => ''],
    ['kind' => 'page', 'id' => 4, 'slug' => 'max-lehmann-fcsm', 'link' => 'https://fcsmstory.com/max-lehmann-fcsm/', 'title' => 'Max LEHMANN', 'html' => '<p>Portrait du demi Max Lehmann.</p>', 'modified' => ''],
    ['kind' => 'page', 'id' => 5, 'slug' => 'zz-inconnu-fcsm', 'link' => 'https://fcsmstory.com/zz-inconnu-fcsm/', 'title' => 'Zébulon ZZINCONNU', 'html' => '<p>Portrait d’un joueur inconnu du musée.</p>', 'modified' => ''],
    ['kind' => 'page', 'id' => 6, 'slug' => 'la-coupe-dupuich-1930-fcsm', 'link' => 'https://fcsmstory.com/la-coupe-dupuich-1930-fcsm/', 'title' => 'La Coupe Dupuich 1930', 'html' => '<p>' . str_repeat('Le tournoi de Pâques à Bruxelles. ', 10) . '</p>', 'modified' => ''],
    ['kind' => 'page', 'id' => 7, 'slug' => '11-janvier-1983-sochaux-bordeaux', 'link' => 'https://fcsmstory.com/x/', 'title' => '11 Janvier 1983 Sochaux Bordeaux', 'html' => '<p>Hors période.</p>', 'modified' => ''],
    ['kind' => 'page', 'id' => 8, 'slug' => 'contact', 'link' => 'https://fcsmstory.com/contact/', 'title' => 'Contact', 'html' => '<p>Écrivez-nous.</p>', 'modified' => ''],
];
$kinds = array_map(fn ($r) => FcsmStory::classify($r), $raw);
$eq('classement : saison, match, match, joueur, joueur, tournoi, hors période, hors sujet', $kinds, ['season', 'match', 'match', 'player', 'player', 'tournament', 'skip', 'skip']);
$a = FcsmStory::analyse($raw);
$eq('matchs sans doublon (le calendrier et le récit ne font qu\'un)', count($a['matches']), 7);
$c = array_values(array_filter($a['matches'], fn ($m) => $m['date'] === '1931-10-17'))[0];
$eq('la fusion garde composition, récit et compétition', [count($c['lineup']), $c['competition'], $c['report'] !== ''], [11, 'Championnat', true]);
$eq('récit à part rattaché au match de la saison', [$a['others']['match'][0]['merged'] ?? null, $a['others']['match'][1]['merged'] ?? null], [true, false]);

// 3. Plan : joueur déjà au musée laissé tel quel ; match à une date déjà au musée écarté.
\App\Core\JsonStore::write(FcsmStory::$dir . '/raw.json', ['at' => date('c'), 'items' => $raw]);
$state = FcsmImport::plan($a);
$it = $state['items'];
$eq('Max Lehmann déjà au musée : non recréé', [$it['player:max-lehmann-fcsm']['status'], (int) ($it['player:max-lehmann-fcsm']['fiche'] ?? 0) > 0], ['existe', true]);
$eq('joueur inconnu du musée : à créer', $it['player:zz-inconnu-fcsm']['status'], 'a-faire');
$eq('saison, tournoi, page de match à part au plan', [isset($it['season:1931-1932']), isset($it['article:la-coupe-dupuich-1930-fcsm']), isset($it['page:13-mai-1934-nimes-sochaux'])], [true, true, true]);
$eq('récit à part rattaché au match du plan', $it['match:1931-10-17|chalon']['extra'] ?? [], ['https://fcsmstory.com/17-octobre-1931-chalon-sochaux/']);

// 4. Ressemblance.
$src = 'Le FC Chalon reçoit le leader sur un terrain lourd et Kenner ouvre la marque d un tir croisé imparable dès la dixième minute de jeu';
$eq('copie : ressemblance 1', round(FcsmImport::similarity($src, $src), 2), 1.0);
$eq('réécriture libre : ressemblance 0', FcsmImport::similarity($src, 'Sur une pelouse grasse, il fallut à peine dix minutes au buteur sochalien pour lancer la machine, d\'une frappe en travers.'), 0.0);
$eq('même personne : nom de famille et un prénom commun', [FcsmImport::samePerson('Miguel Angel Michel Lauri', [[7, ['michel', 'lauri']]]), FcsmImport::samePerson('Pierre Lauri', [[7, ['michel', 'lauri']]])], [7, null]);
$eq('composition : capitaine, remplaçant, parenthèses avec virgules, « et »', array_map(fn ($r) => [$r['name'], $r['sub']], FcsmStory::lineup('Lozes; Wartel (cap.) Mattler; J Laurent puis Reiner, Regan (Sète, Millwall), Courtois absent et Rougeot.')),
    [['Lozes', ''], ['Wartel', ''], ['Mattler', ''], ['J Laurent', 'Reiner'], ['Regan', 'Sète, Millwall'], ['Courtois', ''], ['Rougeot', '']]);
$req = \App\Services\Gemini::requestBody(...array_slice(FcsmImport::prompt(['key' => 'match:x', 'kind' => 'match', 'data' => $chalon + ['us' => 8]], 'récit', 0), 0, 3));
$eq('la consigne arrive bien à Gemini (texte non vide, JSON demandé)', [mb_strlen($req['contents'][0]['parts'][0]['text']) > 200, $req['generationConfig']['responseMimeType'] ?? null], [true, 'application/json']);
$eq('noms de compositions au format du musée', array_map([FcsmImport::class, 'lineupName'], ['J Laurent', 'Leslie Miller', 'De James', 'Wartel', 'Mykowski I']), ['LAURENT J', 'MILLER Leslie', 'DE JAMES', 'WARTEL', 'MYKOWSKI I']);

// 5. Traitement : réponses simulées ; un texte recopié est refusé puis réécrit.
$calls = 0;
$copy = true;
FcsmImport::$generate = function (array $jobs) use (&$calls, &$copy, $report) {
    $out = [];
    foreach ($jobs as $i => [$contents, $system, $opt]) {
        $calls++;
        $ref = $opt['ref'];
        $text = ['intro' => 'Sur une pelouse grasse, Sochaux déroula sans trembler.', 'sections' => [['titre' => 'Le déroulé', 'texte' => "Il fallut dix minutes au buteur pour lancer la machine.\n\nLa suite fut une démonstration."]],
            'temps_forts' => [['minute' => '10', 'texte' => 'Premier but sochalien.', 'but' => true]], 'chiffre' => ['nombre' => '8', 'texte' => 'buts encaissés par Chalon.']];
        if ($ref === 'match:1931-10-17|chalon' && $copy) {
            $text['intro'] = strip_tags($report); // recopie : doit être refusée
            $copy = false;
        }
        if (str_starts_with($ref, 'page:')) {
            $text['faits'] = ['date' => '1934-05-13', 'domicile' => 'Nîmes', 'exterieur' => 'Sochaux', 'buts_domicile' => 1, 'buts_exterieur' => 2, 'competition' => 'Championnat',
                'stade' => 'Stade Jean-Bouin', 'spectateurs' => 8000, 'composition_sochaux' => ['Thibault', 'Hug', 'Mattler', 'Lehmann', 'Ruminski', 'Laurent', 'Duhart', 'Courtois', 'Abegglen', 'Trello', 'Miller'], 'buteurs_sochaux' => "Courtois 12', Abegglen 70'"];
        }
        if (str_starts_with($ref, 'player:')) {
            $text += ['prenom' => 'Zébulon', 'nom' => 'ZZINCONNU', 'poste' => 'avant', 'nationalite' => 'française'];
        }
        if (str_starts_with($ref, 'article:')) {
            $text['titre'] = 'Pâques 1930 à Bruxelles';
        }
        $out[$i] = ['text' => json_encode($text, JSON_UNESCAPED_UNICODE), 'finish' => 'STOP', 'tokens_in' => 0, 'tokens_out' => 0, 'model' => 'essai'];
    }
    return $out;
};
$created = [];
try {
    $r = FcsmImport::run(100);
    $eq('un texte trop proche de l\'original est réécrit', $r['close'], 1);
    $r2 = FcsmImport::run(100);
    $st = FcsmImport::state()['items'];
    $eq('tout est fait au deuxième passage', [$r2['left'], array_count_values(array_column($st, 'status'))['a-faire'] ?? 0], [0, 0]);
    foreach ($st as $x) {
        if (($x['status'] ?? '') === 'fait' && !empty($x['fiche'])) {
            $created[] = (int) $x['fiche'];
        }
    }
    $doc = Fiches::fresh((int) $st['match:1931-10-17|chalon']['fiche']);
    $eq('fiche de match publiée, rangée dans sa saison et sa décennie', [$doc['status'], $doc['categories']], ['publie', ['1931-1932', 'annees-30-fc-sochaux-retro-fcsm', 'matchs-fc-sochaux-retro-fcsm']]);
    $eq('titre et adresse au format du musée', [$doc['title'], $doc['path']], ['Championnat – FC Chalon / Sochaux – 17/10/1931 – 0-8', '/matchs/1931-1932/fc-chalon-sochaux-championnat-17-10-1931/']);
    $eq('score à l\'extérieur, résultat', [$doc['match']['score']['home'], $doc['match']['score']['away'], $doc['match']['result'], $doc['match']['sochaux_home']], [0, 8, 'V', false]);
    $eq('composition et buts', [count($doc['match']['lineup']['rows']), $doc['match']['lineup']['rows'][8]['name'], $doc['match']['lineup']['rows'][8]['goals_text']], [11, 'KENNER', '4 buts']);
    $eq('texte réécrit, temps forts, chiffre clé', [$doc['intro'], count($doc['match']['highlights']), $doc['key_figure']['number']], ['Sur une pelouse grasse, Sochaux déroula sans trembler.', 1, '8']);
    $last = end($doc['sections']);
    $eq('ligne « Sources » avec les deux pages d\'origine', [$last['title'], substr_count($last['html'], 'href="https://fcsmstory.com/')], ['Sources', 2]);
    $amiens = Fiches::fresh((int) $st['match:1931-10-25|amiens']['fiche']);
    $eq('match sans récit : fiche des faits, sans IA', [$amiens['title'], str_starts_with($amiens['intro'], 'Victoire sochalienne 1 à 0 face à Amiens à Amiens'), $amiens['match']['competition']], ['Amical – Amiens / Sochaux – 25/10/1931 – 0-1', true, 'Amical']);
    $nimes = Fiches::fresh((int) $st['page:13-mai-1934-nimes-sochaux']['fiche']);
    $eq('match raconté à part : faits tirés du texte', [$nimes['match']['date'], $nimes['match']['score_raw'], $nimes['match']['result'], $nimes['match']['spectators'], $nimes['match']['season']], ['1934-05-13', '1-2', 'V', 8000, '1933-1934']);
    $pl = Fiches::fresh((int) $st['player:zz-inconnu-fcsm']['fiche']);
    $eq('portrait du joueur absent du musée', [$pl['type'], $pl['title'], $pl['path']], ['personne', 'Zébulon Zzinconnu', '/joueurs/zebulon-zzinconnu/']);
    $ar = Fiches::fresh((int) $st['article:la-coupe-dupuich-1930-fcsm']['fiche']);
    $eq('tournoi : article rangé dans sa saison', [$ar['type'], $ar['title'], $ar['categories'][0] ?? null], ['article', 'Pâques 1930 à Bruxelles', '1930-1931']);
    $c34 = Categories::get('1933-1934');
    $eq('saison absente du musée créée sous sa décennie', [$c34['parent'] ?? null, $c34['path'] ?? null, in_array('1933-1934', $nimes['categories'], true)], ['annees-30-fc-sochaux-retro-fcsm', '/matchs/1933-1934/', true]);
    $eq('récit de saison : texte de la rubrique', str_contains((string) (Categories::get('1931-1932')['description'] ?? ''), 'Sur une pelouse grasse'), true);
    $before = $calls;
    FcsmImport::plan($a);
    $r3 = FcsmImport::run(100);
    $eq('relancé : rien n\'est refait ni dupliqué', [$r3['done'], $calls - $before], [0, 0]);

    // Photos : originaux du domaine public seulement, dans la fiche du bon match, légende et crédit.
    $eq('photos retenues ou écartées', array_map(fn ($f) => \App\Services\FcsmPhotos::eligible($f)[1] ?? null, [
        '1935-02-04-LAuto-velo-photo-match-RC-Paris.jpg', '1929-09-08-Buffalo_8_9_29_equipe_du_Football__.Agence_Rol.jpg', '1932-01-05-Le_Miroir_des_sports-Gibson.png',
        '1930-06-01-photo-equipe-2-ia1.jpg', '1935-12-12-Football-croque-Mattler.png', '1962-05-01-LEquipe-titre.jpg', 'Lassalette-82cb81ed.jpg', 'ecuson-FCS1.png', '1933-09-10-Excelsior-collector-ai-nb.jpg']),
        ['L’Auto', 'Agence Rol', 'Le Miroir des sports', null, null, null, null, null, null]);
    $jpeg = (function () { $im = imagecreatetruecolor(40, 30); ob_start(); imagejpeg($im); imagedestroy($im); return ob_get_clean(); })();
    $got = [];
    \App\Services\FcsmPhotos::$get = function (string $url) use ($jpeg, &$got) { $got[] = $url; return $jpeg; };
    $pr = \App\Services\FcsmPhotos::run(50);
    $eq('une seule photo reprise, téléchargée en pleine taille', [$pr['done'], $got], [1, ['https://fcsmstory.com/wp-content/uploads/2024/03/1931-10-18-LAuto-velo-Chalon-Sochaux.jpg']]);
    $g = Fiches::fresh((int) $st['match:1931-10-17|chalon']['fiche'])['gallery'];
    $eq('fiche sans image : la photo devient l\'image principale (mosaïques)', Fiches::fresh((int) $st['match:1931-10-17|chalon']['fiche'])['featured_image'], $g[0]['image'] ?? null);
    $eq('photo dans la galerie du match de la veille, légende et crédit', [count($g), $g[0]['caption'] ?? null, $g[0]['credit'] ?? null],
        [1, 'Le compte rendu du lendemain (L’Auto, 18 octobre 1931)', \App\Services\FcsmPhotos::CREDIT]);
    $m = \App\Data\Media::get($g[0]['image']);
    $created[] = 'media:' . $g[0]['image'];
    $eq('médiathèque : crédit, droits, source', [$m['credit'] ?? null, $m['rights'] ?? null, $m['source'] ?? null],
        [\App\Services\FcsmPhotos::CREDIT, 'Domaine public (L’Auto, 1931)', 'https://fcsmstory.com/wp-content/uploads/2024/03/1931-10-18-LAuto-velo-Chalon-Sochaux.jpg']);
    $pr2 = \App\Services\FcsmPhotos::run(50);
    $eq('relancé : aucune photo en double', [$pr2['done'], count(Fiches::fresh((int) $st['match:1931-10-17|chalon']['fiche'])['gallery'])], [0, 1]);
    $eq('coûts IA comptés sous « Reprise des années 1928-1969 »', isset(AiCosts::USES['import']), true);
} finally {
    foreach ($created as $id) {
        if (is_string($id)) {
            @unlink(\App\Data\Media::ORIGINALS . '/' . substr($id, 6));
            continue;
        }
        Fiches::destroy($id, ['name' => 'Essai']);
        exec('rm -rf ' . escapeshellarg(STORAGE_PATH . "/versions/$id"));
    }
    file_put_contents(DATA_PATH . '/categories.json', $catsBefore);
    file_put_contents(DATA_PATH . '/media.json', $mediaBefore);
    \App\Data\Media::forget();
    @rmdir(\App\Data\Media::ORIGINALS . '/fcsmstory/1931');
    @rmdir(\App\Data\Media::ORIGINALS . '/fcsmstory');
    Categories::forget();
    exec('rm -rf ' . escapeshellarg($tmp));
}
$left = array_filter(iterator_to_array(Index::all()), fn ($s) => str_contains((string) ($s['title'] ?? ''), 'Zzinconnu'));
$eq('fiches d\'essai supprimées', count($left), 0);
echo $fail ? "\n$fail échec(s).\n" : "\nTout est bon.\n";
exit($fail ? 1 : 0);
