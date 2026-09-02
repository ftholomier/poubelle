<?php

declare(strict_types=1);

/**
 * Suite de tests, sans dépendance externe.
 * Usage : php tests/run.php  (ajouter l'URL d'un serveur pour tester aussi l'API)
 *         php tests/run.php http://localhost:8000
 */

require dirname(__DIR__) . '/bootstrap.php';

use App\Admin\Auth;
use App\Admin\ContentWriter;
use App\Admin\SectionSchema;
use App\Config;
use App\Content;
use App\Http\Router;
use App\Theme\Color;
use App\Theme\Palette;
use App\View;
use App\Shape\ImageSampler;
use App\Shape\Matrix2D;
use App\Shape\PathParser;
use App\Shape\PresetSampler;
use App\Shape\Rng;
use App\Shape\ScanlineFill;
use App\Shape\ShapeService;
use App\Shape\SvgSampler;

Config::boot();

$passed = 0;
$failed = [];
$group = '';

function suite(string $name): void
{
    global $group;
    $group = $name;
    echo "\n\033[1m{$name}\033[0m\n";
}

function check(string $label, callable $test): void
{
    global $passed, $failed, $group;
    try {
        $result = $test();
        if ($result === true || $result === null) {
            $passed++;
            echo "  \033[32m✓\033[0m {$label}\n";
        } else {
            $failed[] = "{$group} › {$label} : " . var_export($result, true);
            echo "  \033[31m✗\033[0m {$label} — {$result}\n";
        }
    } catch (Throwable $e) {
        $failed[] = "{$group} › {$label} : " . $e->getMessage();
        echo "  \033[31m✗\033[0m {$label} — " . $e->getMessage() . "\n";
    }
}

/**
 * Exécute un test d'écriture sur une copie du contenu.
 *
 * Les méthodes de ContentWriter écrivent dans content/pages/ ; lancer la suite
 * ne doit pas laisser le dépôt modifié. On photographie le dossier, on rend la
 * main au test, puis on remet exactement les fichiers d'origine — y compris en
 * cas d'exception, d'où le finally.
 */
function withSandboxedContent(callable $test): mixed
{
    $dir = APP_CONTENT . '/pages';
    $snapshot = [];
    foreach (glob($dir . '/*.json') ?: [] as $file) {
        $snapshot[basename($file)] = (string) file_get_contents($file);
    }
    // Chaque écriture dépose une sauvegarde : celles du test n'ont rien à
    // faire au milieu de celles du back-office.
    $backupsBefore = glob(APP_ROOT . '/var/backups/*.json') ?: [];

    try {
        return $test();
    } finally {
        foreach (glob($dir . '/*.json') ?: [] as $file) {
            if (!isset($snapshot[basename($file)])) {
                @unlink($file);
            }
        }
        foreach ($snapshot as $name => $contents) {
            file_put_contents($dir . '/' . $name, $contents);
        }
        foreach (array_diff(glob(APP_ROOT . '/var/backups/*.json') ?: [], $backupsBefore) as $file) {
            @unlink($file);
        }
        Content::forget();
    }
}

/** @param list<float> $flat */
function extent(array $flat, int $axis): array
{
    $min = INF;
    $max = -INF;
    for ($i = $axis; $i < count($flat); $i += 3) {
        $min = min($min, $flat[$i]);
        $max = max($max, $flat[$i]);
    }
    return [$min, $max];
}

// ---------------------------------------------------------------- Analyse SVG

suite('Analyse des chemins SVG');

check('Un triangle fermé produit un sous-chemin de quatre sommets', function () {
    $sub = PathParser::parse('M0 0 L10 0 L5 10 Z');
    if (count($sub) !== 1) return 'sous-chemins : ' . count($sub);
    if (count($sub[0]['points']) !== 4) return 'sommets : ' . count($sub[0]['points']);
    return $sub[0]['closed'] === true ? true : 'le chemin devrait être fermé';
});

check('Les commandes relatives se cumulent depuis le point courant', function () {
    $sub = PathParser::parse('m10 10 l10 0 l0 10 z');
    $points = $sub[0]['points'];
    return ($points[1] === [20.0, 10.0] && $points[2] === [20.0, 20.0])
        ? true
        : 'points obtenus : ' . json_encode(array_slice($points, 0, 3));
});

check('H et V ne modifient qu\'une seule coordonnée', function () {
    $points = PathParser::parse('M5 5 H15 V25')[0]['points'];
    return ($points[1] === [15.0, 5.0] && $points[2] === [15.0, 25.0])
        ? true
        : json_encode($points);
});

check('Une cubique est aplatie en plusieurs segments', function () {
    $points = PathParser::parse('M0 0 C0 50 100 50 100 0')[0]['points'];
    if (count($points) < 6) return 'trop peu de segments : ' . count($points);
    $last = end($points);
    return (abs($last[0] - 100) < 0.001 && abs($last[1]) < 0.001) ? true : json_encode($last);
});

check('S reflète le point de contrôle de la cubique précédente', function () {
    $points = PathParser::parse('M0 0 C10 10 20 10 30 0 S50 -10 60 0')[0]['points'];
    $last = end($points);
    return (abs($last[0] - 60) < 0.001 && abs($last[1]) < 0.001) ? true : json_encode($last);
});

check('Un arc elliptique rejoint exactement son point d\'arrivée', function () {
    $points = PathParser::parse('M0 0 A50 50 0 0 1 100 0')[0]['points'];
    $last = end($points);
    if (abs($last[0] - 100) > 0.01 || abs($last[1]) > 0.01) return json_encode($last);
    // En repère SVG l'axe Y descend : avec sweep = 1 l'arc s'élève, donc
    // le demi-cercle de rayon 50 culmine à -50.
    $peak = min(array_column($points, 1));
    if (abs($peak + 50) > 1.0) return "hauteur d'arc : {$peak}";
    // Le drapeau sweep inverse le sens de parcours.
    $mirrored = PathParser::parse('M0 0 A50 50 0 0 0 100 0')[0]['points'];
    $mirrorPeak = max(array_column($mirrored, 1));
    return abs($mirrorPeak - 50) < 1.0 ? true : "arc miroir : {$mirrorPeak}";
});

check('Un « d » vide ou incohérent ne provoque pas d\'erreur', function () {
    return PathParser::parse('') === [] && PathParser::parse('M10') === [] ? true : 'sortie inattendue';
});

check('Les paires supplémentaires après M sont des lignes implicites', function () {
    $points = PathParser::parse('M0 0 10 0 10 10')[0]['points'];
    return count($points) === 3 ? true : 'sommets : ' . count($points);
});

// ------------------------------------------------------------ Transformations

suite('Transformations');

check('translate déplace le point', function () {
    [$x, $y] = Matrix2D::fromAttribute('translate(10, 20)')->apply(1, 2);
    return ($x === 11.0 && $y === 22.0) ? true : "({$x}, {$y})";
});

