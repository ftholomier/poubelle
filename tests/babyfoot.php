<?php
/**
 * Babyfoot (App\Services\Babyfoot) : jetons de match signés (niveau, durée), résultats possibles,
 * un résultat par jeton, victoires de la semaine et de toujours par niveau et par compte,
 * classement des seuls comptes confirmés et non retirés, effacement avec le carnet, lignes
 * publiques sans numéro de compte, ménage ; adversaires (couleurs sûres ou tenue neutre, jamais
 * deux maillots qui se confondent), grands matchs à rejouer (vrai résultat, noms des titulaires).
 * Usage : php tests/babyfoot.php (code de sortie 1 en cas d'échec). N'écrit que dans un dossier temporaire.
 */
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Services\Babyfoot as B;
use App\Services\Carnet;
use App\Services\QuizChampionship as CH;

$tmp = sys_get_temp_dir() . '/babyfoot-test-' . bin2hex(random_bytes(4));
B::$dir = "$tmp/babyfoot";
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
$tok = B::token(2, 'buts', $now - 120);
$g = B::check($tok, $now);
$eq('jeton valable (heure, niveau, durée)', [$g['t'] ?? null, $g['level'] ?? null, $g['format'] ?? null], [$now - 120, 2, 'buts']);
$eq('jeton « 2 × 3 min »', B::check(B::token(0, 'temps', $now), $now)['format'] ?? null, 'temps');
$eq('quatre niveaux (Débutant à Ligue 1) : le niveau 3 passe, au-delà ramené à 3', [count(B::LEVELS), B::LEVELS[0], B::check(B::token(3, 'buts', $now), $now)['level'] ?? null, B::check(B::token(9, 'buts', $now), $now)['level'] ?? null], [4, 'Débutant', 3, 3]);
$eq('jeton falsifié refusé', B::check(substr($tok, 0, -1) . (substr($tok, -1) === 'a' ? 'b' : 'a'), $now), null);
$eq('niveau changé dans le jeton : refusé', B::check(preg_replace('/\.2b\./', '.0b.', $tok), $now), null);
$eq('jeton de plus de 2 h refusé', B::check(B::token(1, 'temps', $now - 3 * 3600), $now), null);
$eq('jeton du futur refusé', B::check(B::token(1, 'temps', $now + 60), $now), null);
$eq('jeton mal formé refusé', B::check('abc', $now), null);

// Résultats possibles
$gb = ['t' => $now - 120, 'level' => 1, 'format' => 'buts', 'n' => 'x'];
$gt = ['t' => $now - 400, 'level' => 1, 'format' => 'temps', 'n' => 'x'];
$eq('premier à 5 : 5-3 accepté', B::plausible(5, 3, $gb, $now), null);
$eq('premier à 5 : 4-3 refusé (personne à 5)', B::plausible(4, 3, $gb, $now) !== null, true);
$eq('premier à 5 : 6-2 refusé', B::plausible(6, 2, $gb, $now) !== null, true);
$eq('premier à 5 en 10 s : trop court', B::plausible(5, 0, ['t' => $now - 10] + $gb, $now) !== null, true);
$eq('2 × 3 min : 7-4 accepté', B::plausible(7, 4, $gt, $now), null);
$eq('2 × 3 min en 2 minutes : trop court', B::plausible(3, 1, ['t' => $now - 120] + $gt, $now) !== null, true);
$eq('score négatif refusé', B::plausible(-1, 2, $gt, $now) !== null, true);

// Comptes
$acc = function (string $mail, string $pseudo, bool $confirm = true) {
    $id = Carnet::create($mail)['carnet']['id'];
    CH::setPseudo($id, $pseudo);
    if ($confirm) {
        Carnet::confirm($id);
    }
    return $id;
};
[$a, $b, $c] = [$acc('bf1@example.org', 'Lionceau25'), $acc('bf2@example.org', 'Bonal4ever'), $acc('bf3@example.org', 'Fantome', false)];
$week = B::week($now);

