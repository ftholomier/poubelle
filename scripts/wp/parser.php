<?php
/**
 * Bibliothèque d'analyse des pages du site WordPress (thème BeTheme).
 *
 * Transforme le HTML rendu d'un article en données structurées, en gardant
 * systématiquement le HTML d'origine de chaque bloc (aucune perte).
 */

declare(strict_types=1);

// ---------------------------------------------------------------------------
// Outils texte
// ---------------------------------------------------------------------------

function wp_text(?DOMNode $n): string
{
    if (!$n) {
        return '';
    }
    $t = html_entity_decode($n->textContent, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $t = str_replace('&nbsp', ' ', $t); // entités mal fermées (wpDataTables)
    return trim(preg_replace('/[\s\x{00A0}]+/u', ' ', $t));
}

function wp_inner_html(DOMNode $n): string
{
    $h = '';
    foreach ($n->childNodes as $c) {
        $h .= $n->ownerDocument->saveHTML($c);
    }
    return trim($h);
}

/** Lignes de texte d'un bloc (paragraphes, titres, éléments de liste, <br>). */
function wp_lines(string $html): array
{
    $html = preg_replace('#<br\s*/?>#i', "\n", $html);
    $html = preg_replace('#</(p|h\d|li|div|tr|dd|dt)>#i', "\n", $html);
    $t = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $lines = [];
    foreach (explode("\n", $t) as $l) {
        $l = trim(preg_replace('/[ \t\x{00A0}]+/u', ' ', str_replace('&nbsp', ' ', $l)));
        if ($l !== '' && $l !== '|') {
            $lines[] = $l;
        }
    }
    return $lines;
}

/** HTML éditorial propre : structure conservée, styles et classes du constructeur retirés. */
function wp_clean_html(string $html): string
{
    $html = preg_replace('#<(script|style)\b.*?</\1>#is', '', $html);
    $html = preg_replace('/\s(style|class|data-[\w-]+|id|dir|align|width|height|srcset|sizes|decoding|loading|tabindex)="[^"]*"/i', '', $html);
    $html = preg_replace("/\s(style|class|data-[\w-]+|id)='[^']*'/i", '', $html);
    // <span> et <font> sans attribut n'apportent rien
    for ($i = 0; $i < 3; $i++) {
        $html = preg_replace('#<(span|font)>(.*?)</\1>#is', '$2', $html);
    }
    $html = preg_replace('#<p>\s*(&nbsp;|\x{00A0}|<br\s*/?>)*\s*</p>#u', '', $html);
    $html = preg_replace('#<(strong|b|em|i)>\s*</\1>#', '', $html);
    $html = str_replace(['https://www.fcsochauxretro.com/', 'http://www.fcsochauxretro.com/'], '/', $html);
    return trim(preg_replace("/\n{2,}/", "\n", $html));
}

function wp_slugify(string $s): string
{
    $s = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $s = transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $s) ?: strtolower($s);
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    return trim($s, '-');
}

function wp_norm(string $s): string
{
    $s = transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $s) ?: strtolower($s);
    return trim(preg_replace('/[^a-z0-9]+/', ' ', $s));
}

const WP_MONTHS = ['janvier' => 1, 'fevrier' => 2, 'février' => 2, 'mars' => 3, 'avril' => 4, 'mai' => 5, 'juin' => 6,
    'juillet' => 7, 'aout' => 8, 'août' => 8, 'septembre' => 9, 'octobre' => 10, 'novembre' => 11, 'decembre' => 12, 'décembre' => 12];

/**
 * Date française libre → {iso, precision, text}. « 12 avril 1982 », « 1er mai 1950 »,
 * « juillet 2001 », « 1969 », « 12/04/1982 ». Le texte d'origine est toujours conservé.
 */
function wp_parse_date(?string $text): ?array
{
    if ($text === null || trim($text) === '') {
        return null;
    }
    $t = trim($text);
    $low = mb_strtolower($t);
    if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})#', $low, $m) && checkdate((int) $m[2], (int) $m[1], (int) $m[3])) {
        return ['iso' => sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]), 'precision' => 'day', 'text' => $t];
    }
    if (preg_match('/(\d{1,2})(?:er|ère)?\s+([a-zéû]+)\s+(\d{4})/u', $low, $m) && isset(WP_MONTHS[$m[2]])) {
        $mo = WP_MONTHS[$m[2]];
        if (checkdate($mo, (int) $m[1], (int) $m[3])) {
            return ['iso' => sprintf('%04d-%02d-%02d', $m[3], $mo, $m[1]), 'precision' => 'day', 'text' => $t];
        }
    }
    if (preg_match('/([a-zéû]+)\s+(\d{4})/u', $low, $m) && isset(WP_MONTHS[$m[1]])) {
        return ['iso' => sprintf('%04d-%02d', $m[2], WP_MONTHS[$m[1]]), 'precision' => 'month', 'text' => $t];
    }
    if (preg_match('/\b(1[89]\d\d|20\d\d)\b/', $low, $m)) {
        return ['iso' => $m[1], 'precision' => 'year', 'text' => $t];
    }
    return ['iso' => null, 'precision' => null, 'text' => $t];
}