check('scale puis translate s\'appliquent dans l\'ordre du SVG', function () {
    // Lu de gauche à droite : le point est d'abord translaté, puis mis à l'échelle.
    [$x, $y] = Matrix2D::fromAttribute('scale(2) translate(5, 0)')->apply(0, 0);
    return ($x === 10.0 && $y === 0.0) ? true : "({$x}, {$y})";
});

check('rotate(90) envoie l\'axe X sur l\'axe Y', function () {
    [$x, $y] = Matrix2D::fromAttribute('rotate(90)')->apply(1, 0);
    return (abs($x) < 1e-9 && abs($y - 1) < 1e-9) ? true : "({$x}, {$y})";
});

check('rotate autour d\'un centre laisse ce centre immobile', function () {
    [$x, $y] = Matrix2D::fromAttribute('rotate(37, 8, 3)')->apply(8, 3);
    return (abs($x - 8) < 1e-9 && abs($y - 3) < 1e-9) ? true : "({$x}, {$y})";
});

// --------------------------------------------------------- Balayage par lignes

suite('Remplissage par balayage');

check('Le nombre de points demandé est exactement respecté', function () {
    $square = [[[0.0, 0.0], [10.0, 0.0], [10.0, 10.0], [0.0, 10.0], [0.0, 0.0]]];
    $points = ScanlineFill::sample($square, [0, 0, 10, 10], 500, new Rng(7));
    return count($points) === 500 ? true : count($points);
});

check('Tous les points tombent à l\'intérieur du carré', function () {
    $square = [[[0.0, 0.0], [10.0, 0.0], [10.0, 10.0], [0.0, 10.0], [0.0, 0.0]]];
    foreach (ScanlineFill::sample($square, [0, 0, 10, 10], 2000, new Rng(3)) as [$x, $y]) {
        if ($x < -0.01 || $x > 10.01 || $y < -0.01 || $y > 10.01) return "hors cadre : ({$x}, {$y})";
    }
    return true;
});

check('Un anneau laisse son centre vide (règle non-zéro)', function () {
    // Carré extérieur dans un sens, carré intérieur dans l'autre : le trou se creuse.
    $ring = [
        [[0.0, 0.0], [10.0, 0.0], [10.0, 10.0], [0.0, 10.0], [0.0, 0.0]],
        [[4.0, 4.0], [4.0, 6.0], [6.0, 6.0], [6.0, 4.0], [4.0, 4.0]],
    ];
    foreach (ScanlineFill::sample($ring, [0, 0, 10, 10], 4000, new Rng(11)) as [$x, $y]) {
        if ($x > 4.15 && $x < 5.85 && $y > 4.15 && $y < 5.85) return "point dans le trou : ({$x}, {$y})";
    }
    return true;
});

check('Une même graine redonne le même nuage', function () {
    $square = [[[0.0, 0.0], [10.0, 0.0], [10.0, 10.0], [0.0, 10.0], [0.0, 0.0]]];
    $a = ScanlineFill::sample($square, [0, 0, 10, 10], 300, new Rng(42));
    $b = ScanlineFill::sample($square, [0, 0, 10, 10], 300, new Rng(42));
    return $a === $b ? true : 'les deux tirages diffèrent';
});

// ------------------------------------------------------- Échantillonnage réel

suite('Échantillonnage des formes livrées');

foreach (glob(APP_CONTENT . '/shapes/*.svg') ?: [] as $file) {
    check('« ' . basename($file) .' » se transforme en nuage cohérent', function () use ($file) {
        $started = microtime(true);
        $result = SvgSampler::sample($file, 3000, ['mode' => 'fill']);
        $elapsed = (microtime(true) - $started) * 1000;
        if (count($result['points']) !== 3000) return 'points : ' . count($result['points']);
        if ($elapsed > 1500) return sprintf('trop lent : %.0f ms', $elapsed);
        [, , $vw, $vh] = $result['viewBox'];
        return ($vw > 0 && $vh > 0) ? true : 'viewBox invalide';
    });
}

check('Le mode contour reste sur le tracé', function () {
    $file = APP_CONTENT . '/shapes/cible.svg';
    $result = SvgSampler::sample($file, 2000, ['mode' => 'outline']);
    // Une cible pleine aurait des points au centre ; son contour, non.
    $center = 0;
    foreach ($result['points'] as [$x, $y]) {
        if (abs($x - 100) < 6 && abs($y - 100) < 6) $center++;
    }
    return $center < 60 ? true : "trop de points au centre : {$center}";
});

// ------------------------------------------------------------------ Préréglages

suite('Formes procédurales');

foreach (array_keys(PresetSampler::AVAILABLE) as $preset) {
    check("Le préréglage « {$preset} » produit un volume exploitable", function () use ($preset) {
        $points = PresetSampler::sample($preset, 1200, ['seed' => 5]);
        if (count($points) !== 1200) return 'points : ' . count($points);
        foreach ($points as $p) {
            if (!is_finite($p[0]) || !is_finite($p[1]) || !is_finite($p[2])) return 'coordonnée non finie';
        }
        return true;
    });
}

check('Un préréglage inconnu est signalé explicitement', function () {
    try {
        PresetSampler::sample('licorne', 10);
        return 'aucune exception levée';
    } catch (RuntimeException $e) {
        return str_contains($e->getMessage(), 'licorne') ? true : $e->getMessage();
    }
});

// ------------------------------------------------------------- Service central

suite('Service de formes');

check('Toute forme est ramenée dans le cube [-1, 1]', function () {
    foreach ([
        ['type' => 'svg', 'src' => 'shapes/fusee.svg', 'count' => 2000, 'id' => 't1', 'depth' => 0.1],
        ['type' => 'preset', 'preset' => 'torus', 'count' => 2000, 'id' => 't2', 'depth' => 0.1],
    ] as $shape) {
        $built = ShapeService::build($shape);
        foreach ([0, 1, 2] as $axis) {
            [$min, $max] = extent($built['positions'], $axis);
            if ($min < -1.05 || $max > 1.05) {
                return "{$shape['id']} axe {$axis} : [{$min}, {$max}]";
            }
        }
    }
    return true;
});

check('La forme est centrée sur l\'origine', function () {
    $built = ShapeService::build(['type' => 'preset', 'preset' => 'sphere', 'count' => 4000, 'id' => 'c']);
    foreach ([0, 1, 2] as $axis) {
        [$min, $max] = extent($built['positions'], $axis);
        if (abs($min + $max) > 0.05) return "axe {$axis} décentré : [{$min}, {$max}]";
    }
    return true;
});

