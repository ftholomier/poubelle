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
$eq('« Suivant » pendant les 3 s de lecture : ignoré (double appui)', [Q::next($code)['phase'] ?? null, Q::phase($reload($code))], ['question', 'question']);
$shift($code, 3001);
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

// ------------------------------------------------------------------ championnat
use App\Services\QuizChampionship as CH;
use App\Services\Carnet;

CH::$file = "$tmp/championnat.json";
Carnet::$dir = "$tmp/carnets";
$eq('saison : 1er août – 31 juillet', [CH::season(strtotime('2026-08-01')), CH::season(strtotime('2027-07-31')), CH::season(strtotime('2026-07-31'))], ['2026-2027', '2026-2027', '2025-2026']);
$acc = fn (string $mail) => Carnet::create($mail)['carnet']['id'];
[$u1, $u2, $u3] = [$acc('joueur1@example.org'), $acc('joueur2@example.org'), $acc('joueur3@example.org')];
$eq('pseudo du championnat enregistré', [CH::setPseudo($u1, '  Le <b>Kop</b> '), CH::player($u1)['pseudo']], [null, 'Le bKop/b']);
$eq('pseudo déjà pris (casse, espaces) refusé', is_string(CH::setPseudo($u2, 'le   bkop/b')), true);
$eq('pseudo libre / pris', [CH::free('Mamie'), CH::free('LE BKOP/B'), CH::free('Le bKop/b', $u1)], [true, false, true]);
CH::setPseudo($u2, 'Mamie Bonal');
CH::setPseudo($u3, 'Zizou');
$eq('compte pas encore confirmé (lien de l’e-mail jamais ouvert)', [CH::confirmed($u1), CH::player($u1)['ok']], [false, false]);
foreach ([$u1, $u2, $u3] as $u) {
    Carnet::confirm($u); // lien de l'e-mail ouvert
}
$eq('lien ouvert : compte confirmé au championnat', [CH::confirmed($u1), CH::confirmed($u3)], [true, true]);
$eq('points d’un rang', [CH::points(1), CH::points(2), CH::points(3), CH::points(7), CH::points(8), CH::points(40)], [11, 9, 7, 3, 1, 1]);

// Une partie jouée jusqu'au bout : scores imposés, puis « Suivant » jusqu'au podium.
$play = function (array $who, bool $friendly = false) use ($file, $reload) {
    $r = Q::create(3, 15, 'fr', 'site', '', $friendly);
    $code = $r['code'];
    $pids = [];
    foreach ($who as [$name, $cid, $score]) {
        $j = Q::join($code, $name, $cid);
        $pids[] = $j['pid'];
    }
    $g = json_decode((string) file_get_contents($file($code)), true);
    foreach ($who as $k => [$name, $cid, $score]) {
        $g['players'][$pids[$k]]['score'] = $score;
    }
    $g['phase'] = 'reveal';
    $g['idx'] = 2;
    file_put_contents($file($code), json_encode($g));
    $reload($code);
    $g = Q::next($code);
    return [$code, $g, $pids];
};
[$c1, $g1, $p1] = $play([['Le bKop/b', $u1, 2500], ['Invité', null, 3000], ['Mamie Bonal', $u2, 900], ['Autre', null, 100]]);
$eq('fin de partie : comptée au championnat', [$g1['phase'], $g1['counted']], ['end', true]);
$rk = CH::ranking();
$eq('points selon le rang (invités compris dans le rang)', array_map(fn ($r) => [$r['pseudo'], $r['pts'], $r['rank']], $rk), [['Le bKop/b', 9, 1], ['Mamie Bonal', 7, 2]]);
$eq('mouvements : entrée au championnat', $g1['moves'], [$u1 => [null, 1], $u2 => [null, 2]]);
$eq('une partie n’est comptée qu’une fois', [CH::record($g1)['counted'], CH::ranking()[0]['pts']], [false, 9]);
$se = Q::screenState($g1);
$eq('écran du podium : le championnat après la partie', [count($se['champ']), $se['champ'][0]['new'], $se['champ'][0]['here']], [2, true, true]);
$ps = Q::playerState($g1, $p1[0]);
$eq('téléphone : rang au championnat', [$ps['member'], $ps['champ']['rank'], $ps['champ']['pts']], [true, 1, 9]);
$eq('téléphone d’un invité : pas de championnat', [Q::playerState($g1, $p1[1])['member'], Q::playerState($g1, $p1[1])['champ'] ?? null], [false, null]);
[$c2, $g2] = $play([['Mamie Bonal', $u2, 5000], ['Zizou', $u3, 4000], ['Le bKop/b', $u1, 10]]);
$eq('2e partie : Mamie passe devant, Zizou entre', array_map(fn ($r) => [$r['pseudo'], $r['pts'], $r['games'], $r['wins']], CH::ranking()), [['Mamie Bonal', 18, 2, 1], ['Le bKop/b', 16, 2, 0], ['Zizou', 9, 1, 0]]);
$eq('mouvements de la 2e partie', $g2['moves'], [$u2 => [2, 1], $u1 => [1, 2], $u3 => [null, 3]]);
[, $g3] = $play([['Zizou', $u3, 900], ['Mamie Bonal', $u2, 10], ['Xavier', null, 0]], true);
$eq('partie amicale : pas comptée', [$g3['counted'], CH::stats()['games']], [false, 2]);
[, $g4] = $play([['Zizou', $u3, 900], ['Mamie Bonal', $u2, 10]]);
$eq('moins de 3 joueurs : pas comptée', [$g4['counted'], CH::stats()['games']], [false, 2]);
$eq('écran : le championnat dans la salle d’attente', array_column(Q::screenState(Q::get(Q::create(3, 15, 'fr', 'site')['code']))['champ'], 'name'), ['Mamie Bonal', 'Le bKop/b', 'Zizou']);

