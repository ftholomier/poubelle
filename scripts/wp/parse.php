<?php
/**
 * Transforme les pages aspirées (storage/import/html) en contenus JSON (data/).
 *
 * Usage : php scripts/wp/parse.php [fichier.html|fichier.html.gz ...]
 *   Sans argument : traite toutes les pages listées dans storage/import/urls.json.
 *   Avec des fichiers : affiche le JSON obtenu (mise au point), sans rien écrire.
 *
 * Chaque contenu garde, en plus des champs structurés, le HTML d'origine de
 * chaque bloc (« blocks ») : aucune information n'est perdue même si un champ
 * n'a pas été reconnu.
 */

declare(strict_types=1);
require __DIR__ . '/lib.php';

const DATA_DIR = ROOT . '/data';

$media = load_media_index();
$cats = load_categories();

if (count($argv) > 1) {
    foreach (array_slice($argv, 1) as $f) {
        $html = str_ends_with($f, '.gz') ? gzdecode(file_get_contents($f)) : file_get_contents($f);
        echo json_encode(parse_page($html, $media, $cats), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), "\n";
    }
    exit;
}

$urls = read_json(IMPORT_DIR . '/urls.json');
$report = ['errors' => [], 'warnings' => [], 'tables' => []];
$index = [];
foreach ($urls as $i => $u) {
    $file = IMPORT_DIR . '/html/' . md5($u['link']) . '.html.gz';
    if (!is_file($file)) {
        $report['errors'][] = "Page non aspirée : {$u['link']}";
        continue;
    }
    try {
        $doc = parse_page(gzdecode(file_get_contents($file)), $media, $cats);
    } catch (Throwable $e) {
        $report['errors'][] = "{$u['link']} : " . $e->getMessage();
        continue;
    }
    if ($doc === null) {
        $report['warnings'][] = "Page ignorée (pas un article) : {$u['link']}";
        continue;
    }
    foreach ($doc['_warnings'] as $w) {
        $report['warnings'][] = "{$u['link']} : $w";
    }
    unset($doc['_warnings']);
    if (!empty($doc['table']['source_id'])) {
        $report['tables'][$doc['table']['source_id']][] = $doc['path'];
    }
    $dir = DATA_DIR . '/content';
    write_json("$dir/{$doc['slug']}.json", $doc);
    $index[] = $doc['slug'];
    if ($i % 200 === 0) {
        out(sprintf('parse %d/%d', $i + 1, count($urls)));
    }
}

// Un même tableau wpDataTables affiché sur plusieurs fiches est presque toujours une erreur de saisie.
foreach ($report['tables'] as $tid => $paths) {
    if (count($paths) > 1) {
        $report['warnings'][] = "Tableau wpDataTables n°$tid utilisé par plusieurs fiches : " . implode(', ', $paths);
    }
}
unset($report['tables']);
write_json(IMPORT_DIR . '/parse-report.json', $report);
out(sprintf('%d contenus, %d erreurs, %d avertissements', count($index), count($report['errors']), count($report['warnings'])));

// ---------------------------------------------------------------------------

/** @return array<string,array> index des médias par chemin relatif d'upload */
function load_media_index(): array
{
    $idx = [];
    $file = IMPORT_DIR . '/rest/media.json';
    if (!is_file($file)) {
        return $idx;
    }
    foreach (read_json($file) as $m) {
        $rel = preg_replace('#^.*/wp-content/uploads/#', '', $m['source_url']);
        $idx[$rel] = $m;
    }
    return $idx;
}

/** @return array<string,array> catégories par slug */
function load_categories(): array
{
    $out = [];
    foreach (read_json(IMPORT_DIR . '/rest/categories.json') as $c) {
        $out[$c['slug']] = $c;
    }
    return $out;
}

