<?php
/**
 * Trouvailles (App\Services\Trouvailles) : recherche dans la presse ancienne de Gallica (numéros
 * du jour du match à trois jours après, pages qui citent Sochaux, texte ALTO recollé, passages
 * qui citent aussi l'adversaire d'abord), lecture par l'IA, propositions (rien de ce que la fiche
 * dit déjà, sources fusionnées, jamais reproposées), web, validation dans la fiche (champs, récit,
 * sources), écarter, file d'attente. Gallica et Gemini sont simulés ; la fiche d'essai est
 * supprimée à la fin. Usage : php tests/trouvailles.php
 */
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Data\Fiches;
use App\Data\Index;
use App\Services\AiCosts;
use App\Services\Trouvailles as T;

$tmp = sys_get_temp_dir() . '/trouvailles-test-' . bin2hex(random_bytes(4));
T::$dir = $tmp;
AiCosts::$dir = "$tmp/ia";
$fail = 0;
$eq = function (string $label, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    if (!$ok) {
        $fail++;
    }
    echo ($ok ? 'OK   ' : 'FAIL ') . $label . ($ok ? '' : ' => ' . json_encode($got, JSON_UNESCAPED_UNICODE) . ' attendu ' . json_encode($exp, JSON_UNESCAPED_UNICODE)) . "\n";
};

// Gallica simulé : deux journaux le lendemain du match, l'un cite Chalon, l'autre seulement des juniors.
$alto = fn (array $blocks) => '<?xml version="1.0" encoding="UTF-8"?><alto>' . implode('', array_map(fn ($b) => '<TextBlock ID="b">' . implode('', array_map(
    fn ($w) => is_array($w) ? '<String CONTENT="' . $w[0] . '" SUBS_TYPE="' . $w[1] . '" SUBS_CONTENT="' . $w[2] . '"/>' : '<String CONTENT="' . htmlspecialchars($w) . '"/>',
    $b
)) . '</TextBlock>', $blocks)) . '</alto>';
$reportWords = array_merge(explode(' ', 'Championnat de Bourgogne-Franche-Comté. A Chalon-sur-Saône, le F.C. Sochaux disposa de Chalon par 8 buts à 0. Superbe démonstration où Kenner et les frères Laurent et'), [['Mat-', 'HypPart1', 'Mattler'], ['tler', 'HypPart2', 'Mattler']], explode(' ', 'firent grosse impression. Arbitre M. Cabanne.'));
$calls = [];
T::$get = function (string $url) use ($alto, $reportWords, &$calls) {
    $calls[] = $url;
    if (str_contains($url, '/SRU')) {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $q);
        if (!str_contains($q['query'], '1931/10/18')) {
            return '<srw:searchRetrieveResponse><srw:numberOfRecords>0</srw:numberOfRecords></srw:searchRetrieveResponse>';
        }
        $rec = fn ($ark, $title) => '<srw:record><srw:recordData><oai_dc:dc><dc:title>' . $title . '</dc:title></oai_dc:dc></srw:recordData><srw:extraRecordData><uri>' . $ark . '</uri></srw:extraRecordData></srw:record>';
        return '<srw:searchRetrieveResponse><srw:numberOfRecords>2</srw:numberOfRecords>' . $rec('bpt6kjunior1', 'Le Petit journal (Paris. 1863)') . $rec('bpt6kecho01', 'L\'Écho des sports : organe hebdomadaire de tous les sports') . '</srw:searchRetrieveResponse>';
    }
    if (str_contains($url, 'ContentSearch')) {
        return str_contains($url, 'bpt6kecho01')
            ? '<results><item><p_id>PAG_5</p_id><content>F.C. &lt;span class=\'highlight\'&gt;Sochaux&lt;/span&gt; bat Chalon 8-0</content></item></results>'
            : '<results><item><p_id>PAG_2</p_id><content>F. C. Sochaux (juniors) et A. S. Audincourt font match nul</content></item></results>';
    }
    if (str_contains($url, 'RequestDigitalElement')) {
        return str_contains($url, 'bpt6kecho01')
            ? $alto([explode(' ', 'Publicité : grand hôtel moderne.'), $reportWords])
            : $alto([explode(' ', 'Football. F. C. Sochaux (juniors) et A. S. Audincourt (juniors) font match nul 1 à 1.')]);
    }
    throw new RuntimeException('adresse inattendue ' . $url);
};
// Gemini simulé : réponse de la presse, puis du web (avec pages consultées).
$asked = [];
$copy = false;
T::$ai = function (string $system, string $text, array $opt) use (&$asked, &$copy) {
    $asked[] = ['system' => $system, 'text' => $text, 'opt' => $opt];
    if (!empty($opt['tools'])) {
        return ['text' => json_encode(['concerne' => true, 'arbitre' => ['valeur' => 'M. Cabanne', 'urls' => ['https://exemple.org/chalon-sochaux-1931']],
            'stade' => ['valeur' => 'Stade Léo-Lagrange', 'urls' => ['https://exemple.org/chalon-sochaux-1931']], 'infos' => [], 'pistes' => [['texte' => 'Archives municipales de Chalon : photos du match.', 'urls' => []]]], JSON_UNESCAPED_UNICODE),
            'raw' => ['candidates' => [['groundingMetadata' => ['groundingChunks' => [['web' => ['uri' => 'https://exemple.org/chalon-sochaux-1931', 'title' => 'exemple.org']], ['web' => ['uri' => 'https://musee.fcsochauxretro.com/matchs/x/', 'title' => 'fcsochauxretro.com']]]]]]]];
    }
    $recit = $copy
        ? 'A Chalon-sur-Saône, le F.C. Sochaux disposa de Chalon par 8 buts à 0. Superbe démonstration où Kenner et les frères Laurent et Mattler firent grosse impression. ' . str_repeat('Les Sochaliens ont dominé la rencontre de bout en bout. ', 6)
        : 'Sur la pelouse chalonnaise, les Lionceaux ont livré une démonstration sans appel. Kenner a lancé le festival, bien relayé par les frères Laurent, et Mattler a tenu la défense d’une main de fer. Les jeunes Chalonnais, courageux, n’ont jamais renoncé mais ont plié huit fois face à une équipe déjà taillée pour les sommets. Ce succès éclatant confirmait la place de Sochaux parmi les meilleures formations de la région, à quelques mois du passage au professionnalisme.';
    return ['text' => '```json' . "\n" . json_encode([
        'concerne' => true,
        'score' => ['sochaux' => 8, 'adversaire' => 0, 'sources' => [1]],
        'buteurs' => ['valeur' => 'Kenner, Laurent', 'sources' => [1]],
        'composition' => ['valeur' => 'Lozès, Mattler, Wartel, J. Laurent, Galland, Kenner, L. Laurent, Duhart, Courtois, Rodriguez, Kuntz', 'sources' => [1]],
        'affluence' => ['valeur' => '3 000', 'sources' => [1]],
        'arbitre' => ['valeur' => 'M. Cabanne', 'sources' => [1]],
        'stade' => null,
        'recit' => ['texte' => $recit, 'sources' => [1]],
        'infos' => [['texte' => 'Les Chalonnais alignaient plusieurs juniors.', 'sources' => [1, 9]]],
        'pistes' => [],
    ], JSON_UNESCAPED_UNICODE) . "\n```"];
};

