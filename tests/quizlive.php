<?php
/**
 * Quiz du club-house (App\Services\QuizLive) : questions (quiz du site et fiches de match),
 * création d'une partie, clé de l'animateur, joueurs (pseudos nettoyés, doublons), réponses
 * (une seule, refusée avant l'ouverture et après la fin), points, fermeture automatique quand
 * tout le monde a répondu, classement, podium, retrait d'un joueur, ménage.
 * Usage : php tests/quizlive.php (code de sortie 1 en cas d'échec). N'écrit que dans un dossier temporaire.
 */
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Services\QuizLive as Q;

$tmp = sys_get_temp_dir() . '/quizlive-test-' . bin2hex(random_bytes(4));
Q::$dir = $tmp;
$fail = 0;
$eq = function (string $label, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    if (!$ok) {
        $fail++;
    }
    echo ($ok ? 'OK   ' : 'FAIL ') . $label . ($ok ? '' : ' => ' . json_encode($got, JSON_UNESCAPED_UNICODE) . ' attendu ' . json_encode($exp, JSON_UNESCAPED_UNICODE)) . "\n";
};
$file = fn (string $code) => "$tmp/$code.json";
// Relit la partie sur le disque (sans le cache de JsonStore).
$reload = function (string $code) use ($file) {
    $r = new ReflectionClass(\App\Core\JsonStore::class);
    $c = $r->getProperty('cache');
    $c->setAccessible(true);
    $all = $c->getValue();
    unset($all[$file($code)]);
    $c->setValue(null, $all);
    return Q::get($code);
};

// Avance l'horloge de la partie : décale t0 dans le passé.
$shift = function (string $code, int $ms) use ($file, $reload) {
    $g = json_decode((string) file_get_contents($file($code)), true);
    $g['t0'] -= $ms;
    file_put_contents($file($code), json_encode($g));
    $reload($code);
};

// Questions : bien formées, bonne réponse dans la liste, réponses différentes.
$qs = Q::questions(16, 'mix');
$okShape = array_filter($qs, fn ($q) => is_string($q['q']) && $q['q'] !== '' && count($q['a']) >= 2 && isset($q['a'][$q['c']]) && count(array_unique($q['a'])) === count($q['a']));
$eq('16 questions bien formées (mélange)', [count($qs), count($okShape)], [16, 16]);
$kinds = array_count_values(array_column($qs, 'kind'));
$eq('moitié quiz du site, moitié fiches', [$kinds['quiz'] ?? 0, 16 - ($kinds['quiz'] ?? 0)], [8, 8]);
$fiches = Q::fromMatches(12);
$eq('fiches : les 4 sortes de questions', array_keys(array_count_values(array_column($fiches, 'kind'))) == ['score', 'year', 'opp', 'scorer'] || count(array_unique(array_column($fiches, 'kind'))) === 4, true);
$eq('fiches : 12 questions, toutes avec une anecdote', [count($fiches), count(array_filter($fiches, fn ($q) => $q['fact'] !== ''))], [12, 12]);
$score = array_values(array_filter($fiches, fn ($q) => $q['kind'] === 'score'))[0] ?? null;
$eq('question de score : 4 scores « a–b »', $score ? count(array_filter($score['a'], fn ($a) => (bool) preg_match('/^\d+–\d+$/u', $a))) : 0, 4);
$en = (function () {
    \App\Services\I18n::set('en');
    $r = Q::create(4, 15, 'en', 'site');
    \App\Services\I18n::set('fr');
    return Q::get($r['code']);
})();
$eq('partie en anglais : questions traduites', [$en['lang'], count($en['questions']), (bool) preg_match('/[A-Za-z]/', $en['questions'][0]['q'])], ['en', 4, true]);
Q::delete($en['code']);

// Création.
$r = Q::create(3, 15, 'fr', 'mix', 'Testeur');
$code = $r['code'];
$g = Q::get($code);
$eq('partie créée : code à 5 chiffres, salle d’attente', [Q::validCode($code), $g['phase'], count($g['questions']), $g['duration']], [true, 'lobby', 3, 15]);
$eq('clé de l’animateur : bonne, fausse, vide', [Q::isHost($g, $r['key']), Q::isHost($g, str_repeat('0', 24)), Q::isHost($g, '')], [true, false, false]);
$eq('codes invalides refusés', [Q::get('1234'), Q::get('../x'), Q::get('abcde')], [null, null, null]);
$eq('bornes : 3 à 30 questions, durée connue', (function () {
    $a = Q::create(1, 7, 'fr', 'site');
    $g = Q::get($a['code']);
    Q::delete($a['code']);
    return [count($g['questions']), $g['duration']];
})(), [3, 20]);