function text(?DOMNode $n): string
{
    if (!$n) {
        return '';
    }
    $t = html_entity_decode($n->textContent, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $t = str_replace('&nbsp', ' ', $t); // entités mal fermées dans les tableaux wpDataTables
    return trim(preg_replace('/[ \t\x{00A0}]+/u', ' ', $t));
}

function inner_html(DOMNode $n): string
{
    $h = '';
    foreach ($n->childNodes as $c) {
        $h .= $n->ownerDocument->saveHTML($c);
    }
    return trim($h);
}

function has_class(DOMElement $e, string $cls): bool
{
    return in_array($cls, preg_split('/\s+/', $e->getAttribute('class')), true);
}

/** Chemin relatif d'upload d'une image, ramené à l'original (sans suffixe -LxH). */
function media_rel(string $url, array $media): ?string
{
    if (!preg_match('#/wp-content/uploads/(.+)$#', $url, $m)) {
        return null;
    }
    $rel = strtok($m[1], '?');
    if (isset($media[$rel])) {
        return $rel;
    }
    $orig = preg_replace('/-\d+x\d+(\.\w+)$/', '$1', $rel);
    if (isset($media[$orig])) {
        return $orig;
    }
    $scaled = preg_replace('/(\.\w+)$/', '-scaled$1', $orig);
    return isset($media[$scaled]) ? $scaled : $orig;
}

/** Lignes de texte d'un bloc (une par paragraphe, titre, élément de liste ou <br>). */
function block_lines(DOMElement $el): array
{
    $html = inner_html($el);
    $html = preg_replace('#<br\s*/?>#i', "\n", $html);
    $html = preg_replace('#</(p|h\d|li|div|tr)>#i', "\n", $html);
    $t = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $lines = [];
    foreach (explode("\n", $t) as $l) {
        $l = trim(preg_replace('/[ \t\x{00A0}]+/u', ' ', $l));
        if ($l !== '' && $l !== '|') {
            $lines[] = $l;
        }
    }
    return $lines;
}

function parse_page(string $html, array $media, array $cats): ?array
{
    $warnings = [];
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
    libxml_clear_errors();
    $xp = new DOMXPath($dom);

    $body = $xp->query('//body')->item(0);
    $bodyClass = $body ? $body->getAttribute('class') : '';
    $isPost = preg_match('/\bpostid-(\d+)/', $bodyClass, $pm);
    $isPage = preg_match('/\bpage-id-(\d+)/', $bodyClass, $pgm);
    if (!$isPost && !$isPage) {
        return null;
    }

    $meta = function (string $prop) use ($xp): ?string {
        $n = $xp->query("//meta[@property='$prop' or @name='$prop']")->item(0);
        return $n instanceof DOMElement ? html_entity_decode($n->getAttribute('content'), ENT_QUOTES, 'UTF-8') : null;
    };
    $url = $meta('og:url') ?? '';
    $path = parse_url($url, PHP_URL_PATH) ?: '/';
    $slug = basename(rtrim($path, '/')) ?: 'accueil';

    $doc = [
        'id' => (int) ($isPost ? $pm[1] : $pgm[1]),
        'kind' => $isPost ? 'post' : 'page',
        'type' => 'article',
        'slug' => $slug,
        'path' => $path,
        'title' => '',
        'date' => $meta('article:published_time'),
        'modified' => $meta('article:modified_time'),
        'categories' => [],
        'featured_image' => null,
        'seo' => [
            'title' => html_entity_decode((string) ($xp->query('//title')->item(0)?->textContent ?? ''), ENT_QUOTES, 'UTF-8'),
            'description' => $meta('description') ?? $meta('og:description'),
        ],
        'header' => null,
        'sections' => [],
        'key_figure' => null,
        'gallery' => [],
        'videos' => [],
        'table' => null,
        'blocks' => [],
    ];

    // Catégories : classes de l'élément principal (et non des articles liés).
    $main = $xp->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' post-{$doc['id']} ') and not(contains(@class,'post-related'))]")->item(0);
    if ($main instanceof DOMElement) {
        preg_match_all('/\bcategory-([\w-]+)/', $main->getAttribute('class'), $cm);
        $doc['categories'] = array_values(array_unique($cm[1]));
    }
    foreach ($doc['categories'] as $c) {
        if (!isset($cats[$c])) {
            $warnings[] = "catégorie inconnue « $c »";
        }
    }

    // Titre : og:title de l'article (le premier, sans le suffixe du site).
    $t = $xp->query("//meta[@property='og:title']")->item(0);
    $doc['title'] = $t instanceof DOMElement ? html_entity_decode($t->getAttribute('content'), ENT_QUOTES, 'UTF-8') : $slug;
    $img = $meta('og:image');
    if ($img) {
        $doc['featured_image'] = media_rel($img, $media);
    }

    $doc['type'] = detect_type($doc['categories']);

    $builder = $xp->query("//div[contains(@class,'mfn-builder-content') and contains(@class,'mfn-default-content-buider')]")->item(0);
    if (!$builder) {
        $content = $xp->query("//*[@id='Content']")->item(0);
        $builder = $content;
        $warnings[] = 'contenu hors constructeur BeTheme';
    }
    if (!$builder) {
        $doc['_warnings'] = $warnings;
        return $doc;
    }

    // Parcours des modules dans l'ordre d'affichage.
    $cols = $xp->query(".//div[contains(concat(' ', normalize-space(@class), ' '), ' mcb-column ')]", $builder);
    $items = [];
    foreach ($cols as $col) {
        /** @var DOMElement $col */
        if (!preg_match('/\bcolumn_(\w+)/', $col->getAttribute('class'), $tm)) {
            continue;
        }
        $type = $tm[1];
        if (in_array($type, ['attr', 'header_logo', 'header_menu', 'header_icon', 'header_search', 'header_burger', 'livesearch', 'sidemenu_menu'], true)) {
            continue;
        }
        $inner = $xp->query(".//div[contains(@class,'mcb-column-inner')]", $col)->item(0) ?? $col;
        $items[] = ['type' => $type, 'el' => $inner];
    }

    $section = null;
    $headerDone = false;
    foreach ($items as $k => $it) {
        $el = $it['el'];
        $block = ['type' => $it['type'], 'html' => inner_html($el)];
        $doc['blocks'][] = $block;

        switch ($it['type']) {
            case 'heading':
                $title = text($el);
                if (!$headerDone && preg_match("/^Fiche (d.identit|du match)/iu", $title)) {
                    // Le bloc suivant contient la fiche d'identité / fiche du match.
                    $section = ['title' => $title, '_header' => true, 'html' => ''];
                    break;
                }
                flush_section($doc, $section);
                $section = ['title' => $title, 'html' => ''];
                break;

            case 'column':
                $attr = $xp->query(".//div[contains(@class,'column_attr')]", $el)->item(0) ?? $el;
                $tableEl = $xp->query('.//table', $attr)->item(0);
                if ($tableEl instanceof DOMElement) {
                    $doc['table'] = parse_table($tableEl, $doc['type']);
                    break;
                }
                $lines = block_lines($attr);
                if ($section !== null && !empty($section['_header'])) {
                    $doc['header'] = parse_header($lines, inner_html($attr), $doc['type']);
                    $headerDone = true;
                    $section = null;
                    break;
                }
                if (!$headerDone && $doc['header'] === null && in_array($doc['type'], ['dirigeant', 'personnage', 'article'], true) && $k <= 2) {
                    $doc['header'] = parse_header($lines, inner_html($attr), $doc['type']);
                    $headerDone = true;
                    break;
                }
                // Intertitre posé dans un bloc texte (« Sa carrière au FCSM », « Statistiques »).
                $next = $items[$k + 1]['type'] ?? null;
                if (count($lines) === 1 && mb_strlen($lines[0]) <= 60 && !preg_match('/[.!?]$/u', $lines[0]) && $next !== 'heading') {
                    flush_section($doc, $section);
                    $section = ['title' => $lines[0], 'html' => ''];
                    break;
                }
                if (!$lines && !$xp->query('.//img|.//iframe', $attr)->length) {
                    break; // bloc vide
                }
                $section ??= ['title' => null, 'html' => ''];
                $section['html'] .= ($section['html'] ? "\n" : '') . clean_html(inner_html($attr));
                break;

            case 'counter':
                $num = text($xp->query(".//*[contains(@class,'number')]", $el)->item(0));
                $lines = block_lines($el);
                $doc['key_figure'] = [
                    'number' => $num !== '' ? $num : ($lines[0] ?? ''),
                    'text' => implode(' ', array_slice($lines, 1)),
                ];
                break;

            case 'image_gallery':
                foreach ($xp->query(".//*[contains(concat(' ', normalize-space(@class), ' '), ' gallery-item ')]", $el) as $gi) {
                    $a = $xp->query('.//a', $gi)->item(0);
                    $im = $xp->query('.//img', $gi)->item(0);
                    $src = $a instanceof DOMElement && $a->getAttribute('href') ? $a->getAttribute('href') : ($im instanceof DOMElement ? $im->getAttribute('src') : '');
                    $cap = text($xp->query(".//*[contains(@class,'gallery-caption') or self::figcaption]", $gi)->item(0));
                    $rel = media_rel($src, $media);
                    if ($rel === null) {
                        $warnings[] = "image de galerie hors médiathèque : $src";
                        continue;
                    }
                    $doc['gallery'][] = ['image' => $rel, 'caption' => $cap];
                }
                break;

            case 'image':
                $im = $xp->query('.//img', $el)->item(0);
                if ($im instanceof DOMElement) {
                    $rel = media_rel($im->getAttribute('src'), $media);
                    if ($rel) {
                        $doc['images'][] = ['image' => $rel, 'caption' => text($el)];
                    }
                }
                break;

            case 'video':
                foreach ($xp->query('.//iframe', $el) as $if) {
                    $src = $if->getAttribute('src');
                    if (preg_match('#youtube(?:-nocookie)?\.com/embed/+([\w-]{6,})#', $src, $vm)) {
                        $doc['videos'][] = ['provider' => 'youtube', 'id' => $vm[1]];
                    } elseif ($src) {
                        $doc['videos'][] = ['provider' => 'iframe', 'url' => $src];
                    }
                }
                foreach ($xp->query('.//video//source|.//video[@src]', $el) as $v) {
                    $doc['videos'][] = ['provider' => 'file', 'url' => $v->getAttribute('src')];
                }
                break;

            case 'icon_box_2':
                // Intitulé du tableau (« Composition Sochaux »).
                $doc['table_title'] = text($el);
                break;

            default:
                $warnings[] = "module non traité : {$it['type']}";
        }
    }
    flush_section($doc, $section);

    if ($doc['type'] === 'match') {
        $doc['match'] = parse_match_title($doc['title']);
        if ($doc['match'] === null) {
            $warnings[] = 'titre de match non reconnu';
        }
        $doc['highlights'] = parse_highlights($doc['sections']);
    }
    if (!$doc['featured_image']) {
        $warnings[] = 'pas d\'image à la une';
    }
    $doc['_warnings'] = $warnings;
    return $doc;
}