/** « Taza (Maroc) », « Mantes-la-Jolie (78) », « Soultz » → ville, précision (pays ou département). */
function wp_parse_place(?string $text): ?array
{
    if ($text === null || trim($text) === '') {
        return null;
    }
    $t = trim($text, " .\t");
    $city = $t;
    $extra = null;
    if (preg_match('/^(.*?)\s*\(([^)]+)\)\s*$/u', $t, $m)) {
        $city = trim($m[1]);
        $extra = trim($m[2]);
    }
    $country = null;
    $dept = null;
    if ($extra !== null) {
        if (preg_match('/^\d[\dAB]?$|^\d{2,3}$|^2[AB]$/i', $extra)) {
            $dept = $extra;
            $country = 'France';
        } else {
            $country = $extra;
        }
    }
    return ['text' => $t, 'city' => $city, 'department' => $dept, 'country' => $country, 'lat' => null, 'lng' => null];
}

// ---------------------------------------------------------------------------
// Médias
// ---------------------------------------------------------------------------

/** Chemin relatif d'upload d'une image, ramené à l'original (sans suffixe -LxH). */
function wp_media_rel(string $url, array $media): ?string
{
    if (!preg_match('#/wp-content/uploads/(.+)$#', $url, $m)) {
        return null;
    }
    $rel = strtok($m[1], '?#');
    if (isset($media[$rel])) {
        return $rel;
    }
    $orig = preg_replace('/-\d+x\d+(\.\w+)$/', '$1', $rel);
    if (isset($media[$orig])) {
        return $orig;
    }
    $scaled = preg_replace('/(\.\w+)$/', '-scaled$1', $orig);
    if (isset($media[$scaled])) {
        return $scaled;
    }
    return $orig;
}

/**
 * Sépare légende et crédit : « Jaouad Zaïri – L’est républicain » → légende + crédit.
 * Le texte d'origine reste dans « caption_raw ».
 */
function wp_split_caption(string $raw): array
{
    $raw = trim(preg_replace('/\s+/u', ' ', $raw));
    $out = ['caption' => $raw, 'credit' => '', 'caption_raw' => $raw];
    if ($raw === '') {
        return $out;
    }
    if (preg_match('/^(.*\S)\s+[–—-]\s+(photo\s*:?\s*)?([^–—]{2,80})$/iu', $raw, $m)) {
        $credit = trim($m[3]);
        $creditish = preg_match('/r[ée]publicain|photo|fcsm|\bdr\b|afp|panini|football|onze|archives?|l.?[ée]quipe|alsace|presse|collection|est r|vadam|lemontey|desprez|sprint|magazine|journal|getty|icon|maxppp|ouest|progr[eè]s|fc sochaux|club|\bmus[ée]e|famille|perso/iu', $credit);
        if ($creditish || mb_strlen($credit) <= 40) {
            $out['caption'] = trim($m[1]);
            $out['credit'] = $credit;
        }
    } elseif (preg_match('/^(.*?)\s*(?:photo|crédit)\s*:\s*(.+)$/iu', $raw, $m)) {
        $out['caption'] = trim($m[1], " –-");
        $out['credit'] = trim($m[2]);
    }
    return $out;
}

// ---------------------------------------------------------------------------
// Classement
// ---------------------------------------------------------------------------

const WP_TYPE_BY_CATEGORY = [
    'matchs-fc-sochaux-retro-fcsm' => 'match',
    'joueurs-fc-sochaux-retro-fcsm' => 'joueur',
    'entraineurs-fc-sochaux-retro-fcsm' => 'entraineur',
    'dirigeants-fc-sochaux-retro-fcsm' => 'dirigeant',
    'personnages-emblematiques-fc-sochaux-retro-fcsm' => 'personnage',
];

/**
 * @param array<string,array> $cats catégories par slug (avec 'parent_slug')
 * @return array{kind:string, roles:list<string>}
 */
function wp_classify(array $catSlugs, array $cats): array
{
    $roles = [];
    $isMatch = false;
    foreach ($catSlugs as $slug) {
        // On remonte l'arborescence : une sous-rubrique suffit à classer.
        $s = $slug;
        $guard = 0;
        while ($s !== null && $guard++ < 10) {
            if (isset(WP_TYPE_BY_CATEGORY[$s])) {
                $t = WP_TYPE_BY_CATEGORY[$s];
                if ($t === 'match') {
                    $isMatch = true;
                } elseif (!in_array($t, $roles, true)) {
                    $roles[] = $t;
                }
            }
            $s = $cats[$s]['parent_slug'] ?? null;
        }
    }
    if ($isMatch && !$roles) {
        return ['kind' => 'match', 'roles' => []];
    }
    if ($roles) {
        // Ordre de priorité pour le rôle principal (adresse de la fiche).
        $prio = ['joueur', 'entraineur', 'dirigeant', 'personnage'];
        usort($roles, fn ($a, $b) => array_search($a, $prio, true) <=> array_search($b, $prio, true));
        return ['kind' => 'personne', 'roles' => $roles];
    }
    return ['kind' => $isMatch ? 'match' : 'article', 'roles' => []];
}