// Joueurs.
$a = Q::join($code, '  Lion<script>  ');
$b = Q::join($code, 'lion<script>');
$c = Q::join($code, 'Zoé');
$eq('pseudo nettoyé, doublon numéroté', [$a['name'], $b['name'], $c['name']], ['Lionscript', 'lionscript 2', 'Zoé']);
$eq('pseudo trop court refusé', is_string(Q::join($code, ' x ')), true);
$eq('jeton du joueur : bon, faux', [(bool) Q::player(Q::get($code), $a['pid'], $a['tok']), Q::player(Q::get($code), $a['pid'], 'faux')], [true, null]);
$eq('jeton jamais gardé en clair', str_contains((string) file_get_contents($file($code)), $a['tok']), false);
$s = Q::screenState(Q::get($code));
$eq('écran : 3 joueurs dans la salle d’attente', [$s['phase'], $s['players'], count($s['names'])], ['lobby', 3, 3]);
Q::kick($code, $c['pid']);
$eq('joueur retiré', [count(Q::get($code)['players']), Q::playerState(Q::get($code), $c['pid'])['phase']], [2, 'gone']);

// Question 1 : 3 s de lecture, puis réponses.
Q::next($code);
$g = Q::get($code);
$q = $g['questions'][0];
$eq('question ouverte après 3 s de lecture', [$g['phase'], $g['idx'], Q::screenState($g)['wait'] > 2000], ['question', 0, true]);
$eq('réponse refusée pendant la lecture', Q::answer($code, $a['pid'], $a['tok'], $q['c'])['ok'], false);
$shift($code, 4000);
$eq('bonne réponse acceptée', Q::answer($code, $a['pid'], $a['tok'], $q['c'])['ok'], true);
$g = $reload($code);
$pts = $g['answers']['0'][$a['pid']][2];
$eq('points entre 500 et 1000 (rapide = plus)', $pts >= 900 && $pts <= 1000, true);
$eq('une seule réponse par question', [Q::answer($code, $a['pid'], $a['tok'], ($q['c'] + 1) % count($q['a']))['ok'], $reload($code)['answers']['0'][$a['pid']][0]], [true, $q['c']]);
$eq('réponse hors liste refusée', Q::answer($code, $b['pid'], $b['tok'], 9)['ok'], false);
$eq('pas encore fini : un joueur doit répondre', Q::phase($reload($code)), 'question');
Q::answer($code, $b['pid'], $b['tok'], ($q['c'] + 1) % count($q['a']));
$g = $reload($code);
$eq('tout le monde a répondu : réponse affichée', Q::phase($g), 'reveal');
$sc = Q::screenState($g);
$eq('écran : répartition et bonne réponse', [array_sum($sc['q']['counts']), $sc['q']['c'], $sc['q']['counts'][$q['c']]], [2, $q['c'], 1]);
$ps = Q::playerState($g, $b['pid']);
$eq('téléphone : verdict, points, rang', [$ps['answered'] !== $ps['c'], $ps['gain'], $ps['rank'], $ps['of']], [true, 0, 2, 2]);
$eq('pas de bonne réponse envoyée pendant la question', array_key_exists('c', Q::playerState(array_merge($g, ['phase' => 'lobby']), $a['pid'])), false);

// Classement, puis question 2 qui se ferme au bout du temps.
Q::next($code);
$g = $reload($code);
$sb = Q::screenState($g);
$eq('classement après la question 1', [$sb['phase'], $sb['top'][0]['name'], $sb['top'][0]['gain'] === $pts, $sb['top'][1]['score']], ['board', 'Lionscript', true, 0]);
Q::next($code);
$shift($code, 3000 + 15000 + 1000);
$g = $reload($code);
$eq('temps écoulé : réponse affichée', Q::phase($g), 'reveal');
$eq('réponse trop tardive refusée', Q::answer($code, $b['pid'], $b['tok'], $g['questions'][1]['c'])['ok'], false);
Q::next($code);
Q::next($code);
$eq('« Suivant » pendant une question : la ferme', [Q::next($code)['phase'] ?? null, Q::phase($reload($code))], ['reveal', 'reveal']);
Q::next($code);
$g = $reload($code);
$se = Q::screenState($g);
$eq('dernière question : podium', [$se['phase'], count($se['top']), $se['top'][0]['rank']], ['end', 2, 1]);
$eq('partie terminée : plus d’inscription', is_string(Q::join($code, 'Retard')), true);
$eq('ex aequo au même rang', (function () {
    $g = ['players' => ['a' => ['name' => 'B', 'score' => 10], 'b' => ['name' => 'A', 'score' => 10], 'c' => ['name' => 'C', 'score' => 3]]];
    return array_map(fn ($r) => [$r[1], $r[3]], Q::ranking($g));
})(), [['A', 1], ['B', 1], ['C', 3]]);

// Liste du back-office, ménage.
$eq('liste du back-office', [count(Q::all()), Q::all()[0]['code'], Q::all()[0]['by']], [1, $code, 'Testeur']);
touch($file($code), time() - 90000);
$eq('ménage : partie de plus de 24 h effacée', [Q::purge(), is_file($file($code))], [1, false]);

array_map('unlink', glob("$tmp/*") ?: []);
@rmdir($tmp);
echo $fail ? "\n$fail échec(s)\n" : "\nTout est bon.\n";
exit($fail ? 1 : 0);