check('L\'axe Y est inversé : le haut du dessin est en haut de l\'écran', function () {
    // Un triangle pointe vers le bas dans le repère SVG.
    $svg = APP_CACHE . '/test-triangle.svg';
    file_put_contents($svg, '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><path d="M50 90 L10 10 L90 10 Z"/></svg>');
    copy($svg, APP_CONTENT . '/shapes/.test-triangle.svg');

    $built = ShapeService::build([
        'type' => 'svg', 'src' => 'shapes/.test-triangle.svg',
        'count' => 3000, 'id' => 'tri', 'depth' => 0,
    ]);

    // Après inversion, la pointe doit se retrouver en bas : peu de points dans le bas.
    $low = 0;
    $high = 0;
    for ($i = 1; $i < count($built['positions']); $i += 3) {
        $built['positions'][$i] < -0.5 ? $low++ : ($built['positions'][$i] > 0.5 ? $high++ : null);
    }
    @unlink($svg);
    @unlink(APP_CONTENT . '/shapes/.test-triangle.svg');

    return $high > $low * 2 ? true : "haut={$high} bas={$low} — l'inversion n'a pas eu lieu";
});

check('Une source hors de content/ est refusée', function () {
    foreach (['../bootstrap.php', '/etc/passwd', 'shapes/../../bootstrap.php'] as $evil) {
        try {
            ShapeService::build(['type' => 'svg', 'src' => $evil, 'count' => 100, 'id' => 'x']);
            return "chemin accepté à tort : {$evil}";
        } catch (InvalidArgumentException) {
            // Comportement attendu.
        }
    }
    return true;
});

check('Le cache renvoie un résultat identique et plus rapide', function () {
    $shape = ['type' => 'svg', 'src' => 'shapes/engrenage.svg', 'count' => 9000, 'id' => 'cache', 'seed' => 99];
    $first = ShapeService::build($shape);
    $started = microtime(true);
    $second = ShapeService::build($shape);
    $elapsed = (microtime(true) - $started) * 1000;
    if ($first['positions'] !== $second['positions']) return 'les deux nuages diffèrent';
    return $elapsed < 120 ? true : sprintf('cache trop lent : %.0f ms', $elapsed);
});

check('Une forme textuelle est déléguée au navigateur', function () {
    $built = ShapeService::build(['type' => 'text', 'text' => 'OK', 'count' => 500, 'id' => 'txt']);
    return ($built['source'] === 'client' && $built['text'] === 'OK') ? true : json_encode($built);
});

check('Une image est convertie en nuage', function () {
    $file = APP_CONTENT . '/shapes/.test-disque.png';
    $im = imagecreatetruecolor(80, 80);
    imagefill($im, 0, 0, imagecolorallocate($im, 255, 255, 255));
    imagefilledellipse($im, 40, 40, 50, 50, imagecolorallocate($im, 0, 0, 0));
    imagepng($im, $file);
    imagedestroy($im);

    $result = ImageSampler::sample($file, 1500, ['criterion' => 'dark']);
    @unlink($file);

    if (count($result['points']) !== 1500) return 'points : ' . count($result['points']);
    // Les points doivent se concentrer dans le disque noir, pas sur le fond blanc.
    foreach ($result['points'] as [$x, $y]) {
        if (hypot($x - 40, $y - 40) > 30) return "point hors du disque : ({$x}, {$y})";
    }
    return true;
});

// -------------------------------------------------------------------- Contenu

suite('Charte graphique');

check('Toute couleur fait l\'aller-retour hexadécimal sans perte', function () {
    foreach (['#7b01f7', '#ff6b00', '#00b894', '#ffffff', '#000000', '#808080', '#f00'] as $hex) {
        $back = Color::fromHex($hex)->toHex();
        $expected = strlen(ltrim($hex, '#')) === 3
            ? '#' . preg_replace('/(.)/', '$1$1', ltrim($hex, '#'))
            : strtolower($hex);
        if ($back !== $expected) {
            return "{$hex} est revenu en {$back}";
        }
    }
    return true;
});

check('Un gris reste gris : aucune teinte ne lui est inventée', function () {
    $palette = Palette::build(['dominant' => '#808080']);
    $accent = Color::fromHex($palette['accent']);
    return $accent->saturation < 0.12 ? true : "saturation obtenue : {$accent->saturation}";
});

check('Le texte garde un contraste suffisant quelle que soit la dominante', function () {
    foreach (['#7b01f7', '#ff6b00', '#00b894', '#1863dc', '#e91e63', '#f5c400', '#111111'] as $hex) {
        foreach (array_keys(Palette::HARMONIES) as $harmony) {
            $palette = Palette::build(['dominant' => $hex, 'harmony' => $harmony]);
            $background = Color::fromHex($palette['background']);

            $main = Color::fromHex($palette['foreground'])->contrastWith($background);
            if ($main < 7.0) {
                return sprintf('%s/%s : texte à %.1f:1', $hex, $harmony, $main);
            }
            $muted = Color::fromHex($palette['muted'])->contrastWith($background);
            if ($muted < 4.5) {
                return sprintf('%s/%s : texte secondaire à %.1f:1', $hex, $harmony, $muted);
            }
        }
    }
    return true;
});

check('Une valeur posée à la main prime sur la dérivation', function () {
    $palette = Palette::build(['dominant' => '#7b01f7', 'accent3' => '#00ff00']);
    return $palette['accent3'] === '#00ff00' ? true : $palette['accent3'];
});

check('Une couleur invalide est refusée', function () {
    foreach (['pas-une-couleur', '#12345', ''] as $bad) {
        try {
            Color::fromHex($bad);
            return "accepté à tort : « {$bad} »";
        } catch (InvalidArgumentException) {
            // Comportement attendu.
        }
    }
    return true;
});

// -------------------------------------------------------------------- Contenu

suite('Contenu multi-pages et routage');

check('site.json et les pages sont valides', function () {
    $site = Content::site();
    if (empty($site['name'])) return 'nom du site manquant';
    if (empty($site['theme']['accent'])) return 'charte non dérivée';
    return count(Content::pages()) >= 2 ? true : 'moins de deux pages';
});

check('Chaque page a un identifiant utilisable en URL', function () {
    foreach (Content::pages() as $page) {
        if (!Content::isValidSlug($page['slug'])) return "identifiant refusé : {$page['slug']}";
        if ($page['url'] === '') return "URL vide pour {$page['slug']}";
    }
    return true;
});

check('Les identifiants de section sont uniques au sein d\'une page', function () {
    foreach (Content::pages() as $page) {
        $seen = [];
        foreach ($page['sections'] as $section) {
            $id = (string) $section['id'];
            if (isset($seen[$id])) return "doublon {$page['slug']}/{$id}";
            $seen[$id] = true;
            if (empty($section['shape']['type'])) return "forme absente : {$page['slug']}/{$id}";
        }
    }
    return true;
});

check('Deux pages peuvent porter le même identifiant de section', function () {
    // Les clés de forme sont préfixées par la page : aucun conflit possible.
    $keys = [];
    foreach (Content::pages() as $page) {
        foreach ($page['sections'] as $section) {
            $key = $section['shapeKey'];
            if (isset($keys[$key])) return "clé de forme en double : {$key}";
            $keys[$key] = true;
        }
    }
    return count($keys) > 0 ? true : 'aucune clé produite';
});