function flush_section(array &$doc, ?array &$section): void
{
    if ($section !== null && empty($section['_header']) && ($section['html'] !== '' || $section['title'] !== null)) {
        $doc['sections'][] = ['title' => $section['title'], 'html' => $section['html']];
    }
    $section = null;
}

/** Nettoie le HTML d'un bloc : garde la structure éditoriale, enlève styles et classes. */
function clean_html(string $html): string
{
    $html = preg_replace('/\s(style|class|data-[\w-]+|id)="[^"]*"/i', '', $html);
    $html = preg_replace('#<(span|font)>(.*?)</\1>#is', '$2', $html);
    $html = preg_replace('#<p>\s*(&nbsp;|\x{00A0})?\s*</p>#u', '', $html);
    return trim(preg_replace("/\n{2,}/", "\n", $html));
}

function detect_type(array $cats): string
{
    $map = [
        'matchs-fc-sochaux-retro-fcsm' => 'match',
        'joueurs-fc-sochaux-retro-fcsm' => 'joueur',
        'entraineurs-fc-sochaux-retro-fcsm' => 'entraineur',
        'dirigeants-fc-sochaux-retro-fcsm' => 'dirigeant',
        'personnages-emblematiques-fc-sochaux-retro-fcsm' => 'personnage',
    ];
    foreach ($map as $slug => $type) {
        if (in_array($slug, $cats, true)) {
            return $type;
        }
    }
    // Sous-rubriques sans la rubrique parente cochée.
    $sub = [
        'match' => ['amical', 'coupe-de-france', 'coupe-de-la-ligue', 'coupe-deurope', 'barrages', 'coupes-diverses', 'coupe-charles-drago', 'coupe-dete'],
        'joueur' => ['a-lessai', 'formes-au-club-fc-sochaux-retro-fcsm', 'internationaux-fc-sochaux-retro-fcsm', 'internationaux-francais-fc-sochaux-retro-fcsm'],
        'entraineur' => ['entraineurs-adjoints', 'entraineurs-principaux'],
        'dirigeant' => ['presidents', 'presidents-dhonneur', 'presidents-executifs', 'directeurs-sportifs', 'administratifs', 'directeurs-du-centre-de-formation'],
    ];
    foreach ($sub as $type => $slugs) {
        if (array_intersect($slugs, $cats)) {
            return $type;
        }
    }
    foreach ($cats as $c) {
        if (preg_match('/^annees-|^\d{4}-?\d{4}$|^\d{4}-\d{4}$/', $c)) {
            return 'match';
        }
    }
    return 'article';
}

