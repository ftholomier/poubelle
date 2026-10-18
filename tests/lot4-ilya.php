<?php
/**
 * Lot 4 : « Il y a N ans » de l'accueil (Pages::yearsAgo) le même dans les deux langues, textes et
 * liens dans la langue de la page ; lien de secours vers une saison existante (/matchs/AAAA-AAAA/) ;
 * menus sans bilan vide ; liens « L'association » et « L'équipe » propres au club ; minutes des tweets.
 * Usage : php tests/lot4-ilya.php (club par défaut) ou MUSEE_CLUB=paris php tests/lot4-ilya.php
 */
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Front\Pages;
use App\Services\I18n;

$fail = 0;
$eq = function (string $label, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    if (!$ok) {
        $fail++;
    }
    echo ($ok ? 'OK   ' : 'FAIL ') . $label . ($ok ? '' : ' => ' . json_encode($got, JSON_UNESCAPED_UNICODE) . ' attendu ' . json_encode($exp, JSON_UNESCAPED_UNICODE)) . "\n";
};

// Journal du jour mis de côté, remis à la fin.
$log = STORAGE_PATH . '/home/il-y-a.json';
$saved = is_file($log) ? file_get_contents($log) : null;
try {
    $today = date('Y-m-d');
    $j = is_file($log) ? (array) json_decode((string) file_get_contents($log), true) : [];
    unset($j[$today]);
    @mkdir(dirname($log), 0775, true);
    file_put_contents($log, json_encode($j));

    I18n::set('fr');
    $fr = Pages::yearsAgo([5, 10, 20, 30]);
    I18n::set('en');
    $en = Pages::yearsAgo([5, 10, 20, 30]);
    I18n::set('fr');
    $fr2 = Pages::yearsAgo([5, 10, 20, 30]);

    $eq('même choix en français et en anglais', array_map(fn ($x) => [$x['n'], $x['key'] ?? null], $en), array_map(fn ($x) => [$x['n'], $x['key'] ?? null], $fr));
    $eq('liens anglais sous /en/', array_values(array_filter($en, fn ($x) => $x['href'] !== null && !str_starts_with((string) $x['href'], '/en/'))), []);
    $eq('liens français sans /en/', array_values(array_filter($fr, fn ($x) => str_starts_with((string) $x['href'], '/en/'))), []);
    $eq('français relu depuis le journal, pas l’anglais', $fr2, $fr);
    $seasons = \App\Data\Derived::part('seasons');
    $bad = array_filter([...$fr, ...$en], fn ($x) => preg_match('#/saisons/#', (string) $x['href'])
        || (preg_match('#/matchs/(\d{4}-\d{4})/$#', (string) $x['href'], $m) && !isset($seasons[$m[1]])));
    $eq('aucun lien vers /saisons/AAAA-AAAA/ ni vers une saison absente', array_values($bad), []);
    $entry = (array) json_decode((string) file_get_contents($log), true)[$today];
    $eq('journal : fiches montrées et une entrée par langue', [isset($entry['keys']), array_keys((array) $entry['by'])], [true, ['fr|5,10,20,30', 'en|5,10,20,30']]);
} finally {
    if ($saved === null) {
        @unlink($log);
    } else {
        file_put_contents($log, $saved);
    }
}

// Menus : pas de lien vers un bilan sans match ; association et équipe propres au club.
$hrefs = [];
foreach (\App\Front\Menus::get() as $part) {
    foreach ((array) $part as $col) {
        foreach (array_merge((array) ($col['links'] ?? []), (array) ($col['tools'] ?? []), isset($col['href']) ? [$col] : []) as $x) {
            if (($h = \App\Front\Menus::href((string) ($x['href'] ?? ''))) !== null) {
                $hrefs[] = $h[0];
            }
        }
    }
}
foreach (array_filter($hrefs, fn ($h) => str_contains($h, '/bilans/')) as $h) {
    $eq("bilan du menu existant : $h", \App\Front\Explore::bilanPage(basename($h)) !== null, true);
}
$asso = \App\Core\Club::s('vitrine_url');
$eq('lien « L’association » : celui du club, ou aucun', in_array(\App\Vitrine\Host::base(), $hrefs, true) || $asso === '', true);
$eq('aucun lien vers le site de Sochaux ailleurs', $asso === '' ? array_values(array_filter($hrefs, fn ($h) => str_contains($h, 'fcsochauxretro'))) : [], []);
$eq('page « L’équipe » du club', Pages::teamPath(), \App\Core\Club::isDefault() ? '/supporters/l-equipe-de-sochaux-retro/' : '/supporters/l-equipe-du-musee/');
$eq('réseaux sociaux par défaut : ceux du profil', array_filter([\App\Core\Settings::defaults()['social.x'] ?? '']), array_filter([(string) (\App\Core\Club::get('social', [])['x'] ?? '')]));

// Minutes des tweets : temps additionnel lu en entier.
$tw = fn (string $before) => tweets_by_minute($before . ' <blockquote>https://x.com/a/status/123456789</blockquote>')[0]['min'] ?? null;
$eq('tweets : 90\'+2, 90+3\', 52\', 34e min', [$tw("90'+2 : Nouri fait le break."), $tw("90+3' : arrêt"), $tw("52' : but"), $tw('à la 34e min')], ['90+2', '90+3', '52', '34']);

echo $fail ? "\n$fail échec(s).\n" : "\nTout est bon.\n";
exit($fail ? 1 : 0);
