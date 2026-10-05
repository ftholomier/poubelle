<?php
/**
 * Murs de photos (App\Services\PhotoWall, App\Front\Walls) : crédits écartés (DR, à risque,
 * date ou légende sans auteur), crédit et photographe extraits, photos montrables seulement,
 * tirage et filtres, motifs de la mosaïque, pages et fragment « nouveau tirage ».
 * Usage : php tests/photos.php (code de sortie 1 en cas d'échec). N'écrit que des fichiers de cache.
 */
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Core\Request;
use App\Core\Response;
use App\Core\Settings;
use App\Data\Index;
use App\Data\Media;
use App\Front\Walls;
use App\Kernel;
use App\Services\I18n;
use App\Services\PhotoWall;

$fail = 0;
$eq = function (string $label, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    if (!$ok) {
        $fail++;
    }
    echo ($ok ? 'OK   ' : 'FAIL ') . $label . ($ok ? '' : ' => ' . json_encode($got, JSON_UNESCAPED_UNICODE) . ' attendu ' . json_encode($exp, JSON_UNESCAPED_UNICODE)) . "\n";
};

// 1. Crédits
$eq('« DR », « D.R. », droits réservés, auteur inconnu : sans auteur', array_map([PhotoWall::class, 'isDr'], ['DR', 'D.R.', 'Photo DR', 'Droits réservés', 'Auteur inconnu', 'Lionel Vadam', 'Drancy']), [true, true, true, true, true, false, false]);
$eq('crédits à risque : agences, presse nationale, sites web', array_map([PhotoWall::class, 'risky'], ['AFP', 'Photo L’Equipe', 'Icon Sport / Panoramic', 'www.footnostalgie.fr', 'Site internet : foot nostalgie', 'L’Est Républicain', 'Lionel Vadam']), ['AFP', 'L’Équipe', 'Icon Sport', 'site web', 'Site internet', null, null]);
$eq('« But » écarte le magazine, pas « Butin »', [PhotoWall::risky('But'), PhotoWall::risky('Edouard Butin')], ['But', null]);
$credit = fn (string $raw): string => PhotoWall::credit($raw)['name'];
$eq('crédits nettoyés', array_map($credit, [
    'Crédit photo : Lionel Vadam', 'Melisey (Lionel Vadam)', 'Finale 1988. L’est républicain', 'Sochaux-Metz 1999-2000 -L’est républicain',
    'L’est républicain Lionel Vadam', 'Christian Manicourt pour L’est républicain', 'Est Républicain de Christian Manicourt', 'Joël Le gall / Ouest France',
    'C Erkul pour le Progrès', 'L’est républicainL’est républicain', 'est republicain', 'fcsm', 'Premier contrat pro (FCSM)',
]), [
    'Lionel Vadam', 'Lionel Vadam', 'L’Est Républicain', 'L’Est Républicain',
    'Lionel Vadam · L’Est Républicain', 'Christian Manicourt · L’Est Républicain', 'Christian Manicourt · L’Est Républicain', 'Joël Le gall · Ouest-France',
    'C Erkul · Le Progrès', 'L’Est Républicain', 'L’Est Républicain', 'FC Sochaux-Montbéliard', 'FC Sochaux-Montbéliard',
]);
$eq('photographe du filtre : le photographe, pas son journal', [PhotoWall::credit('L’est républicain Lionel Vadam')['key'], PhotoWall::credit('Lionel Vadam')['key']], ['lionel-vadam', 'lionel-vadam']);
$noAuthor = fn (string $raw): bool => PhotoWall::noAuthor($credit($raw));
$eq('date ou légende à la place du crédit : sans auteur', array_map($noAuthor, ['Saison 1980-1981', '1997/1998', '22 Juillet 2000', 'Années 30', 'Sochaux 1997/1998', 'Auxerre-Sochaux', 'Sochaux-Strasbourg']), [true, true, true, true, true, true, true]);
$eq('vrais crédits gardés', array_map($noAuthor, ['FC Sochaux-Montbéliard', 'Mai Thi', 'Sochaux-Metz 1999-2000 -L’est républicain', 'Melisey (Lionel Vadam)', 'France Bleu Belfort-Montbéliard']), [false, false, false, false, false]);

