<?php
declare(strict_types=1);

namespace LD;

/**
 * Nettoyage HTML (liste blanche) pour les articles du blog.
 */
final class Html
{
    private const ALLOWED = [
        'p' => [], 'br' => [], 'hr' => [], 'h2' => ['id'], 'h3' => ['id'], 'h4' => [],
        'strong' => [], 'b' => [], 'em' => [], 'i' => [], 'u' => [], 's' => [], 'mark' => [],
        'a' => ['href', 'title', 'target', 'rel'], 'ul' => [], 'ol' => [], 'li' => [],
        'blockquote' => [], 'code' => [], 'pre' => [], 'figure' => [], 'figcaption' => [],
        'img' => ['src', 'alt', 'width', 'height'], 'table' => [], 'thead' => [], 'tbody' => [],
        'tr' => [], 'th' => [], 'td' => [],
    ];

    public static function sanitize(string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }
        $doc = new \DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"><div id="ld-root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
        libxml_clear_errors();
        $root = $doc->getElementById('ld-root');
        if (!$root) {
            return '';
        }
        self::walk($root);
        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $doc->saveHTML($child);
        }
        return trim($out);
    }

    private static function walk(\DOMNode $node): void
    {
        for ($i = $node->childNodes->length - 1; $i >= 0; $i--) {
            $child = $node->childNodes->item($i);
            if ($child instanceof \DOMElement) {
                $tag = strtolower($child->tagName);
                if (in_array($tag, ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'textarea', 'select', 'svg', 'math', 'link', 'meta'], true)) {
                    $node->removeChild($child);
                    continue;
                }
                if (in_array($tag, ['div', 'span', 'section', 'article', 'font'], true)) {
                    // On garde le contenu, on retire l'enveloppe.
                    self::walk($child);
                    if ($tag === 'div' || $tag === 'section' || $tag === 'article') {
                        $p = $child->ownerDocument->createElement('p');
                        $hasBlock = false;
                        foreach ($child->childNodes as $c) {
                            if ($c instanceof \DOMElement && in_array(strtolower($c->tagName), ['p', 'h2', 'h3', 'h4', 'ul', 'ol', 'blockquote', 'figure', 'pre', 'table', 'hr'], true)) {
                                $hasBlock = true;
                                break;
                            }
                        }
                        if (!$hasBlock && trim($child->textContent) !== '') {
                            while ($child->firstChild) {
                                $p->appendChild($child->firstChild);
                            }
                            $node->replaceChild($p, $child);
                            continue;
                        }
                    }
                    while ($child->firstChild) {
                        $node->insertBefore($child->firstChild, $child);
                    }
                    $node->removeChild($child);
                    continue;
                }
                if (!array_key_exists($tag, self::ALLOWED)) {
                    while ($child->firstChild) {
                        $node->insertBefore($child->firstChild, $child);
                    }
                    $node->removeChild($child);
                    continue;
                }
                $allowed = self::ALLOWED[$tag];
                for ($a = $child->attributes->length - 1; $a >= 0; $a--) {
                    $attr = $child->attributes->item($a);
                    $name = strtolower($attr->name);
                    if (!in_array($name, $allowed, true)) {
                        $child->removeAttribute($attr->name);
                        continue;
                    }
                    if (in_array($name, ['href', 'src'], true)) {
                        $v = trim($attr->value);
                        if (!preg_match('~^(https?://|/|#|mailto:|tel:)~i', $v)) {
                            $child->removeAttribute($attr->name);
                        }
                    }
                }
                if ($tag === 'a' && preg_match('~^https?://~i', $child->getAttribute('href'))) {
                    $child->setAttribute('rel', 'noopener');
                    $child->setAttribute('target', '_blank');
                }
                if ($tag === 'img') {
                    $child->setAttribute('loading', 'lazy');
                }
                self::walk($child);
            } elseif ($child instanceof \DOMComment) {
                $node->removeChild($child);
            }
        }
    }

    /**
     * Ajoute des ancres aux titres H2 et retourne [html, sommaire].
     */
    public static function withToc(string $html): array
    {
        $toc = [];
        $used = [];
        $html = preg_replace_callback('~<h2([^>]*)>(.*?)</h2>~is', static function ($m) use (&$toc, &$used) {
            $text = trim(html_entity_decode(strip_tags($m[2]), ENT_QUOTES, 'UTF-8'));
            $id = slugify($text);
            $base = $id;
            $n = 2;
            while (isset($used[$id])) {
                $id = $base . '-' . $n++;
            }
            $used[$id] = true;
            $toc[] = ['id' => $id, 'text' => $text];
            $attrs = preg_replace('~\sid="[^"]*"~', '', $m[1]);
            return '<h2' . $attrs . ' id="' . e($id) . '">' . $m[2] . '</h2>';
        }, $html) ?? $html;
        return [$html, $toc];
    }
}
