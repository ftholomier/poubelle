<?php
/**
 * 100 moments du centenaire (App\Services\Moments, App\Services\MomentIdeas) : numéros dans l'ordre
 * des dates de parution (gelés une fois en ligne), contrôle de la date choisie, note de validation,
 * date anniversaire, alertes et rythme du calendrier ; boîte à idées (catalogue des fiches, idées
 * de l'IA appuyées sur ce catalogue seulement, doublons, idées écartées), premier jet « À relire »,
 * grille publique sans date. Gemini est simulé. Usage : php tests/moments.php (code de sortie 1 en
 * cas d'échec). N'écrit que dans un dossier temporaire.
 */
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Data\Fiches;
use App\Data\Index;
use App\Front\Interactive;
use App\Services\AiCosts;
use App\Services\MomentIdeas as Ideas;
use App\Services\Moments;

$tmp = sys_get_temp_dir() . '/moments-test-' . bin2hex(random_bytes(4));
mkdir($tmp, 0775, true);
Ideas::$file = "$tmp/idees.json";
AiCosts::$dir = "$tmp/ia";
$fail = 0;
$eq = function (string $label, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    if (!$ok) {
        $fail++;
    }
    echo ($ok ? 'OK   ' : 'FAIL ') . $label . ($ok ? '' : ' => ' . json_encode($got, JSON_UNESCAPED_UNICODE) . ' attendu ' . json_encode($exp, JSON_UNESCAPED_UNICODE)) . "\n";
};
$day = fn (int $days, string $h = '08:00') => date('c', (int) strtotime(date('Y-m-d', strtotime("$days days")) . ' ' . $h));
$mo = function (int $id, string $status, ?string $at, ?int $n = null, array $extra = []) {
    $d = Fiches::blank('moment');
    $d['id'] = $id;
    $d['title'] = 'Moment ' . $id;
    $d['status'] = $status;
    $d['publish_at'] = $at;
    $d['moment']['number'] = $n;
    return array_replace_recursive($d, $extra);
};

// 1. Numéros : ordre des dates, gelés une fois en ligne, rien pour les brouillons.
$docs = [
    1 => $mo(1, 'publie', $day(-20), 1),
    2 => $mo(2, 'publie', $day(-10), 2),
    3 => $mo(3, 'planifie', $day(30)),
    4 => $mo(4, 'planifie', $day(10), 9),
    5 => $mo(5, 'relire', null, 7),
    6 => $mo(6, 'brouillon', $day(5)),
    7 => $mo(7, 'corbeille', $day(3), 5),
];
$eq('en ligne gardés ; planifiés à la suite par date ; brouillons et corbeille sans numéro', Moments::numbering($docs), [1 => 1, 2 => 2, 3 => 4, 4 => 3, 5 => null, 6 => null, 7 => null]);
$docs[8] = $mo(8, 'planifie', $day(1));
$eq('un moment daté avant les autres prend le numéro suivant les moments en ligne', [Moments::numbering($docs)[8], Moments::numbering($docs)[4], Moments::numbering($docs)[3]], [3, 4, 5]);
$docs[2]['moment']['number'] = 7; // un numéro en ligne n'est jamais changé, même avec un trou
$eq('numéro en ligne gelé (même avec un trou) : les suivants viennent après', [Moments::numbering($docs)[2], Moments::numbering($docs)[8]], [7, 8]);
$docs[9] = $mo(9, 'planifie', $day(-1, '00:00'), null); // date passée : en ligne, sans numéro (données anciennes)
$eq('moment en ligne sans numéro : numéroté à la suite, avant les moments à venir', [Moments::numbering($docs)[9], Moments::numbering($docs)[8]], [8, 9]);
$early = $mo(10, 'publie', $day(60), null, ['date' => $day(0, '00:01')]); // publié à la main, date future oubliée
$eq('publié à la main avec une date future encore saisie : rangé à sa date de publication', Moments::numbering([1 => $docs[1], 3 => $docs[3], 10 => $early])[10], 2);
$many = [];
for ($i = 1; $i <= 102; $i++) {
    $many[$i] = $mo($i, 'planifie', $day($i));
}
$n = Moments::numbering($many);
$eq('au-delà de 100 moments datés : pas de numéro', [$n[100], $n[101], $n[102]], [100, null, null]);

