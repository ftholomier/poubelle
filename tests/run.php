<?php

declare(strict_types=1);

/**
 * Suite de tests, sans dépendance externe.
 * Usage : php tests/run.php  (ajouter l'URL d'un serveur pour tester aussi l'API)
 *         php tests/run.php http://localhost:8000
 */

require dirname(__DIR__) . '/bootstrap.php';

use App\Config;
use App\Content;
use App\Http\Router;
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

suite('Contenu et routage');

check('site.json et sections.json sont valides', function () {
    $site = Content::site();
    $sections = Content::sections();
    if (empty($site['name'])) return 'nom du site manquant';
    if (count($sections) < 1) return 'aucune section';
    return true;
});

check('Chaque section possède un identifiant unique et une forme', function () {
    $seen = [];
    foreach (Content::sections() as $section) {
        $id = $section['id'];
        if (isset($seen[$id])) return "identifiant en double : {$id}";
        $seen[$id] = true;
        if (empty($section['shape']['type'])) return "forme absente pour {$id}";
    }
    return true;
});

check('Toutes les formes déclarées se construisent réellement', function () {
    foreach (Content::sections() as $section) {
        $built = ShapeService::build($section['shape']);
        if ($built['source'] === 'server' && $built['count'] < 100) {
            return "{$section['id']} : seulement {$built['count']} points";
        }
    }
    return true;
});

check('L\'écriture courte d\'une forme est comprise', function () {
    $file = APP_CONTENT . '/sections.json';
    $backup = file_get_contents($file);
    $data = json_decode($backup, true);
    $data['sections'] = [['id' => 'court', 'shape' => 'galaxy']];
    file_put_contents($file, json_encode($data));

    // Content mémorise ses lectures : un nouveau processus est nécessaire.
    $output = shell_exec(
        'php -r ' . escapeshellarg(
            'require "' . APP_ROOT . '/bootstrap.php"; ' .
            'App\\Config::boot(); ' .
            '$s = App\\Content::sections()[0]["shape"]; ' .
            'echo $s["type"] . "|" . $s["preset"];'
        )
    );
    file_put_contents($file, $backup);

    return trim((string) $output) === 'preset|galaxy' ? true : "obtenu : {$output}";
});

check('Le routeur distingue les paramètres et les méthodes', function () {
    $router = new Router();
    $captured = null;
    $router->get('/api/shape/{id}', function (array $p) use (&$captured) { $captured = $p['id']; });

    ob_start();
    $router->dispatch('GET', '/api/shape/hero?format=bin');
    ob_end_clean();
    if ($captured !== 'hero') return "paramètre : " . var_export($captured, true);

    ob_start();
    $router->dispatch('POST', '/api/shape/hero');
    $body = ob_get_clean();
    return str_contains((string) $body, '405') ? true : "réponse POST : {$body}";
});

// ------------------------------------------------------------------------ API

$baseUrl = $argv[1] ?? null;
if ($baseUrl !== null) {
    suite("API en ligne ({$baseUrl})");

    $get = static function (string $path) use ($baseUrl): array {
        $context = stream_context_create(['http' => ['ignore_errors' => true, 'timeout' => 20]]);
        $body = @file_get_contents($baseUrl . $path, false, $context);
        $status = 0;
        foreach ($http_response_header ?? [] as $line) {
            if (preg_match('#HTTP/\S+\s+(\d+)#', $line, $m)) $status = (int) $m[1];
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
        foreach (['shapes-data', 'id="hero"', 'Accompagnement'] as $needle) {
            if (!str_contains($body, $needle)) return "« {$needle} » absent de la page";
        }
        return true;
    });

    check('GET /api/sections liste toutes les sections', function () use ($get) {
        [$status, $body] = $get('/api/sections');
        $data = json_decode($body, true);
        return ($status === 200 && count($data['sections'] ?? []) === count(Content::sections()))
            ? true
            : "statut {$status}";
    });

    check('GET /api/shape/hero?format=bin renvoie du Float32', function () use ($get) {
        [$status, $body] = $get('/api/shape/hero?format=bin');
        if ($status !== 200) return "statut {$status}";
        if (strlen($body) % 12 !== 0) return 'taille non multiple de 12 octets';
        $floats = unpack('g*', $body);
        foreach (array_slice($floats, 0, 300) as $value) {
            if (!is_finite($value) || abs($value) > 1.2) return "valeur hors bornes : {$value}";
        }
        return true;
    });

    check('Le format binaire est bien plus léger que le JSON', function () use ($get) {
        [, $json] = $get('/api/shape/hero');
        [, $bin] = $get('/api/shape/hero?format=bin');
        $ratio = strlen($json) / max(1, strlen($bin));
        return $ratio > 1.8 ? true : sprintf('rapport insuffisant : %.2f', $ratio);
    });

    check('GET /api/preview accepte des réglages arbitraires', function () use ($get) {
        [$status, $body] = $get('/api/preview?type=preset&preset=torus&count=800');
        $data = json_decode($body, true);
        return ($status === 200 && ($data['count'] ?? 0) === 800) ? true : "statut {$status} — {$body}";
    });

    check('Une section inconnue renvoie 404', function () use ($get) {
        [$status] = $get('/api/shape/inexistante');
        return $status === 404 ? true : $status;
    });

    check('Un type de forme invalide renvoie 422', function () use ($get) {
        [$status] = $get('/api/preview?type=chose');
        return $status === 422 ? true : $status;
    });

    check('Une page inconnue renvoie 404 en HTML', function () use ($get) {
        [$status, $body] = $get('/page-absente');
        return ($status === 404 && str_contains($body, '404')) ? true : "statut {$status}";
    });

    check('/labo se charge avec ses réglages', function () use ($get) {
        [$status, $body] = $get('/labo');
        return ($status === 200 && str_contains($body, 'lab-form')) ? true : "statut {$status}";
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