// 2. Photos montrables
PhotoWall::forget();
$photos = PhotoWall::photos();
$usage = Media::usage();
$bad = [];
foreach ($photos as $p) {
    $m = Media::get($p['r']);
    $why = PhotoWall::reason($p['r'], $m, $usage);
    $fiche = Index::get((int) $p['f']);
    if ($why !== null || trim((string) ($m['credit'] ?? '')) === '' || PhotoWall::isDr((string) $m['credit']) || PhotoWall::risky((string) $m['credit']) !== null
        || min($p['w'], $p['h']) < PhotoWall::MIN_SIDE || !$fiche || !Index::visible($fiche) || $p['c'] === '' || !is_file(Media::ORIGINALS . '/' . $p['r'])) {
        $bad[] = $p['r'] . ' : ' . ($why ?? 'fiche ou crédit');
    }
}
$eq('des milliers de photos, toutes créditées, sûres, d’une fiche publiée, assez grandes', [count($photos) > 2000, array_slice($bad, 0, 3)], [true, []]);
$stats = PhotoWall::stats();
$eq('chaque média compté une fois (montré ou écarté pour une raison connue)', [array_sum($stats), array_diff(array_keys($stats), array_merge(array_keys(PhotoWall::REASONS), ['montrees']))], [count(Media::all()), []]);
$eq('raisons du tableau de bord', [$stats['montrees'], ($stats['dr'] ?? 0) > 0, ($stats['exclu'] ?? 0) > 0, ($stats['sans-auteur'] ?? 0) > 0], [count($photos), true, true, true]);
$one = $photos[0];
$m = Media::get($one['r']);
$eq('raison d’une photo : retirée à la main, crédit vidé, DR, exclu, trop petite', [
    PhotoWall::reason($one['r'], ['nowall' => true] + $m, $usage), PhotoWall::reason($one['r'], ['credit' => ' '] + $m, $usage), PhotoWall::reason($one['r'], ['credit' => 'DR'] + $m, $usage),
    PhotoWall::reason($one['r'], ['credit' => 'AFP'] + $m, $usage), PhotoWall::reason($one['r'], ['credit' => 'Saison 1980-1981'] + $m, $usage), PhotoWall::reason($one['r'], ['width' => 200] + $m, $usage),
    PhotoWall::reasonKey(PhotoWall::reason($one['r'], ['credit' => 'AFP'] + $m, $usage)), PhotoWall::reasonKey(null),
], ['retirée à la main', 'sans crédit', 'crédit DR', 'crédit exclu (AFP)', 'crédit sans auteur', 'trop petite', 'exclu', 'montrees']);
$eq('photo d’aucune fiche publiée : écartée', PhotoWall::reason($one['r'], $m, [$one['r'] => []]), 'dans aucune fiche publiée');
$eq('liste des crédits exclus : celle de départ tant qu’elle n’est pas modifiée', PhotoWall::excluded(), PhotoWall::RISKY);
$hits = PhotoWall::excludedHits();
$eq('ce que retire chaque ligne : autant que de photos au crédit exclu', array_sum($hits) >= ($stats['exclu'] ?? 0), true);

// 3. Tirage, filtres et nombres
$draw = PhotoWall::draw(36);
$per = array_count_values(array_column($draw, 'f'));
$eq('tirage : 36 photos différentes, deux au plus par fiche', [count($draw), count(array_unique(array_column($draw, 'r'))), max($per) <= 2], [36, 36, true]);
$decades = PhotoWall::decades();
$d = (int) array_key_first($decades);
$byDecade = PhotoWall::draw(40, ['decade' => $d]);
$eq("filtre années $d : années de la décennie seulement", count(array_filter($byDecade, fn ($p) => intdiv($p['y'], 10) * 10 !== $d)), 0);
$who = PhotoWall::photographers()[0];
$byWho = PhotoWall::draw(40, ['who' => $who['key']]);
$eq('filtre ' . $who['name'] . ' : ses photos seulement', array_values(array_unique(array_column($byWho, 'k'))), [$who['key']]);
$eq('paysage : plus larges que hautes', count(array_filter(PhotoWall::draw(40, ['landscape' => true]), fn ($p) => $p['w'] < $p['h'] * 0.95)), 0);
$counts = PhotoWall::counts(null, null);
$eq('nombres sans filtre = décennies et photographes proposés', [$counts['decades'], array_values($counts['who'])], [$decades, array_column(PhotoWall::photographers(), 'n')]);
$c2 = PhotoWall::counts($d, null);
$eq("nombres des photographes pour les années $d : ceux de la décennie", array_sum($c2['who']) <= $decades[$d] && array_sum($c2['who']) > 0, true);
$eq('filtres inconnus ignorés', Walls::filters(new Request('GET', '/', ['decennie' => '1234', 'photographe' => '<script>'], [], [], [], '')), ['decade' => null, 'who' => null]);