/** Poste détaillé → ligne (G, D, M, A) pour les filtres et le terrain. */
function wp_position_line(?string $pos): ?string
{
    if (!$pos) {
        return null;
    }
    $p = wp_norm($pos);
    return match (true) {
        (bool) preg_match('/gardien|goal/', $p) => 'G',
        (bool) preg_match('/attaqu|ailier|avant|buteur|pointe/', $p) => 'A',
        (bool) preg_match('/milieu|meneur|demi|relayeur|recuperateur|inter/', $p) => 'M',
        (bool) preg_match('/defens|arriere|lateral|libero|stoppeur|central|charniere/', $p) => 'D',
        (bool) preg_match('/entraineur/', $p) => 'E',
        default => null,
    };
}

// ---------------------------------------------------------------------------
// Analyse d'une page
// ---------------------------------------------------------------------------

/**
 * @return array|null null si la page n'est ni un article ni une page.
 */
function wp_parse_page(string $html, array $media): ?array
{
    $warnings = [];
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
    libxml_clear_errors();
    $xp = new DOMXPath($dom);

    $body = $xp->query('//body')->item(0);
    $bodyClass = $body instanceof DOMElement ? $body->getAttribute('class') : '';
    $isPost = (bool) preg_match('/\bpostid-(\d+)/', $bodyClass, $pm);
    $isPage = (bool) preg_match('/\bpage-id-(\d+)/', $bodyClass, $pgm);
    if (!$isPost && !$isPage) {
        return null;
    }
    $id = (int) ($isPost ? $pm[1] : $pgm[1]);

    $out = [
        'id' => $id,
        'kind' => $isPost ? 'post' : 'page',
        'header' => null,
        'sections' => [],
        'key_figure' => null,
        'gallery' => [],
        'images' => [],
        'videos' => [],
        'table' => null,
        'tables' => [],
        'table_title' => null,
        'embeds' => [],
        'blocks' => [],
        'warnings' => [],
    ];

    $builder = $xp->query("//div[contains(@class,'mfn-builder-content') and contains(@class,'mfn-default-content-buider')]")->item(0);
    if (!$builder) {
        $builder = $xp->query("//*[@id='Content']")->item(0);
        $warnings[] = 'contenu hors constructeur BeTheme';
    }
    if (!$builder) {
        $out['warnings'] = $warnings;
        return $out;
    }

    $skip = ['attr', 'header_logo', 'header_menu', 'header_icon', 'header_search', 'header_burger', 'livesearch', 'sidemenu_menu', 'header_promo', 'header_button'];
    $items = [];
    foreach ($xp->query(".//div[contains(concat(' ', normalize-space(@class), ' '), ' mcb-column ')]", $builder) as $col) {
        /** @var DOMElement $col */
        if (!preg_match('/\bcolumn_(\w+)/', $col->getAttribute('class'), $tm) || in_array($tm[1], $skip, true)) {
            continue;
        }
        $inner = $xp->query(".//div[contains(@class,'mcb-column-inner')]", $col)->item(0) ?? $col;
        $items[] = ['type' => $tm[1], 'el' => $inner];
    }

    $section = null;
    $headerDone = false;
    $flush = function () use (&$section, &$out) {
        if ($section !== null && empty($section['_header']) && ($section['html'] !== '' || $section['title'] !== null)) {
            $out['sections'][] = ['title' => $section['title'], 'html' => $section['html']];
        }
        $section = null;
    };

    foreach ($items as $k => $it) {
        $el = $it['el'];
        $out['blocks'][] = ['type' => $it['type'], 'html' => wp_inner_html($el)];

        switch ($it['type']) {
            case 'heading':
                $title = wp_text($el);
                if (!$headerDone && preg_match("/^Fiche (d.identit|du match)/iu", $title)) {
                    $section = ['title' => $title, '_header' => true, 'html' => ''];
                    break;
                }
                $flush();
                $section = ['title' => $title, 'html' => ''];
                break;

            case 'column':
                $attr = $xp->query(".//div[contains(@class,'column_attr')]", $el)->item(0) ?? $el;
                $tableEl = $xp->query('.//table', $attr)->item(0);
                if ($tableEl instanceof DOMElement) {
                    foreach ($xp->query('.//table', $attr) as $tb) {
                        $out['tables'][] = wp_parse_table($tb);
                    }
                    $out['table'] ??= $out['tables'][0];
                    // Un intitulé (« Statistiques ») resté seul devient le titre du tableau.
                    if ($section !== null && $section['html'] === '' && $section['title']) {
                        $out['table_title'] ??= $section['title'];
                        $section = null;
                    }
                    break;
                }
                $inner = wp_inner_html($attr);
                $lines = wp_lines($inner);
                if ($section !== null && !empty($section['_header'])) {
                    $out['header'] = ['lines' => $lines, 'html' => wp_clean_html($inner)];
                    $headerDone = true;
                    $section = null;
                    break;
                }
                // Pages sans titre « Fiche… » : le premier bloc texte court sert d'en-tête.
                if (!$headerDone && $out['header'] === null && !$out['sections'] && $k <= 1 && count($lines) <= 4) {
                    $out['header'] = ['lines' => $lines, 'html' => wp_clean_html($inner)];
                    $headerDone = true;
                    break;
                }
                $next = $items[$k + 1]['type'] ?? null;
                if (count($lines) === 1 && mb_strlen($lines[0]) <= 60 && !preg_match('/[.!?:»]$/u', $lines[0])
                    && $next !== 'heading' && !$xp->query('.//img|.//iframe', $attr)->length) {
                    $flush();
                    $section = ['title' => $lines[0], 'html' => ''];
                    break;
                }
                if (!$lines && !$xp->query('.//img|.//iframe', $attr)->length) {
                    break;
                }
                $section ??= ['title' => null, 'html' => ''];
                $section['html'] .= ($section['html'] !== '' ? "\n" : '') . wp_clean_html($inner);
                // Images insérées dans le texte : on les répertorie aussi.
                foreach ($xp->query('.//img', $attr) as $im) {
                    $rel = wp_media_rel($im->getAttribute('src'), $media);
                    if ($rel) {
                        $out['images'][] = ['image' => $rel, 'caption' => '', 'in_text' => true];
                    }
                }
                break;

            case 'counter':
                $num = wp_text($xp->query(".//*[contains(@class,'number')]", $el)->item(0));
                $prefix = wp_text($xp->query(".//*[contains(@class,'label')][1]", $el)->item(0));
                $lines = wp_lines(wp_inner_html($el));
                $num = $num !== '' ? $num : ($lines[0] ?? '');
                $text = implode(' ', array_values(array_filter($lines, fn ($l) => $l !== $num)));
                $out['key_figure'] = ['number' => $num, 'text' => trim($text)];
                break;

            case 'image_gallery':
                foreach ($xp->query(".//*[contains(concat(' ', normalize-space(@class), ' '), ' gallery-item ')]", $el) as $gi) {
                    $a = $xp->query('.//a', $gi)->item(0);
                    $im = $xp->query('.//img', $gi)->item(0);
                    $src = $a instanceof DOMElement && $a->getAttribute('href') ? $a->getAttribute('href') : ($im instanceof DOMElement ? $im->getAttribute('src') : '');
                    $cap = wp_text($xp->query(".//*[contains(@class,'gallery-caption')]", $gi)->item(0));
                    $rel = wp_media_rel($src, $media);
                    if ($rel === null) {
                        $warnings[] = "image de galerie hors médiathèque : $src";
                        continue;
                    }
                    $out['gallery'][] = ['image' => $rel] + wp_split_caption($cap);
                }
                break;

            case 'image':
                $im = $xp->query('.//img', $el)->item(0);
                if ($im instanceof DOMElement) {
                    $rel = wp_media_rel($im->getAttribute('src'), $media);
                    if ($rel) {
                        $out['images'][] = ['image' => $rel] + wp_split_caption(wp_text($el));
                    }
                }
                break;

            case 'video':
                foreach ($xp->query('.//iframe', $el) as $if) {
                    $src = $if->getAttribute('src') ?: $if->getAttribute('data-src');
                    if (preg_match('#(?:youtube(?:-nocookie)?\.com/embed/+|youtu\.be/)([\w-]{6,})#', $src, $vm)) {
                        $out['videos'][] = ['provider' => 'youtube', 'id' => $vm[1], 'title' => ''];
                    } elseif (preg_match('#player\.vimeo\.com/video/(\d+)#', $src, $vm)) {
                        $out['videos'][] = ['provider' => 'vimeo', 'id' => $vm[1], 'title' => ''];
                    } elseif ($src) {
                        $out['videos'][] = ['provider' => 'iframe', 'url' => $src, 'title' => ''];
                    }
                }
                foreach ($xp->query('.//video[@src]|.//video//source', $el) as $v) {
                    $out['videos'][] = ['provider' => 'file', 'url' => $v->getAttribute('src'), 'title' => ''];
                }
                break;

            case 'icon_box_2':
            case 'icon_box':
                $out['table_title'] = wp_text($el);
                break;

            case 'plain_text':
                $raw = wp_inner_html($el);
                if (preg_match("/videoId\s*=\s*['\"]([\w-]+)['\"]/", $raw, $vm) && str_contains($raw, 'dailymotion')) {
                    $out['videos'][] = ['provider' => 'dailymotion', 'id' => $vm[1], 'title' => ''];
                    break;
                }
                if (preg_match('#<blockquote[^>]*class="twitter-tweet"#', $raw)) {
                    preg_match_all('#href="(https://twitter\.com/[^/"]+/status/\d+)[^"]*"#', $raw, $tw);
                    $quote = preg_match('#<blockquote.*?</blockquote>#s', $raw, $bq) ? $bq[0] : $raw;
                    $out['embeds'][] = ['provider' => 'x', 'url' => $tw[1][0] ?? '', 'text' => implode("\n", wp_lines($quote))];
                    break;
                }
                $lines = wp_lines($raw);
                if ($lines) {
                    $section ??= ['title' => null, 'html' => ''];
                    $section['html'] .= "\n" . wp_clean_html($raw);
                }
                break;

            case 'tabs':
                foreach ($xp->query('.//table', $el) as $tb) {
                    $out['tables'][] = wp_parse_table($tb);
                }
                if ($out['tables']) {
                    $out['table'] ??= $out['tables'][0];
                }
                // Texte éventuel des onglets (hors titres d'exemple du thème)
                $txt = array_filter(wp_lines(wp_inner_html($el)), fn ($l) => !preg_match('/^This is the/i', $l));
                if (!$xp->query('.//table', $el)->length && $txt) {
                    $section ??= ['title' => null, 'html' => ''];
                    $section['html'] .= "\n<p>" . implode('</p><p>', array_map('htmlspecialchars', $txt)) . '</p>';
                }
                break;

            case 'button':
            case 'divider':
            case 'divider_2':
            case 'spacer':
            case 'code':
            case 'placeholder':
                break;

            default:
                $warnings[] = "module non traité : {$it['type']}";
                $section ??= ['title' => null, 'html' => ''];
                $section['html'] .= "\n" . wp_clean_html(wp_inner_html($el));
        }
    }
    $flush();

    // Vidéos YouTube insérées hors module vidéo (dans un bloc texte).
    foreach ($out['sections'] as $s) {
        if (preg_match_all('#youtube(?:-nocookie)?\.com/embed/+([\w-]{6,})#', $s['html'], $vm)) {
            foreach ($vm[1] as $vid) {
                if (!in_array($vid, array_column($out['videos'], 'id'), true)) {
                    $out['videos'][] = ['provider' => 'youtube', 'id' => $vid, 'title' => ''];
                }
            }
        }
    }

    // Les galeries peuvent répéter la même image (BeTheme) : on garde l'ordre, sans doublon exact.
    $seen = [];
    $out['gallery'] = array_values(array_filter($out['gallery'], function ($g) use (&$seen) {
        $k = $g['image'] . '|' . $g['caption_raw'];
        if (isset($seen[$k])) {
            return false;
        }
        return $seen[$k] = true;
    }));

    $out['warnings'] = $warnings;
    return $out;
}

