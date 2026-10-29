<?php
/**
 * Flipper (App\Services\Flipper) : jetons de partie signés, scores vraisemblables, un score par
 * jeton, meilleur score de la semaine et de toujours par compte, classement des seuls comptes
 * confirmés et non retirés, effacement avec le carnet, lignes publiques sans numéro de compte,
 * faits du musée, ménage, réglages du club.
 * Usage : php tests/flipper.php (code de sortie 1 en cas d'échec). N'écrit que dans un dossier temporaire.
 */
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Services\Carnet;
use App\Services\Flipper as F;
use App\Services\QuizChampionship as CH;

$tmp = sys_get_temp_dir() . '/flipper-test-' . bin2hex(random_bytes(4));
F::$dir = "$tmp/flipper";
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
$now = time();

// Jetons
$tok = F::token($now - 120);
$eq('jeton valable', F::check($tok, $now)['t'] ?? null, $now - 120);
$eq('jeton falsifié refusé', F::check(substr($tok, 0, -1) . (substr($tok, -1) === 'a' ? 'b' : 'a'), $now), null);
$eq('jeton de plus de 4 h refusé', F::check(F::token($now - 5 * 3600), $now), null);
$eq('jeton du futur refusé', F::check(F::token($now + 60), $now), null);
$eq('jeton mal formé refusé', F::check('abc', $now), null);

// Scores vraisemblables
$eq('score correct', F::plausible(1234560, $now - 300, $now), null);
$eq('score pas multiple de 10', F::plausible(1234561, $now - 300, $now) !== null, true);
$eq('partie de moins de 10 s', F::plausible(1000, $now - 5, $now) !== null, true);
$eq('score trop haut pour la durée', F::plausible(F::MARGIN + 61 * F::MAX_RATE + 10, $now - 60, $now) !== null, true);
$eq('score négatif', F::plausible(-10, $now - 300, $now) !== null, true);

// Comptes
$acc = function (string $mail, string $pseudo, bool $confirm = true) {
    $id = Carnet::create($mail)['carnet']['id'];
    CH::setPseudo($id, $pseudo);
    if ($confirm) {
        Carnet::confirm($id);
    }
    return $id;
};
[$a, $b, $c] = [$acc('fl1@example.org', 'Lionceau25'), $acc('fl2@example.org', 'Bonal4ever'), $acc('fl3@example.org', 'Fantome', false)];
$week = F::week($now);

$t1 = F::token($now - 400);
$eq('score enregistré', F::record($a, 'Lionceau25', 2500000, $t1, $now), null);
$eq('même jeton refusé ensuite', F::record($a, 'Lionceau25', 2500000, $t1, $now) !== null, true);
$eq('score plus bas : meilleur gardé', [F::record($a, 'Lionceau25', 1000, F::token($now - 400), $now), F::ranking($week)[0]['score']], [null, 2500000]);
F::record($b, 'Bonal4ever', 3100000, F::token($now - 600), $now);
F::record($c, 'Fantome', 9000000, F::token($now - 900), $now);
$eq('classement de la semaine (comptes confirmés)', array_map(fn ($r) => [$r['rank'], $r['pseudo'], $r['score']], F::ranking($week)), [[1, 'Bonal4ever', 3100000], [2, 'Lionceau25', 2500000]]);
$eq('rang d’un compte', [F::rankOf($week, $a), F::rankOf($week, $c)], [2, null]);
$eq('de tous les temps', array_column(F::ranking('all'), 'pseudo'), ['Bonal4ever', 'Lionceau25']);
$pub = F::publicRows(F::ranking($week), $a);
$eq('lignes publiques : sans numéro de compte, « moi » repéré', [array_keys($pub[0]), $pub[1]['me'], $pub[0]['me']], [['rank', 'pseudo', 'score', 'me'], true, false]);
$eq('score refusé non enregistré', [F::record($a, 'Lionceau25', 99999999990, F::token($now - 30), $now) !== null, F::ranking($week)[1]['score']], [true, 2500000]);
Carnet::confirm($c);
$eq('compte confirmé ensuite : il apparaît', F::ranking($week)[0]['pseudo'], 'Fantome');
CH::ban($c, true);
$eq('joueur retiré du championnat : absent du tableau', array_column(F::ranking($week), 'pseudo'), ['Bonal4ever', 'Lionceau25']);
CH::ban($c, false);
Carnet::delete($b);
$eq('carnet supprimé : effacé des fichiers du flipper', [str_contains((string) file_get_contents(F::$dir . "/semaine-$week.json"), $b), str_contains((string) file_get_contents(F::$dir . '/records.json'), $b), array_column(F::ranking('all'), 'pseudo')], [false, false, ['Fantome', 'Lionceau25']]);

// Ménage : semaines de plus de 6 mois.
$old = F::week($now - 200 * 86400);
@mkdir(F::$dir, 0775, true);
file_put_contents(F::$dir . "/semaine-$old.json", '{"scores":{}}');
$eq('ménage des vieilles semaines', [F::purge(), is_file(F::$dir . "/semaine-$old.json"), is_file(F::$dir . "/semaine-$week.json")], [1, false, true]);

// Faits du musée et réglages du club.
$facts = F::facts();
$eq('faits : la naissance du club en premier', str_contains($facts[0] ?? '', '1928'), true);
$eq('faits : courts (écran à points)', count(array_filter($facts, fn ($f) => mb_strlen($f) > 150)), 0);
$cfg = F::config();
$eq('réglages du club', [$cfg['title'], $cfg['bumpers'], $cfg['cap'], $cfg['roar']], ['Le Flipper du Lion', 'Les Lions', 'LION', 'Le Lion rugit !']);

exec('rm -rf ' . escapeshellarg($tmp));
echo $fail ? "$fail échec(s)\n" : "Tout est bon\n";
exit($fail ? 1 : 0);