// 4. Motifs de la mosaïque
$cells = fn (array $g): int => array_sum(array_map(fn ($r) => count(array_filter($r)), $g));
foreach (array_keys(Walls::MOTIFS) as $k) {
    $k = (string) $k;
    $w = Walls::grid($k, true);
    $n = Walls::grid($k, false);
    $eq("motif $k : 24 × 11 cases (12 de large sur téléphone), motif identique aux deux tailles",
        [count($w), count($w[0]), count($n[0]), $cells($w) > 40, $cells($n) > 20, count(array_unique(array_map('count', $w))), count(array_unique(array_map('count', $n)))],
        [11, 24, 12, true, true, 1, 1]);
}

// 5. Pages
$values = new ReflectionProperty(Settings::class, 'values');
$values->setValue(null, ['waiting.enabled' => false] + Settings::all());
$get = function (string $path, array $query = []): Response {
    I18n::set(str_starts_with($path, '/en/') ? 'en' : 'fr');
    return Kernel::handle(new Request('GET', $path, $query, [], [], ['HTTP_HOST' => 'musee.fcsochauxretro.com', 'REQUEST_URI' => $path, 'REMOTE_ADDR' => '127.0.0.1', 'HTTP_USER_AGENT' => 'test-murs'], ''));
};
foreach (['planche-contact' => 36, 'le-lion-illustre' => 12, 'mur-du-vestiaire' => 15, 'mosaique' => 264] as $slug => $min) {
    $r = $get("/interactif/$slug/");
    $n = substr_count($r->body, 'data-photo=');
    $eq("/interactif/$slug/ : page, $min photos au moins, crédits, filtres", [$r->status, $n >= $min, str_contains($r->body, 'data-credit="'), str_contains($r->body, 'name="decennie"'), str_contains($r->body, 'murs.css')], [200, true, true, true, true]);
}
$frag = $get('/interactif/planche-contact/', ['partiel' => '1', 'decennie' => (string) $d]);
preg_match('#<script type="application/json" data-wall-counts>(.*?)</script>#s', $frag->body, $json);
$eq('nouveau tirage : le mur seul, avec les nombres des filtres, jamais en cache ni indexé', [
    $frag->status, str_contains($frag->body, '<html'), str_contains($frag->body, 'class="pc"'), isset(json_decode($json[1] ?? '', true)['who']), $frag->headers['Cache-Control'] ?? '', $frag->headers['X-Robots-Tag'] ?? '',
], [200, false, true, true, 'no-store', 'noindex']);
$filtered = $get('/interactif/mur-du-vestiaire/', ['photographe' => $who['key']]);
$eq('page filtrée : pas indexée, choix gardé', [str_contains($filtered->body, 'noindex'), str_contains($filtered->body, 'value="' . $who['key'] . '" data-label')], [true, true]);
$eq('page sans filtre : indexable', str_contains($get('/interactif/mosaique/')->body, '"noindex'), false);
$en = $get('/en/interactif/le-lion-illustre/');
$eq('en anglais', [$en->status, str_contains($en->body, 'Next edition'), str_contains($en->body, 'Photographer or source')], [200, true, true]);
$hub = $get('/interactif/');
$eq('les quatre pavés dans Interactif', count(array_filter(array_column(Walls::WALLS, 0), fn ($u) => str_contains($hub->body, 'href="' . $u . '"'))), 4);

PhotoWall::forget();
echo $fail ? "\n$fail échec(s)\n" : "\nTout est bon.\n";
exit($fail ? 1 : 0);