/**
 * Fiche d'identité / fiche du match : lignes « Libellé : valeur » conservées dans
 * l'ordre, plus les champs reconnus pour les masques de saisie.
 */
function parse_header(array $lines, string $html, string $type): array
{
    $h = ['lines' => [], 'fields' => [], 'html' => clean_html($html)];
    $listKey = null;
    foreach ($lines as $i => $line) {
        if (preg_match('/^([^:]{2,60}?)\s*:\s*(.*)$/u', $line, $m) && !preg_match('/^\d/', $m[1]) && !($type === 'match' && $i === 0)) {
            $label = trim($m[1]);
            $value = trim($m[2]);
            $h['lines'][] = ['label' => $label, 'value' => $value];
            $listKey = $value === '' ? $label : null;
        } else {
            $h['lines'][] = ['label' => null, 'value' => $line];
            if ($listKey !== null) {
                $h['fields']['lists'][$listKey][] = $line;
                continue;
            }
        }
    }

    $f = &$h['fields'];
    if ($type === 'match') {
        $f['score_line'] = $lines[0] ?? null;
        foreach (array_slice($lines, 1) as $line) {
            if (preg_match('/^(Lundi|Mardi|Mercredi|Jeudi|Vendredi|Samedi|Dimanche)\b/iu', $line)) {
                $f['date_text'] = $line;
            } elseif (preg_match('/spectateurs?/iu', $line)) {
                $f['spectators'] = (int) preg_replace('/\D/', '', $line);
            } elseif (preg_match('/^(Arbitre|Arbitrage)\s*:\s*(.+)$/iu', $line, $m)) {
                $f['referee'] = $m[2];
            } elseif (preg_match('/^Buts?\s*:\s*(.+)$/iu', $line, $m)) {
                $f['goals_text'] = $m[1];
            } elseif (preg_match('/^(Stade|Parc|Stadium)\b/iu', $line)) {
                $f['stadium'] = $line;
            } elseif (!isset($f['competition_text'])) {
                $f['competition_text'] = $line;
            } elseif (!isset($f['stadium'])) {
                $f['stadium'] = $line;
            }
        }
        return $h;
    }

    $f['name'] = $lines[0] ?? null;
    // Sous-titre libre (« Président de 1960 à 1974 », « Jardinier du club de 1959 à 1997. »).
    if (isset($h['lines'][1]) && $h['lines'][1]['label'] === null
        && !preg_match('/^(n[ée]e? le|d[ée]c[ée]d|International)/iu', $h['lines'][1]['value'])) {
        $f['subtitle'] = $h['lines'][1]['value'];
    }
    foreach ($h['lines'] as $l) {
        $label = $l['label'] !== null ? mb_strtolower($l['label']) : null;
        $v = $l['value'];
        if ($label === null) {
            if (preg_match('/^n[ée]e? le\s+(.+?)(?:\s+à\s+(.+))?$/iu', $v, $m)) {
                $f['birth_date'] = $m[1];
                $f['birth_place'] = $m[2] ?? null;
            } elseif (preg_match('/^d[ée]c[ée]d[ée]e? le\s+(.+?)(?:\s+à\s+(.+))?$/iu', $v, $m)) {
                $f['death_date'] = $m[1];
                $f['death_place'] = $m[2] ?? null;
            } elseif (preg_match('/^International/iu', $v)) {
                $f['international'][] = $v;
            }
            continue;
        }
        $key = match (true) {
            $label === 'taille' => 'height',
            $label === 'poids' => 'weight',
            $label === 'pied' => 'foot',
            $label === 'poste' => 'position',
            str_starts_with($label, 'arrivée') => 'arrival',
            str_starts_with($label, 'départ') => 'departure',
            str_starts_with($label, 'premier match entraîné') => 'first_match_coached',
            str_starts_with($label, 'dernier match entraîné') => 'last_match_coached',
            str_starts_with($label, 'premier match') => 'first_match',
            str_starts_with($label, 'dernier match') => 'last_match',
            str_starts_with($label, 'premier but') => 'first_goal',
            str_starts_with($label, 'a l\'essai') || str_starts_with($label, 'à l\'essai') => 'trial',
            str_starts_with($label, 'palmarès') => 'honours',
            $label === 'puis' => 'then',
            default => null,
        };
        if ($key === null || $key === 'honours') {
            continue;
        }
        if ($key === 'then') {
            $f['then'][] = $v;
        } elseif (!isset($f[$key])) {
            $f[$key] = $v;
        } else {
            // Double carrière (joueur puis entraîneur) : on garde les occurrences suivantes.
            $f[$key . '_2'] = $v;
        }
    }
    foreach ($f['lists'] ?? [] as $lbl => $items) {
        if (str_starts_with(mb_strtolower($lbl), 'palmarès')) {
            $f['honours'] = $items;
        }
    }
    return $h;
}