/** Tableau wpDataTables : en-têtes + lignes. */
function wp_parse_table(DOMElement $t): array
{
    $rows = [];
    $headers = [];
    foreach ($t->getElementsByTagName('tr') as $tr) {
        $cells = [];
        foreach ($tr->childNodes as $c) {
            if ($c instanceof DOMElement && in_array($c->tagName, ['td', 'th'], true)) {
                $cells[] = wp_text($c);
            }
        }
        if (!$cells || !array_filter($cells, fn ($c) => $c !== '')) {
            continue;
        }
        if (!$headers) {
            $headers = $cells;
        } else {
            $rows[] = $cells;
        }
    }
    preg_match('/wpdtSimpleTable-(\d+)/', $t->getAttribute('id'), $m);
    $h0 = mb_strtolower($headers[0] ?? '');
    $h1 = mb_strtolower($headers[1] ?? '');
    $firstCol = array_map(fn ($r) => strtoupper(trim($r[0] ?? '')), $rows);
    $looksLineup = $rows && count(array_filter($firstCol, fn ($c) => in_array($c, ['G', 'D', 'M', 'A', 'R', 'E', 'GB'], true))) >= count($rows) * 0.6;
    $kind = match (true) {
        str_starts_with($h0, 'poste') || (str_contains($h1, 'nom') && $looksLineup) => 'lineup',
        str_starts_with($h0, 'saison') => 'stats',
        default => 'other',
    };
    return ['source_id' => isset($m[1]) ? (int) $m[1] : null, 'kind' => $kind, 'headers' => $headers, 'rows' => $rows];
}

