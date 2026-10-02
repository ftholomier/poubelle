<?php
/**
 * Aspiration du site WordPress actuel, reprenable à tout moment.
 *
 * Usage : WP_PASSWORD=... php scripts/wp/fetch.php [index|html|orders|media|all]
 *
 *  index   liste des articles et pages (plans de site Yoast)
 *  html    HTML rendu de chaque article et page (contenu BeTheme + tableaux wpDataTables)
 *  orders  ordre d'affichage des articles dans chaque mosaïque de catégorie
 *  media   fichiers originaux des médias (sans les miniatures WordPress)
 */

declare(strict_types=1);
require __DIR__ . '/lib.php';

$step = $argv[1] ?? 'all';
$steps = $step === 'all' ? ['index', 'html', 'orders', 'media'] : [$step];

foreach ($steps as $s) {
    out("=== Étape $s");
    match ($s) {
        'index' => step_index(),
        'html' => step_html(),
        'orders' => step_orders(),
        'media' => step_media(),
        default => throw new InvalidArgumentException("Étape inconnue : $s"),
    };
}
out('Terminé');

function step_index(): void
{
    // L'API REST n'étant plus accessible publiquement, la liste des contenus
    // vient des plans de site Yoast. Catégories et médias proviennent d'une
    // extraction REST antérieure (storage/import/rest/*.json) si présente.
    $urls = [];
    foreach (['post-sitemap', 'post-sitemap2', 'post-sitemap3', 'post-sitemap4', 'page-sitemap'] as $sm) {
        $r = http(wp_url() . "/$sm.xml", ['no_login_check' => true]);
        if ($r['code'] !== 200 || !str_contains($r['body'], '<urlset')) {
            continue;
        }
        $xml = new SimpleXMLElement($r['body']);
        foreach ($xml->url as $u) {
            $img = $u->children('http://www.google.com/schemas/sitemap-image/1.1');
            $urls[] = [
                'type' => str_starts_with($sm, 'page') ? 'page' : 'post',
                'link' => (string) $u->loc,
                'lastmod' => (string) $u->lastmod,
                'image' => isset($img->image) ? (string) $img->image->loc : null,
            ];
        }
        out("$sm : " . count($urls) . ' URL cumulées');
    }
    write_json(IMPORT_DIR . '/urls.json', $urls);
    write_file(IMPORT_DIR . '/html/_home.html', http(wp_url() . '/')['body']);
}

function step_html(): void
{
    $dir = IMPORT_DIR . '/html';
    $list = read_json(IMPORT_DIR . '/urls.json');
    $n = count($list);
    foreach ($list as $i => $p) {
        $file = sprintf('%s/%s.html.gz', $dir, md5($p['link']));
        if (is_file($file)) {
            continue;
        }
        $r = http($p['link']);
        if ($r['code'] !== 200) {
            out("HTTP {$r['code']} pour {$p['link']}");
        }
        write_file($file, gzencode($r['body'], 6));
        if ($i % 50 === 0) {
            out(sprintf('html %d/%d', $i + 1, $n));
        }
        usleep(400000);
    }
}

function step_orders(): void
{
    $cats = read_json(IMPORT_DIR . '/rest/categories.json');
    $orders = [];
    foreach ($cats as $c) {
        $r = http($c['link']);
        preg_match_all('/<article[^>]*class="[^"]*\bpost-(\d+)\b/', $r['body'], $m);
        $orders[$c['id']] = array_map('intval', $m[1]);
        out(sprintf('catégorie %s : %d articles', $c['slug'], count($m[1])));
        usleep(400000);
    }
    write_json(IMPORT_DIR . '/category-orders.json', $orders);
}

function step_media(): void
{
    $media = read_json(IMPORT_DIR . '/rest/media.json');
    $base = ROOT . '/storage/media/originals';
    $done = 0;
    $bytes = 0;
    $errors = [];
    foreach ($media as $i => $m) {
        $url = $m['source_url'];
        $rel = preg_replace('#^.*/wp-content/uploads/#', '', $url);
        $file = "$base/$rel";
        $expected = $m['media_details']['filesize'] ?? null;
        if (is_file($file) && ($expected === null || filesize($file) === $expected)) {
            $done++;
            continue;
        }
        try {
            ensure_dir(dirname($file));
            $fp = fopen("$file.part", 'wb');
            $r = http($url, ['timeout' => 600, 'curl' => [CURLOPT_FILE => $fp, CURLOPT_RETURNTRANSFER => false]]);
            fclose($fp);
            if ($r['code'] !== 200) {
                throw new RuntimeException("HTTP {$r['code']}");
            }
            $size = filesize("$file.part");
            if ($expected !== null && $size !== $expected) {
                throw new RuntimeException("taille $size au lieu de $expected");
            }
            rename("$file.part", $file);
            $bytes += $size;
            $done++;
        } catch (Throwable $e) {
            @unlink("$file.part");
            $errors[$m['id']] = $url . ' : ' . $e->getMessage();
        }
        if ($i % 100 === 0) {
            out(sprintf('médias %d/%d (%.1f Mo cette session, %d erreurs)', $i + 1, count($media), $bytes / 1e6, count($errors)));
        }
        usleep(150000);
    }
    write_json(IMPORT_DIR . '/media-errors.json', $errors);
    out(sprintf('Médias : %d OK, %d erreurs', $done, count($errors)));
}