// Même compte qui revient, lien de l'e-mail ouvert pendant la partie.
$r5 = Q::create(3, 15, 'fr', 'site');
$a5 = Q::join($r5['code'], 'Zizou', $u3);
$b5 = Q::join($r5['code'], 'Zizou', $u3);
$eq('même compte sur un autre appareil : même place, nouveau jeton', [$a5['pid'] === $b5['pid'], count(Q::get($r5['code'])['players']), (bool) Q::player(Q::get($r5['code']), $b5['pid'], $b5['tok']), Q::player(Q::get($r5['code']), $a5['pid'], $a5['tok'])], [true, 1, true, null]);
$c5 = Q::join($r5['code'], 'Nouveau', null, $u1);
$eq('lien envoyé : joueur en attente', [Q::playerState(Q::get($r5['code']), $c5['pid'])['pending'], Q::playerState(Q::get($r5['code']), $c5['pid'])['member']], [true, false]);
$eq('rattachement refusé pour un autre compte', Q::claim($r5['code'], $c5['pid'], $u2, 'Mamie Bonal'), false);
$eq('lien ouvert : la partie compte pour ce compte', [Q::claim($r5['code'], $c5['pid'], $u1, 'Le bKop/b'), Q::get($r5['code'])['players'][$c5['pid']]['name'], Q::playerState(Q::get($r5['code']), $c5['pid'])['member']], [true, 'Le bKop/b', true]);

// Back-office, suppression du compte.
CH::ban($u2, true);
$eq('joueur exclu : hors classement', array_column(CH::ranking(), 'pseudo'), ['Le bKop/b', 'Zizou']);
CH::ban($u2, false);
CH::resetPseudo($u3);
$eq('pseudo déplacé remplacé', (bool) preg_match('/^Joueur \d{1,4}$/', CH::player($u3)['pseudo']), true);
Carnet::delete($u1);
$eq('compte supprimé : effacé du championnat', [CH::has($u1), in_array($u1, array_column(CH::ranking(), 'id'), true)], [false, false]);
// Compte jamais confirmé (adresse inventée ?) : points gardés, absent des classements jusqu'au lien.
$u4 = $acc('joueur4@example.org');
CH::setPseudo($u4, 'Fantôme');
[, $g6, $p6] = $play([['Fantôme', $u4, 9000], ['Zizou', $u3, 100], ['Invité', null, 50]]);
$eq('compte non confirmé : partie comptée, hors classement public', [$g6['counted'], in_array('Fantôme', array_column(CH::ranking(), 'pseudo'), true), in_array('Fantôme', array_column(CH::ranking(null, null, true), 'pseudo'), true)], [true, false, true]);
$ps6 = Q::playerState($g6, $p6[0]);
$eq('téléphone : « ouvrez le lien de l’e-mail »', [$ps6['confirm'] ?? false, $ps6['champ'] ?? null], [true, null]);
Carnet::confirm($u4);
$eq('lien ouvert plus tard : points retrouvés au classement', CH::rankOf($u4)['pts'] ?? null, 11);
// Joueur inscrit avant la règle (pas de champ ok) : son carnet fait foi.
$cj = json_decode((string) file_get_contents(CH::$file), true);
unset($cj['players'][$u4]['ok'], $cj['players'][$u3]['ok']);
file_put_contents(CH::$file, json_encode($cj));
$eq('ancien joueur au carnet confirmé : toujours classé', [CH::confirmed($u4), in_array($u4, array_column(CH::ranking(), 'id'), true)], [true, true]);
CH::resetPseudo($u4);
CH::resetPseudo($u3);
$eq('pseudo déplacé : jamais le même que celui d’un autre joueur', CH::player($u4)['pseudo'] !== CH::player($u3)['pseudo'], true);

$old = Carnet::create('ancien@example.org')['carnet']['id'];
$cf = Carnet::$dir . "/$old.json";
$cd = json_decode((string) file_get_contents($cf), true);
$cd['created'] = date('c', time() - 200 * 86400);
file_put_contents($cf, json_encode($cd));
\App\Core\JsonStore::forget($cf);
CH::setPseudo($old, 'Ancien');
$eq('ménage des carnets : un joueur actif du championnat est gardé', [Carnet::purge(), (bool) Carnet::get($old)], [0, true]);

array_map('unlink', glob("$tmp/*") ?: []);
array_map('unlink', glob("$tmp/carnets/*") ?: []);
@rmdir("$tmp/carnets");
@rmdir($tmp);
echo $fail ? "\n$fail échec(s)\n" : "\nTout est bon.\n";
exit($fail ? 1 : 0);
