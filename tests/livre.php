<?php
/**
 * Livre des récits (App\Pdf\Livre) : règle de définition des photos, PDF prêt à imprimer
 * (TrimBox/BleedBox, pages par multiple de 4), personnalisation. Ne modifie aucune donnée.
 * Usage : php tests/livre.php
 */
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Pdf\Livre;

$fail = 0;
$eq = function (string $label, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    if (!$ok) {
        $fail++;
    }
    echo ($ok ? 'OK   ' : 'FAIL ') . $label . ($ok ? '' : ' => ' . json_encode($got, JSON_UNESCAPED_UNICODE) . ' attendu ' . json_encode($exp, JSON_UNESCAPED_UNICODE)) . "\n";
};
$mm = 72 / 25.4;

// Pleine page à fonds perdus (216 × 276 mm) : une photo web de 1 920 px ne suffit pas, un scan oui.
$page = [216 * $mm, 276 * $mm];
$eq('photo web 1920 × 1280 refusée en pleine page', Livre::dpi([1920, 1280], ...$page) >= Livre::DPI['page'], false);
$eq('scan 2600 × 3300 accepté en pleine page', Livre::dpi([2600, 3300], ...$page) >= Livre::DPI['page'], true);
$col = [84 * $mm, 56 * $mm];
$eq('photo web 1600 px acceptée en colonne', Livre::dpi([1600, 1067], ...$col) >= Livre::DPI['colonne'], true);
$eq('vignette 480 px refusée même en colonne', Livre::dpi([480, 320], ...$col) >= Livre::DPI['colonne'], false);

// Couverture (photo en haut, 216 × 168 mm) : photo web refusée, original 2 560 px accepté.
$eq('photo web 1920 px refusée en couverture', Livre::dpi([1920, 1280], 216 * $mm, Livre::coverH()) >= Livre::DPI['page'], false);
$eq('original 2560 × 1706 accepté en couverture', Livre::dpi([2560, 1706], 216 * $mm, Livre::coverH()) >= Livre::DPI['page'], true);

$eq('naissance hors base : pas de page, pas d’erreur', str_starts_with((new Livre(['naissance' => '1800-01-01', 'limite' => 1]))->build(), '%PDF'), true);
$eq('photo du lecteur trop petite refusée', Livre::photoFrame(PUBLIC_PATH . '/assets/img/favicon.png'), null);

$book = new Livre(['nom' => 'Test Lecteur', 'dedicace' => 'Pour toi.', 'numero' => '7', 'depuis' => 1998, 'couverture' => 'absente/inconnue.jpg', 'match' => 999999999, 'joueurs' => [999999998], 'maillot_nom' => 'Test', 'maillot_numero' => '9', 'carnet' => 'inconnu', 'relire' => true, 'limite' => 3]);
$pdf = $book->build();
$eq('PDF produit', str_starts_with($pdf, '%PDF'), true);
$pages = preg_match_all('#/Type /Page\b#', $pdf);
$eq('pages par multiple de 4', $pages % 4, 0);
$eq('format fini et fonds perdus déclarés', substr_count($pdf, '/TrimBox') === $pages && substr_count($pdf, '/BleedBox') === $pages, true);
$eq('au plus 3 récits', $book->report()['recits'] <= 3, true);

echo $fail ? "\n$fail échec(s).\n" : "\nTout est bon.\n";
exit($fail ? 1 : 0);