// ---------------------------------------------------------------------------
// Matchs
// ---------------------------------------------------------------------------

/**
 * Titre de match : « J31 – Sochaux / Pau – L2 – 15/04/2023 – 2-3 »,
 * « Amical – Sochaux (D1) / Young Boys de Berne (D1 SUI) – 29/07/1970 – 1-0 »,
 * « 8è tour – Thaon-les-Vosges (N3) / Sochaux (L2) – CDF – 19/11/2022 – 2-2 (3-1 tab) ».
 */
function wp_parse_match_title(string $title): ?array
{
    $title = html_entity_decode($title, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $parts = array_values(array_filter(array_map('trim', preg_split('/\s+[–—-]\s+/u', $title)), fn ($p) => $p !== ''));
    $date = null;
    $di = null;
    foreach ($parts as $i => $p) {
        if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $p, $m) && checkdate((int) $m[2], (int) $m[1], (int) $m[3])) {
            $date = sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
            $di = $i;
        }
    }
    $ti = null;
    foreach ($parts as $i => $p) {
        if (preg_match('#\S\s*/\s*\S#u', $p) && !preg_match('#^\d+/\d+#', $p)) {
            $ti = $i;
            break;
        }
    }
    if ($ti === null) {
        return $date === null ? null : ['round' => null, 'home' => ['name' => '', 'level' => null], 'away' => ['name' => '', 'level' => null],
            'competition_code' => null, 'date' => $date, 'score' => null, 'score_raw' => '', 'event' => implode(' – ', array_slice($parts, 0, $di))];
    }
    [$home, $away] = array_map('trim', explode('/', $parts[$ti], 2)) + [1 => ''];
    $team = fn (string $s): array => preg_match('/^(.*?)\s*\(([^)]+)\)\s*$/u', $s, $m)
        ? ['name' => trim($m[1]), 'level' => trim($m[2])] : ['name' => trim($s), 'level' => null];
    $scoreRaw = $di !== null ? implode(' – ', array_slice($parts, $di + 1)) : '';
    $score = null;
    if (preg_match('/^(\d+)\s*-\s*(\d+)\s*(.*)$/u', $scoreRaw, $sm)) {
        $extra = trim($sm[3]);
        $pens = null;
        if (preg_match('/\(?\s*(\d+)\s*-\s*(\d+)\s*(?:t\.?a\.?b|tab|tirs)/iu', $extra, $pm)) {
            $pens = ['home' => (int) $pm[1], 'away' => (int) $pm[2]];
        }
        $score = ['home' => (int) $sm[1], 'away' => (int) $sm[2], 'extra' => $extra !== '' ? $extra : null,
            'aet' => (bool) preg_match('/a\.?\s*p\b|prolong/iu', $extra), 'pens' => $pens];
    }
    return [
        'round' => $ti > 0 ? implode(' – ', array_slice($parts, 0, $ti)) : null,
        'home' => $team($home),
        'away' => $team($away),
        'competition_code' => $di !== null && $di - $ti > 1 ? implode(' – ', array_slice($parts, $ti + 1, $di - $ti - 1)) : null,
        'date' => $date,
        'score' => $score,
        'score_raw' => $scoreRaw,
    ];
}

