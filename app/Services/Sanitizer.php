<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Nettoyage du HTML issu de l'éditeur du back-office.
 * Liste blanche stricte : ni script, ni style, ni attribut événementiel,
 * ni URL javascript:. Tout le reste est supprimé.
 */
final class Sanitizer
{
    private const ALLOWED = [
        'p' => [], 'br' => [], 'strong' => [], 'b' => [], 'em' => [], 'i' => [], 'u' => [],
        'h2' => ['id'], 'h3' => ['id'], 'h4' => ['id'],
        'ul' => [], 'ol' => [], 'li' => [], 'blockquote' => [],
        'a' => ['href', 'title', 'rel', 'target'],
        'img' => ['src', 'alt', 'width', 'height', 'loading'],
        'table' => [], 'thead' => [], 'tbody' => [], 'tr' => [], 'th' => [], 'td' => [],
        'hr' => [], 'figure' => [], 'figcaption' => [], 'code' => [], 'pre' => [],
    ];

    public static function html(string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }

        $document = new \DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML(
            '<?xml encoding="UTF-8"><div id="imtt-root">' . $html . '</div>',
            LIBXML_HTML_NODEFDTD | LIBXML_HTML_NOIMPLIED | LIBXML_NONET,
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $document->getElementById('imtt-root');
        if ($root === null) {
            return '';
        }

        self::clean($root);

        $out = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $out .= $document->saveHTML($child);
        }
        return trim($out);
    }

    private static function clean(\DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof \DOMText) {
                continue;
            }
            if ($child instanceof \DOMComment) {
                $child->parentNode?->removeChild($child);
                continue;
            }
            if (!$child instanceof \DOMElement) {
                $child->parentNode?->removeChild($child);
                continue;
            }

            $tag = strtolower($child->tagName);
            if (!isset(self::ALLOWED[$tag])) {
                // Balise interdite : on conserve son contenu textuel, pas la balise.
                if (in_array($tag, ['script', 'style', 'iframe', 'object', 'embed', 'form'], true)) {
                    $child->parentNode?->removeChild($child);
                    continue;
                }
                self::clean($child);
                while ($child->firstChild !== null) {
                    $child->parentNode?->insertBefore($child->firstChild, $child);
                }
                $child->parentNode?->removeChild($child);
                continue;
            }

            foreach (iterator_to_array($child->attributes ?? []) as $attribute) {
                /** @var \DOMAttr $attribute */
                $name = strtolower($attribute->name);
                if (!in_array($name, self::ALLOWED[$tag], true)) {
                    $child->removeAttribute($attribute->name);
                    continue;
                }
                if (in_array($name, ['href', 'src'], true) && !self::safeUrl($attribute->value)) {
                    $child->removeAttribute($attribute->name);
                }
            }

            // Un lien externe ne doit jamais donner la main sur l'onglet d'origine.
            if ($tag === 'a' && $child->getAttribute('target') === '_blank') {
                $child->setAttribute('rel', 'noopener noreferrer');
            }
            if ($tag === 'img') {
                $child->setAttribute('loading', 'lazy');
            }

            self::clean($child);
        }
    }

    private static function safeUrl(string $url): bool
    {
        $url = trim($url);
        if ($url === '') {
            return false;
        }
        if (str_starts_with($url, '/') || str_starts_with($url, '#')) {
            return true;
        }
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        return in_array($scheme, ['http', 'https', 'mailto'], true);
    }

    /** Texte brut : supprime tout balisage, conserve les retours à la ligne. */
    public static function text(string $input, int $max = 8000): string
    {
        $text = strip_tags($input);
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = (string) preg_replace('/\n{3,}/', "\n\n", $text);
        return mb_substr(trim($text), 0, $max);
    }
}
