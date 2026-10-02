<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Nettoyage HTML par liste blanche (DOMDocument) : seules les balises et attributs
 * autorisés sont conservés ; scripts, styles, iframes et gestionnaires d'événements disparaissent.
 */
final class Sanitizer
{
    private const DROP = ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'textarea', 'select', 'option', 'svg', 'math', 'link', 'meta', 'head', 'title', 'noscript', 'template', 'video', 'audio', 'canvas', 'frame', 'frameset', 'applet', 'base'];
    private const RENAME = ['b' => 'strong', 'i' => 'em', 'h1' => 'h2', 'h5' => 'h4', 'h6' => 'h4', 'center' => 'p', 'strike' => 's', 'del' => 's'];

    public static function html(string $html, array $opt = []): string
    {
        $opt += ['images' => false, 'tables' => false, 'link_rel' => 'nofollow noopener', 'headings' => true, 'max_length' => 60000];
        $html = Str::clean($html);
        if ($html === '') {
            return '';
        }
        if (strip_tags($html) === $html) {
            return Str::paragraphs($html);
        }
        $allowed = ['p' => [], 'br' => [], 'strong' => [], 'em' => [], 'u' => [], 's' => [], 'ul' => [], 'ol' => [], 'li' => [], 'blockquote' => [], 'a' => ['href', 'title'], 'hr' => [], 'sup' => [], 'sub' => []];
        if ($opt['headings']) {
            $allowed += ['h2' => ['id'], 'h3' => ['id'], 'h4' => []];
        }
        if ($opt['images']) {
            $allowed += ['img' => ['src', 'alt', 'width', 'height', 'loading'], 'figure' => [], 'figcaption' => []];
        }
        if ($opt['tables']) {
            $allowed += ['table' => [], 'thead' => [], 'tbody' => [], 'tr' => [], 'th' => ['colspan', 'rowspan'], 'td' => ['colspan', 'rowspan']];
        }

        $doc = new \DOMDocument('1.0', 'UTF-8');
        $prev = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"?><!DOCTYPE html><html><body><div id="__root">' . $html . '</div></body></html>', LIBXML_NONET | LIBXML_NOBLANKS);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        $root = $doc->getElementById('__root');
        if (!$root) {
            return Str::paragraphs(Str::text($html));
        }
        self::walk($root, $doc, $allowed, $opt);
        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $doc->saveHTML($child);
        }
        // Texte orphelin hors paragraphe → paragraphes ; nettoyage des paragraphes vides.
        $out = self::wrapLooseText($out);
        $out = preg_replace('#<p>(\s|&nbsp;|<br\s*/?>)*</p>#i', '', $out) ?? $out;
        $out = preg_replace('#(<br\s*/?>\s*){3,}#i', '<br><br>', $out) ?? $out;
        return mb_substr(trim($out), 0, $opt['max_length']);
    }

    private static function walk(\DOMNode $node, \DOMDocument $doc, array $allowed, array $opt): void
    {
        for ($i = $node->childNodes->length - 1; $i >= 0; $i--) {
            $child = $node->childNodes->item($i);
            if ($child === null) {
                continue;
            }
            if ($child instanceof \DOMComment || $child instanceof \DOMProcessingInstruction || $child instanceof \DOMCdataSection) {
                $node->removeChild($child);
                continue;
            }
            if (!$child instanceof \DOMElement) {
                continue;
            }
            $tag = strtolower($child->tagName);
            if (in_array($tag, self::DROP, true)) {
                $node->removeChild($child);
                continue;
            }
            if (isset(self::RENAME[$tag])) {
                $child = self::rename($child, self::RENAME[$tag], $doc);
                $tag = self::RENAME[$tag];
            }
            self::walk($child, $doc, $allowed, $opt);
            if (!isset($allowed[$tag])) {
                // balise inconnue : on garde son contenu (div → paragraphe)
                if (in_array($tag, ['div', 'section', 'article'], true) && trim($child->textContent) !== '' && !self::hasBlockChild($child)) {
                    $child = self::rename($child, 'p', $doc);
                    $tag = 'p';
                } else {
                    while ($child->firstChild) {
                        $node->insertBefore($child->firstChild, $child);
                    }
                    $node->removeChild($child);
                    continue;
                }
            }
            foreach (iterator_to_array($child->attributes) as $attr) {
                $name = strtolower($attr->name);
                if (!in_array($name, $allowed[$tag], true)) {
                    $child->removeAttribute($attr->name);
                }
            }
            if ($tag === 'a') {
                $href = trim($child->getAttribute('href'));
                if (!self::safeUrl($href)) {
                    while ($child->firstChild) {
                        $node->insertBefore($child->firstChild, $child);
                    }
                    $node->removeChild($child);
                    continue;
                }
                if (preg_match('#^https?://#i', $href) && !self::isInternal($href)) {
                    $child->setAttribute('rel', $opt['link_rel']);
                    $child->setAttribute('target', '_blank');
                }
            }
            if ($tag === 'img') {
                $src = trim($child->getAttribute('src'));
                if (!preg_match('#^(https?://|/media/)#i', $src)) {
                    $node->removeChild($child);
                    continue;
                }
                $child->setAttribute('loading', 'lazy');
            }
        }
    }

    private static function rename(\DOMElement $el, string $tag, \DOMDocument $doc): \DOMElement
    {
        $new = $doc->createElement($tag);
        foreach (iterator_to_array($el->attributes) as $a) {
            $new->setAttribute($a->name, $a->value);
        }
        while ($el->firstChild) {
            $new->appendChild($el->firstChild);
        }
        $el->parentNode?->replaceChild($new, $el);
        return $new;
    }

    private static function hasBlockChild(\DOMElement $el): bool
    {
        foreach ($el->childNodes as $c) {
            if ($c instanceof \DOMElement && in_array(strtolower($c->tagName), ['p', 'div', 'ul', 'ol', 'h2', 'h3', 'h4', 'blockquote', 'table', 'section', 'h1', 'figure'], true)) {
                return true;
            }
        }
        return false;
    }

    private static function wrapLooseText(string $html): string
    {
        $parts = preg_split('#(<(?:p|ul|ol|h2|h3|h4|blockquote|hr|table|figure)\b[^>]*>.*?</(?:p|ul|ol|h2|h3|h4|blockquote|table|figure)>|<hr\s*/?>)#is', $html, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [];
        $out = '';
        foreach ($parts as $part) {
            if (preg_match('#^<(p|ul|ol|h2|h3|h4|blockquote|hr|table|figure)\b#i', $part)) {
                $out .= $part;
                continue;
            }
            $t = trim($part);
            if ($t === '' || preg_match('#^(<br\s*/?>\s*)+$#i', $t)) {
                continue;
            }
            foreach (preg_split('#(?:<br\s*/?>\s*){2,}#i', $t) as $chunk) {
                $chunk = trim(preg_replace('#^(<br\s*/?>\s*)+|(<br\s*/?>\s*)+$#i', '', $chunk) ?? $chunk);
                if ($chunk !== '') {
                    $out .= '<p>' . $chunk . '</p>';
                }
            }
        }
        return $out;
    }

    public static function safeUrl(string $url): bool
    {
        if ($url === '') {
            return false;
        }
        if (str_starts_with($url, '/') && !str_starts_with($url, '//')) {
            return true;
        }
        if (str_starts_with($url, '#')) {
            return true;
        }
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        return in_array($scheme, ['http', 'https', 'mailto', 'tel'], true);
    }

    private static function isInternal(string $url): bool
    {
        $host = Str::domain($url);
        $own = Str::domain((string) Env::get('APP_URL', ''));
        return $own !== '' && $host === $own;
    }

    /** Texte simple sur une ligne, sans balise. */
    public static function line(string $s, int $max = 255): string
    {
        return mb_substr(Str::clean(strip_tags($s), false), 0, $max);
    }

    /** Texte multi-lignes sans balise. */
    public static function text(string $s, int $max = 10000): string
    {
        return mb_substr(Str::clean(strip_tags($s)), 0, $max);
    }
}