/** Lignes de la « Fiche du match » → champs. */
function wp_parse_match_header(array $lines): array
{
    $f = ['score_line' => $lines[0] ?? null];
    foreach (array_slice($lines, 1) as $line) {
        if (preg_match('/^(Lundi|Mardi|Mercredi|Jeudi|Vendredi|Samedi|Dimanche)\b/iu', $line)) {
            $f['date_text'] = $line;
        } elseif (preg_match('/^([\d\s.\x{00A0}]+)\s*spectateurs?/iu', $line, $m)) {
            $f['spectators'] = (int) preg_replace('/\D/', '', $m[1]);
            $f['spectators_text'] = $line;
        } elseif (preg_match('/spectateurs?|huis clos/iu', $line)) {
            $f['spectators_text'] = $line;
        } elseif (preg_match('/^(Arbitres?|Arbitrage)\s*:?\s*(.*)$/iu', $line, $m)) {
            $f['referee'] = trim($m[2]);
        } elseif (preg_match('/^(Buts?|Buteurs?)\s*:?\s*(.*)$/iu', $line, $m)) {
            $f['goals_text'] = trim(($f['goals_text'] ?? '') . ' ' . $m[2]);
        } elseif (preg_match('/^(Stade|Parc|Stadium|Estadio|Stadion|Terrain)\b/iu', $line)) {
            $f['stadium'] = $line;
        } elseif (!isset($f['competition_text'])) {
            $f['competition_text'] = $line;
        } elseif (!isset($f['stadium'])) {
            $f['stadium'] = $line;
        } else {
            $f['extra'][] = $line;
        }
    }
    return $f;
}

/** « Mauricio 58' et Alvero 90'+3 pour Sochaux ; Sylvestre 10' pour Pau » → par équipe. */
function wp_parse_goals(?string $text): array
{
    if (!$text) {
        return [];
    }
    $out = [];
    foreach (preg_split('/\s*;\s*/u', rtrim(trim($text), '.')) as $part) {
        if (preg_match('/^(.*)\s+pour\s+(.+)$/iu', $part, $m)) {
            $out[] = ['team' => trim($m[2], ' .'), 'scorers' => trim($m[1])];
        } elseif ($part !== '') {
            $out[] = ['team' => null, 'scorers' => trim($part)];
        }
    }
    return $out;
}

