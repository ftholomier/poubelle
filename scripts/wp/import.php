<?php
/**
 * Import complet du site WordPress aspiré vers les données du nouveau site.
 *
 * Usage : php scripts/wp/import.php
 *
 * Entrées (storage/import) : rest-full/*.json, html/*.html.gz, category-orders.json
 * Sorties (data/) :
 *   fiches/{id}.json      une fiche par article / page
 *   categories.json       arborescence, nouvelles adresses, ordre des mosaïques
 *   media.json            médiathèque (légende, crédit, droits, dimensions)
 *   redirects.json        ancienne adresse → nouvelle (301)
 * Rapport : storage/import/import-report.json
 *
 * Le script est rejouable : il réécrit les fiches importées (sans toucher aux
 * fiches créées dans le back-office, dont l'identifiant dépasse 1 000 000).
 */

declare(strict_types=1);
require __DIR__ . '/lib.php';
require __DIR__ . '/parser.php';

const DATA = ROOT . '/data';
const REST = IMPORT_DIR . '/rest-full';

$t0 = microtime(true);
$report = ['errors' => [], 'warnings' => [], 'stats' => []];

// ---------------------------------------------------------------------------
// 1. Médiathèque
// ---------------------------------------------------------------------------
$restMedia = read_json(REST . '/media.json');
$media = [];
foreach ($restMedia as $m) {
    $rel = preg_replace('#^.*/wp-content/uploads/#', '', $m['source_url']);
    $d = $m['media_details'] ?? [];
    $cap = trim(html_entity_decode(strip_tags($m['caption']['rendered'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    $split = wp_split_caption($cap);
    $media[$rel] = [
        'id' => $m['id'],
        'file' => $rel,
        'mime' => $m['mime_type'],
        'width' => $d['width'] ?? null,
        'height' => $d['height'] ?? null,
        'size' => $d['filesize'] ?? null,
        'title' => html_entity_decode($m['title']['rendered'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8'),
        'caption' => $split['caption'],
        'credit' => $split['credit'],
        'caption_raw' => $cap,
        'alt' => $m['alt_text'] ?? '',
        'rights' => '',
        'date' => $m['date'] ?? null,
        'wp_parent' => $m['post'] ?? null,
    ];
}
out(count($media) . ' médias');

// ---------------------------------------------------------------------------
// 2. Catégories et nouvelles adresses
// ---------------------------------------------------------------------------
$restCats = read_json(REST . '/categories.json');
$byId = [];
foreach ($restCats as $c) {
    $byId[$c['id']] = $c;
}
$cats = [];
foreach ($restCats as $c) {
    $cats[$c['slug']] = [
        'id' => $c['id'],
        'slug' => $c['slug'],
        'name' => html_entity_decode($c['name'], ENT_QUOTES | ENT_HTML5, 'UTF-8'),
        'description' => trim(strip_tags(html_entity_decode($c['description'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8'))),
        'parent_slug' => $c['parent'] ? ($byId[$c['parent']]['slug'] ?? null) : null,
        'wp_count' => $c['count'],
        'old_path' => parse_url($c['link'], PHP_URL_PATH),
    ];
}

/** Segment d'adresse lisible pour une catégorie. */
function cat_segment(string $slug): string
{
    $s = preg_replace('/-fc-sochaux-retro-fcsm$/', '', $slug);
    return match ($s) {
        'a-lessai' => 'a-l-essai',
        'coupe-deurope' => 'coupe-d-europe',
        'coupe-dete' => 'coupe-d-ete',
        'presidents-dhonneur' => 'presidents-d-honneur',
        'lequipe-de-sochaux-retro' => 'l-equipe-de-sochaux-retro',
        default => $s,
    };
}

function season_from_slug(string $slug): ?string
{
    return preg_match('/^(\d{4})\D+(\d{4})$/', $slug, $m) && (int) $m[2] === (int) $m[1] + 1 ? "{$m[1]}-{$m[2]}" : null;
}

$technical = ['a-la-une', 'uncategorized', 'zoom-sur-defilement-bas', 'zoom-sur-pave-droit'];
$catPath = function (string $slug) use (&$catPath, $cats, $technical): ?string {
    if (in_array($slug, $technical, true) || !isset($cats[$slug])) {
        return null;
    }
    if ($season = season_from_slug($slug)) {
        return "/matchs/$season/";
    }
    $parent = $cats[$slug]['parent_slug'];
    $seg = cat_segment($slug);
    if ($parent === null) {
        return "/$seg/";
    }
    $pp = $catPath($parent);
    return $pp === null ? "/$seg/" : $pp . $seg . '/';
};

// Ordre d'affichage relevé sur les mosaïques actuelles.
$orders = is_file(IMPORT_DIR . '/category-orders.json') ? read_json(IMPORT_DIR . '/category-orders.json') : [];

// Intitulés affichés : accents rétablis (le nom WordPress d'origine reste dans « name »).
$labelFixes = [
    'matchs-fc-sochaux-retro-fcsm' => 'Matchs',
    'nos-lions-fc-sochaux-retro-fcsm' => 'Nos Lions',
    'joueurs-fc-sochaux-retro-fcsm' => 'Joueurs',
    'entraineurs-fc-sochaux-retro-fcsm' => 'Entraîneurs',
    'entraineurs-adjoints' => 'Entraîneurs adjoints',
    'entraineurs-principaux' => 'Entraîneurs principaux',
    'dirigeants-fc-sochaux-retro-fcsm' => 'Dirigeants',
    'personnages-emblematiques-fc-sochaux-retro-fcsm' => 'Personnages emblématiques',
    'a-lessai' => "À l'essai",
    'directeurs-du-centre-de-formation' => 'Directeurs du centre de formation',
    'presidents-dhonneur' => "Présidents d'honneur",
];
// Ordre des sous-rubriques (celui des méga-menus).
$positions = [
    'joueurs-fc-sochaux-retro-fcsm' => 1, 'entraineurs-fc-sochaux-retro-fcsm' => 2, 'dirigeants-fc-sochaux-retro-fcsm' => 3, 'personnages-emblematiques-fc-sochaux-retro-fcsm' => 4,
    'entraineurs-principaux' => 1, 'entraineurs-adjoints' => 2,
    'presidents' => 1, 'presidents-executifs' => 2, 'presidents-dhonneur' => 3, 'directeurs-sportifs' => 4, 'directeurs-du-centre-de-formation' => 5, 'administratifs' => 6,
    'internationaux-fc-sochaux-retro-fcsm' => 1, 'internationaux-francais-fc-sochaux-retro-fcsm' => 2, 'formes-au-club-fc-sochaux-retro-fcsm' => 3, 'a-lessai' => 4,
    'le-stade' => 1, 'la-pelouse' => 2, 'le-centre-de-formation' => 3,
    'coupe-de-france' => 1, 'coupe-de-la-ligue' => 2, 'coupe-deurope' => 3, 'barrages' => 4, 'coupe-charles-drago' => 5, 'coupes-diverses' => 6, 'coupe-dete' => 7, 'amical' => 8,
];

$catOut = [];
foreach ($cats as $slug => $c) {
    $catOut[$slug] = [
        'id' => $c['id'],
        'slug' => $slug,
        'name' => $c['name'],
        'label' => $labelFixes[$slug] ?? null,
        'position' => $positions[$slug] ?? (preg_match('/^annees-(\d+)/', $slug, $pm) ? (strlen($pm[1]) === 2 ? (int) ('19' . $pm[1]) : (int) $pm[1]) : null),
        'description' => $c['description'],
        'parent' => $c['parent_slug'],
        'path' => $catPath($slug),
        'season' => season_from_slug($slug),
        'technical' => in_array($slug, $technical, true),
        'old_path' => $c['old_path'],
        'order' => array_map('intval', $orders[$c['id']] ?? $orders[(string) $c['id']] ?? []),
        'wp_count' => $c['wp_count'],
    ];
}

// ---------------------------------------------------------------------------
// 3. Fiches
// ---------------------------------------------------------------------------
$posts = array_merge(read_json(REST . '/posts.json'), read_json(REST . '/pages.json'));
$users = [];
foreach (read_json(REST . '/users.json') as $u) {
    $users[$u['id']] = $u['name'];
}

$docs = [];
$usedPaths = [];
$redirects = [];

foreach ($posts as $i => $p) {
    $link = $p['link'];
    $file = IMPORT_DIR . '/html/' . md5($link) . '.html.gz';
    if (!is_file($file)) {
        $report['errors'][] = "Page non aspirée : $link";
        continue;
    }
    try {
        $parsed = wp_parse_page(gzdecode((string) file_get_contents($file)), $media);
    } catch (Throwable $e) {
        $report['errors'][] = "$link : " . $e->getMessage();
        continue;
    }
    if ($parsed === null) {
        $report['errors'][] = "Pas un article : $link";
        continue;
    }
    if ($parsed['id'] !== $p['id']) {
        $report['warnings'][] = "Identifiant différent ({$parsed['id']} ≠ {$p['id']}) : $link";
    }

    $catSlugs = [];
    foreach ($p['categories'] ?? [] as $cid) {
        if (isset($byId[$cid])) {
            $catSlugs[] = $byId[$cid]['slug'];
        }
    }
    $title = html_entity_decode($p['title']['rendered'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $yoast = $p['yoast_head_json'] ?? [];
    $featured = null;
    if (!empty($p['featured_media'])) {
        foreach ($media as $rel => $m) {
            if ($m['id'] === $p['featured_media']) {
                $featured = $rel;
                break;
            }
        }
    }
    if ($featured === null && !empty($yoast['og_image'][0]['url'])) {
        $featured = wp_media_rel($yoast['og_image'][0]['url'], $media);
    }

    $kind = $p['type'] === 'page'
        ? ['kind' => 'page', 'roles' => []]
        : wp_classify($catSlugs, array_map(fn ($c) => ['parent_slug' => $c['parent']], $catOut));

    // « Bilan de la saison… » : rangé dans Matchs mais c'est un article (avec classement).
    if ($kind['kind'] === 'match' && preg_match('/^bilan\b/iu', $title)) {
        $kind = ['kind' => 'article', 'roles' => [], 'sub' => 'bilan_saison'];
    }

    $doc = [
        'id' => $p['id'],
        'type' => $kind['kind'],
        'status' => $p['type'] === 'page' && in_array($p['slug'], ['test-elfsight', 'data'], true) ? 'brouillon' : 'publie',
        'publish_at' => null,
        'slug' => '',
        'path' => '',
        'title' => $title,
        'categories' => $catSlugs,
        'a_la_une' => in_array('a-la-une', $catSlugs, true),
        'featured_image' => $featured,
        'date' => $p['date'],
        'modified' => $p['modified'],
        'author' => $users[$p['author']] ?? null,
        'seo' => [
            'title' => '',
            'description' => trim(html_entity_decode(strip_tags($yoast['description'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8')),
        ],
        'intro' => '',
        'sections' => $parsed['sections'],
        'key_figure' => $parsed['key_figure'],
        'gallery' => $parsed['gallery'],
        'images' => $parsed['images'],
        'videos' => $parsed['videos'],
        'embeds' => $parsed['embeds'],
        'legacy' => [
            'wp_id' => $p['id'],
            'url' => $link,
            'path' => parse_url($link, PHP_URL_PATH),
            'slug' => $p['slug'],
            'seo_title' => $yoast['title'] ?? '',
            'header_html' => $parsed['header']['html'] ?? '',
            'header_lines' => $parsed['header']['lines'] ?? [],
            'table' => $parsed['table'],
            'tables' => $parsed['tables'],
            'table_title' => $parsed['table_title'],
            'blocks' => $parsed['blocks'],
        ],
        'i18n' => [],
    ];
    foreach ($parsed['warnings'] as $w) {
        $report['warnings'][] = "$link : $w";
    }

    if ($doc['type'] === 'match') {
        $doc['match'] = build_match($doc, $parsed, $catSlugs, $catOut, $report);
    } elseif ($doc['type'] === 'personne') {
        $doc['personne'] = build_person($doc, $parsed, $kind['roles'], $catSlugs, $report);
    } else {
        $lines = $parsed['header']['lines'] ?? [];
        // En-tête d'origine (sans son premier titre) : chiffres d'un bilan, chapeau d'un article…
        $headerHtml = preg_replace('#^\s*<h[1-6][^>]*>.*?</h[1-6]>#is', '', (string) ($parsed['header']['html'] ?? ''), 1);
        $doc['article'] = [
            'kind' => $kind['sub'] ?? 'article',
            'heading' => $lines[0] ?? '',
            'subtitle' => implode("\n", array_slice($lines, 1)),
            'header_html' => trim((string) $headerHtml),
            'season' => null,
        ];
        if (($kind['sub'] ?? '') === 'bilan_saison' && preg_match('/(\d{4})\D+(\d{2,4})/', $title, $sm)) {
            $y2 = strlen($sm[2]) === 2 ? substr($sm[1], 0, 2) . $sm[2] : $sm[2];
            $doc['article']['season'] = $sm[1] . '-' . $y2;
        }
    }
    // Tableaux non repris comme composition ou statistiques : conservés et affichés tels quels.
    $used = array_filter([$doc['match']['lineup']['source_table'] ?? null, $doc['personne']['stats']['source_table'] ?? null]);
    foreach ($doc['match']['other_lineups'] ?? [] as $ol) {
        $used[] = $ol['source_table'];
    }
    $doc['tables'] = [];
    foreach ($parsed['tables'] as $tb) {
        if ($tb['source_id'] !== null && in_array($tb['source_id'], $used, true)) {
            continue;
        }
        if ($doc['type'] === 'personne' && $tb === ($parsed['tables'][0] ?? null) && isset($doc['personne']['stats'])) {
            continue;
        }
        $doc['tables'][] = ['title' => '', 'headers' => $tb['headers'], 'rows' => $tb['rows'], 'source_table' => $tb['source_id']];
    }
    $docs[$p['id']] = $doc;
}
out(count($docs) . ' fiches analysées');

// ---------------------------------------------------------------------------
// 4. Adresses (option B) et redirections
// ---------------------------------------------------------------------------
$personBase = ['joueur' => 'joueurs', 'entraineur' => 'entraineurs', 'dirigeant' => 'dirigeants', 'personnage' => 'personnages-emblematiques'];

// Les fiches les plus anciennes réservent l'adresse la plus courte.
uasort($docs, fn ($a, $b) => strcmp((string) $a['date'], (string) $b['date']) ?: $a['id'] <=> $b['id']);
foreach ($docs as $id => &$doc) {
    [$base, $slug] = match ($doc['type']) {
        'personne' => [$personBase[$doc['personne']['roles'][0] ?? 'joueur'], wp_slugify($doc['personne']['display_name'] ?: $doc['title'])],
        'match' => ['matchs/' . ($doc['match']['season'] ?? 'saison-inconnue'), match_slug($doc)],
        'page' => [null, $doc['legacy']['slug'] === 'sochaux-retro-musee-numerique-football-club-sochaux-montbeliard-fcsm' ? 'accueil-wordpress' : $doc['legacy']['slug']],
        default => [article_base($doc['categories'], $catOut), wp_slugify(article_title_for_slug($doc))],
    };
    $slug = substr($slug, 0, 90);
    $slug = rtrim($slug, '-') ?: (string) $id;
    $candidate = '/' . ($base ? "$base/" : '') . "$slug/";
    if (isset($usedPaths[$candidate])) {
        $suffix = match ($doc['type']) {
            'personne' => person_year($doc) ?? (string) $id,
            default => (string) $id,
        };
        $candidate = '/' . ($base ? "$base/" : '') . "$slug-$suffix/";
        if (isset($usedPaths[$candidate])) {
            $candidate = '/' . ($base ? "$base/" : '') . "$slug-$id/";
        }
        $report['warnings'][] = "Adresse en double résolue : {$doc['title']} → $candidate";
    }
    $usedPaths[$candidate] = $id;
    $doc['path'] = $candidate;
    $doc['slug'] = basename(rtrim($candidate, '/'));
    $old = $doc['legacy']['path'];
    if ($old && $old !== $candidate) {
        $redirects[$old] = $candidate;
    }
    $redirects["/?p=$id"] = $candidate;
}
unset($doc);

foreach ($catOut as $slug => $c) {
    if ($c['old_path']) {
        $redirects[$c['old_path']] = $c['path'] ?? '/';
    }
}
// Anciennes pages et accueil WordPress
$redirects['/accueil-wordpress/'] = '/';

// ---------------------------------------------------------------------------
// 5. Écriture
// ---------------------------------------------------------------------------
$dir = DATA . '/fiches';
$legacyDir = DATA . '/legacy';
ensure_dir($dir);
ensure_dir($legacyDir);
foreach ($docs as $id => $doc) {
    // Archive brute (blocs HTML d'origine, tableaux bruts) : compressée, hors des fiches.
    $archive = ['wp_id' => $id, 'url' => $doc['legacy']['url'], 'blocks' => $doc['legacy']['blocks'],
        'table' => $doc['legacy']['table'], 'tables' => $doc['legacy']['tables'], 'table_title' => $doc['legacy']['table_title']];
    write_file("$legacyDir/$id.json.gz", gzencode(json_encode($archive, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 9));
    unset($doc['legacy']['blocks'], $doc['legacy']['table'], $doc['legacy']['tables'], $doc['legacy']['table_title']);
    write_json("$dir/$id.json", $doc);
}
// Médias : enrichis des légendes trouvées dans les galeries (la galerie fait foi si la médiathèque est vide).
foreach ($docs as $doc) {
    foreach (array_merge($doc['gallery'], $doc['images']) as $g) {
        if (isset($media[$g['image']]) && $media[$g['image']]['caption_raw'] === '' && ($g['caption_raw'] ?? '') !== '') {
            $media[$g['image']]['caption'] = $g['caption'];
            $media[$g['image']]['credit'] = $g['credit'];
            $media[$g['image']]['caption_raw'] = $g['caption_raw'];
        }
    }
}
ksort($media);
write_json(DATA . '/media.json', $media);
write_json(DATA . '/categories.json', $catOut);
ksort($redirects);
write_json(DATA . '/redirects.json', $redirects);

$types = array_count_values(array_column($docs, 'type'));
$report['stats'] = [
    'fiches' => count($docs),
    'types' => $types,
    'medias' => count($media),
    'categories' => count($catOut),
    'redirections' => count($redirects),
    'sans_image_a_la_une' => count(array_filter($docs, fn ($d) => !$d['featured_image'])),
    'matchs_sans_date' => count(array_filter($docs, fn ($d) => $d['type'] === 'match' && empty($d['match']['date']))),
    'matchs_sans_score' => count(array_filter($docs, fn ($d) => $d['type'] === 'match' && empty($d['match']['score']))),
    'duree_s' => round(microtime(true) - $t0, 1),
];
write_json(IMPORT_DIR . '/import-report.json', $report);
out(json_encode($report['stats'], JSON_UNESCAPED_UNICODE));
out(count($report['errors']) . ' erreurs, ' . count($report['warnings']) . ' avertissements');

// ---------------------------------------------------------------------------

function build_match(array $doc, array $parsed, array $catSlugs, array $catOut, array &$report): array
{
    $t = wp_parse_match_title($doc['title']);
    $h = wp_parse_match_header($parsed['header']['lines'] ?? []);
    if ($t === null) {
        $report['warnings'][] = "Titre de match non reconnu : {$doc['title']}";
    }
    $season = null;
    foreach ($catSlugs as $s) {
        if ($season = season_from_slug($s)) {
            break;
        }
    }
    $date = $t['date'] ?? null;
    if (!$date && !empty($h['date_text'])) {
        $pd = wp_parse_date($h['date_text']);
        $date = ($pd['precision'] ?? null) === 'day' ? $pd['iso'] : null;
    }
    if (!$season && $date) {
        $y = (int) substr($date, 0, 4);
        $mo = (int) substr($date, 5, 2);
        $season = $mo >= 7 ? $y . '-' . ($y + 1) : ($y - 1) . '-' . $y;
    }

    // Compétition : famille (filtres) + libellé précis.
    $family = 'Championnat';
    foreach (['amical' => 'Amical', 'coupe-de-france' => 'Coupe de France', 'coupe-de-la-ligue' => 'Coupe de la Ligue',
        'coupe-deurope' => "Coupe d'Europe", 'barrages' => 'Barrages', 'coupe-charles-drago' => 'Coupe Charles Drago',
        'coupes-diverses' => 'Coupes diverses', 'coupe-dete' => "Coupe d'été"] as $slug => $label) {
        if (in_array($slug, $catSlugs, true)) {
            $family = $label;
            break;
        }
    }
    $round = $t['round'] ?? null;
    if ($family === 'Championnat' && $round && preg_match('/^amical/iu', $round)) {
        $family = 'Amical';
    }
    $code = $t['competition_code'] ?? null;
    $label = competition_label($code, $family, $h['competition_text'] ?? null);

    $home = $t['home'] ?? ['name' => '', 'level' => null];
    $away = $t['away'] ?? ['name' => '', 'level' => null];
    $sochauxHome = (bool) preg_match('/sochaux/iu', $home['name']) || !preg_match('/sochaux/iu', $away['name']);
    $score = $t['score'] ?? null;
    // Score absent du titre (« 19/09/1998 -3-1 ») : repris de la fiche du match.
    if (!$score) {
        foreach ([$h['score_line'] ?? '', $doc['title']] as $src) {
            if (preg_match('/(?::|\d{4}\s*-)\s*(\d+)\s*-\s*(\d+)\s*(.*)$/u', $src, $sm)) {
                $extra = trim($sm[3]);
                $pens = preg_match('/\(?\s*(\d+)\s*-\s*(\d+)\s*(?:t\.?a\.?b|tab|tirs)/iu', $extra, $pm) ? ['home' => (int) $pm[1], 'away' => (int) $pm[2]] : null;
                $score = ['home' => (int) $sm[1], 'away' => (int) $sm[2], 'extra' => $extra ?: null,
                    'aet' => (bool) preg_match('/a\.?\s*p\b|prolong/iu', $extra), 'pens' => $pens];
                break;
            }
        }
    }
    $result = null;
    if ($score) {
        [$us, $them] = $sochauxHome ? [$score['home'], $score['away']] : [$score['away'], $score['home']];
        if ($us !== $them) {
            $result = $us > $them ? 'V' : 'D';
        } elseif (!empty($score['pens'])) {
            [$pu, $pt] = $sochauxHome ? [$score['pens']['home'], $score['pens']['away']] : [$score['pens']['away'], $score['pens']['home']];
            $result = $pu > $pt ? 'V' : 'D';
        } else {
            $result = 'N';
        }
    }

    // Récit : sections reconnues pour la mise en page de la maquette (le HTML reste dans sections).
    $highlights = [];
    $reactions = [];
    $breves = [];
    foreach ($parsed['sections'] as $s) {
        $title = mb_strtolower((string) $s['title']);
        if (preg_match('/r[ée]sum[ée]/u', $title)) {
            $highlights = array_merge($highlights, wp_parse_highlights($s['html']));
        } elseif (preg_match('/r[ée]action/u', $title)) {
            $reactions = array_merge($reactions, wp_parse_reactions($s['html']));
        } elseif (preg_match('/br[èe]ve/u', $title)) {
            $breves = array_merge($breves, wp_parse_items($s['html']));
        }
    }

    $lineup = null;
    $otherLineups = [];
    foreach ($parsed['tables'] as $ti => $tb) {
        if ($tb['kind'] !== 'lineup') {
            continue; // conservé dans « tables »
        }
        $l = [
            'title' => $ti === 0 ? ($parsed['table_title'] ?: 'Composition Sochaux') : 'Composition',
            'headers' => $tb['headers'],
            'rows' => array_map('wp_parse_lineup_row', $tb['rows']),
            'source_table' => $tb['source_id'],
        ];
        if ($lineup === null) {
            $lineup = $l;
        } else {
            $otherLineups[] = $l;
        }
    }

    $goalsText = $h['goals_text'] ?? '';
    return [
        'date' => $date,
        'date_text' => $h['date_text'] ?? null,
        'season' => $season,
        'competition' => $family,
        'competition_label' => $label,
        'competition_code' => $code,
        'round' => $round,
        'round_text' => $h['competition_text'] ?? null,
        'home' => $home,
        'away' => $away,
        'sochaux_home' => $sochauxHome,
        'score' => $score,
        'score_raw' => $t['score_raw'] ?? null,
        'score_line' => $h['score_line'] ?? null,
        'result' => $result,
        'stadium' => $h['stadium'] ?? null,
        'spectators' => $h['spectators'] ?? null,
        'spectators_text' => $h['spectators_text'] ?? null,
        'referee' => $h['referee'] ?? null,
        'goals_text' => $goalsText,
        'goals' => wp_parse_goals($goalsText),
        'header_extra' => $h['extra'] ?? [],
        'highlights' => $highlights,
        'reactions' => $reactions,
        'breves' => $breves,
        'lineup' => $lineup,
        'other_lineups' => $otherLineups,
        'event' => $t['event'] ?? null,
        'opponent_club' => null,
        'stadium_id' => null,
    ];
}

function competition_label(?string $code, string $family, ?string $text): string
{
    $c = mb_strtoupper(trim((string) $code));
    $map = ['L1' => 'Ligue 1', 'L2' => 'Ligue 2', 'D1' => 'Division 1', 'D2' => 'Division 2', 'D3' => 'Division 3',
        'N1' => 'National', 'N' => 'National', 'NAT' => 'National', 'CDF' => 'Coupe de France', 'CDL' => 'Coupe de la Ligue',
        'C1' => 'Coupe des clubs champions', 'C2' => 'Coupe des coupes', 'C3' => 'Coupe UEFA', 'UEFA' => 'Coupe UEFA',
        'CFA' => 'CFA', 'TDC' => 'Trophée des champions'];
    if (isset($map[$c])) {
        return $map[$c];
    }
    if ($c !== '') {
        return trim((string) $code);
    }
    if ($family !== 'Championnat') {
        return $family;
    }
    if ($text && preg_match('/(ligue \d|division \d|national|coupe [\w\' ]+)/iu', $text, $m)) {
        return mb_convert_case($m[1], MB_CASE_TITLE);
    }
    return 'Championnat';
}

function match_slug(array $doc): string
{
    $m = $doc['match'];
    if (!$m['date'] || !$m['home']['name']) {
        return wp_slugify($doc['title']);
    }
    $comp = wp_slugify($m['competition'] === 'Championnat' ? $m['competition_label'] : $m['competition']);
    $d = date('d-m-Y', strtotime($m['date']));
    return wp_slugify($m['home']['name']) . '-' . wp_slugify($m['away']['name']) . ($comp ? "-$comp" : '') . "-$d";
}

function article_base(array $catSlugs, array $catOut): string
{
    foreach ($catSlugs as $s) {
        $root = $s;
        $guard = 0;
        while (($catOut[$root]['parent'] ?? null) !== null && $guard++ < 10) {
            $root = $catOut[$root]['parent'];
        }
        if (in_array($root, ['infrastructures', 'symboles', 'supporters'], true)) {
            return $root;
        }
    }
    return 'articles';
}

function article_title_for_slug(array $doc): string
{
    // Titres longs : on garde l'essentiel (avant « : » ou « ! »).
    $t = $doc['title'];
    if (mb_strlen($t) > 70 && preg_match('/^(.{20,70}?)[:!?]/u', $t, $m)) {
        return $m[1];
    }
    return $t;
}

function person_year(array $doc): ?string
{
    $p = $doc['personne'];
    foreach ([$p['arrival']['iso'] ?? null, $p['birth']['date']['iso'] ?? null, $p['trial']['iso'] ?? null] as $v) {
        if ($v && preg_match('/^(\d{4})/', $v, $m)) {
            return $m[1];
        }
    }
    return null;
}

function build_person(array $doc, array $parsed, array $roles, array $catSlugs, array &$report): array
{
    $hp = wp_parse_person_header($parsed['header']['lines'] ?? []);
    $f = $hp['fields'];
    $name = wp_split_name($doc['title']);
    if (!empty($f['name']) && mb_strlen($f['name']) < 60) {
        $fromHeader = wp_split_name($f['name']);
        if (wp_norm($fromHeader['display']) === wp_norm($name['display'])) {
            $name = $fromHeader; // la fiche d'identité a souvent les accents corrects
        }
    }
    $birth = isset($f['birth_date']) ? ['date' => wp_parse_date($f['birth_date']), 'place' => wp_parse_place($f['birth_place'] ?? null), 'text' => $f['birth_text'] ?? ''] : null;
    $death = isset($f['death_date']) ? ['date' => wp_parse_date($f['death_date']), 'place' => wp_parse_place($f['death_place'] ?? null), 'text' => $f['death_text'] ?? ''] : null;

    $stats = null;
    if (($parsed['table']['kind'] ?? null) === 'stats') {
        $stats = [
            'title' => $parsed['table_title'] ?: 'Statistiques',
            'headers' => $parsed['table']['headers'],
            'rows' => $parsed['table']['rows'],
            'source_table' => $parsed['table']['source_id'],
        ];
    } elseif ($parsed['table']) {
        $stats = ['title' => $parsed['table_title'] ?: '', 'headers' => $parsed['table']['headers'], 'rows' => $parsed['table']['rows'],
            'source_table' => $parsed['table']['source_id'], 'kind' => $parsed['table']['kind']];
        $report['warnings'][] = "Tableau de type {$parsed['table']['kind']} sur une fiche personne : {$doc['title']}";
    }

    $position = $f['position'] ?? null;
    return [
        'roles' => $roles,
        'first_name' => $name['first_name'],
        'last_name' => $name['last_name'],
        'display_name' => $name['display'],
        'nickname' => $f['nickname'] ?? '',
        'subtitle' => $f['subtitle'] ?? '',
        'trial' => isset($f['trial']) ? wp_parse_date($f['trial']) : null,
        'is_trial' => in_array('a-lessai', $catSlugs, true) || isset($f['trial']),
        'formed_at_club' => in_array('formes-au-club-fc-sochaux-retro-fcsm', $catSlugs, true),
        'international_flag' => (bool) array_intersect(['internationaux-fc-sochaux-retro-fcsm', 'internationaux-francais-fc-sochaux-retro-fcsm'], $catSlugs),
        'birth' => $birth,
        'death' => $death,
        'height' => $f['height'] ?? null,
        'height_cm' => wp_cm($f['height'] ?? null),
        'weight' => $f['weight'] ?? null,
        'weight_kg' => wp_kg($f['weight'] ?? null),
        'foot' => $f['foot'] ?? null,
        'position' => $position,
        'line' => wp_position_line($position) ?? (in_array('entraineur', $roles, true) && !in_array('joueur', $roles, true) ? 'E' : null),
        'nationality' => $f['nationality'] ?? '',
        'international' => $f['international'] ?? [],
        'arrival' => isset($f['arrival']) ? wp_parse_date($f['arrival']) : null,
        'departure' => isset($f['departure']) ? wp_parse_date($f['departure']) : null,
        'arrival_coach' => isset($f['arrival_coach']) ? wp_parse_date($f['arrival_coach']) : null,
        'departure_coach' => isset($f['departure_coach']) ? wp_parse_date($f['departure_coach']) : null,
        'first_match' => $f['first_match'] ?? null,
        'last_match' => $f['last_match'] ?? null,
        'first_goal' => $f['first_goal'] ?? null,
        'first_match_coached' => $f['first_match_coached'] ?? null,
        'last_match_coached' => $f['last_match_coached'] ?? null,
        'honours' => $f['honours'] ?? [],
        'then' => $f['then'] ?? [],
        'fiche' => $hp['rows'],
        'stats' => $stats,
        'shirt_numbers' => '',
        'legend' => false,
        'album' => ['in' => false, 'rarity' => null, 'number' => null],
        'on_map' => true,
    ];
}
