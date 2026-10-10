<?php
/**
 * Import ciblé d'une catégorie de l'ancien site WordPress (par exemple une saison ajoutée après
 * l'aspiration d'origine), sans toucher aux fiches existantes : seuls les articles absents du
 * musée sont créés. Reprend l'analyse de l'import complet (parser.php, fonctions d'import.php).
 *
 * Usage : WP_URL=https://old.fcsochauxretro.com WP_PASSWORD=... php scripts/wp/import-category.php 2026-2027 [--essai]
 *   --essai  analyse et affiche, sans rien écrire.
 * Ensuite, sur le serveur : php scripts/wp/media-sync.php (photos des nouvelles fiches).
 */

declare(strict_types=1);
require __DIR__ . '/lib.php';
require __DIR__ . '/parser.php';

const DATA = ROOT . '/data';

// Fonctions de l'import complet (sans exécuter son programme).
$src = (string) file_get_contents(__DIR__ . '/import.php');
preg_match_all('/^(?:\/\*\*(?:(?!\*\/).)*\*\/\n)?function \w+\(.*?^}\n/ms', $src, $fns);
eval(implode("\n", $fns[0]));

$slug = $argv[1] ?? '';
$dry = in_array('--essai', $argv, true);
if ($slug === '') {
    fwrite(STDERR, "Usage : php scripts/wp/import-category.php <catégorie> [--essai]\n");
    exit(1);
}
login();

$restCats = rest_all('categories');
$byId = [];
foreach ($restCats as $c) {
    $byId[$c['id']] = $c;
}
$cat = array_values(array_filter($restCats, fn ($c) => $c['slug'] === $slug))[0] ?? null;
if (!$cat) {
    throw new RuntimeException("Catégorie introuvable : $slug");
}
$catOut = read_json(DATA . '/categories.json') ?: [];
$media = read_json(DATA . '/media.json') ?: [];
$redirects = read_json(DATA . '/redirects.json') ?: [];
$report = ['errors' => [], 'warnings' => []];

$users = [];
try {
    foreach (rest_all('users') as $u) {
        $users[$u['id']] = $u['name'];
    }
} catch (Throwable) {
}

$used = [];
foreach (glob(DATA . '/fiches/*.json') ?: [] as $f) {
    $d = json_decode((string) file_get_contents($f), true);
    if (!empty($d['path'])) {
        $used[$d['path']] = (int) $d['id'];
    }
}