$t1 = B::token(2, 'buts', $now - 400);
$eq('victoire enregistrée', B::record($a, 'Lionceau25', 5, 3, $t1, $now), null);
$eq('même jeton refusé ensuite', B::record($a, 'Lionceau25', 5, 3, $t1, $now) !== null, true);
$eq('défaite : un match de plus, pas de victoire', [B::record($a, 'Lionceau25', 2, 5, B::token(2, 'buts', $now - 400), $now), B::ranking(2, $week)[0]['wins'], B::ranking(2, $week)[0]['games']], [null, 1, 2]);
B::record($b, 'Bonal4ever', 5, 1, B::token(2, 'buts', $now - 400), $now);
B::record($b, 'Bonal4ever', 5, 4, B::token(2, 'buts', $now - 400), $now);
B::record($c, 'Fantome', 5, 0, B::token(2, 'buts', $now - 400), $now);
B::record($a, 'Lionceau25', 5, 2, B::token(0, 'buts', $now - 400), $now);
$eq('classement Ligue 1 de la semaine (comptes confirmés)', array_map(fn ($r) => [$r['rank'], $r['pseudo'], $r['wins']], B::ranking(2, $week)), [[1, 'Bonal4ever', 2], [2, 'Lionceau25', 1]]);
$eq('chaque niveau a son classement', array_map(fn ($r) => [$r['pseudo'], $r['wins']], B::ranking(0, $week)), [['Lionceau25', 1]]);
$eq('rang d’un compte', [B::rankOf(2, $week, $a), B::rankOf(2, $week, $c)], [2, null]);
$eq('de tous les temps', array_column(B::ranking(2, 'all'), 'pseudo'), ['Bonal4ever', 'Lionceau25']);
$pub = B::publicRows(B::ranking(2, $week), $a);
$eq('lignes publiques : sans numéro de compte, « moi » repéré', [array_keys($pub[0]), $pub[1]['me'], $pub[0]['me']], [['rank', 'pseudo', 'wins', 'me'], true, false]);
$eq('résultat refusé non enregistré', [B::record($a, 'Lionceau25', 9, 0, B::token(2, 'buts', $now - 400), $now) !== null, B::ranking(2, $week)[1]['games']], [true, 2]);
$eq('joueurs pour la modération (tous niveaux)', array_map(fn ($r) => [$r['pseudo'], $r['all']], array_values(B::players())), [['Lionceau25', 2], ['Bonal4ever', 2]]);
Carnet::confirm($c);
$eq('compte confirmé ensuite : il apparaît', array_column(B::ranking(2, $week), 'pseudo'), ['Bonal4ever', 'Fantome', 'Lionceau25']);
CH::ban($c, true);
$eq('joueur retiré du championnat : absent du tableau', array_column(B::ranking(2, $week), 'pseudo'), ['Bonal4ever', 'Lionceau25']);
CH::ban($c, false);
Carnet::delete($b);
$eq('carnet supprimé : effacé des fichiers du babyfoot', [str_contains((string) file_get_contents(B::$dir . "/semaine-$week.json"), $b), str_contains((string) file_get_contents(B::$dir . '/records.json'), $b), array_column(B::ranking(2, 'all'), 'pseudo')], [false, false, ['Fantome', 'Lionceau25']]);

// Ménage : semaines de plus de 6 mois.
$old = B::week($now - 200 * 86400);
@mkdir(B::$dir, 0775, true);
file_put_contents(B::$dir . "/semaine-$old.json", '{"levels":{}}');
$eq('ménage des vieilles semaines', [B::purge(), is_file(B::$dir . "/semaine-$old.json"), is_file(B::$dir . "/semaine-$week.json")], [1, false, true]);

// Adversaires : couleurs connues, sinon tenue neutre ; jamais le même maillot que le nôtre.
$cfg = B::config();
$eq('réglages du club', [$cfg['title'], $cfg['short'], $cfg['kit']['shirt']], ['Babyfoot des Lionceaux', 'FCSM', '#F6C400']);
$metz = B::opponent('metz', 'Metz');
$eq('Metz : grenat, sigle FCM', [$metz['kit']['shirt'], $metz['ini'], $metz['short'], $metz['known']], ['#7B1E2E', 'FCM', 'METZ', true]);
$x = B::opponent('club-inconnu', 'Saint-Inconnu');
$eq('club inconnu : tenue neutre, sigle tiré du nom', [$x['kit']['shirt'], $x['kit']['shorts'], $x['ini'], $x['known']], [B::NEUTRAL[0], B::NEUTRAL[1], 'SAI', false]);
$eq('Nantes (jaune) contre Sochaux (jaune) : maillot vert', B::opponent('nantes', 'Nantes')['kit']['shirt'], '#0A6B3A');
$eq('nom trop long pour le tableau de score : sigle', B::opponent('saint-etienne', 'Saint-Etienne')['short'], 'ASSE');
$opps = B::opponents();
$eq('adversaires : 16 clubs, aucun maillot jaune', [count($opps) > 0, count(array_filter($opps, fn ($o) => strtoupper($o['kit']['shirt']) === '#F6C400'))], [true, 0]);