/** Tableau wpDataTables : en-têtes + lignes, et lecture structurée des compositions. */
function parse_table(DOMElement $t, string $type): array
{
    $rows = [];
    $headers = [];
    foreach ($t->getElementsByTagName('tr') as $tr) {
        $cells = [];
        foreach ($tr->childNodes as $c) {
            if ($c instanceof DOMElement && in_array($c->tagName, ['td', 'th'], true)) {
                $cells[] = text($c);
            }
        }
        if (!$cells) {
            continue;
        }
        if (!$headers) {
            $headers = $cells;
        } else {
            $rows[] = $cells;
        }
    }
    preg_match('/wpdtSimpleTable-(\d+)/', $t->getAttribute('id'), $m);
    $table = ['source_id' => isset($m[1]) ? (int) $m[1] : null, 'headers' => $headers, 'rows' => $rows];

    $h0 = mb_strtolower($headers[0] ?? '');
    if (str_starts_with($h0, 'poste')) {
        $table['kind'] = 'lineup';
        $table['lineup'] = array_map('parse_lineup_row', $rows);
    } elseif (str_starts_with($h0, 'saison')) {
        $table['kind'] = 'stats';
    } else {
        $table['kind'] = 'other';
    }
    return $table;
}

function parse_lineup_row(array $r): array
{
    $name = $r[1] ?? '';
    $captain = (bool) preg_match('/\(c\)/iu', $name);
    $minutes = fn (string $s): array => preg_match_all("/(\d+)\s*'(?:\s*\+\s*(\d+))?/u", $s, $mm, PREG_SET_ORDER)
        ? array_map(fn ($x) => isset($x[2]) && $x[2] !== '' ? "{$x[1]}+{$x[2]}" : $x[1], $mm) : [];
    $sub = $r[3] ?? '';
    $cards = $r[4] ?? '';
    return [
        'position' => $r[0] ?? '',
        'name' => trim(preg_replace('/\(c\)/iu', '', $name)),
        'captain' => $captain,
        'goals' => $minutes($r[2] ?? ''),
        'sub_in' => preg_match('/↑|entr[ée]e/iu', $sub) ? ($minutes($sub)[0] ?? '') : null,
        'sub_out' => preg_match('/↓|sortie/iu', $sub) ? ($minutes($sub)[0] ?? '') : null,
        'yellow' => preg_match('/🟨|\bJ\b|jaune/u', $cards) ? $minutes($cards) : [],
        'red' => preg_match('/🟥|\bR\b|rouge/u', $cards) ? $minutes($cards) : [],
        'raw' => $r,
    ];
}

