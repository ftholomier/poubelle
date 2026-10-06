<?php
/**
 * Défi du jour (App\Services\DailyQuiz) : mêmes questions pour tous (et en anglais), bonne
 * réponse jamais donnée avant la réponse, temps mesuré par le serveur, une tentative par jour et
 * par compte, invités hors classement, classements du jour, du mois et de la saison, séries de
 * jours d'affilée, suppression d'un compte, ménage.
 * Usage : php tests/defi.php (code de sortie 1 en cas d'échec). N'écrit que dans un dossier temporaire.
 */
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Core\JsonStore;
use App\Services\Carnet;
use App\Services\DailyQuiz as D;
use App\Services\QuizChampionship as CH;

$tmp = sys_get_temp_dir() . '/defi-test-' . bin2hex(random_bytes(4));
D::$dir = "$tmp/defi";
CH::$file = "$tmp/championnat.json";
Carnet::$dir = "$tmp/carnets";
$fail = 0;
$eq = function (string $label, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    if (!$ok) {
        $fail++;
    }
    echo ($ok ? 'OK   ' : 'FAIL ') . $label . ($ok ? '' : ' => ' . json_encode($got, JSON_UNESCAPED_UNICODE) . ' attendu ' . json_encode($exp, JSON_UNESCAPED_UNICODE)) . "\n";
};
// Recule l'horloge de la question en cours d'un joueur.
$shift = function (string $date, string $key, int $ms) {
    $f = D::$dir . "/$date/$key.json";
    $p = json_decode((string) file_get_contents($f), true);
    $p['t0'] -= $ms;
    file_put_contents($f, json_encode($p));
    JsonStore::forget($f);
};
$day = '2026-10-06';

// Questions du jour.
$fr = D::questions($day, 'fr');
$en = D::questions($day, 'en');
$eq('10 questions, bien formées', [count($fr), count(array_filter($fr, fn ($q) => isset($q['a'][$q['c']])))], [10, 10]);
$eq('même tirage en français et en anglais', [array_column($fr, 'kind'), array_column($fr, 'c')], [array_column($en, 'kind'), array_column($en, 'c')]);
$eq('questions gardées pour la journée', D::questions($day, 'fr'), $fr);
$eq('autre jour, autre tirage', D::questions('2026-10-07', 'fr') !== $fr, true);

// Comptes.
$acc = function (string $mail, string $pseudo) {
    $id = Carnet::create($mail)['carnet']['id'];
    CH::setPseudo($id, $pseudo);
    Carnet::confirm($id); // lien de l'e-mail ouvert : le compte entre aux classements
    return $id;
};
[$a, $b, $c] = [$acc('defi1@example.org', 'Lionceau25'), $acc('defi2@example.org', 'Mamie Bonal'), $acc('defi3@example.org', 'Zizou')];
$eq('clé : compte, invité, rien', [D::key($a), strlen((string) D::key(null, str_repeat('ab', 16))), D::key(null, 'court'), D::key('../x')], [$a, 21, null, null]);

// Une partie complète pour un compte.
$s = D::start($day, $a, 'fr');
$eq('début : question 1, sans la bonne réponse', [$s['phase'], $s['i'], isset($s['c']), isset($s['q']['a'][3]), $s['left'] > 19000], ['question', 0, false, true, true]);
$eq('pas de question suivante tant que la question court', D::next($day, $a, $a)['i'], 0);
$s = D::answer($day, $a, $fr[0]['c']);
$eq('bonne réponse rapide : près de 1 000 points, réponse et anecdote', [$s['phase'], $s['pts'] >= 950, $s['c'], $s['fact'] !== ''], ['verdict', true, $fr[0]['c'], true]);
$eq('une seule réponse par question', [D::answer($day, $a, ($fr[0]['c'] + 1) % 4)['choice'], D::state($day, $a)['score'] === $s['pts']], [$fr[0]['c'], true]);
D::next($day, $a, $a);
$s = D::answer($day, $a, ($fr[1]['c'] + 1) % count($fr[1]['a']));
$eq('mauvaise réponse : 0 point', [$s['pts'], $s['choice'] !== $s['c']], [0, true]);
D::next($day, $a, $a);
$shift($day, $a, 25000);
$s = D::answer($day, $a, $fr[2]['c']);
$eq('bonne réponse trop tard : 0 point', $s['pts'], 0);
D::next($day, $a, $a);
$shift($day, $a, 25000);
$s = D::next($day, $a, $a);
$eq('temps écoulé sans réponse : question suivante quand même', [$s['i'], D::play($day, $a)['answers'][3][0]], [4, -1]);
for ($i = 4; $i < 10; $i++) {
    $shift($day, $a, 10000); // à mi-temps : 750 points
    D::answer($day, $a, $fr[$i]['c']);
    $s = D::next($day, $a, $a);
}
$eq('fin : 7 bonnes réponses sur 10', [$s['phase'], $s['good'], $s['grid']], ['end', 7, [1, 0, 0, 0, 1, 1, 1, 1, 1, 1]]);
$r = D::result($day, $a);
$eq('résultat du jour enregistré', [$r['good'], $r['pts'] === $s['score'], $r['grid']], [7, true, '1000111111']);
$eq('une seule tentative par jour : on retrouve son résultat', D::start($day, $a, 'fr')['phase'], 'end');