// Grands matchs à rejouer : faits tirés des fiches.
$eq('libellé d’un tour', [B::roundLabel('Finale', 'Coupe de France'), B::roundLabel('1/2 retour', 'Coupe de France'), B::roundLabel('1/8e', 'Coupe de France'), B::roundLabel('7e tour', 'Coupe de France'), B::roundLabel('J12', 'Championnat')],
    ['Finale', 'Demi-finale', '8e de finale', '7e tour', 'Championnat']);
$eq('vrai résultat : finale perdue aux tirs au but', B::realText(['date' => '1988-06-11', 'us' => 1, 'them' => 1, 'result' => 'D'], [4, 5], true, true), 'En vrai, le 11 juin 1988 : 1-1 après prolongation, puis la Coupe perdue aux tirs au but (4-5).');
$eq('vrai résultat : victoire', B::realText(['date' => '2018-01-07', 'us' => 6, 'them' => 0, 'result' => 'V'], null, false, false), 'En vrai, le 7 janvier 2018 : victoire 6-0.');
$eq('vrai résultat : qualification aux tirs au but', B::realText(['date' => '2024-12-22', 'us' => 0, 'them' => 0, 'result' => 'V'], [5, 3], false, false), 'En vrai, le 22 décembre 2024 : 0-0, puis la qualification aux tirs au but (5-3).');
$rows = fn (array $pos) => ['match' => ['lineup' => ['rows' => array_map(fn ($p, $i) => ['position' => $p, 'name' => 'NOM' . $i . ' Prénom', 'number' => $i === 9 ? '10' : ''], $pos, array_keys($pos))]]];
$names = B::names($rows(['G', 'D', 'D', 'D', 'D', 'M', 'M', 'M', 'M', 'A', 'A', 'E', 'R']));
$eq('noms : gardien, 2 défenseurs, 5 milieux, 3 attaquants (numéro seulement s’il est sur la feuille)', [array_keys($names), count($names[3]), $names[0][0], $names[5][1]], [[0, 1, 3, 5], 5, ['name' => 'NOM0', 'num' => null], ['name' => 'NOM9', 'num' => 10]]);
$eq('feuille incomplète : pas de noms', B::names($rows(['G', 'D', 'D', 'M', 'A'])), null);
$eq('deux gardiens titulaires : pas de noms', B::names($rows(['G', 'G', 'D', 'D', 'D', 'M', 'M', 'M', 'M', 'A', 'A'])), null);
$hist = B::history();
if ($hist) {
    $f88 = array_values(array_filter($hist, fn ($m) => str_starts_with($m['label'], 'Finale 1988')))[0] ?? null;
    $eq('finale 1988 contre Metz au programme', [$f88['opp']['id'] ?? null, $f88['real']['us'] ?? null, $f88['real']['them'] ?? null, $f88['golden'] ?? null], ['metz', 1, 1, true]);
    $eq('finale 1988 : noms des titulaires (Rousset au but)', $f88['names'][0][0]['name'] ?? null, 'ROUSSET');
    $eq('un match par adversaire, du plus ancien au plus récent', [count(array_unique(array_map(fn ($m) => $m['opp']['id'], $hist))) === count($hist), array_column($hist, 'date') === (function ($d) { sort($d); return $d; })(array_column($hist, 'date'))], [true, true]);
}

exec('rm -rf ' . escapeshellarg($tmp));
echo $fail ? "$fail échec(s)\n" : "Tout est bon\n";
exit($fail ? 1 : 0);