/**
 * Titre de match : « J31 – Sochaux / Pau – L2 – 15/04/2023 – 2-3 »,
 * « Amical – Sochaux (D1) / Young Boys de Berne (D1 SUI) – 29/07/1970 – 1-0 »,
 * « 8è tour – Thaon-les-Vosges (N3) / Sochaux (L2) – CDF – 19/11/2022 – 2-2 (3-1 tab) ».
 */
function parse_match_title(string $title): ?array
{
    $parts = array_map('trim', preg_split('/\s+[–—-]\s+/u', html_entity_decode($title, ENT_QUOTES, 'UTF-8')));
    $date = null;
    $di = null;
    foreach ($parts as $i => $p) {
        if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $p, $m)) {
            $date = sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
            $di = $i;
        }
    }
    $teamsIdx = null;
    foreach ($parts as $i => $p) {
        if (str_contains($p, ' / ') || preg_match('#\S/\S#u', $p) && !preg_match('#\d/\d#', $p)) {
            $teamsIdx = $i;
            break;
        }
    }
    if ($date === null || $teamsIdx === null) {
        return null;
    }
    [$home, $away] = array_map('trim', explode('/', $parts[$teamsIdx], 2)) + [1 => ''];
    $team = function (string $s): array {
        return preg_match('/^(.*?)\s*\(([^)]+)\)\s*$/u', $s, $m)
            ? ['name' => trim($m[1]), 'level' => $m[2]] : ['name' => trim($s), 'level' => null];
    };
    $scoreRaw = implode(' – ', array_slice($parts, $di + 1));
    preg_match('/^(\d+)\s*-\s*(\d+)\s*(.*)$/u', $scoreRaw, $sm);
    return [
        'round' => $teamsIdx > 0 ? $parts[0] : null,
        'home' => $team($home),
        'away' => $team($away),
        'competition' => implode(' – ', array_slice($parts, $teamsIdx + 1, $di - $teamsIdx - 1)) ?: null,
        'date' => $date,
        'score' => $sm ? ['home' => (int) $sm[1], 'away' => (int) $sm[2], 'extra' => trim($sm[3]) ?: null] : null,
        'score_raw' => $scoreRaw,
    ];
}

/** Temps forts « 19' : texte » de la section Résumé, avec repérage des buts (score entre parenthèses). */
function parse_highlights(array $sections): array
{
    $out = [];
    foreach ($sections as $s) {
        if (!$s['title'] || !preg_match('/r[ée]sum[ée]/iu', $s['title'])) {
            continue;
        }
        $html = preg_replace('#</(p|li)>|<br\s*/?>#i', "\n", $s['html']);
        foreach (explode("\n", html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8')) as $l) {
            $l = trim($l);
            if (preg_match("/^(\d{1,3}(?:\s*'\s*\+\s*\d+|\s*\+\s*\d+)?)\s*'?\s*:\s*(.+)$/u", $l, $m)) {
                $text = $m[2];
                $out[] = [
                    'minute' => preg_replace('/\s+/', '', str_replace("'", '', $m[1])),
                    'text' => $text,
                    'goal' => (bool) preg_match('/\(\d+\s*-\s*\d+\)/u', $text),
                ];
            }
        }
    }
    return $out;
}
