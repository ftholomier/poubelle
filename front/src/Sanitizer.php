<?php

declare(strict_types=1);

/** Nettoyage du HTML saisi dans le back-office (liste blanche de balises et d'attributs). */
final class Sanitizer
{
    private const TAGS = [
        'p' => [], 'br' => [], 'strong' => [], 'b' => [], 'em' => [], 'i' => [], 'u' => [], 's' => [],
        'a' => ['href', 'target', 'rel', 'title'], 'ul' => [], 'ol' => [], 'li' => [],
        'h2' => [], 'h3' => [], 'h4' => [], 'blockquote' => [], 'hr' => [],
        'img' => ['src', 'alt', 'width', 'height'], 'figure' => [], 'figcaption' => [],
        'section' => [], 'header' => [], 'span' => ['class'], 'div' => [],
        'table' => [], 'thead' => [], 'tbody' => [], 'tr' => [], 'th' => [], 'td' => [],
    ];
    private const DROP = ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'textarea', 'select', 'link', 'meta', 'svg', 'math', 'template', 'noscript'];

    public static function html(string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }
        $doc = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"?><div id="rv-root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
        libxml_clear_errors();
        $root = $doc->getElementById('rv-root') ?? $doc->documentElement;
        if (!$root) {
            return '';
        }
        self::clean($root);
        $out = '';
        foreach ($root->childNodes as $c) {
            $out .= $doc->saveHTML($c);
        }
        return trim((string) preg_replace('/<p>(\s|&nbsp;|<br\s*\/?>)*<\/p>/u', '', $out));
    }

    private static function clean(DOMNode $node): void
    {
        for ($i = $node->childNodes->length - 1; $i >= 0; $i--) {
            $c = $node->childNodes->item($i);
            if ($c instanceof DOMComment) {
                $node->removeChild($c);
                continue;
            }
            if (!$c instanceof DOMElement) {
                continue;
            }
            $tag = strtolower($c->tagName);
            if (in_array($tag, self::DROP, true)) {
                $node->removeChild($c);
                continue;
            }
            self::clean($c);
            if (!isset(self::TAGS[$tag])) {
                // Balise inconnue : on garde son contenu
                while ($c->firstChild) {
                    $node->insertBefore($c->firstChild, $c);
                }
                $node->removeChild($c);
                continue;
            }
            $allowed = self::TAGS[$tag];
            for ($a = $c->attributes->length - 1; $a >= 0; $a--) {
                $attr = $c->attributes->item($a);
                $name = strtolower($attr->nodeName);
                $val = trim($attr->nodeValue ?? '');
                if (!in_array($name, $allowed, true)) {
                    $c->removeAttribute($attr->nodeName);
                    continue;
                }
                if (($name === 'href' || $name === 'src') && !self::url($val, $name === 'src')) {
                    $c->removeAttribute($attr->nodeName);
                }
                if ($name === 'class' && !preg_match('/^[a-z0-9 _-]{1,60}$/', $val)) {
                    $c->removeAttribute('class');
                }
            }
            if ($tag === 'a' && $c->getAttribute('target') === '_blank') {
                $c->setAttribute('rel', 'noopener');
            } elseif ($tag === 'a') {
                $c->removeAttribute('target');
            }
        }
    }

    /** Adresse autorisée : relative, http(s), mailto, tel (pas de javascript:, data:…). */
    public static function url(string $u, bool $image = false): bool
    {
        $u = trim($u);
        if ($u === '') {
            return false;
        }
        if (str_starts_with($u, '/') && !str_starts_with($u, '//')) {
            return true;
        }
        if (str_starts_with($u, '#')) {
            return !$image;
        }
        $scheme = strtolower((string) parse_url($u, PHP_URL_SCHEME));
        if ($image) {
            return $scheme === 'https' || $scheme === 'http';
        }
        return in_array($scheme, ['http', 'https', 'mailto', 'tel'], true);
    }

    /** Texte simple sur une ligne. */
    public static function line($v, int $max = 300): string
    {
        $v = is_scalar($v) ? (string) $v : '';
        $v = preg_replace('/[\x00-\x1F\x7F]/u', ' ', $v) ?? '';
        return mb_substr(trim($v), 0, $max);
    }

    /** Texte multiligne. */
    public static function text($v, int $max = 5000): string
    {
        $v = is_scalar($v) ? (string) $v : '';
        $v = str_replace("\r\n", "\n", $v);
        $v = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $v) ?? '';
        return mb_substr(trim($v), 0, $max);
    }

    /** Chemin d'image : fichier du site (/media, /uploads, /assets) ou adresse https. */
    public static function image($v): string
    {
        $v = self::line($v, 500);
        if ($v === '') {
            return '';
        }
        if (preg_match('#^/(media|uploads|assets)/[^\s"\'<>]+$#', $v) && !str_contains($v, '..')) {
            return $v;
        }
        return preg_match('#^https://[^\s"\'<>]+$#', $v) ? $v : '';
    }

    /** Chemin d'URL du site : « /mon-adresse/ ». */
    public static function path($v): string
    {
        $v = self::line($v, 300);
        $v = '/' . trim($v, "/ \t");
        $v = preg_replace('#/+#', '/', $v) ?? '/';
        if ($v !== '/') {
            $v .= '/';
        }
        return preg_match('#^/[^\s"\'<>?\#]*$#u', $v) ? $v : '/';
    }
}
