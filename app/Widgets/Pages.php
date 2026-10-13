<?php
declare(strict_types=1);

namespace App\Widgets;

use App\Core\Request;
use App\Core\Response;
use App\Services\I18n;

/** Page publique « Intégrez le musée sur votre site » (/widgets/) et code à copier. */
final class Pages
{
    public static function demo(Request $req): Response
    {
        $lang = I18n::lang();
        $list = [];
        foreach (Registry::all() as $k => $w) {
            if (Store::widget($k)['access'] === 'libre') {
                $list[] = ['w' => $w, 'src' => self::src($k, ['lang' => $lang, 'theme' => 'clair']), 'code' => self::iframe($w, ['lang' => $lang, 'theme' => 'clair']), 'script' => self::script($w, ['lang' => $lang, 'theme' => 'clair']), 'api' => Context::abs('/api/v1/widgets/' . $k . ($lang === 'en' ? '?lang=en' : '')), 'd' => $w->describe($lang)];
            }
        }
        return \App\Front\Pages::render('widgets/demo', ['list' => $list, 'resize' => self::resizeScript(), 'base' => Context::base(), 'loader' => self::loader()], [
            'title' => t('Intégrez le musée sur votre site'),
            'description' => t('Widgets gratuits de {site} à copier-coller sur votre site : ce jour-là, chiffre du jour, quiz du jour, records, palmarès, carte de légende…'),
            'styles' => ['css/widgets.css'],
            'scripts' => ['js/widgets.js'],
        ]);
    }

    /** Adresse de l'iframe (paramètres vides retirés). */
    public static function src(string $key, array $q): string
    {
        $q = array_filter($q, fn ($v) => $v !== '' && $v !== null);
        if (($q['lang'] ?? '') === 'fr') {
            unset($q['lang']);
        }
        return Context::abs('/w/' . $key . ($q ? '?' . http_build_query($q) : ''));
    }

    /** Code iframe à copier. */
    public static function iframe(Widget $w, array $q): string
    {
        $lang = ($q['lang'] ?? 'fr') === 'en' ? 'en' : 'fr';
        return '<iframe src="' . e(self::src($w->key(), $q)) . '" title="' . e($w->name($lang) . ' · ' . \App\Core\Club::s('site')) . '" data-musee="' . e($w->key()) . '"'
            . ' width="100%" height="' . $w->height() . '" style="border:0;max-width:440px" loading="lazy"></iframe>';
    }

    /**
     * Code « script » à copier : un <div data-musee-widget> par widget et, une fois par page, le
     * script d'intégration /embed.js (iframe isolée, hauteur automatique, chargement paresseux).
     */
    public static function script(Widget $w, array $q): string
    {
        $attrs = '';
        foreach (array_filter($q, fn ($v) => $v !== '' && $v !== null) as $k => $v) {
            $attrs .= ' data-' . e((string) $k) . '="' . e((string) $v) . '"';
        }
        return '<div data-musee-widget="' . e($w->key()) . '"' . $attrs . '></div>' . "\n" . self::loader();
    }

    /** Balise du script d'intégration (une seule par page, même avec plusieurs widgets). */
    public static function loader(): string
    {
        return '<script src="' . e(Context::abs('/embed.js')) . '" async></script>';
    }

    /** Facultatif : quelques lignes chez le partenaire pour que l'iframe prenne la hauteur du widget. */
    public static function resizeScript(): string
    {
        return '<script>addEventListener("message",function(e){var d=e.data;if(!d||d.type!=="musee-widget")return;'
            . 'document.querySelectorAll("iframe[data-musee]").forEach(function(f){if(f.contentWindow===e.source)f.style.height=d.height+"px"})})</script>';
    }
}