// Deux autres comptes et un invité.
$playAll = function (string $date, string $key, ?string $cid, array $good, int $wait) use ($shift) {
    $qs = D::questions($date, 'fr');
    D::start($date, $key, 'fr');
    foreach ($qs as $i => $q) {
        $shift($date, $key, $wait);
        D::answer($date, $key, in_array($i, $good, true) ? $q['c'] : ($q['c'] + 1) % count($q['a']));
        D::next($date, $key, $cid);
    }
    return D::state($date, $key);
};
$sb = $playAll($day, $b, $b, range(0, 9), 2000);
$sc = $playAll($day, $c, $c, range(0, 6), 1000);
$guest = D::key(null, str_repeat('cd', 16));
$sg = $playAll($day, $guest, null, range(0, 9), 0);
$eq('invité : partie jouée, hors classement', [$sg['phase'], $sg['good'], D::players($day)], ['end', 10, 3]);
$rk = D::ranking('jour', $day);
$eq('classement du jour : points, puis temps', array_map(fn ($r) => [$r['pseudo'], $r['rank']], $rk), [['Mamie Bonal', 1], ['Zizou', 2], ['Lionceau25', 3]]);
$eq('rang d’un compte', D::rankOf('jour', $day, $c)['rank'], 2);

// Mois, saison, séries.
$playAll('2026-10-05', $b, $b, [0, 1], 5000);
$eq('classement du mois : points cumulés, jours joués', array_map(fn ($r) => [$r['pseudo'], $r['days']], D::ranking('mois', '2026-10')), [['Mamie Bonal', 2], ['Zizou', 1], ['Lionceau25', 1]]);
$eq('classement de la saison', array_column(D::ranking('saison', '2026-2027'), 'pseudo'), ['Mamie Bonal', 'Zizou', 'Lionceau25']);
$eq('série cassée après un jour sans jouer', D::streak($a, '2026-10-09')['cur'], 0);
$playAll('2026-10-07', $a, $a, [0], 1000);
$eq('série : deux jours d’affilée', D::streak($a, '2026-10-07'), ['cur' => 2, 'best' => 2]);
CH::ban($c, true);
$eq('joueur retiré du championnat : retiré aussi du défi', array_column(D::ranking('jour', $day), 'pseudo'), ['Mamie Bonal', 'Lionceau25']);
CH::ban($c, false);

// Compte jamais confirmé (adresse inventée ?) : résultat gardé, hors classement jusqu'au lien.
$x = Carnet::create('defi4@example.org')['carnet']['id'];
CH::setPseudo($x, 'Fantôme');
$playAll($day, $x, $x, range(0, 9), 0);
$eq('compte non confirmé : résultat gardé, absent du classement', [(bool) D::result($day, $x), D::rankOf('jour', $day, $x)], [true, null]);
Carnet::confirm($x);
$eq('lien ouvert : il apparaît au classement', D::rankOf('jour', $day, $x)['rank'] ?? null, 1);

// Invité qui crée son compte sur le même appareil dans la journée : il reprend sa partie, hors classement.
$y = Carnet::create('defi5@example.org')['carnet']['id'];
CH::setPseudo($y, 'Malin');
Carnet::confirm($y);
$gt = D::key(null, str_repeat('ef', 16));
$playAll($day, $gt, null, range(0, 9), 0);
D::adopt($day, $gt, $y);
$sy = D::start($day, $y, 'fr');
$eq('invité devenu membre : même partie, terminée, hors classement', [$sy['phase'], $sy['unranked'], D::result($day, $y), D::rankOf('jour', $day, $y)], ['end', true, null, null]);
$z = Carnet::create('defi6@example.org')['carnet']['id'];
CH::setPseudo($z, 'Curieux');
Carnet::confirm($z);
$gz = D::key(null, str_repeat('0a', 16));
D::start($day, $gz, 'fr');
D::adopt($day, $gz, $z);
D::answer($day, $z, $fr[0]['c']);
$shift($day, $z, 25000);
for ($i = 0; $i < 10; $i++) {
    $shift($day, $z, 25000);
    D::next($day, $z, $z);
}
$eq('partie d’invité en cours reprise par le compte : finie hors classement', [D::state($day, $z)['phase'], D::result($day, $z)], ['end', null]);
D::adopt($day, $gt, $a);
$eq('compte qui a déjà joué : rien ne change', D::result($day, $a)['grid'], '1000111111');

// Partie commencée avant minuit : on peut la finir le lendemain.
$late = '2026-10-10';
$w = D::key(null, str_repeat('9b', 16));
D::start($late, $w, 'fr');
$eq('partie de la veille pas finie : reconnue', [D::unfinished($late, $w), D::unfinished($late, $a)], [true, false]);

// Suppression du compte, ménage.
Carnet::delete($b);
$eq('compte supprimé : effacé du défi', [D::result($day, $b), in_array($b, array_column(D::ranking('mois', '2026-10'), 'id'), true)], [null, false]);
$playAll('2026-09-01', $a, $a, [0], 1000);
$eq('ménage : parties en cours de plus de 2 jours effacées, résultats gardés', [D::purge() > 0, is_dir(D::$dir . '/2026-09-01'), (bool) D::result('2026-09-01', $a)], [true, false, true]);

exec('rm -rf ' . escapeshellarg($tmp));
echo $fail ? "\n$fail échec(s)\n" : "\nTout est bon.\n";
exit($fail ? 1 : 0);