check('La navigation reprend l\'ordre déclaré', function () {
    $nav = Content::navigation();
    if ($nav === []) return 'navigation vide';
    $orders = [];
    foreach (Content::pages() as $page) {
        if ($page['inNav']) $orders[] = $page['order'];
    }
    $sorted = $orders;
    sort($sorted);
    return $orders === $sorted ? true : 'ordre non respecté';
});

check('Toutes les formes du site se construisent réellement', function () {
    foreach (Content::pages() as $page) {
        foreach ($page['sections'] as $section) {
            $built = ShapeService::build($section['shape']);
            if ($built['source'] === 'server' && $built['count'] < 100) {
                return "{$section['shapeKey']} : seulement {$built['count']} points";
            }
        }
    }
    return true;
});

check('L\'écriture courte d\'une forme est comprise', function () {
    // Content mémorise ses lectures : un nouveau processus est nécessaire.
    $file = APP_CONTENT . '/pages/.test-court.json';
    file_put_contents($file, json_encode([
        'title' => 'Test',
        'inNav' => false,
        'sections' => [['id' => 'unique', 'shape' => 'galaxy']],
    ]));

    $output = shell_exec('php -r ' . escapeshellarg(
        'require "' . APP_ROOT . '/bootstrap.php"; App\\Config::boot(); ' .
        '$s = App\\Content::page(".test-court"); ' .
        'echo $s === null ? "page ignoree" : "trouvee";'
    ));
    @unlink($file);

    // Un nom de fichier commençant par un point n'est pas un identifiant valide :
    // la page doit être écartée plutôt que servie sur une URL bancale.
    return trim((string) $output) === 'page ignoree' ? true : "obtenu : {$output}";
});

check('Un identifiant de page hostile est rejeté', function () {
    foreach (['../site', 'Accueil', 'page espace', '', 'a/b'] as $bad) {
        if (Content::isValidSlug($bad)) return "accepté à tort : « {$bad} »";
    }
    return Content::isValidSlug('ma-page-2') ? true : 'un identifiant valide a été refusé';
});

check('La carte d\'imports couvre tous les modules du site', function () {
    $map = json_decode(View::importMap(), true);
    $imports = $map['imports'] ?? [];
    if (count($imports) < 8) {
        return 'seulement ' . count($imports) . ' entrée(s)';
    }
    foreach ($imports as $from => $to) {
        if (!str_starts_with($to, $from . '?v=')) {
            return "« {$from} » pointe vers « {$to} », sans version";
        }
        if (!is_file(APP_PUBLIC . $from)) {
            return "« {$from} » ne correspond à aucun fichier";
        }
    }

    // Tout module réellement présent doit figurer dans la carte, sans quoi il
    // serait chargé sans version et resterait figé en cache.
    $found = 0;
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(APP_PUBLIC . '/assets/js', FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if ($file instanceof SplFileInfo && $file->isFile() && $file->getExtension() === 'js') {
            $found++;
        }
    }

    return $found === count($imports) ? true : "{$found} fichiers sur le disque, " . count($imports) . ' dans la carte';
});

check('Le routeur distingue les paramètres et les méthodes', function () {
    $router = new Router();
    $captured = null;
    $router->get('/api/shape/{page}/{section}', function (array $p) use (&$captured) {
        $captured = $p['page'] . '/' . $p['section'];
    });

    ob_start();
    $router->dispatch('GET', '/api/shape/accueil/hero?format=bin');
    ob_end_clean();
    if ($captured !== 'accueil/hero') return 'paramètres : ' . var_export($captured, true);

    ob_start();
    $router->dispatch('POST', '/api/shape/accueil/hero');
    $body = ob_get_clean();
    return str_contains((string) $body, '405') ? true : "réponse POST : {$body}";
});

// ------------------------------------------------------------- Back-office

suite('Back-office');

check('Une forme est nettoyée avant d\'être écrite', function () {
    $clean = ContentWriter::sanitizeShape([
        'type' => 'preset',
        'preset' => 'torus',
        'count' => 999999,          // au-delà du plafond
        'depth' => -4,              // hors bornes
        'spin' => 0,                // valeur par défaut, à ne pas écrire
        'inconnu' => 'à jeter',     // clé non reconnue
    ]);
    if ($clean['count'] !== 40000) return 'plafond non appliqué : ' . $clean['count'];
    if ($clean['depth'] !== 0.0) return 'borne basse non appliquée : ' . $clean['depth'];
    if (isset($clean['spin'])) return 'une rotation nulle ne doit pas être écrite';
    return !isset($clean['inconnu']) ? true : 'une clé inconnue a été conservée';
});

check('Une source hors de content/shapes/ est refusée', function () {
    foreach (['../../bootstrap.php', '/etc/passwd', 'site.json', 'shapes/../site.json'] as $evil) {
        try {
            ContentWriter::sanitizeShape(['type' => 'svg', 'src' => $evil]);
            return "chemin accepté à tort : {$evil}";
        } catch (InvalidArgumentException) {
            // Comportement attendu.
        }
    }
    return true;
});

check('Un type de forme inconnu est refusé', function () {
    try {
        ContentWriter::sanitizeShape(['type' => 'hologramme']);
        return 'accepté à tort';
    } catch (InvalidArgumentException $e) {
        return str_contains($e->getMessage(), 'hologramme') ? true : $e->getMessage();
    }
});

check('Un texte vide est refusé', function () {
    try {
        ContentWriter::sanitizeShape(['type' => 'text', 'text' => '   ']);
        return 'accepté à tort';
    } catch (InvalidArgumentException) {
        return true;
    }
});

check('Écrire sur une section inexistante échoue sans toucher au fichier', function () {
    $file = APP_CONTENT . '/pages/accueil.json';
    $before = file_get_contents($file);
    try {
        ContentWriter::saveSectionShape('accueil', 'section-fantome', ['type' => 'preset', 'preset' => 'sphere']);
        return 'aucune exception levée';
    } catch (InvalidArgumentException) {
        return file_get_contents($file) === $before ? true : 'le fichier a été modifié';
    }
});

check('Un mot de passe trop court est refusé', function () {
    try {
        Auth::storeCredentials('essai@exemple.fr', 'court');
        return 'accepté à tort';
    } catch (InvalidArgumentException) {
        return true;
    }
});

check('Une adresse invalide est refusée', function () {
    foreach (['', 'pas-une-adresse', 'a@b', 'frederic@'] as $bad) {
        try {
            Auth::storeCredentials($bad, 'un-mot-de-passe-assez-long');
            return "acceptée à tort : « {$bad} »";
        } catch (InvalidArgumentException) {
            // Comportement attendu.
        }
    }
    return true;
});

