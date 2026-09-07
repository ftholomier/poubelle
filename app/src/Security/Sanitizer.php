<?php

declare(strict_types=1);

namespace App\Security;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

/**
 * Nettoyage du HTML produit par l'éditeur WYSIWYG (liste blanche stricte).
 * Empêche toute injection de script/CSS/iframe depuis le back-office.
 */
final class Sanitizer
{
    /** @var array<string,array<int,string>> balise => attributs autorisés */
    private const ALLOWED = [
        'p' => ['class'], 'br' => [], 'strong' => [], 'b' => [], 'em' => [], 'i' => [],
        'u' => [], 's' => [], 'mark' => [], 'small' => [], 'sub' => [], 'sup' => [],
        'h2' => ['id'], 'h3' => ['id'], 'h4' => ['id'], 'h5' => ['id'], 'h6' => ['id'],
        'ul' => [], 'ol' => ['start'], 'li' => [],
        'blockquote' => [], 'hr' => [], 'pre' => [], 'code' => [],
        'a' => ['href', 'title', 'target', 'rel'],
        'img' => ['src', 'alt', 'title', 'width', 'height', 'loading'],
        'figure' => ['class'], 'figcaption' => [],
        'table' => ['class'], 'thead' => [], 'tbody' => [], 'tr' => [], 'th' => ['colspan', 'rowspan'], 'td' => ['colspan', 'rowspan'],
        'span' => ['class'], 'div' => ['class'],
    ];

    private const ALLOWED_CLASSES = [
        'lead', 'text-center', 'text-right', 'highlight', 'muted', 'badge',
        'table', 'figure', 'note', 'callout',
    ];

    public static function html(string $dirty): string
    {
        $dirty = trim($dirty);
        if ($dirty === '') {
            return '';
        }

        // Retrait préalable des blocs manifestement dangereux.
        $dirty = preg_replace('#<(script|style|iframe|object|embed|form|input|button|link|meta|svg)\b[^>]*>.*?</\1>#is', '', $dirty) ?? '';
        $dirty = preg_replace('#<(script|style|iframe|object|embed|form|input|button|link|meta|svg)\b[^>]*/?>#is', '', $dirty) ?? '';

        $doc = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML(
            '<?xml encoding="UTF-8"><div id="lcal-root">' . $dirty . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new DOMXPath($doc);
        $root = $doc->getElementById('lcal-root');
        if (!$root) {
            $nodes = $xpath->query('//div[@id="lcal-root"]');
            $root = $nodes && $nodes->length ? $nodes->item(0) : null;
        }
        if (!$root instanceof DOMElement) {
            return '';
        }

        // Suppression des commentaires (peuvent masquer des charges utiles).
        foreach (iterator_to_array($xpath->query('//comment()') ?: []) as $comment) {
            $comment->parentNode?->removeChild($comment);
        }

        self::cleanNode($root);

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $doc->saveHTML($child);
        }
        return trim($out);
    }

    private static function cleanNode(DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof DOMElement) {
                $tag = strtolower($child->tagName);

                if (!array_key_exists($tag, self::ALLOWED)) {
                    // Balise inconnue : on conserve son contenu, on jette l'enveloppe.
                    self::cleanNode($child);
                    while ($child->firstChild) {
                        $node->insertBefore($child->firstChild, $child);
                    }
                    $node->removeChild($child);
                    continue;
                }

                foreach (iterator_to_array($child->attributes) as $attr) {
                    $name = strtolower($attr->nodeName);
                    if (!in_array($name, self::ALLOWED[$tag], true)) {
                        $child->removeAttribute($attr->nodeName);
                        continue;
                    }
                    $value = trim((string) $attr->nodeValue);

                    if ($name === 'href' || $name === 'src') {
                        if (!self::safeUrl($value)) {
                            $child->removeAttribute($attr->nodeName);
                            continue;
                        }
                    }
                    if ($name === 'class') {
                        $kept = array_values(array_intersect(
                            preg_split('/\s+/', $value) ?: [],
                            self::ALLOWED_CLASSES
                        ));
                        if ($kept === []) {
                            $child->removeAttribute('class');
                        } else {
                            $child->setAttribute('class', implode(' ', $kept));
                        }
                    }
                }

                // Liens externes : ouverture sûre.
                if ($tag === 'a' && $child->getAttribute('target') === '_blank') {
                    $child->setAttribute('rel', 'noopener noreferrer');
                }
                if ($tag === 'img') {
                    $child->setAttribute('loading', 'lazy');
                    if ($child->getAttribute('alt') === '') {
                        $child->setAttribute('alt', '');
                    }
                }

                self::cleanNode($child);
            }
        }
    }

    private static function safeUrl(string $url): bool
    {
        if ($url === '') {
            return false;
        }
        if (preg_match('/^\s*(javascript|vbscript|file|data)\s*:/i', $url)) {
            // Seules les images data:image/... raisonnables sont tolérées.
            return (bool) preg_match('#^data:image/(png|jpeg|jpg|gif|webp);base64,[A-Za-z0-9+/=\s]+$#i', $url);
        }
        return (bool) preg_match('#^(https?://|/|\#|mailto:|tel:)#i', $url);
    }

    /** Texte brut : échappement complet. */
    public static function text(string $value, int $maxLength = 5000): string
    {
        $value = strip_tags($value);
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';
        return mb_substr(trim($value), 0, $maxLength);
    }

    /** Slug URL sûr. */
    public static function slug(string $value): string
    {
        $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
        $value = strtolower((string) preg_replace('/[^a-zA-Z0-9]+/', '-', $value));
        return trim($value, '-') ?: 'page';
    }
}