// 2. Contrôle de la date choisie (validation) et note de validation.
$draft = $mo(0, 'relire', null);
$eq('brouillon, à relire : rien à contrôler', Moments::check($draft), null);
$eq('planifié sans date : refusé', is_string(Moments::check(['status' => 'planifie', 'publish_at' => null] + $draft)), true);
$eq('planifié à une date passée : refusé', is_string(Moments::check(['status' => 'planifie', 'publish_at' => $day(-2)] + $draft)), true);
$eq('planifié après le centenaire : refusé', is_string(Moments::check(['status' => 'planifie', 'publish_at' => date('c', strtotime(Moments::end() . ' +1 day'))] + $draft)), true);
$eq('planifié à une date à venir : accepté ; publié tout de suite : accepté', [Moments::check(['status' => 'planifie', 'publish_at' => $day(3)] + $draft), Moments::check(['status' => 'publie'] + $draft)], [null, null]);
$before = $mo(5, 'planifie', $day(-1));
$eq('date passée déjà enregistrée (moment en ligne) : pas de nouveau refus', Moments::check($before, $before), null);
$v = Moments::stamp(['status' => 'planifie', 'publish_at' => $day(3)] + $draft, $draft, ['name' => 'Historienne']);
$eq('validation : par qui', [$v['moment']['validated']['by'] ?? null, isset($v['moment']['validated']['at'])], ['Historienne', true]);
$eq('remis à relire : validation retirée', Moments::stamp(['status' => 'relire'] + $v, $v, ['name' => 'X'])['moment']['validated'], null);
$eq('déjà validé : la note reste celle de la validation', Moments::stamp($v, $v, ['name' => 'Autre'])['moment']['validated']['by'], 'Historienne');

// 3. Date anniversaire de l'événement.
$now = strtotime('2026-10-05 12:00');
$eq('finale du 11 juin 1988 : 11 juin 2027 (39 ans)', Moments::anniversary('1988-06-11', [], $now), ['date' => '2027-06-11', 'years' => 39, 'taken' => false]);
$eq('jour déjà pris : l’anniversaire suivant (40 ans) avant le centenaire ? non : après le 20 mai 2028, garde 2027 « pris »', Moments::anniversary('1988-06-11', ['2027-06-11'], $now), ['date' => '2027-06-11', 'years' => 39, 'taken' => true]);
$eq('20 mars 1937 : 2027 libre, sinon 2028', [Moments::anniversary('1937-03-20', [], $now)['date'], Moments::anniversary('1937-03-20', ['2027-03-20'], $now)['date']], ['2027-03-20', '2028-03-20']);
$eq('29 février : 28 février les années non bissextiles', Moments::anniversary('1980-02-29', [], $now)['date'], '2027-02-28');
$eq('pas de date : pas de proposition', Moments::anniversary(null), null);
$match = null;
foreach (Index::published('match') as $s) {
    if (!empty($s['m']['date'])) {
        $match = $s;
        break;
    }
}
$eq('date de l’événement : saisie, sinon celle du premier match lié', [Moments::eventDate($mo(1, 'relire', null, null, ['moment' => ['event_date' => '1938-05-01']])), Moments::eventDate($mo(1, 'relire', null, null, ['moment' => ['linked' => [$match['id']]]]))], ['1938-05-01', $match['m']['date']]);

// 4. Alertes et rythme du calendrier.
$rows = [
    ['id' => 1, 'title' => 'A', 'status' => 'publie', 'number' => 1, 'date' => date('c', $now - 86400 * 9), 'visible' => true, 'image' => 'x.jpg', 'year' => 1988, 'event' => null, 'ai' => null, 'validated' => null, 'path' => ''],
    ['id' => 2, 'title' => 'B', 'status' => 'planifie', 'number' => 2, 'date' => date('c', $now + 86400 * 5), 'visible' => false, 'image' => null, 'year' => 1990, 'event' => null, 'ai' => null, 'validated' => null, 'path' => ''],
    ['id' => 3, 'title' => 'C', 'status' => 'planifie', 'number' => 3, 'date' => date('c', $now + 86400 * 5 + 3600), 'visible' => false, 'image' => 'y.jpg', 'year' => 1991, 'event' => null, 'ai' => null, 'validated' => null, 'path' => ''],
    ['id' => 4, 'title' => 'D', 'status' => 'planifie', 'number' => 4, 'date' => date('c', $now + 86400 * 80), 'visible' => false, 'image' => 'z.jpg', 'year' => 1992, 'event' => null, 'ai' => null, 'validated' => null, 'path' => ''],
];
$texts = array_column(Moments::alerts($rows, $now), 'text');
$has = fn (string $needle) => (bool) array_filter($texts, fn ($t) => str_contains($t, $needle));
$eq('alertes : même jour, trou de plusieurs semaines, rien jusqu’au centenaire, sans image', [$has('le même jour'), $has('entre le'), $has('(centenaire)'), $has('Sans image')], [true, true, true, true]);
$eq('rythme : 96 moments à dater', Moments::pace($rows, $now)['left'], 96);

