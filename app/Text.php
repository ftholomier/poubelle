<?php
declare(strict_types=1);

namespace App;

/** Petites aides de texte partagées par le front et le back-office. */
final class Text
{
    public static function e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /** Attribut HTML sûr (URL comprises : on refuse les schémas exotiques). */
    public static function attr(?string $value): string
    {
        return self::e($value);
    }

    public static function url(?string $value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }
        if (preg_match('#^(https?:)?//#i', $value) || str_starts_with($value, '/') || str_starts_with($value, 'mailto:') || str_starts_with($value, 'tel:') || str_starts_with($value, '#')) {
            return self::e($value);
        }
        return self::e('/' . ltrim($value, '/'));
    }

    public static function slug(string $value): string
    {
        $value = self::deaccent($value);
        $value = strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', $value) ?? '');
        return trim($value, '-');
    }

    public static function deaccent(string $value): string
    {
        if (class_exists(\Transliterator::class)) {
            $tr = \Transliterator::create('Any-Latin; Latin-ASCII');
            if ($tr !== null) {
                $out = $tr->transliterate($value);
                if ($out !== false) {
                    return $out;
                }
            }
        }
        $out = @iconv('UTF-8', 'ASCII//TRANSLIT', $value);
        return $out === false ? $value : $out;
    }

    public static function excerpt(string $html, int $length = 160): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', strip_tags($html)) ?? '');
        if (mb_strlen($text) <= $length) {
            return $text;
        }
        return rtrim(mb_substr($text, 0, $length - 1), " ,.;:!?") . '…';
    }

    public static function initials(string $name): string
    {
        $parts = preg_split('/\s+/u', trim($name)) ?: [];
        $out = '';
        foreach (\array_slice($parts, 0, 2) as $part) {
            $out .= mb_strtoupper(mb_substr($part, 0, 1));
        }
        return $out === '' ? '·' : $out;
    }

    /**
     * Nettoyage du HTML issu de l'éditeur WYSIWYG : liste blanche stricte de
     * balises et d'attributs, aucun script ni style en ligne.
     */
    public static function sanitizeHtml(string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }
        $allowed = ['p', 'br', 'strong', 'b', 'em', 'i', 'u', 'ul', 'ol', 'li', 'h2', 'h3', 'h4', 'a', 'blockquote'];
        // Ces balises sont supprimées avec leur contenu : on ne garde même pas le texte.
        $dropped = ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'svg', 'noscript'];

        $doc = new \DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"><div id="ioio-root">' . $html . '</div>', LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $doc->getElementById('ioio-root');
        if ($root === null) {
            return '';
        }

        $walk = static function (\DOMNode $node) use (&$walk, $allowed, $dropped): void {
            foreach (iterator_to_array($node->childNodes) as $child) {
                if ($child instanceof \DOMElement) {
                    $tag = strtolower($child->nodeName);
                    if (\in_array($tag, $dropped, true)) {
                        $node->removeChild($child);
                        continue;
                    }
                    if (!\in_array($tag, $allowed, true)) {
                        // On garde le texte, on jette la balise.
                        while ($child->firstChild !== null) {
                            $node->insertBefore($child->firstChild, $child);
                        }
                        $node->removeChild($child);
                        continue;
                    }
                    foreach (iterator_to_array($child->attributes ?? []) as $attr) {
                        $name = strtolower($attr->nodeName);
                        $keep = $tag === 'a' && \in_array($name, ['href', 'title'], true);
                        if (!$keep) {
                            $child->removeAttribute($attr->nodeName);
                        }
                    }
                    if ($tag === 'a') {
                        $href = trim($child->getAttribute('href'));
                        if ($href === '' || preg_match('/^\s*(javascript|data|vbscript):/i', $href)) {
                            $child->removeAttribute('href');
                        } elseif (preg_match('#^https?://#i', $href)) {
                            $child->setAttribute('rel', 'noopener');
                            $child->setAttribute('target', '_blank');
                        }
                    }
                    $walk($child);
                } elseif ($child instanceof \DOMComment) {
                    $node->removeChild($child);
                }
            }
        };
        $walk($root);

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $doc->saveHTML($child);
        }
        return trim($out);
    }
}
