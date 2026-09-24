<?php
declare(strict_types=1);

namespace App\Support;

/**
 * Icônes SVG en ligne. Traits 2–2.4, extrémités arrondies, aucune bibliothèque.
 * Les icônes sont décoratives : aria-hidden par défaut, le libellé vient du texte voisin.
 */
final class Icon
{
    private const PATHS = [
        'search'   => '<circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/>',
        'pin'      => '<path d="M12 21s7-6.2 7-11a7 7 0 10-14 0c0 4.8 7 11 7 11z"/><circle cx="12" cy="10" r="2.6"/>',
        'globe'    => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.5 2.7 2.5 15.3 0 18M12 3c-2.5 2.7-2.5 15.3 0 18"/>',
        'arrow-r'  => '<path d="M5 12h14M13 6l6 6-6 6"/>',
        'arrow-l'  => '<path d="M19 12H5M11 18l-6-6 6-6"/>',
        'arrow-up' => '<path d="M12 19V5M6 11l6-6 6 6"/>',
        'check'    => '<path d="M4 12.5l5 5L20 6.5"/>',
        'upload'   => '<path d="M12 16V4M7 9l5-5 5 5"/><path d="M4 16v3a1 1 0 001 1h14a1 1 0 001-1v-3"/>',
        'download' => '<path d="M12 4v12M7 11l5 5 5-5"/><path d="M4 20h16"/>',
        'star'     => '<path d="M12 3.5l2.6 5.5 6 .8-4.4 4.2 1.1 6-5.3-2.9-5.3 2.9 1.1-6L3.4 9.8l6-.8z"/>',
        'close'    => '<path d="M6 6l12 12M18 6L6 18"/>',
        'menu'     => '<path d="M4 7h16M4 12h16M4 17h16"/>',
        'send'     => '<path d="M4 12l16-8-6 16-2.5-6.5z"/>',
        'robot'    => '<rect x="4" y="8" width="16" height="11" rx="3"/><path d="M12 8V4.5"/><circle cx="12" cy="3.5" r="1.2"/>'
                    . '<circle cx="9.2" cy="13.5" r="1.1" fill="currentColor" stroke="none"/>'
                    . '<circle cx="14.8" cy="13.5" r="1.1" fill="currentColor" stroke="none"/>',
        'translate'=> '<path d="M4 5h9M8 5v3c0 4-2 6-4 7M6 11c1.5 3 4 5 7 5M13 19l4-9 4 9M14.6 16h5.8"/>',
        'briefcase'=> '<rect x="3" y="7.5" width="18" height="12" rx="2.5"/><path d="M9 7.5V6a2 2 0 012-2h2a2 2 0 012 2v1.5M3 12.5h18"/>',
        'user'     => '<circle cx="12" cy="8" r="3.6"/><path d="M4.5 20c.9-3.8 3.9-5.8 7.5-5.8s6.6 2 7.5 5.8"/>',
        'building' => '<rect x="5" y="4" width="14" height="16" rx="2"/><path d="M9 8h2M13 8h2M9 12h2M13 12h2M9 16h6"/>',
        'clock'    => '<circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/>',
        'euro'     => '<path d="M17.5 6.5A6.5 6.5 0 007 10m0 4a6.5 6.5 0 0010.5 3.5M4.5 10.5h7M4.5 13.5h7"/>',
        'calendar' => '<rect x="4" y="5.5" width="16" height="15" rx="2.5"/><path d="M4 10h16M9 3.5v4M15 3.5v4"/>',
        'file'     => '<path d="M14 3.5H7.5a2 2 0 00-2 2v13a2 2 0 002 2h9a2 2 0 002-2V8z"/><path d="M14 3.5V8h4.5"/>',
        'lock'     => '<rect x="5" y="10.5" width="14" height="9.5" rx="2.5"/><path d="M8.5 10.5V7.8a3.5 3.5 0 017 0v2.7"/>',
        'shield'   => '<path d="M12 3.5l7 2.8v5c0 4.3-2.9 7.7-7 9.2-4.1-1.5-7-4.9-7-9.2v-5z"/>',
        'sparkle'  => '<path d="M12 4l1.6 4.4L18 10l-4.4 1.6L12 16l-1.6-4.4L6 10l4.4-1.6z"/>',
        'plus'     => '<path d="M12 5v14M5 12h14"/>',
        'filter'   => '<path d="M4 6h16M7 12h10M10 18h4"/>',
        'facebook' => '<path d="M14 8.5h2.5V5.5H14c-2 0-3.5 1.5-3.5 3.5v2H8v3h2.5v6h3v-6H16l.5-3h-3v-1.6c0-.5.4-.9 1-.9z" fill="currentColor" stroke="none"/>',
        'instagram'=> '<rect x="4" y="4" width="16" height="16" rx="4.5"/><circle cx="12" cy="12" r="3.6"/><circle cx="16.8" cy="7.2" r="1" fill="currentColor" stroke="none"/>',
        'linkedin' => '<rect x="4" y="4" width="16" height="16" rx="3"/><path d="M8 10.5V16M8 7.6v.1M12 16v-3.2a1.8 1.8 0 013.6 0V16"/>',
        'youtube'  => '<rect x="3" y="6" width="18" height="12" rx="4"/><path d="M11 9.8l3.6 2.2-3.6 2.2z" fill="currentColor" stroke="none"/>',

        // Familles de métiers.
        'mic'      => '<rect x="9" y="3" width="6" height="11" rx="3"/><path d="M5.5 11a6.5 6.5 0 0013 0M12 17.5V21M8.5 21h7"/>',
        'bulb'     => '<path d="M9 18h6M10 21h4"/><path d="M12 3a6 6 0 00-3.5 10.9c.7.5 1 1.3 1 2.1h5c0-.8.3-1.6 1-2.1A6 6 0 0012 3z"/>',
        'stage'    => '<path d="M3 4h18M5 4v16M19 4v16M3 20h18"/><path d="M5 4c1.5 3.5 4 5 7 5s5.5-1.5 7-5"/>',
        'clipboard'=> '<rect x="5" y="4.5" width="14" height="16.5" rx="2"/><path d="M9 4.5V3h6v1.5M8.5 10h7M8.5 13.5h7M8.5 17h4"/>',
        'camera'   => '<rect x="3" y="7" width="12" height="10" rx="2"/><path d="M15 10.5l6-3.5v10l-6-3.5z"/>',
        'film'     => '<rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 3v18M16 3v18M4 7.5h4M4 12h4M4 16.5h4M16 7.5h4M16 12h4M16 16.5h4"/>',
        'shirt'    => '<path d="M8.5 3.5L3 6.5l2 4 3-1.2V21h8V9.3l3 1.2 2-4-5.5-3c-.6 1.6-2.1 2.6-3.5 2.6s-2.9-1-3.5-2.6z"/>',
        'clapper'  => '<rect x="3.5" y="10" width="17" height="10" rx="1.5"/><path d="M3.5 10l1.8-5.6 15.2 2.3-.8 3.3"/><path d="M8.2 4.9l2.6 4.6M13.3 5.6l2.6 4.6"/>',
        'mask'     => '<path d="M4 4.5c2.7 1 5.3 1 8 0 2.7 1 5.3 1 8 0V11c0 5-3.6 9-8 9s-8-4-8-9z"/><path d="M8 10.5c.6-.6 1.6-.6 2.2 0M13.8 10.5c.6-.6 1.6-.6 2.2 0M9 14.5c1.7 1.5 4.3 1.5 6 0"/>',
        'party'    => '<path d="M4 20l4.5-12.5 8 8z"/><path d="M14 3.5c.5 1.3.3 2.5-.5 3.5M18 6l-1.5 1.5M20.5 10c-1.2-.3-2.3 0-3.2.8M16.5 3v1"/>',
    ];

    public static function svg(string $name, int $size = 18, string $color = 'currentColor', float $stroke = 2.2): string
    {
        $path = self::PATHS[$name] ?? null;
        if ($path === null) {
            return '';
        }
        return sprintf(
            '<svg width="%d" height="%d" viewBox="0 0 24 24" fill="none" stroke="%s" stroke-width="%s"'
            . ' stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">%s</svg>',
            $size,
            $size,
            e($color),
            $stroke,
            $path,
        );
    }

    /** Étoile pleine, pour la note Google. */
    public static function star(int $size = 15, string $color = '#FFC531'): string
    {
        return sprintf(
            '<svg width="%d" height="%d" viewBox="0 0 24 24" fill="%s" aria-hidden="true" focusable="false">'
            . '<path d="M12 3.5l2.6 5.5 6 .8-4.4 4.2 1.1 6-5.3-2.9-5.3 2.9 1.1-6L3.4 9.8l6-.8z"/></svg>',
            $size,
            $size,
            e($color),
        );
    }

    public static function has(string $name): bool
    {
        return isset(self::PATHS[$name]);
    }
}