$id = null;
try {
    // Fiche de match d'essai : score connu, ni récit, ni composition, ni buteurs.
    $doc = Fiches::blank('match');
    $doc['title'] = 'Zzessai trouvailles – FC Chalon / Sochaux – 17/10/1931';
    $doc['path'] = '/matchs/1931-1932/zzessai-trouvailles-17-10-1931/';
    $doc['slug'] = 'zzessai-trouvailles-17-10-1931';
    $doc['status'] = 'publie';
    $doc['match']['date'] = '1931-10-17';
    $doc['match']['season'] = '1931-1932';
    $doc['match']['home'] = ['name' => 'FC Chalon', 'level' => null];
    $doc['match']['away'] = ['name' => 'Sochaux', 'level' => null];
    $doc['match']['sochaux_home'] = false;
    $doc['match']['score'] = ['home' => 0, 'away' => 8, 'extra' => null, 'aet' => false, 'pens' => null];
    $doc['sections'] = [['title' => 'Sources', 'html' => '<p>FCSM Story.</p>']];
    $id = (int) Fiches::save($doc, ['name' => 'Essai'], 'essai')['id'];

    $eq('mot cherché pour l’adversaire', [T::keyword('FC Chalon'), T::keyword('Red Star'), T::keyword('Stade Rennais'), T::keyword('AS Saint-Étienne')], ['Chalon', 'Red Star', 'Rennais', 'Étienne']);
    $eq('ce qui manque à la fiche', T::gaps(Fiches::get($id)), ['recit', 'composition', 'buteurs', 'affluence', 'arbitre', 'stade']);
    $eq('match proposé à la recherche (1931)', in_array($id, T::candidates(1931, 1931), true), true);
    $eq('hors période : non proposé', in_array($id, T::candidates(1950, 1955), true), false);

    // Texte ALTO : coupure de fin de ligne recollée, passages qui citent l'adversaire seulement.
    $txt = T::altoText($alto([$reportWords]));
    $eq('ALTO : « Mat- tler » recollé en « Mattler »', [str_contains($txt, 'Mattler firent'), str_contains($txt, 'tler firent') && !str_contains($txt, 'Mattler')], [true, false]);
    [$p, $sure] = T::passages("Publicité.\n" . str_repeat('x ', 1200) . "Sochaux juniors.\n" . str_repeat('y ', 2000) . 'Sochaux bat Chalon 8-0.', 'Chalon');
    $eq('passages : seul celui qui cite l’adversaire', [$sure, str_contains($p, 'juniors'), str_contains($p, 'bat Chalon')], [true, false, true]);

    $ex = T::gallica(T::facts(Fiches::get($id)));
    $eq('Gallica : deux journaux, celui qui cite Chalon d’abord', array_map(fn ($e) => [$e['journal'], $e['date'], $e['page'], $e['sure']], $ex), [['L\'Écho des sports', '1931-10-18', 5, true], ['Le Petit journal', '1931-10-18', 2, false]]);
    $eq('Gallica : lien vers la page du journal', $ex[0]['url'], 'https://gallica.bnf.fr/ark:/12148/bpt6kecho01/f5.item');
    $eq('Gallica : recherche exacte, jour par jour, avec l’adversaire', count(array_filter($calls, fn ($u) => str_contains($u, '/SRU') && str_contains(urldecode($u), 'text adj "Sochaux" and text adj "Chalon"'))), 4);

    // Recherche complète : presse puis web.
    $calls = [];
    $r = T::search($id, ['gallica', 'web']);
    $p = T::props($id);
    $byField = [];
    foreach ($p['items'] as $it) {
        $byField[$it['field']] = $it;
    }
    $eq('consigne : le match et les extraits numérotés', [str_contains($asked[0]['text'], '[1] L\'Écho des sports, 1931-10-18, page 5'), str_contains($asked[0]['text'], 'Score selon le musée (Sochaux d\'abord) : 8-0'), $asked[0]['opt']['for']], [true, true, 'trouvailles']);
    $eq('propositions : ce qui manque, pas le score déjà juste', array_keys($byField), ['buteurs', 'composition', 'arbitre', 'affluence', 'recit', 'info', 'stade', 'piste']);
    $eq('affluence lue « 3 000 » → 3000, complément', [$byField['affluence']['value'], $byField['affluence']['type']], ['3000', 'complement']);
    $eq('source : journal, date, page, lien', $byField['arbitre']['sources'][0]['label'] . ' | ' . $byField['arbitre']['sources'][0]['url'], 'L\'Écho des sports, 18 octobre 1931, p. 5 | https://gallica.bnf.fr/ark:/12148/bpt6kecho01/f5.item');
    $eq('même arbitre trouvé sur le web : une seule proposition, deux sources', [count(array_filter($p['items'], fn ($it) => $it['field'] === 'arbitre')), array_column($byField['arbitre']['sources'], 'url')], [1, ['https://gallica.bnf.fr/ark:/12148/bpt6kecho01/f5.item', 'https://exemple.org/chalon-sochaux-1931']]);
    $eq('web : le site du musée n’est jamais une source', in_array('https://musee.fcsochauxretro.com/matchs/x/', array_merge(...array_map(fn ($it) => array_column($it['sources'], 'url'), $p['items'])), true), false);
    $eq('source inconnue (n° 9) ignorée', count($byField['info']['sources']), 1);
    $eq('relancé : rien en double', [T::search($id, ['gallica', 'web'])['new'], count(T::props($id)['items'])], [0, count($p['items'])]);
    $eq('résumé : 8 en attente, 1 match', [T::summary()['attente'], T::summary()['matchs'], T::summary()['searched']], [8, 1, 1]);
    $eq('match fouillé : plus proposé (sauf « refouiller »)', [in_array($id, T::candidates(1931, 1931), true), in_array($id, T::candidates(1931, 1931, true, true), true)], [false, true]);

    // Validation : champs, récit, sources ; refus d'une valeur absurde ; écarter.
    T::accept($id, $byField['arbitre']['id'], null, ['name' => 'Essai']);
    T::accept($id, $byField['affluence']['id'], '3500', ['name' => 'Essai']);
    T::accept($id, $byField['composition']['id'], 'Lozès, Mattler, Wartel, J. Laurent, Galland, Kenner, L. Laurent, Duhart, Courtois, Rodriguez, Kuntz', ['name' => 'Essai']);
    T::accept($id, $byField['recit']['id'], null, ['name' => 'Essai']);
    T::accept($id, $byField['buteurs']['id'], 'Kenner (2), Laurent', ['name' => 'Essai']);
    $f = Fiches::fresh($id);
    $eq('fiche : arbitre, affluence corrigée à la main, buteurs', [$f['match']['referee'], $f['match']['spectators'], $f['match']['goals_text'], $f['match']['goals'][0]], ['M. Cabanne', 3500, 'Kenner (2), Laurent', ['team' => 'Sochaux', 'scorers' => 'Kenner (2), Laurent']]);
    $eq('fiche : composition (gardien d’abord, noms au format du musée)', [count($f['match']['lineup']['rows']), $f['match']['lineup']['rows'][0]['position'], $f['match']['lineup']['rows'][0]['name'], $f['match']['lineup']['rows'][3]['name']], [11, 'G', 'LOZÈS', 'LAURENT J']);
    $eq('fiche : récit dans « Dans la presse de l’époque », avant « Sources »', array_column($f['sections'], 'title'), ['Dans la presse de l’époque', 'Sources']);
    $eq('fiche : récit crédité, sources ajoutées une seule fois', [str_contains($f['sections'][0]['html'], 'D’après <a href="https://gallica.bnf.fr/ark:/12148/bpt6kecho01/f5.item"'), substr_count($f['sections'][1]['html'], 'bpt6kecho01'), str_contains($f['sections'][1]['html'], 'FCSM Story'), str_contains($f['sections'][1]['html'], '(Gallica, BnF)')], [true, 1, true, true]);
    $eq('plus rien ne manque côté récit, composition, arbitre', array_values(array_intersect(T::gaps($f), ['recit', 'composition', 'arbitre', 'affluence', 'buteurs'])), []);
    $err = null;
    try {
        T::accept($id, $byField['stade']['id'], '', ['name' => 'Essai']);
    } catch (RuntimeException $e) {
        $err = $e->getMessage();
    }
    $eq('valeur vide refusée', $err, 'Valeur vide.');
    $err = null;
    try {
        T::accept($id, $byField['arbitre']['id'], null, ['name' => 'Essai']);
    } catch (RuntimeException $e) {
        $err = $e->getMessage();
    }
    $eq('proposition déjà envoyée : refusée', $err, 'Proposition déjà traitée.');
    T::reject($id, $byField['stade']['id'], ['name' => 'Essai']);
    T::search($id, ['gallica', 'web']);
    $eq('écartée : jamais reproposée', count(array_filter(T::props($id)['items'], fn ($it) => $it['field'] === 'stade')), 1);
    T::restore($id, $byField['stade']['id']);
    $eq('remise en attente', array_values(array_filter(T::props($id)['items'], fn ($it) => $it['field'] === 'stade'))[0]['status'], 'attente');
    $eq('liste de l’écran : le match et ses propositions envoyées', [count(T::listing('envoye')), count(T::listing('envoye')[0]['items'] ?? [])], [1, 5]);
    T::accept($id, $byField['info']['id'], null, ['name' => 'Essai']);
    T::accept($id, $byField['piste']['id'], null, ['name' => 'Essai']);
    $f = Fiches::fresh($id);
    $eq('information : section « Compléments » ; piste : seulement dans les sources', [array_column($f['sections'], 'title'), str_contains($f['sections'][2]['html'], 'exemple.org')], [['Dans la presse de l’époque', 'Compléments', 'Sources'], true]);

    // Récit recopié des journaux : écarté.
    file_put_contents("$tmp/props/$id.json", json_encode(['id' => $id, 'items' => []]));
    $copy = true;
    T::search($id, ['gallica']);
    $eq('récit trop proche du journal : pas proposé', in_array('recit', array_column(T::props($id)['items'], 'field'), true), false);
    $copy = false;

    // File d'attente et tâche planifiée.
    $eq('file : un match, tâche planifiée', [T::start([$id], ['gallica']), str_starts_with((string) T::tick(), '1 match(s) fouillé(s)'), T::state()['running'], T::tick()], [1, true, false, null]);
    T::start([$id], ['gallica']);
    $lk = fopen("$tmp/work.lock", 'c');
    flock($lk, LOCK_EX);
    $eq('fouille déjà en cours (tâche planifiée / page ouverte) : on attend', [T::work(10, 1)['busy'] ?? false, count(T::state()['queue'])], [true, 1]);
    flock($lk, LOCK_UN);
    fclose($lk);
    $eq('verrou libéré : la page fait avancer la file, journal à jour', [T::work(60, 1)['done'], count(T::state()['queue']), str_contains(T::state()['log'][0], 'FC Chalon – Sochaux 0-8 (1931)')], [1, 0, true]);
    $eq('coûts IA comptés sous « Trouvailles »', isset(AiCosts::USES['trouvailles']), true);
} finally {
    if ($id) {
        Fiches::destroy($id, ['name' => 'Essai']);
        exec('rm -rf ' . escapeshellarg(STORAGE_PATH . "/versions/$id"));
    }
    exec('rm -rf ' . escapeshellarg($tmp));
}
$left = array_filter(iterator_to_array(Index::all()), fn ($s) => str_contains((string) ($s['title'] ?? ''), 'Zzessai'));
$eq('fiche d’essai supprimée', count($left), 0);
echo $fail ? "\n$fail échec(s).\n" : "\nTout est bon.\n";
exit($fail ? 1 : 0);
