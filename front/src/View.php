<?php

declare(strict_types=1);

/** Gabarits PHP (views/*.php). */
final class View
{
    /** Rendu d'un gabarit en chaîne. */
    public static function fetch(string $tpl, array $vars = []): string
    {
        $file = APP_ROOT . '/views/' . $tpl . '.php';
        if (!preg_match('#^[a-z0-9_/-]+$#', $tpl) || !is_file($file)) {
            throw new RuntimeException('Gabarit introuvable : ' . $tpl);
        }
        extract($vars, EXTR_SKIP);
        ob_start();
        try {
            include $file;
        } catch (Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return (string) ob_get_clean();
    }

    public static function render(string $tpl, array $vars = []): void
    {
        echo self::fetch($tpl, $vars);
    }

    /** Bouton à fond glissant de la maquette. */
    public static function btn(string $label, string $href, string $class = 'btn-y btn-md', array $attrs = []): string
    {
        $a = '';
        foreach ($attrs as $k => $v) {
            $a .= ' ' . e($k) . '="' . e($v) . '"';
        }
        return '<a class="btn ' . e($class) . '" href="' . e($href) . '"' . $a . '><span class="fx"></span><span>' . e($label) . '</span></a>';
    }

    /** Sur-titre de section : « 01 — Libellé ». */
    public static function eyebrow(string $num, string $label, bool $dark = false): string
    {
        return '<div class="eyebrow' . ($dark ? ' eyebrow--dark' : '') . '">' . ($num !== '' ? '<b>' . e($num) . '</b><i></i>' : '<i></i>') . '<span>' . e($label) . '</span></div>';
    }

    /** Carte d'actualité. */
    public static function newsCard(array $n, int $i = 0): string
    {
        $img = Content::postImage($n);
        $delay = ($i % 3) * 90;
        return '<a class="ncard" href="' . e($n['path']) . '" data-reveal="up" data-reveal-delay="' . $delay . '" data-cat="' . e($n['cat'] ?? '') . '">'
            . '<div class="ncard__img' . ($img === '' ? ' ncard__img--empty' : '') . '">'
            . ($img !== '' ? '<img src="' . e($img) . '" alt="' . e(plain((string) $n['title'])) . '" loading="lazy">' : '')
            . '<div class="pill">' . e($n['cat'] ?? '') . '</div></div>'
            . '<div class="ncard__b"><div class="ncard__d">' . e(date_fr((string) $n['date'])) . '</div>'
            . '<div class="ncard__t">' . e(plain((string) $n['title'])) . '</div>'
            . '<div class="ncard__x">' . e(Content::postExcerpt($n, 150)) . '</div>'
            . '<div class="ncard__m">Lire l\'actu <i></i></div></div></a>';
    }

    /** Carte d'avis client. */
    public static function reviewCard(array $a, int $delay = 0): string
    {
        $stars = str_repeat('★', max(1, min(5, (int) ($a['rating'] ?? 5))));
        return '<div class="acard" data-reveal="up" data-reveal-delay="' . $delay . '">'
            . '<div class="stars" aria-label="' . (int) ($a['rating'] ?? 5) . ' sur 5">' . $stars . '</div>'
            . '<div class="acard__x">« ' . nl2br(e(trim((string) $a['text']))) . ' »</div>'
            . '<div class="acard__w">' . e(trim((string) $a['name'])) . '</div>'
            . '<div class="acard__s">' . e(trim((string) (($a['where'] ?? '') !== '' ? $a['where'] : 'Avis ' . ($a['source'] ?? 'Google')))) . '</div></div>';
    }

    /** HTML de contenu éditorial (saisi dans le back-office, nettoyé à l'enregistrement). */
    public static function html(?string $html): string
    {
        return (string) $html;
    }
}