check('La connexion exige l\'adresse ET le mot de passe', function () {
    // On travaille sur une copie : le compte réel ne doit pas être touché.
    $file = Auth::credentialsFile();
    $backup = is_file($file) ? file_get_contents($file) : null;

    try {
        Auth::storeCredentials('Frederic@Exemple.FR', 'mot-de-passe-de-controle');

        $cases = [
            ['frederic@exemple.fr', 'mot-de-passe-de-controle', true,  'le bon couple'],
            ['frederic@exemple.fr', 'mauvais-mot-de-passe',     false, 'un mot de passe faux'],
            ['autre@exemple.fr',    'mot-de-passe-de-controle', false, 'une adresse fausse'],
            ['',                    'mot-de-passe-de-controle', false, 'une adresse vide'],
            // La casse et les espaces ne doivent pas faire échouer une connexion légitime.
            ['  FREDERIC@exemple.fr  ', 'mot-de-passe-de-controle', true, 'la casse et les espaces'],
        ];

        foreach ($cases as [$email, $password, $expected, $label]) {
            $result = Auth::attempt($email, $password);
            if ($result['ok'] !== $expected) {
                return "{$label} : obtenu " . var_export($result['ok'], true);
            }
            // Le message ne doit jamais dire laquelle des deux valeurs était fausse.
            if (!$expected && preg_match('/adresse|courriel|mail|mot de passe incorrect/i', $result['message'])) {
                return "le message trahit la cause : « {$result['message']} »";
            }
        }

        return true;
    } finally {
        if ($backup !== null) {
            file_put_contents($file, $backup);
        } else {
            @unlink($file);
        }
    }
});

check('Le jeton CSRF est comparé sans fuite de temps', function () {
    // hash_equals ne renvoie vrai que sur une égalité stricte.
    $token = Auth::csrfToken();
    if (!Auth::checkCsrf($token)) return 'le jeton légitime a été rejeté';
    return !Auth::checkCsrf($token . 'x') && !Auth::checkCsrf('') && !Auth::checkCsrf(null)
        ? true
        : 'un jeton invalide a été accepté';
});

// ------------------------------------------------------- Édition du contenu

suite('Édition du contenu');

check('Un titre devient un identifiant lisible', function () {
    $cases = [
        'Tarifs & devis'        => 'tarifs-devis',
        'Nos Références'        => 'nos-references',
        '  Espaces   multiples ' => 'espaces-multiples',
        'Chiffres clés 2026'    => 'chiffres-cles-2026',
        'À propos'              => 'a-propos',
        '---'                   => '',
        '../../etc/passwd'      => 'etc-passwd',
    ];
    foreach ($cases as $input => $expected) {
        $got = ContentWriter::slugify((string) $input);
        if ($got !== $expected) {
            return "« {$input} » a donné « {$got} » au lieu de « {$expected} »";
        }
    }
    return true;
});

check('Une page créée est immédiatement servie par le site', function () {
    return withSandboxedContent(function () {
        $slug = ContentWriter::createPage([
            'title'       => 'Tarifs & devis',
            'description' => 'Nos formules.',
            'order'       => 7,
        ]);
        if ($slug !== 'tarifs-devis') return "identifiant inattendu : « {$slug} »";

        $page = Content::page($slug);
        if ($page === null) return 'la page créée reste introuvable';
        if ($page['title'] !== 'Tarifs & devis') return 'titre non repris';
        // Sans libellé de menu, c'est le titre qui sert d'entrée.
        if (($page['navLabel'] ?? '') !== 'Tarifs & devis') return 'libellé de menu non déduit du titre';
        if (count($page['sections']) !== 1) return 'la page doit naître avec une section';

        return in_array($slug, array_column(Content::pages(), 'slug'), true)
            ? true
            : 'la page manque dans la liste';
    });
});

check('Deux pages ne peuvent pas porter le même identifiant', function () {
    return withSandboxedContent(function () {
        try {
            ContentWriter::createPage(['title' => 'Accueil', 'slug' => 'accueil']);
            return 'doublon accepté';
        } catch (InvalidArgumentException $e) {
            return str_contains($e->getMessage(), 'accueil') ? true : $e->getMessage();
        }
    });
});

check('Une page sans titre est refusée', function () {
    return withSandboxedContent(function () {
        try {
            ContentWriter::createPage(['title' => '   ']);
            return 'acceptée à tort';
        } catch (InvalidArgumentException) {
            return true;
        }
    });
});

check('Retirer une page du menu ne la retire pas du site', function () {
    return withSandboxedContent(function () {
        // Une page du menu autre que l'accueil, quelle que soit l'arborescence.
        $cible = null;
        foreach (Content::navigation() as $item) {
            if ($item['slug'] !== Content::HOME) {
                $cible = $item['slug'];
                break;
            }
        }
        if ($cible === null) return 'aucune page à masquer dans le menu';

        ContentWriter::updatePage($cible, ['inNav' => false]);

        $menu = array_column(Content::navigation(), 'slug');
        if (in_array($cible, $menu, true)) return 'la page est restée dans le menu';

        // Elle doit rester lisible : une adresse déjà partagée ne doit pas casser.
        return Content::page($cible) !== null ? true : 'la page a disparu du site';
    });
});

check('L\'ordre du menu suit l\'ordre demandé', function () {
    return withSandboxedContent(function () {
        $slugs = array_column(Content::pages(), 'slug');
        $reversed = array_reverse($slugs);
        ContentWriter::reorderPages($reversed);

        $got = array_column(Content::navigation(), 'slug');
        // navPages() ne garde que les pages du menu : on compare à ce sous-ensemble.
        $expected = array_values(array_intersect($reversed, $got));

        return $got === $expected ? true : 'obtenu ' . implode(', ', $got);
    });
});

check('Un identifiant inconnu dans l\'ordre du menu est ignoré', function () {
    return withSandboxedContent(function () {
        $before = array_column(Content::navigation(), 'slug');
        ContentWriter::reorderPages(['page-fantome', '../etc/passwd', '']);

        return array_column(Content::navigation(), 'slug') === $before
            ? true
            : 'l\'ordre a bougé';
    });
});

check('L\'accueil ne peut pas être supprimé', function () {
    return withSandboxedContent(function () {
        try {
            ContentWriter::deletePage(Content::HOME);
            return 'suppression acceptée';
        } catch (InvalidArgumentException) {
            return is_file(APP_CONTENT . '/pages/' . Content::HOME . '.json')
                ? true
                : 'le fichier a tout de même été supprimé';
        }
    });
});

check('Une page supprimée laisse une sauvegarde', function () {
    return withSandboxedContent(function () {
        $slug = ContentWriter::createPage(['title' => 'Page jetable']);
        ContentWriter::deletePage($slug);

        if (Content::page($slug) !== null) return 'la page répond encore';

        $backups = glob(APP_ROOT . '/var/backups/' . $slug . '-*.json') ?: [];
        foreach ($backups as $file) {
            @unlink($file);
        }
        return $backups !== [] ? true : 'aucune sauvegarde écrite';
    });
});