// 5. Boîte à idées : catalogue, idées de l'IA appuyées sur le catalogue seulement.
$cat = Ideas::catalog(1980, 1989);
$eq('catalogue des années 1980 : des matchs, des personnes ; la finale de 1988 en tête', [count($cat['lines']) > 50, str_contains($cat['lines'][0], '11/06/1988'), (bool) array_filter($cat['lines'], fn ($l) => str_contains($l, '· personne ·'))], [true, true, true]);
$eq('chaque ligne commence par le numéro d’une fiche publiée', count(array_filter($cat['ids'], fn ($id) => ($s = Index::get($id)) && Index::visible($s))), count($cat['ids']));
$final = $cat['ids'][0];
$person = (int) current(array_filter($cat['ids'], fn ($id) => Index::get($id)['type'] === 'personne'));
$answer = json_encode([
    ['title' => 'La finale de 1988', 'year' => 1988, 'date' => '1988-06-11', 'why' => 'Sochaux dispute la finale de la Coupe de France, perdue aux tirs au but.', 'theme' => 'match', 'sources' => [$final]],
    ['title' => 'Un exploit inventé', 'year' => 1985, 'date' => '', 'why' => 'Aucune fiche ne raconte cette histoire-là, l’IA l’invente.', 'theme' => 'match', 'sources' => [999999]],
    ['title' => 'La finale de 1988', 'year' => 1988, 'date' => '', 'why' => 'Même idée, même titre : un doublon à écarter.', 'theme' => 'match', 'sources' => [$person]],
    ['title' => 'Un grand joueur', 'year' => 3000, 'date' => '1988-13-45', 'why' => 'Année et date impossibles, corrigées ou vidées.', 'theme' => 'inconnu', 'sources' => [$person, $person]],
], JSON_UNESCAPED_UNICODE);
$ideas = Ideas::parseIdeas("```json\n$answer\n```", $cat['ids'], []);
$eq('idées gardées : appuyées sur le catalogue, sans doublon', array_column($ideas, 'title'), ['La finale de 1988', 'Un grand joueur']);
$eq('date et année : la date fait foi ; impossibles, vidées ; thème inconnu : « autre » ; source unique', [$ideas[0]['year'], $ideas[0]['date'], $ideas[1]['year'], $ideas[1]['date'], $ideas[1]['theme'], $ideas[1]['sources']], [1988, '1988-06-11', null, null, 'autre', [$person]]);
$eq('réponse illisible : aucune idée', [Ideas::parseIdeas('pas du JSON', $cat['ids'], []), Ideas::parseIdeas('{"ideas":"x"}', $cat['ids'], [])], [[], []]);

// 6. Idées : ajout par l'équipe, propositions de l'IA (simulée), tri, couverture.
$calls = [];
Ideas::$ai = function (array $jobs) use (&$calls, $answer) {
    $calls[] = $jobs;
    return array_map(fn ($j) => ['text' => $answer, 'model' => 'essai', 'finish' => 'STOP', 'tokens_in' => 0, 'tokens_out' => 0], $jobs);
};
$me = ['name' => 'Historien d’essai'];
$mine = Ideas::add(['title' => 'Le premier match à Bonal', 'year' => 1931, 'why' => 'Ouverture du stade de la Forge.', 'theme' => 'stade', 'sources' => [$person]], $me);
$eq('idée de l’équipe : retenue d’office', [$mine['state'], $mine['origin'], $mine['by']], ['retenue', 'equipe', 'Historien d’essai']);
$r = Ideas::propose($me);
$eq('sommaire : une demande par époque, en une fois', [count($calls), count($calls[0]) >= 9], [1, true]);
$eq('chaque demande : consignes « n’invente rien », catalogue, idées déjà là', [str_contains($calls[0][0][1], 'N\'invente aucun fait'), str_contains($calls[0][4][0][0]['text'], 'CATALOGUE'), str_contains($calls[0][4][0][0]['text'], 'DÉJÀ PROPOSÉES')], [true, true, true]);
$eq('sommaire : idées ajoutées une seule fois (doublons écartés)', [$r['added'], count(array_filter(Ideas::all(), fn ($i) => $i['title'] === 'La finale de 1988'))], [2, 1]);
$finalIdea = current(array_filter(Ideas::all(), fn ($i) => $i['title'] === 'La finale de 1988'));
Ideas::setState($finalIdea['id'], 'ecartee', 'Déjà raconté ailleurs', $me);
Ideas::propose($me);
$eq('idée écartée : rappelée à l’IA avec sa raison', str_contains($calls[1][4][0][0]['text'], 'La finale de 1988 (1988) : Déjà raconté ailleurs'), true);
$eq('couverture : années 1930 (idée de l’équipe), années 1980 (idée écartée non comptée)', [Ideas::coverage()[1930]['kept'], Ideas::coverage()[1980]['ideas']], [1, 0]);
$edited = Ideas::edit($mine['id'], ['title' => 'Bonal ouvre ses portes', 'date' => '1931-06-14'], $me);
$eq('modifier : titre et date (l’année suit la date)', [$edited['title'], $edited['year'], $edited['date']], ['Bonal ouvre ses portes', 1931, '1931-06-14']);
$failed = false;
try {
    Ideas::add(['title' => '  '], $me);
} catch (\InvalidArgumentException $e) {
    $failed = true;
}
$eq('idée sans titre : refusée', $failed, true);