$created = [];
foreach (rest_all('posts', ['categories' => $cat['id']]) as $p) {
    $id = (int) $p['id'];
    if (is_file(DATA . "/fiches/$id.json")) {
        continue; // déjà au musée : jamais réécrite
    }
    $link = $p['link'];
    $html = http($link)['body'];
    write_file(IMPORT_DIR . '/html/' . md5($link) . '.html.gz', gzencode($html, 9));

    // Médias de l'article (pièces jointes et image à la une) ajoutés à la médiathèque.
    $atts = rest_all('media', ['parent' => $id]);
    if (!empty($p['featured_media']) && !in_array($p['featured_media'], array_column($atts, 'id'), true)) {
        $atts = array_merge($atts, rest_all('media', ['include' => $p['featured_media']]));
    }
    $featured = null;
    foreach ($atts as $m) {
        $rel = preg_replace('#^.*/wp-content/uploads/#', '', $m['source_url']);
        $d = $m['media_details'] ?? [];
        $cap = trim(html_entity_decode(strip_tags($m['caption']['rendered'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $split = wp_split_caption($cap);
        $media[$rel] ??= [
            'id' => $m['id'], 'file' => $rel, 'mime' => $m['mime_type'], 'width' => $d['width'] ?? null, 'height' => $d['height'] ?? null,
            'size' => $d['filesize'] ?? null, 'title' => html_entity_decode($m['title']['rendered'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            'caption' => $split['caption'], 'credit' => $split['credit'], 'caption_raw' => $cap, 'alt' => $m['alt_text'] ?? '',
            'rights' => '', 'date' => $m['date'] ?? null, 'wp_parent' => $m['post'] ?? null,
        ];
        if ($m['id'] === ($p['featured_media'] ?? 0)) {
            $featured = $rel;
        }
    }

    $parsed = wp_parse_page($html, $media);
    if ($parsed === null) {
        $report['errors'][] = "Pas un article : $link";
        continue;
    }
    $catSlugs = array_values(array_filter(array_map(fn ($cid) => $byId[$cid]['slug'] ?? null, $p['categories'] ?? [])));
    $title = html_entity_decode($p['title']['rendered'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $yoast = $p['yoast_head_json'] ?? [];
    if ($featured === null && !empty($yoast['og_image'][0]['url'])) {
        $featured = wp_media_rel($yoast['og_image'][0]['url'], $media);
    }
    $kind = wp_classify($catSlugs, array_map(fn ($c) => ['parent_slug' => $c['parent'] ?? null], $catOut));
    $doc = [
        'id' => $id, 'type' => $kind['kind'], 'status' => 'publie', 'publish_at' => null, 'slug' => '', 'path' => '',
        'title' => $title, 'categories' => $catSlugs, 'a_la_une' => in_array('a-la-une', $catSlugs, true), 'featured_image' => $featured,
        'date' => $p['date'], 'modified' => $p['modified'], 'author' => $users[$p['author']] ?? null,
        'seo' => ['title' => '', 'description' => trim(html_entity_decode(strip_tags($yoast['description'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'))],
        'intro' => '', 'sections' => $parsed['sections'], 'key_figure' => $parsed['key_figure'], 'gallery' => $parsed['gallery'],
        'images' => $parsed['images'], 'videos' => $parsed['videos'], 'embeds' => $parsed['embeds'],
        'legacy' => ['wp_id' => $id, 'url' => $link, 'path' => parse_url($link, PHP_URL_PATH), 'slug' => $p['slug'], 'seo_title' => $yoast['title'] ?? '',
            'header_html' => $parsed['header']['html'] ?? '', 'header_lines' => $parsed['header']['lines'] ?? []],
        'i18n' => [],
    ];
    if ($doc['type'] !== 'match') {
        $report['errors'][] = "Pas une fiche de match (type {$doc['type']}) : $title";
        continue;
    }
    $doc['match'] = build_match($doc, $parsed, $catSlugs, $catOut, $report);
    $usedT = array_filter([$doc['match']['lineup']['source_table'] ?? null]);
    foreach ($doc['match']['other_lineups'] ?? [] as $ol) {
        $usedT[] = $ol['source_table'];
    }
    $doc['tables'] = [];
    foreach ($parsed['tables'] as $tb) {
        if (($tb['source_id'] !== null && in_array($tb['source_id'], $usedT, true)) || (!$tb['headers'] && !$tb['rows'])) {
            continue;
        }
        $doc['tables'][] = ['title' => (string) ($tb['title'] ?? ''), 'headers' => $tb['headers'], 'rows' => $tb['rows'], 'source_table' => $tb['source_id']];
    }

    // Adresse : /matchs/<saison>/<match>/, sans doublon.
    $base = '/matchs/' . ($doc['match']['season'] ?? 'saison-inconnue') . '/';
    $s = rtrim(substr(match_slug($doc), 0, 90), '-') ?: (string) $id;
    $path = $base . $s . '/';
    if (isset($used[$path])) {
        $path = $base . "$s-$id/";
    }
    $used[$path] = $id;
    $doc['path'] = $path;
    $doc['slug'] = basename(rtrim($path, '/'));
    if ($doc['legacy']['path'] && $doc['legacy']['path'] !== $path) {
        $redirects[$doc['legacy']['path']] = $path;
    }
    $redirects["/?p=$id"] = $path;

    $archive = ['wp_id' => $id, 'url' => $link, 'blocks' => $parsed['blocks'], 'table' => $parsed['table'], 'tables' => $parsed['tables'], 'table_title' => $parsed['table_title']];
    if (!$dry) {
        write_file(DATA . "/legacy/$id.json.gz", gzencode(json_encode($archive, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 9));
        write_json(DATA . "/fiches/$id.json", $doc);
    }
    $m = $doc['match'];
    $created[] = $id;
    out(sprintf('%s %d · %s · %s · %s · composition %d · %d photo(s)', $dry ? 'ESSAI' : 'CRÉÉE', $id, $title, $m['date'] ?? '?', $path,
        count($m['lineup']['rows'] ?? []), count($doc['gallery']) + count($doc['images'])));
}
foreach ($report['warnings'] as $w) {
    out("Avertissement : $w");
}
foreach ($report['errors'] as $e) {
    out("Erreur : $e");
}
if (!$dry && $created) {
    ksort($media);
    write_json(DATA . '/media.json', $media);
    ksort($redirects);
    write_json(DATA . '/redirects.json', $redirects);
}
out(count($created) . ' fiche(s) ' . ($dry ? 'à créer' : 'créée(s)'));
if (!$dry && $created) {
    // Index des fiches et données calculées refaits : les nouvelles fiches apparaissent sur le site.
    foreach (['index', 'derived', 'search'] as $task) {
        passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(ROOT . '/bin/console.php') . ' ' . $task);
    }
    out('Index refaits : les fiches sont en ligne. Reste à copier leurs photos (scripts/wp/media-sync.php).');
}