check('Une section ajoutée reçoit un identifiant unique', function () {
    return withSandboxedContent(function () {
        $first = ContentWriter::addSection('accueil', 'stats', 'Chiffres clés');
        if ($first !== 'chiffres-cles') return "identifiant inattendu : « {$first} »";

        $second = ContentWriter::addSection('accueil', 'stats', 'Chiffres clés');
        if ($second !== 'chiffres-cles-2') return "collision non résolue : « {$second} »";

        $ids = array_column(Content::page('accueil')['sections'], 'id');
        return count($ids) === count(array_unique($ids)) ? true : 'identifiants en double';
    });
});

check('Un type de section inconnu est refusé', function () {
    return withSandboxedContent(function () {
        try {
            ContentWriter::addSection('accueil', 'carrousel-3d');
            return 'accepté à tort';
        } catch (InvalidArgumentException $e) {
            return str_contains($e->getMessage(), 'carrousel-3d') ? true : $e->getMessage();
        }
    });
});

check('Le texte saisi est nettoyé avant d\'être écrit', function () {
    return withSandboxedContent(function () {
        $id = ContentWriter::addSection('accueil', 'stats', 'essai');
        ContentWriter::updateSection('accueil', $id, [
            'eyebrow' => "  En   chiffres  ",
            'items'   => [
                ['value' => '48', 'suffix' => '%', 'label' => 'Taux'],
                ['value' => '',   'suffix' => '',  'label' => ''],   // ligne laissée vide
                ['value' => '99999999', 'label' => 'Plafonné'],       // au-delà du maximum
            ],
            'clef_inventee' => 'à jeter',
        ]);

        $section = null;
        foreach (Content::page('accueil')['sections'] as $s) {
            if ($s['id'] === $id) $section = $s;
        }
        if ($section === null) return 'section introuvable';

        if ($section['eyebrow'] !== 'En chiffres') return "espaces non normalisés : « {$section['eyebrow']} »";
        if (count($section['items']) !== 2) return 'la ligne vide a été écrite';
        if ($section['items'][0]['value'] !== 48) return 'un entier doit rester entier';
        if ($section['items'][1]['value'] !== 1000000) return 'plafond non appliqué';
        if (isset($section['clef_inventee'])) return 'une clé inconnue a été conservée';

        return true;
    });
});

check('Modifier une section ne touche pas à sa forme', function () {
    return withSandboxedContent(function () {
        $before = null;
        foreach (Content::page('accueil')['sections'] as $s) {
            if ($s['id'] === 'hero') $before = $s['shape'] ?? null;
        }
        if ($before === null) return 'la section d\'essai n\'a pas de forme';

        ContentWriter::updateSection('accueil', 'hero', ['title' => "Nouveau titre\nsur deux lignes"]);

        $after = null;
        foreach (Content::page('accueil')['sections'] as $s) {
            if ($s['id'] === 'hero') $after = $s;
        }
        if (($after['shape'] ?? null) !== $before) return 'la forme a changé';

        return $after['title'] === ['Nouveau titre', 'sur deux lignes']
            ? true
            : 'le titre à plusieurs lignes n\'a pas été découpé';
    });
});

check('Une section se déplace, sans sortir de la liste', function () {
    return withSandboxedContent(function () {
        $ids = fn () => array_column(Content::page('accueil')['sections'], 'id');
        $start = $ids();
        if (count($start) < 2) return 'il faut au moins deux sections pour ce test';

        ContentWriter::moveSection('accueil', $start[0], 'down');
        $moved = $ids();
        if ($moved[0] !== $start[1] || $moved[1] !== $start[0]) {
            return 'échange raté : ' . implode(', ', $moved);
        }

        // Monter la première ne doit rien casser : il n'y a rien au-dessus.
        ContentWriter::moveSection('accueil', $moved[0], 'up');
        return $ids() === $moved ? true : 'la liste a bougé alors qu\'il n\'y a pas de place';
    });
});

check('Une page garde au moins une section', function () {
    return withSandboxedContent(function () {
        $slug = ContentWriter::createPage(['title' => 'Page minimale']);
        $only = Content::page($slug)['sections'][0]['id'];
        try {
            ContentWriter::deleteSection($slug, $only);
            return 'la dernière section a été supprimée';
        } catch (InvalidArgumentException) {
            return count(Content::page($slug)['sections']) === 1 ? true : 'la page a été vidée';
        }
    });
});

check('Une écriture qui échoue ne laisse pas le fichier à moitié écrit', function () {
    return withSandboxedContent(function () {
        $file = APP_CONTENT . '/pages/accueil.json';
        $before = file_get_contents($file);
        foreach ([
            fn () => ContentWriter::updateSection('accueil', 'section-fantome', []),
            fn () => ContentWriter::moveSection('accueil', 'section-fantome', 'up'),
            fn () => ContentWriter::deleteSection('accueil', 'section-fantome'),
            fn () => ContentWriter::addSection('page-fantome', 'hero'),
        ] as $call) {
            try {
                $call();
                return 'aucune exception levée';
            } catch (InvalidArgumentException) {
                if (file_get_contents($file) !== $before) return 'le fichier a été modifié';
            }
        }
        return true;
    });
});

check('Le fichier réécrit garde le style du dépôt', function () {
    return withSandboxedContent(function () {
        $file = APP_CONTENT . '/pages/contact.json';
        $before = (string) file_get_contents($file);

        // Réécrire sans rien changer doit rendre le fichier au caractère près :
        // sinon chaque enregistrement noierait la modification réelle dans un
        // diff de réindentation.
        $write = new ReflectionMethod(ContentWriter::class, 'write');
        $write->setAccessible(true);
        $write->invoke(null, $file, json_decode($before, true));

        return file_get_contents($file) === $before ? true : 'le fichier a été réindenté';
    });
});

check('Chaque type de section a un gabarit de rendu', function () {
    foreach (array_keys(SectionSchema::all()) as $kind) {
        if (!is_file(APP_ROOT . '/views/partials/section-' . $kind . '.php')) {
            return "gabarit manquant pour « {$kind} »";
        }
    }
    return true;
});

check('Le schéma décrit tous les types déjà utilisés', function () {
    foreach (Content::pages() as $page) {
        foreach (Content::page($page['slug'])['sections'] as $section) {
            if (!SectionSchema::isKnownKind($section['kind'])) {
                return "type absent du schéma : « {$section['kind']} » ({$page['slug']})";
            }
        }
    }
    return true;
});

