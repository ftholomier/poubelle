<?php
/**
 * Liens des filtres des pages publiques : un filtre cliqué remplace la valeur en cours et garde
 * les autres (livre des records : décennie, compétition, onglet ; recherche : type).
 * Usage : php tests/filtres.php (code de sortie 1 en cas d'échec). N'écrit rien.
 */
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Core\Request;
use App\Front\Explore;
use App\Front\Pages;

$fail = 0;
$eq = function (string $label, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    if (!$ok) {
        $fail++;
    }
    echo ($ok ? 'OK   ' : 'FAIL ') . $label . ($ok ? '' : ' => ' . json_encode($got, JSON_UNESCAPED_UNICODE) . ' attendu ' . json_encode($exp, JSON_UNESCAPED_UNICODE)) . "\n";
};
$req = fn (string $path, array $q) => new Request('GET', $path, $q, [], [], ['HTTP_HOST' => 'musee.fcsochauxretro.com', 'REQUEST_URI' => $path, 'REMOTE_ADDR' => '127.0.0.1'], '');
$links = function (string $html, string $class): array {
    preg_match_all('/<a class="' . $class . '[^"]*" href="([^"]*)"[^>]*>([^<]*)/', $html, $m, PREG_SET_ORDER);
    $out = [];
    foreach ($m as [, $href, $label]) {
        $out[html_entity_decode(trim($label), ENT_QUOTES)] ??= html_entity_decode($href, ENT_QUOTES);
    }
    return $out;
};

$h = Explore::records($req('/records/', []))->body;
$l = $links($h, 'rchip');
$eq('records : la décennie cliquée est dans le lien', $l["'90"], '/records/?decennie=1990');

$h = Explore::records($req('/records/', ['decennie' => '1990']))->body;
$l = $links($h, 'rchip');
$eq('records : une autre décennie remplace la décennie en cours', $l['2000'], '/records/?decennie=2000');
$eq('records : la compétition s’ajoute à la décennie', $l['Coupe de France'], '/records/?decennie=1990&comp=coupe-de-france');

$h = Explore::records($req('/records/', ['decennie' => '1990', 'comp' => 'amical', 'cat' => 'matchs']))->body;
$l = $links($h, 'rchip');
$eq('records : « Toutes » (décennie) retire la décennie et garde le reste', $l['Toutes'], '/records/?cat=matchs&comp=amical');
$eq('records : une autre compétition remplace la compétition en cours', $l['Championnat'], '/records/?cat=matchs&decennie=1990&comp=championnat');
preg_match_all('/<nav class="rhead__tabs">(.*?)<\/nav>/s', $h, $tabs);
$eq('records : l’onglet « Buteurs » garde les filtres', str_contains($tabs[1][0] ?? '', 'href="/records/?decennie=1990&amp;comp=amical"'), true);

$h = Pages::search($req('/recherche/', ['q' => 'paille', 'type' => 'match']))->body;
$l = $links($h, 'mchip');
$eq('recherche : « Tout » retire le type', array_values(array_filter($l, fn ($u) => !str_contains($u, 'type=')))[0] ?? null, '/recherche/?q=paille');
$eq('recherche : un autre type remplace le type en cours', in_array('/recherche/?q=paille&type=personne', $l, true), true);

echo $fail ? "\n$fail échec(s).\n" : "\nTout est bon.\n";
exit($fail ? 1 : 0);
