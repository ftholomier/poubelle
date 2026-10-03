<?php
declare(strict_types=1);

namespace App\Admin;

/**
 * Nettoyage du HTML saisi dans le back-office (liste blanche de balises et
 * d'attributs). Tout le reste est retiré : scripts, styles, classes, événements.
 */
final class Html
{
    private const ALLOWED = [
        'p' => [], 'br' => [], 'strong' => [], 'em' => [], 'u' => [], 'h2' => [], 'h3' => [], 'h4' => [],
        'ul' => [], 'ol' => [], 'li' => [], 'blockquote' => [], 'cite' => [], 'sup' => [], 'sub' => [], 'hr' => [],
        'a' => ['href', 'title', 'target', 'rel'], 'img' => ['src', 'alt', 'width', 'height', 'loading', 'data-media'],
        'figure' => [], 'figcaption' => [], 'table' => [], 'thead' => [], 'tbody' => [], 'tr' => [], 'th' => ['colspan', 'rowspan'], 'td' => ['colspan', 'rowspan'],
    ];
    private const RENAME = ['b' => 'strong', 'i' => 'em', 'h1' => 'h2', 'h5' => 'h4', 'h6' => 'h4', 'div' => 'p'];
    private const DROP = ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'select', 'textarea', 'svg', 'math', 'template', 'meta', 'link', 'noscript'];

    public static function clean(?string $html): string
    {
        $html = trim((string) $html);
        if ($html === '') {
            return '';
        }
        if (!preg_match('#<(/?[a-z][a-z0-9]*)\b[^>]*>#i', $html)) {
            // Texte brut : paragraphes et retours à la ligne.
            $paras = preg_split('/\R{2,}/u', $html) ?: [];
            return implode('', array_map(fn ($p) => '<p>' . nl2br(e(trim($p)), false) . '</p>', array_filter($paras, fn ($p) => trim($p) !== '')));
        }
        $doc = new \DOMDocument();
        $prev = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"?><div id="__root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        $root = $doc->getElementById('__root');
        if (!$root) {
            return e(strip_tags($html));
        }
        self::walk($root, $doc);
        $out = '';
        foreach ($root->childNodes as $n) {
            $out .= $doc->saveHTML($n);
        }
        $out = preg_replace(['#<p>(\s|&nbsp;|<br>)*</p>#u', '#(<br>\s*){3,}#'], ['', '<br><br>'], $out);
        return trim((string) $out);
    }

    private static function walk(\DOMNode $node, \DOMDocument $doc): void
    {
        for ($i = $node->childNodes->length - 1; $i >= 0; $i--) {
            $ch = $node->childNodes->item($i);
            if ($ch instanceof \DOMComment || $ch instanceof \DOMProcessingInstruction) {
                $node->removeChild($ch);
                continue;
            }
            if (!$ch instanceof \DOMElement) {
                continue;
            }
            $tag = strtolower($ch->tagName);
            if (in_array($tag, self::DROP, true) || str_contains($tag, ':')) {
                $node->removeChild($ch);
                continue;
            }
            if (isset(self::RENAME[$tag])) {
                $new = $doc->createElement(self::RENAME[$tag]);
                while ($ch->firstChild) {
                    $new->appendChild($ch->firstChild);
                }
                foreach (iterator_to_array($ch->attributes ?? []) as $a) {
                    $new->setAttribute($a->name, $a->value);
                }
                $node->replaceChild($new, $ch);
                $ch = $new;
                $tag = self::RENAME[$tag];
            }
            self::walk($ch, $doc);
            if (!isset(self::ALLOWED[$tag])) {
                // Balise non autorisée (span, font…) : on garde son contenu.
                while ($ch->firstChild) {
                    $node->insertBefore($ch->firstChild, $ch);
                }
                $node->removeChild($ch);
                continue;
            }
            foreach (iterator_to_array($ch->attributes ?? []) as $a) {
                if (!in_array(strtolower($a->name), self::ALLOWED[$tag], true)) {
                    $ch->removeAttribute($a->name);
                }
            }
            if ($tag === 'a') {
                $href = trim($ch->getAttribute('href'));
                if ($href !== '' && !preg_match('#^(https?:|mailto:|tel:|/|\#)#i', $href)) {
                    $ch->removeAttribute('href');
                }
                if ($ch->getAttribute('target') !== '') {
                    $ch->setAttribute('target', '_blank');
                    $ch->setAttribute('rel', 'noopener');
                }
            }
            if ($tag === 'img') {
                $src = trim($ch->getAttribute('src'));
                if (!preg_match('#^(/|https?:)#i', $src)) {
                    $node->removeChild($ch);
                    continue;
                }
                $ch->setAttribute('loading', 'lazy');
            }
        }
    }

    /** Texte simple sur une ligne (sans balises ni retours). */
    public static function line(mixed $v, int $max = 500): string
    {
        $s = is_scalar($v) ? (string) $v : '';
        $s = trim(preg_replace('/\s+/u', ' ', strip_tags($s)));
        return mb_substr($s, 0, $max);
    }

    /** Texte simple sur plusieurs lignes. */
    public static function text(mixed $v, int $max = 20000): string
    {
        $s = is_scalar($v) ? (string) $v : '';
        $s = str_replace("\r\n", "\n", strip_tags($s));
        return mb_substr(trim(preg_replace("/\n{3,}/", "\n\n", $s)), 0, $max);
    }
}