check('Le schéma sait relire ce que le site contient déjà', function () {
    // Passer une section existante par le nettoyage ne doit rien perdre : le
    // formulaire du back-office est capable d'éditer le contenu livré.
    foreach (Content::pages() as $page) {
        foreach (Content::page($page['slug'])['sections'] as $section) {
            $editable = $section;
            unset(
                $editable['id'], $editable['kind'], $editable['shape'],
                // Clés ajoutées à la lecture, jamais saisies dans le formulaire.
                $editable['index'], $editable['page'], $editable['shapeKey']
            );

            $roundTrip = SectionSchema::sanitize($section['kind'], $editable);
            if ($roundTrip != $editable) {
                return sprintf(
                    '%s/%s : %s',
                    $page['slug'],
                    $section['id'],
                    json_encode(array_keys(array_diff_key($editable, $roundTrip)), JSON_UNESCAPED_UNICODE)
                );
            }
        }
    }
    return true;
});

check('Une ligne de grand titre tient dans la largeur', function () {
    // Le corps du titre suit la largeur de l'écran : la limite est donc la
    // même du téléphone au grand écran, une vingtaine de caractères. Au-delà,
    // la ligne se replie et la découpe plein / contour se décale.
    $trop = [];
    foreach (Content::pages() as $page) {
        foreach ($page['sections'] as $section) {
            $limite = in_array($section['kind'], ['hero', 'contact'], true) ? 20 : 40;
            foreach ($section['title'] ?? [] as $ligne) {
                if (mb_strlen($ligne) > $limite) {
                    $trop[] = sprintf('%s/%s : %d car. « %s »',
                        $page['slug'], $section['id'], mb_strlen($ligne), $ligne);
                }
            }
        }
    }
    return $trop === [] ? true : implode(' | ', $trop);
});

check('Le contour porte sur une ligne qui existe', function () {
    foreach (Content::pages() as $page) {
        foreach ($page['sections'] as $section) {
            if (!isset($section['outlineFrom'])) {
                continue;
            }
            $lignes = count($section['title'] ?? []);
            if ($section['outlineFrom'] >= $lignes) {
                return sprintf('%s/%s : contour à partir de %d, pour %d ligne(s)',
                    $page['slug'], $section['id'], $section['outlineFrom'], $lignes);
            }
        }
    }
    return true;
});

check('Le rappel de contact vise la page qui porte la section contact', function () {
    $contact = Content::contactPage();
    if ($contact === null) return 'aucune page de contact trouvée';

    $kinds = array_column(Content::page($contact['slug'])['sections'], 'kind');
    return in_array('contact', $kinds, true)
        ? true
        : "la page « {$contact['slug'] }» ne contient pas de section contact";
});

check('La page de contact est celle qui se désigne comme telle', function () {
    return withSandboxedContent(function () {
        // Toutes les pages finissent par une prise de contact : sans repère
        // explicite, le bouton mènerait à la première venue.
        $designee = null;
        foreach (Content::pages() as $page) {
            if (($page['isContact'] ?? false) === true) {
                $designee = $page['slug'];
            }
        }
        if ($designee === null) return 'aucune page ne porte « isContact »';

        $cible = Content::contactPage();
        if ($cible['slug'] !== $designee) {
            return "le bouton mène à « {$cible['slug']} » au lieu de « {$designee} »";
        }

        // Le libellé du bouton vient du menu de cette page : le renommer depuis
        // le back-office doit suffire à renommer le bouton.
        ContentWriter::updatePage($designee, ['navLabel' => 'Écrivez-nous']);
        $apres = Content::contactPage();

        return $apres['label'] === 'Écrivez-nous' && $apres['slug'] === $designee
            ? true
            : 'libellé inattendu : ' . json_encode($apres, JSON_UNESCAPED_UNICODE);
    });
});

check('Sans page désignée, le rappel retombe sur une prise de contact', function () {
    return withSandboxedContent(function () {
        // Le repère peut manquer — un fichier écrit à la main, une page
        // supprimée : le bouton doit continuer de mener quelque part.
        foreach (Content::pages() as $page) {
            if (($page['isContact'] ?? false) !== true) {
                continue;
            }
            $file = APP_CONTENT . '/pages/' . $page['slug'] . '.json';
            $data = json_decode((string) file_get_contents($file), true);
            unset($data['isContact']);
            file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }
        Content::forget();

        $cible = Content::contactPage();
        if ($cible === null) return 'plus aucune destination';

        $kinds = array_column(Content::page($cible['slug'])['sections'], 'kind');
        return in_array('contact', $kinds, true)
            ? true
            : "« {$cible['slug']} » ne propose pas de prise de contact";
    });
});

// ------------------------------------------------------------------- Logo

suite('Logo');

check('Le logo est inséré dans la page, pas chargé comme image', function () {
    $svg = View::inlineSvg('assets/img/logo-mono.svg');
    if ($svg === '') return 'fichier introuvable ou vide';
    if (!str_starts_with(trim($svg), '<svg')) return 'ce n\'est pas un SVG';

    // Inséré dans le document, il doit suivre la charte plutôt que porter
    // ses propres couleurs en dur.
    if (!str_contains($svg, 'currentColor')) return 'l\'encre ne suit pas la couleur du texte';
    return str_contains($svg, '--logo-accent') ? true : 'la barre ne suit pas la couleur dominante';
});

check('Le logo de partage garde les couleurs de la marque', function () {
    $svg = View::inlineSvg('assets/img/logo.svg');
    if ($svg === '') return 'fichier introuvable';
    // Hors du site — favicone, partage, impression — aucune variable ne serait
    // résolue : ce fichier-là porte les couleurs réelles.
    if (str_contains($svg, 'currentColor') || str_contains($svg, 'var(--')) {
        return 'des variables subsistent';
    }
    return str_contains($svg, '#d51317') ? true : 'le rouge de la marque a disparu';
});

check('inlineSvg refuse ce qui n\'est pas un SVG de public/', function () {
    foreach ([
        '../bootstrap.php',
        '../content/site.json',
        'assets/css/app.css',
        'assets/img/inexistant.svg',
    ] as $chemin) {
        if (View::inlineSvg($chemin) !== '') return "accepté à tort : {$chemin}";
    }
    return true;
});

check('Le logo à particules tient dans un carré', function () {
    $file = APP_CONTENT . '/shapes/logo.svg';
    if (!is_file($file)) return 'forme absente de la bibliothèque';

    $echantillon = \App\Shape\SvgSampler::sample($file, 2000, ['mode' => 'fill'], new Rng(11));
    [, , $largeur, $hauteur] = $echantillon['viewBox'];

    // Au-delà de deux fois plus large que haut, le cadrage écrase le nuage et
    // le mot devient une traînée illisible.
    $rapport = $largeur / $hauteur;
    return $rapport < 2.0 ? true : sprintf('rapport %.2f, trop allongé', $rapport);
});

check('La favicone existe et reste un fichier léger', function () {
    $file = APP_PUBLIC . '/assets/img/favicon.svg';
    if (!is_file($file)) return 'favicone absente';
    $poids = filesize($file);
    return $poids < 8192 ? true : "{$poids} octets, trop lourd pour une favicone";
});

// ------------------------------------------------------------------- Menu

suite('Menu');