function wp_parse_lineup_row(array $r): array
{
    $name = $r[1] ?? '';
    $captain = (bool) preg_match('/\((c|cap\.?|capitaine)\)/iu', $name);
    $minutes = function (string $s): array {
        if (!preg_match_all("/(\d+)\s*['’]?(?:\s*\+\s*(\d+))?/u", $s, $mm, PREG_SET_ORDER)) {
            return [];
        }
        return array_map(fn ($x) => isset($x[2]) && $x[2] !== '' ? "{$x[1]}+{$x[2]}" : $x[1], $mm);
    };
    $sub = $r[3] ?? '';
    $cards = $r[4] ?? '';
    $goalsCell = $r[2] ?? '';
    return [
        'position' => strtoupper(trim($r[0] ?? '')),
        'name' => trim(preg_replace('/\((c|cap\.?|capitaine)\)/iu', '', $name)),
        'captain' => $captain,
        'goals' => $minutes($goalsCell),
        'goals_text' => $goalsCell,
        'sub_in' => preg_match('/↑|entr[ée]e|rentr/iu', $sub) ? ($minutes($sub)[0] ?? '') : null,
        'sub_out' => preg_match('/↓|sortie|sort\b|rempl/iu', $sub) ? ($minutes($sub)[0] ?? '') : null,
        'sub_text' => $sub,
        'yellow' => preg_match('/🟨|\bJ\b|jaune|carton j/u', $cards) ? $minutes($cards) : [],
        'red' => preg_match('/🟥|\bR\b|rouge|carton r/u', $cards) ? $minutes($cards) : [],
        'cards_text' => $cards,
        'person_id' => null,
    ];
}