// 7. Premier jet : texte de l'IA lu, fiche « À relire », provenance gardée pour le back-office.
$draftJson = json_encode(['title' => 'Bonal ouvre ses portes', 'hook' => 'Le 14 juin 1931, le stade accueille son premier match.', 'paragraphs' => [str_repeat('Un récit précis, tiré des fiches. ', 8), str_repeat('La suite du récit, sans invention. ', 6)], 'caption' => 'Le stade en 1931', 'checks' => ['Date exacte du premier match', 'Affluence'], 'sources_used' => [$person, 424242]], JSON_UNESCAPED_UNICODE);
$d = Ideas::parseDraft($draftJson, [$person]);
$eq('premier jet lu : titre, paragraphes, points à vérifier, sources du dossier seulement', [$d['title'], count($d['paragraphs']), $d['checks'], $d['sources']], ['Bonal ouvre ses portes', 2, ['Date exacte du premier match', 'Affluence'], [$person]]);
$eq('premier jet trop court ou illisible : refusé', [Ideas::parseDraft('{"title":"x","paragraphs":["court"]}', [$person]), Ideas::parseDraft('???', [$person])], [null, null]);
$doc = Ideas::draftDoc(Ideas::get($mine['id']), $d, 'essai', $me);
$eq('fiche : « À relire », sans numéro ni date, récit et fiches liées', [$doc['type'], $doc['status'], $doc['moment']['number'], $doc['publish_at'], str_contains($doc['intro'], 'premier match'), str_contains($doc['sections'][0]['html'], '<p>Un récit'), $doc['moment']['linked'], $doc['moment']['event_date']], ['moment', 'relire', null, null, true, true, [$person], '1931-06-14']);
$eq('provenance (IA, sources, points à vérifier) gardée pour le back-office', [$doc['moment']['ai']['model'], $doc['moment']['ai']['checks'], $doc['moment']['ai']['by']], ['essai', ['Date exacte du premier match', 'Affluence'], 'Historien d’essai']);
$eq('rien de la provenance dans les textes publiés', [str_contains($doc['intro'] . $doc['sections'][0]['html'] . $doc['title'] . $doc['seo']['description'], 'IA'), str_contains($doc['path'], '/centenaire/100-moments/')], [false, true]);
$eq('un premier jet n’est jamais planifié ni publié par l’IA', Moments::check($doc), null);

// 8. Grille publique : 100 cases, sans date, « À venir » tant que le moment n'est pas en ligne.
$grid = Interactive::moments100();
$eq('grille : 100 cases, ni date ni case « bientôt »', [count($grid), array_key_exists('date', $grid[0]), array_key_exists('due', $grid[0])], [100, false, false]);
$eq('numéros 1 à 100 dans l’ordre', array_column($grid, 'n'), range(1, 100));

Ideas::$ai = null;
array_map('unlink', glob("$tmp/ia/*") ?: []);
@rmdir("$tmp/ia");
array_map('unlink', glob("$tmp/*") ?: []);
@rmdir($tmp);
echo $fail ? "\n$fail échec(s)\n" : "\nTout est bon.\n";
exit($fail ? 1 : 0);
