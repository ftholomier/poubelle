<?php
declare(strict_types=1);

namespace App\Front;

use App\Data\Categories;
use App\Data\Index;

/**
 * Mise en page « récit illustré » des pages de la rubrique Infrastructures (le stade, la pelouse,
 * le centre de formation) : le texte d'une fiche, saisi d'un seul bloc avec ses intertitres, est
 * découpé en chapitres numérotés (sommaire qui suit la lecture), l'accroche et le chapô sont mis
 * en valeur, les citations de l'époque deviennent des citations, les phrases en gras des encadrés
 * « À retenir », et les photos de la galerie sont réparties entre les chapitres. La page se situe
 * dans l'histoire de sa rubrique (frise des époques, série « Partie 1, 2… », page précédente et
 * suivante). Rien n'est changé dans la fiche : tout est tiré de ce qui est saisi.
 */
final class Recit
{
    public const ROOT = 'infrastructures';
    /** Photos placées dans un chapitre au plus ; les autres vont à l'album en fin de page. */
    private const PER_CHAPTER = 2;

    /** La fiche est-elle mise en page en récit illustré ? */
    public static function applies(array $doc): bool
    {
        if (!in_array($doc['type'] ?? '', ['article', 'page', 'personne'], true)) {
            return false;
        }
        foreach ((array) ($doc['categories'] ?? []) as $c) {
            if ($c === self::ROOT || (Categories::get((string) $c) && Categories::root((string) $c) === self::ROOT)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Le récit : ['era', 'tagline', 'lead', 'chapters' => [['n', 'id', 'title', 'year', 'html', 'photos']],
     * 'album', 'minutes', 'words', 'photos', 'rubric', 'timeline', 'prev', 'next', 'series'].
     */
    public static function build(array $doc, ?string $skipImage = null): array
    {
        $era = '';
        $html = '';
        foreach ((array) ($doc['sections'] ?? []) as $i => $s) {
            $title = trim((string) ($s['title'] ?? ''));
            $body = (string) ($s['html'] ?? '');
            if (trim(strip_tags($body, '<img><iframe>')) === '' && $title === '') {
                continue;
            }
            // Titre de section qui n'est qu'une époque (« 1931 - 2000 ») : affiché comme époque.
            if ($title !== '' && self::eraOf($title) !== '' && mb_strlen(self::stripEra($title)) < 3) {
                $era = $era ?: self::eraOf($title);
                $title = '';
            }
            $html .= ($title !== '' ? '<h3>' . e($title) . '</h3>' : '') . $body;
        }
        $html = safe_html($html);
        $parts = preg_split('#(<h[2-6]\b[^>]*>.*?</h[2-6]>)#is', $html, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [];
        $heads = array_values(array_filter($parts, fn ($p) => (bool) preg_match('#^<h[2-6]\b#i', $p)));
        $levels = array_map(fn ($h) => (int) $h[2], $heads);

        // Accroche : un premier intertitre qui ouvre le texte, d'un niveau au-dessus des suivants
        // (« La tanière des Lions ! » en h4, puis les intertitres en h5).
        $tagline = '';
        if ($heads && preg_match('#^<h([2-6])\b#i', (string) ($parts[0] ?? ''), $hm)) {
            $rest = array_slice($levels, 1);
            if ($rest && min($rest) > (int) $hm[1]) {
                $tagline = self::text($parts[0]);
                array_shift($parts);
            }
        }

        // Chapitres : ce qui précède le premier intertitre (chapô puis introduction), puis un par intertitre.
        $chapters = [];
        $cur = ['title' => '', 'html' => ''];
        foreach ($parts as $p) {
            if (preg_match('#^<h[2-6]\b#i', $p)) {
                if (trim(strip_tags($cur['html'], '<img><iframe>')) !== '' || $cur['title'] !== '') {
                    $chapters[] = $cur;
                }
                $cur = ['title' => self::text($p), 'html' => ''];
            } else {
                $cur['html'] .= $p;
            }
        }
        if (trim(strip_tags($cur['html'], '<img><iframe>')) !== '' || $cur['title'] !== '') {
            $chapters[] = $cur;
        }
        $lead = '';
        if ($chapters && $chapters[0]['title'] === '') {
            [$lead, $chapters[0]['html']] = self::lead($chapters[0]['html']);
            if (trim(strip_tags($chapters[0]['html'], '<img><iframe>')) === '') {
                array_shift($chapters);
            }
        }
        if ($lead === '' && $chapters) {
            [$lead, $chapters[0]['html']] = self::lead($chapters[0]['html']);
        }

        $out = [];
        $n = 0;
        foreach ($chapters as $c) {
            $n += $c['title'] !== '' ? 1 : 0;
            $out[] = [
                'n' => $c['title'] !== '' ? $n : 0,
                'id' => $c['title'] !== '' ? 'ch' . $n . '-' . substr(slugify($c['title']), 0, 40) : '',
                'title' => $c['title'],
                'year' => self::year($c['title']),
                'html' => self::dress($c['html']),
                'photos' => [],
            ];
        }

        // Photos de la galerie réparties entre les chapitres (dans l'ordre), le reste en album.
        $photos = array_values(array_filter((array) ($doc['gallery'] ?? []), fn ($g) => !empty($g['image']) && $g['image'] !== $skipImage));
        $album = $photos;
        $slots = count($out);
        if ($slots > 0 && $photos) {
            $placed = min(count($photos), $slots * self::PER_CHAPTER);
            // Le premier chapitre suit l'image d'en-tête : on commence les photos au deuxième s'il y en a assez.
            $start = $slots > 1 && $placed < $slots ? 1 : 0;
            $span = $slots - $start;
            for ($i = 0; $i < $placed; $i++) {
                $out[$start + intdiv($i * $span, $placed)]['photos'][] = $photos[$i];
            }
            $album = array_slice($photos, $placed);
        }

        $words = str_word_count(self::text($html), 0, 'àâäéèêëîïôöùûüçœæÀÂÄÉÈÊËÎÏÔÖÙÛÜÇŒÆ’\'-');
        return [
            'era' => $era ?: self::dash(self::eraOf((string) ($doc['title'] ?? ''))),
            'tagline' => $tagline,
            'lead' => $lead,
            'chapters' => $out,
            'album' => $album,
            'words' => $words,
            'minutes' => max(1, (int) round($words / 230)),
            'photos' => count($photos),
        ] + self::place($doc);
    }

    /** Chapô : premier paragraphe entièrement en italique ou en gras, s'il ouvre le texte. @return array{0:string,1:string} */
    private static function lead(string $html): array
    {
        if (preg_match('#^\s*(<p\b[^>]*>(.*?)</p>)#is', $html, $m)) {
            $inner = trim(preg_replace('#^(\s*<(em|strong|b|i)\b[^>]*>)+|(</(em|strong|b|i)>\s*)+$#i', '', trim($m[2])) ?? '');
            $plain = self::text($m[2]);
            if (mb_strlen($plain) >= 60 && self::emphasis($m[2]) >= 0.85) {
                return ['<p>' . strip_tags($inner, '<a><br><sup><sub>') . '</p>', substr($html, strlen($m[0]))];
            }
        }
        return ['', $html];
    }

    /** Citations, encadrés « À retenir », paragraphes vides retirés. */
    private static function dress(string $html): string
    {
        $html = (string) preg_replace('#<p\b[^>]*>(\s|&nbsp;|<br\s*/?>)*</p>#i', '', $html);
        return (string) preg_replace_callback('#<p\b[^>]*>(.*?)</p>#is', function ($m) {
            $plain = self::text($m[1]);
            $len = mb_strlen($plain);
            // Citation d'époque : ouvre par « ou “, surtout en italique.
            if ($len >= 70 && preg_match('/^[«“"]/u', $plain) && self::emphasis($m[1], ['em', 'i']) >= 0.5) {
                $inner = trim(preg_replace('#</?(em|i)\b[^>]*>#i', '', $m[1]) ?? $m[1]);
                // Guillemets du texte retirés : la citation a les siens.
                $inner = trim((string) preg_replace(['/^(\s|&nbsp;)*[«“"](\s|&nbsp;)*/u', '/(\s|&nbsp;)*[»”"](\s|&nbsp;)*$/u'], '', $inner));
                $inner = trim((string) preg_replace(['/^(\s|&nbsp;)*[«“"](\s|&nbsp;)*/u', '/(\s|&nbsp;)*[»”"](\s|&nbsp;)*$/u'], '', $inner));
                return '<blockquote class="rquote"><p>' . $inner . '</p></blockquote>';
            }
            // Phrase tout en gras : encadré « À retenir ».
            if ($len >= 50 && self::emphasis($m[1], ['strong', 'b']) >= 0.9) {
                $inner = trim(preg_replace('#</?(strong|b)\b[^>]*>#i', '', $m[1]) ?? $m[1]);
                return '<aside class="rnote"><span class="rnote__k">' . e(t('À retenir')) . '</span><p>' . $inner . '</p></aside>';
            }
            return $m[0];
        }, $html);
    }

    /** Part du texte d'un passage qui est en italique ou en gras (ou seulement $tags). */
    private static function emphasis(string $html, array $tags = ['em', 'i', 'strong', 'b']): float
    {
        $all = mb_strlen(self::text($html));
        if ($all === 0) {
            return 0.0;
        }
        $in = 0;
        $re = '#<(' . implode('|', $tags) . ')\b[^>]*>(.*?)</\1>#is';
        $rest = $html;
        while (preg_match($re, $rest, $m, PREG_OFFSET_CAPTURE)) {
            $in += mb_strlen(self::text($m[2][0]));
            $rest = substr($rest, $m[0][1] + strlen($m[0][0]));
        }
        return min(1.0, $in / $all);
    }

    // ------------------------------------------------------------------ place dans la rubrique

    /**
     * La page dans l'histoire de sa rubrique : ['rubric' => [label, href], 'timeline' => [...],
     * 'series' => ['title', 'parts' => [...]] ou null, 'prev', 'next'].
     */
    public static function place(array $doc): array
    {
        $cat = null;
        foreach ((array) ($doc['categories'] ?? []) as $c) {
            if ($c !== self::ROOT && Categories::get((string) $c) && Categories::root((string) $c) === self::ROOT) {
                $cat = (string) $c;
                break;
            }
        }
        $cat ??= self::ROOT;
        $c = Categories::get($cat);
        $entries = array_values(array_filter(Index::inCategory($cat), fn ($s) => !isset($s['m'])));
        $id = (int) ($doc['id'] ?? 0);

        // Série « … Partie N » : une seule ligne dans la frise, les parties dans l'ordre.
        $seriesOf = function (string $title): ?array {
            return preg_match('/^(.*?)\s*[–—:,-]?\s*Partie\s+(\d+)\s*(?::\s*(.*))?$/iu', $title, $m) ? [self::clean($m[1]), (int) $m[2], trim($m[3] ?? '')] : null;
        };
        $items = [];
        $series = [];
        foreach ($entries as $s) {
            $title = (string) $s['title'];
            if ($sr = $seriesOf($title)) {
                $series[$sr[0]][$sr[1]] = ['n' => $sr[1], 'title' => $sr[2] !== '' ? $sr[2] : $title, 'href' => url($s['path']), 'image' => $s['image'] ?? null, 'current' => (int) $s['id'] === $id];
                continue;
            }
            $items[] = ['title' => self::stripEra($title), 'era' => self::dash(self::eraOf($title)), 'year' => self::year($title), 'href' => url($s['path']), 'image' => $s['image'] ?? null, 'current' => (int) $s['id'] === $id, 'kind' => $s['type'] === 'personne' ? 'personne' : 'page'];
        }
        // Frise : les époques dans l'ordre, puis les portraits.
        usort($items, fn ($a, $b) => [$a['year'] === null, $a['kind'] === 'personne', $a['year'] ?? 9999] <=> [$b['year'] === null, $b['kind'] === 'personne', $b['year'] ?? 9999]);
        foreach ($series as $name => $parts) {
            ksort($parts);
            $series[$name] = array_values($parts);
        }

        $mine = $seriesOf((string) ($doc['title'] ?? ''));
        $prev = $next = null;
        $seq = $mine && isset($series[$mine[0]]) ? $series[$mine[0]] : array_values(array_filter($items, fn ($x) => $x['year'] !== null));
        foreach ($seq as $k => $x) {
            if ($x['current']) {
                $prev = $seq[$k - 1] ?? null;
                $next = $seq[$k + 1] ?? null;
            }
        }
        $timeline = $items;
        foreach ($series as $name => $parts) {
            $timeline[] = ['title' => $name, 'era' => count($parts) . ' ' . t('parties'), 'year' => null, 'href' => $parts[0]['href'], 'image' => $parts[0]['image'], 'current' => (bool) array_filter($parts, fn ($p) => $p['current']), 'kind' => 'serie'];
        }
        return [
            'rubric' => ['label' => $c ? t(Categories::label($cat)) : t('Infrastructures'), 'href' => url((string) ($c['path'] ?? '/infrastructures/'))],
            'timeline' => $timeline,
            'series' => $mine && isset($series[$mine[0]]) ? ['title' => $mine[0], 'n' => $mine[1], 'parts' => $series[$mine[0]]] : null,
            'prev' => $prev,
            'next' => $next,
        ];
    }

    // ------------------------------------------------------------------ outils

    private static function text(string $html): string
    {
        return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
    }

    public static function year(string $s): ?int
    {
        return preg_match('/\b(1[89]\d{2}|20[0-4]\d)\b/', $s, $m) ? (int) $m[1] : null;
    }

    /** Époque écrite dans un titre (« 1931 – 2000 », « 2000 à aujourd'hui », « 2024 – … »). */
    public static function eraOf(string $title): string
    {
        if (preg_match('/(1[89]\d{2}|20[0-4]\d)\s*(?:[–—-]|à)\s*(1[89]\d{2}|20[0-4]\d|aujourd[’\']hui|…|\.{3,4})/u', $title, $m)) {
            return $m[1] . ' – ' . (preg_match('/^\d/', $m[2]) ? $m[2] : (str_starts_with($m[2], 'aujourd') ? t('aujourd’hui') : '…'));
        }
        return '';
    }

    /** Titre sans son époque finale (« Les premiers stades temporaires – 1928 – 1931 » → « Les premiers stades temporaires »). */
    public static function stripEra(string $title): string
    {
        $t = preg_replace('/\s*[:–—-]?\s*(1[89]\d{2}|20[0-4]\d)\s*(?:[–—-]|à)\s*(?:1[89]\d{2}|20[0-4]\d|aujourd[’\']hui|…|\.{3,4})\.?\s*/u', ' ', $title) ?? $title;
        return self::clean(preg_replace('/\s{2,}/u', ' ', $t) ?? $t);
    }

    /** Espaces, tirets et deux-points retirés aux deux bouts (sans couper un caractère accentué). */
    private static function clean(string $s): string
    {
        return (string) preg_replace('/^[\s–—:\-]+|[\s–—:\-]+$/u', '', $s);
    }

    private static function dash(string $s): string
    {
        return trim(preg_replace('/\s*[-–—]\s*/u', ' – ', $s) ?? $s);
    }
}