/** Temps forts « 19' : texte » ; un score « (0-1) » marque un but. */
function wp_parse_highlights(string $html): array
{
    $out = [];
    $html = preg_replace('#</(p|li)>|<br\s*/?>#i', "\n", $html);
    $cur = null;
    foreach (explode("\n", html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')) as $l) {
        $l = trim(preg_replace('/[\s\x{00A0}]+/u', ' ', $l));
        if ($l === '') {
            continue;
        }
        if (preg_match("/^(\d{1,3})\s*(?:['’]\s*)?(?:\+\s*(\d{1,2})\s*['’]?)?\s*[:\-–]\s*(.+)$/u", $l, $m)) {
            if ($cur) {
                $out[] = $cur;
            }
            $text = $m[3];
            $score = preg_match('/\((\d+\s*-\s*\d+)\)/u', $text, $sm) ? preg_replace('/\s+/', '', $sm[1]) : null;
            $cur = ['minute' => $m[2] !== '' ? "{$m[1]}+{$m[2]}" : $m[1], 'text' => $text, 'goal' => $score !== null, 'score' => $score];
        } elseif ($cur) {
            $cur['text'] .= ' ' . $l;
        }
    }
    if ($cur) {
        $out[] = $cur;
    }
    return $out;
}

/** Réactions « Nom : « citation » » → liste (la citation peut tenir sur plusieurs paragraphes). */
function wp_parse_reactions(string $html): array
{
    $out = [];
    $html = preg_replace('#</(p|li)>|<br\s*/?>#i', "\n", $html);
    $cur = null;
    foreach (explode("\n", html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')) as $l) {
        $l = trim(preg_replace('/[\s\x{00A0}]+/u', ' ', $l));
        if ($l === '') {
            continue;
        }
        if (preg_match('/^([^:«»"]{2,70}?)\s*:\s*(.*)$/u', $l, $m) && !preg_match('/^\d/', $m[1])) {
            if ($cur) {
                $out[] = $cur;
            }
            $cur = ['who' => trim($m[1]), 'text' => trim($m[2])];
        } elseif ($cur) {
            $cur['text'] .= "\n" . $l;
        } else {
            $cur = ['who' => '', 'text' => $l];
        }
    }
    if ($cur) {
        $out[] = $cur;
    }
    foreach ($out as &$q) {
        $q['text'] = trim($q['text'], " \n«»\"“”");
    }
    return $out;
}

/** Brèves : une entrée par élément de liste ou paragraphe. */
function wp_parse_items(string $html): array
{
    if (preg_match_all('#<li[^>]*>(.*?)</li>#is', $html, $m)) {
        $items = array_map(fn ($x) => trim(html_entity_decode(strip_tags($x), ENT_QUOTES | ENT_HTML5, 'UTF-8')), $m[1]);
    } else {
        $items = wp_lines($html);
    }
    return array_values(array_filter(array_map(fn ($x) => trim(preg_replace('/\s+/u', ' ', $x)), $items), fn ($x) => $x !== ''));
}

// ---------------------------------------------------------------------------
// Personnes
// ---------------------------------------------------------------------------

/** Fiche d'identité → lignes ordonnées (sans perte) + champs reconnus. */
function wp_parse_person_header(array $lines): array
{
    $rows = [];
    $f = ['lists' => []];
    $listKey = null;
    foreach ($lines as $i => $line) {
        if ($i > 0 && preg_match('/^([^:]{2,60}?)\s*:\s*(.*)$/u', $line, $m) && !preg_match('/^\d/', $m[1])) {
            $rows[] = ['label' => trim($m[1]), 'value' => trim($m[2])];
            $listKey = trim($m[2]) === '' ? trim($m[1]) : null;
        } else {
            $rows[] = ['label' => null, 'value' => $line];
            if ($listKey !== null && $i > 0) {
                $f['lists'][$listKey][] = $line;
            }
        }
    }

    $f['name'] = $lines[0] ?? null;
    if (isset($rows[1]) && $rows[1]['label'] === null
        && !preg_match('/^(n[ée]e? le|d[ée]c[ée]d|International|Carri[eè]re)/iu', $rows[1]['value'])) {
        $f['subtitle'] = $rows[1]['value'];
    }
    $career = 'player';
    foreach ($rows as $r) {
        $label = $r['label'] !== null ? mb_strtolower($r['label']) : null;
        $v = $r['value'];
        if ($label === null) {
            if (preg_match('/^n[ée]e? le\s+(.+?)(?:\s+à\s+(.+))?$/iu', $v, $m)) {
                $f['birth_text'] = $v;
                $f['birth_date'] = $m[1];
                $f['birth_place'] = $m[2] ?? null;
            } elseif (preg_match('/^n[ée]e? (?:en|vers)\s+(.+?)(?:\s+à\s+(.+))?$/iu', $v, $m)) {
                $f['birth_text'] = $v;
                $f['birth_date'] = $m[1];
                $f['birth_place'] = $m[2] ?? null;
            } elseif (preg_match('/^d[ée]c[ée]d[ée]e?\s+(?:le|en)?\s*(.+?)(?:\s+à\s+(.+))?$/iu', $v, $m)) {
                $f['death_text'] = $v;
                $f['death_date'] = trim($m[1]);
                $f['death_place'] = $m[2] ?? null;
            } elseif (preg_match('/^International/iu', $v)) {
                $f['international'][] = $v;
            } elseif (preg_match("/carri[eè]re d.entra[iî]neur/iu", $v)) {
                $career = 'coach';
            } elseif (preg_match('/carri[eè]re de joueur/iu', $v)) {
                $career = 'player';
            }
            continue;
        }
        $key = match (true) {
            $label === 'taille' => 'height',
            $label === 'poids' => 'weight',
            $label === 'pied' => 'foot',
            $label === 'poste' || $label === 'postes' => 'position',
            $label === 'surnom' => 'nickname',
            $label === 'nationalité' => 'nationality',
            str_starts_with($label, 'arrivée') => 'arrival',
            str_starts_with($label, 'départ') => 'departure',
            str_starts_with($label, 'premier match entraîné') || str_starts_with($label, 'premier match dirigé') => 'first_match_coached',
            str_starts_with($label, 'dernier match entraîné') || str_starts_with($label, 'dernier match dirigé') => 'last_match_coached',
            str_starts_with($label, 'premier match') => 'first_match',
            str_starts_with($label, 'dernier match') => 'last_match',
            str_starts_with($label, 'premier but') => 'first_goal',
            str_starts_with($label, "a l'essai") || str_starts_with($label, "à l'essai") || str_starts_with($label, 'a l’essai') || str_starts_with($label, 'à l’essai') => 'trial',
            str_starts_with($label, 'palmarès') => 'honours',
            $label === 'puis' => 'then',
            default => null,
        };
        if ($key === null || $key === 'honours') {
            continue;
        }
        if ($key === 'then') {
            $f['then'][] = $v;
            continue;
        }
        if (in_array($key, ['arrival', 'departure'], true) && $career === 'coach') {
            $key .= '_coach';
        }
        if (!isset($f[$key])) {
            $f[$key] = $v;
        } else {
            $f[$key . '_2'] = $v;
        }
    }
    foreach ($f['lists'] as $lbl => $items) {
        if (str_starts_with(mb_strtolower($lbl), 'palmarès')) {
            $f['honours'] = $items;
        }
    }
    return ['rows' => $rows, 'fields' => $f];
}

/** « 1m82 » → 182 ; « 73 kg » → 73. */
function wp_cm(?string $s): ?int
{
    if (!$s) {
        return null;
    }
    if (preg_match('/(\d)\s*m\s*(\d{1,2})/u', $s, $m)) {
        return (int) $m[1] * 100 + (int) str_pad($m[2], 2, '0');
    }
    if (preg_match('/(\d{3})\s*cm/u', $s, $m)) {
        return (int) $m[1];
    }
    return null;
}

function wp_kg(?string $s): ?int
{
    return $s && preg_match('/(\d{2,3})/', $s, $m) ? (int) $m[1] : null;
}

/** Prénom / nom à partir du titre (« Jaouad Zaïri », « Jesus Mouzo Fandino (A l'essai) »). */
function wp_split_name(string $title): array
{
    $t = trim(preg_replace('/\s*\([^)]*\)\s*$/u', '', html_entity_decode($title, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    $t = trim($t, " \"“”«»");
    $parts = preg_split('/\s+/u', $t);
    if (count($parts) === 1) {
        return ['first_name' => '', 'last_name' => $t, 'display' => $t];
    }
    $particles = ['de', 'da', 'di', 'du', 'des', 'van', 'von', 'le', 'la', 'del', 'dos', 'el', 'ben', 'ter', 'mac', 'mc', "d'"];
    $i = count($parts) - 1;
    while ($i > 1 && in_array(mb_strtolower($parts[$i - 1]), $particles, true)) {
        $i--;
    }
    return ['first_name' => implode(' ', array_slice($parts, 0, $i)), 'last_name' => implode(' ', array_slice($parts, $i)), 'display' => $t];
}
