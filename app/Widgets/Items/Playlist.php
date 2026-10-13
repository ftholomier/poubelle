<?php
declare(strict_types=1);

namespace App\Widgets\Items;

use App\Widgets\Context;
use App\Widgets\Screen\Playlists;
use App\Widgets\Widget;

/**
 * Playlist écran : le mode écran (/ecran/<playlist>, écrans de stade, télés, bornes) est un widget
 * comme les autres pour l'accès (libre, réservé, payant, désactivé : « réservé » par défaut, la
 * clé du partenaire dans l'adresse). Ses données : la liste des playlists (nom, nombre de slides,
 * durée, format) ; son iframe présente le mode écran. Les slides elles-mêmes sont dessinées par
 * App\Widgets\Screen (Page en HTML, Image en PNG).
 */
final class Playlist extends Widget
{
    public function key(): string
    {
        return 'playlist';
    }

    public function name(string $lang = 'fr'): string
    {
        return $lang === 'en' ? 'Screen playlist' : 'Playlist écran';
    }

    public function description(string $lang = 'fr'): string
    {
        return $lang === 'en' ? 'Full-screen sequence of museum content for stadium screens, TVs and kiosks.'
            : 'Enchaînement plein écran de contenus du musée pour les écrans de stade, télés et bornes.';
    }

    public function defaultAccess(): string
    {
        return 'reserve';
    }

    public function height(): int
    {
        return 420;
    }

    public function ttl(): int
    {
        return 300;
    }

    public function data(array $opt, Context $c): ?array
    {
        $list = [];
        foreach (Playlists::all() as $id => $p) {
            $list[] = ['id' => $id, 'name' => (string) $p['name'], 'preset' => !empty($p['preset']), 'slides' => count($p['slides']), 'duration' => Playlists::duration($p), 'format' => $p['format'], 'screen' => '/ecran/' . $id];
        }
        return ['playlists' => $list, 'href' => '/widgets/#ecrans'];
    }

    public function body(array $d, Context $c): string
    {
        $h = '<p class="w-eyebrow">' . e($c->tr('Mode écran', 'Screen mode')) . '</p><p class="w-title">' . e($c->tr('Le musée sur grand écran', 'The museum on the big screen')) . '</p><ul class="w-list">';
        foreach ($d['playlists'] as $p) {
            $min = intdiv($p['duration'], 60);
            $h .= '<li><b>' . e($p['name']) . '</b> ' . e($c->tr($p['slides'] . ' slides, ' . ($min ? $min . ' min ' : '') . ($p['duration'] % 60) . ' s', $p['slides'] . ' slides, ' . ($min ? $min . ' min ' : '') . ($p['duration'] % 60) . ' s')) . '</li>';
        }
        return $h . '</ul><p><a class="w-btn" href="' . e($d['href']) . '" target="_blank" rel="noopener">' . e($c->tr('Les écrans du musée', 'Museum screens')) . ' →</a></p>';
    }
}
