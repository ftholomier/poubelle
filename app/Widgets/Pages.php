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
                $list[] = ['w' => $w, 'src' => self::src($k, ['lang' => $lang, 'theme' => 'clair']), 'code' => self::iframe($w, ['lang' => $lang, 'theme' => 'clair']), 'api' => Context::abs('/api/v1/widgets/' . $k . ($lang === 'en' ? '?lang=en' : ''))];
            }
        }
        return \App\Front\Pages::render('widgets/demo', ['list' => $list, 'resize' => self::resizeScript()], [
            'title' => t('Intégrez le musée sur votre site'),
            'description' => t('Widgets gratuits de {site} à copier-coller sur votre site : ce jour-là, chiffre du jour, carte de légende, photo du jour…'),
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

    /** Facultatif : quelques lignes chez le partenaire pour que l'iframe prenne la hauteur du widget. */
    public static function resizeScript(): string
    {
        return '<script>addEventListener("message",function(e){var d=e.data;if(!d||d.type!=="musee-widget")return;'
            . 'document.querySelectorAll("iframe[data-musee]").forEach(function(f){if(f.contentWindow===e.source)f.style.height=d.height+"px"})})</script>';
    }
}