check('Le contact ne figure pas dans la liste ordinaire du menu', function () {
    $contact = Content::contactPage();
    if ($contact === null) return 'aucune page de contact';

    // Il a sa place ailleurs, en bouton : le laisser aussi dans la liste
    // ferait deux fois le même lien.
    $menu = array_column(Content::navigation(), 'slug');
    return !in_array($contact['slug'], $menu, true)
        ? true
        : 'le contact apparaît deux fois dans l\'en-tête';
});

check('Chaque entrée de menu mène à une page servie', function () {
    foreach (Content::navigation() as $item) {
        if (Content::page($item['slug']) === null) {
            return "« {$item['slug']} » est au menu mais introuvable";
        }
        if ($item['label'] === '') return "« {$item['slug']} » n'a pas de libellé";
    }
    return true;
});

// ------------------------------------------------------------------------ API

$baseUrl = $argv[1] ?? null;
if ($baseUrl !== null) {
    suite("API en ligne ({$baseUrl})");

    $get = static function (string $path) use ($baseUrl): array {
        // Les redirections ne sont pas suivies : une réponse 302 est en soi le
        // résultat attendu de certains tests, et la suivre masquerait le fait
        // qu'une page protégée renvoie bien vers la connexion.
        $context = stream_context_create([
            'http' => ['ignore_errors' => true, 'timeout' => 20, 'follow_location' => 0],
        ]);
        $body = @file_get_contents($baseUrl . $path, false, $context);

        $status = 0;
        foreach ($http_response_header ?? [] as $line) {
            if (preg_match('#^HTTP/\S+\s+(\d+)#', $line, $m)) {
                $status = (int) $m[1];
                break;
            }
        }

        return [$status, (string) $body];
    };

    check('GET /health répond 200', function () use ($get) {
        [$status, $body] = $get('/health');
        return ($status === 200 && str_contains($body, '"status":"ok"')) ? true : "{$status} — {$body}";
    });

    check('GET / renvoie la page complète', function () use ($get) {
        [$status, $body] = $get('/');
        if ($status !== 200) return "statut {$status}";

        // Le repère vient du contenu, pas d'un mot figé ici : réécrire les
        // textes depuis le back-office ne doit pas faire échouer la suite.
        $accueil = Content::page(Content::HOME);
        $attendus = ['shapes-data', 'id="' . $accueil['sections'][0]['id'] . '"'];
        foreach ($accueil['sections'][0]['title'] ?? [] as $ligne) {
            $attendus[] = htmlspecialchars($ligne, ENT_QUOTES, 'UTF-8');
        }

        foreach ($attendus as $needle) {
            if (!str_contains($body, $needle)) return "« {$needle} » absent de la page";
        }
        return true;
    });

    check('GET /api/pages liste toutes les pages', function () use ($get) {
        [$status, $body] = $get('/api/pages');
        $data = json_decode($body, true);
        return ($status === 200 && count($data['pages'] ?? []) === count(Content::pages()))
            ? true
            : "statut {$status}";
    });

    check('Chaque page publique répond', function () use ($get) {
        foreach (Content::pages() as $page) {
            [$status, $body] = $get($page['url']);
            if ($status !== 200) return "{$page['url']} : statut {$status}";
            if (!str_contains($body, 'shapes-data')) return "{$page['url']} : formes absentes";
        }
        return true;
    });

    check('Le back-office est fermé sans session', function () use ($get) {
        foreach (['/admin', '/admin/pages', '/admin/formes', '/admin/theme'] as $path) {
            [$status] = $get($path);
            // 302 vers la connexion, ou 401 pour les points d'entrée JSON.
            if (!in_array($status, [302, 401], true)) return "{$path} répond {$status}";
        }
        [$status] = $get('/admin/palette?dominant=%23ff0000');
        return $status === 401 ? true : "/admin/palette répond {$status}";
    });

    check('GET /api/shape/accueil/hero?format=bin renvoie du Float32', function () use ($get) {
        [$status, $body] = $get('/api/shape/accueil/hero?format=bin');
        if ($status !== 200) return "statut {$status}";
        if (strlen($body) % 12 !== 0) return 'taille non multiple de 12 octets';
        $floats = unpack('g*', $body);
        foreach (array_slice($floats, 0, 300) as $value) {
            if (!is_finite($value) || abs($value) > 1.2) return "valeur hors bornes : {$value}";
        }
        return true;
    });

    check('Le format binaire est bien plus léger que le JSON', function () use ($get) {
        [, $json] = $get('/api/shape/accueil/hero');
        [, $bin] = $get('/api/shape/accueil/hero?format=bin');
        $ratio = strlen($json) / max(1, strlen($bin));
        return $ratio > 1.8 ? true : sprintf('rapport insuffisant : %.2f', $ratio);
    });

    check('GET /api/preview accepte des réglages arbitraires', function () use ($get) {
        [$status, $body] = $get('/api/preview?type=preset&preset=torus&count=800');
        $data = json_decode($body, true);
        return ($status === 200 && ($data['count'] ?? 0) === 800) ? true : "statut {$status} — {$body}";
    });

    check('Une section inconnue renvoie 404', function () use ($get) {
        [$status] = $get('/api/shape/accueil/inexistante');
        if ($status !== 404) return "section inconnue : {$status}";
        [$status] = $get('/api/shape/page-inconnue/hero');
        return $status === 404 ? true : "page inconnue : {$status}";
    });

    check('Un type de forme invalide renvoie 422', function () use ($get) {
        [$status] = $get('/api/preview?type=chose');
        return $status === 422 ? true : $status;
    });

    check('Une page inconnue renvoie 404 en HTML', function () use ($get) {
        [$status, $body] = $get('/page-absente');
        return ($status === 404 && str_contains($body, '404')) ? true : "statut {$status}";
    });

    check('La page de diagnostic répond et reste lisible sans script', function () use ($get) {
        [$status, $body] = $get('/diagnostic');
        if ($status !== 200) return "statut {$status}";
        foreach (['Version de PHP', 'three.module.min.js', 'client-checks'] as $needle) {
            if (!str_contains($body, $needle)) return "« {$needle} » absent";
        }
        // Ni particules ni animations : la page doit tenir sans JavaScript.
        return !str_contains($body, 'data-reveal') ? true : 'la page dépend des animations';
    });

    check('L\'ancienne adresse publique du laboratoire n\'existe plus', function () use ($get) {
        // L'atelier a rejoint le back-office : /labo ne doit plus rien servir.
        [$status] = $get('/labo');
        return $status === 404 ? true : "/labo répond {$status}";
    });
}

// ------------------------------------------------------------------- Synthèse

echo "\n" . str_repeat('─', 62) . "\n";
if ($failed === []) {
    echo "\033[32m{$passed} tests réussis.\033[0m\n";
    exit(0);
}
printf("\033[31m%d échec(s) sur %d tests :\033[0m\n", count($failed), $passed + count($failed));
foreach ($failed as $line) {
    echo "  · {$line}\n";
}
exit(1);
