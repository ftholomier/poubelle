<?php
declare(strict_types=1);

namespace App\Widgets\Items;

use App\Front\Donations;
use App\Front\Menus;
use App\Widgets\Context;
use App\Widgets\Widget;

/**
 * Soutenir le musée : seulement les liens qui existent et sont ouverts pour ce musée —
 * faire un don (dons activés avec un moyen de paiement prêt, bouton « don » affiché), boutique
 * (ouverte au public, bouton affiché), contribuer (bouton affiché). Les libellés sont ceux des
 * boutons du site (Éditorial › Menus) ; rien à montrer si aucun n'est ouvert.
 */
final class Soutenir extends Widget
{
    public function key(): string
    {
        return 'soutenir';
    }

    public function name(string $lang = 'fr'): string
    {
        return $lang === 'en' ? 'Support the museum' : 'Soutenir le musée';
    }

    public function description(string $lang = 'fr'): string
    {
        return $lang === 'en' ? 'Donate, shop, contribute: only the ways of supporting the museum that are open.'
            : 'Faire un don, la boutique, contribuer : seulement les moyens de soutenir le musée qui sont ouverts.';
    }

    public function height(): int
    {
        return 380;
    }

    public function ttl(): int
    {
        return 600;
    }

    /** Liens ouverts : clé => chemin du musée. @return array<string,string> */
    public static function open(): array
    {
        $out = [];
        $paths = ['don' => '/faire-un-don/', 'boutique' => '/boutique/', 'contribuer' => '/contribuer/'];
        foreach ($paths as $k => $path) {
            $b = Menus::button($k);
            if (!$b || !empty($b['ext'])) {
                continue;
            }
            if ($k === 'don' && !Donations::methods()) {
                continue;
            }
            if ($k === 'boutique' && !\App\Shop\Orders::open()) {
                continue;
            }
            $out[$k] = $path;
        }
        return $out;
    }

    public function data(array $opt, Context $c): ?array
    {
        $text = [
            'don' => [$c->tr('Faire un don', 'Make a donation'), $c->tr('Le musée vit de bénévolat et de dons : chaque euro aide à numériser les archives.', 'The museum runs on volunteers and donations: every euro helps digitise the archives.')],
            'boutique' => [$c->tr('La boutique', 'The shop'), $c->tr('Posters, livres et objets du musée.', 'Posters, books and museum goods.')],
            'contribuer' => [$c->tr('Contribuer', 'Contribute'), $c->tr('Une photo, un programme, un souvenir ? Enrichissez le musée.', 'A photo, a programme, a memory? Add it to the museum.')],
        ];
        $links = [];
        foreach (self::open() as $k => $path) {
            $links[] = ['kind' => $k, 'label' => $text[$k][0], 'text' => $text[$k][1], 'href' => $path];
        }
        return $links ? ['links' => $links, 'href' => $links[0]['href']] : null;
    }

    public function body(array $d, Context $c): string
    {
        $h = '<p class="w-eyebrow">' . e($c->tr('Soutenir le musée', 'Support the museum')) . '</p><ul class="w-help">';
        foreach ($d['links'] as $i => $l) {
            $h .= '<li><a class="' . ($i === 0 ? 'w-btn' : 'w-btn w-btn--ghost') . '" href="' . e($l['href']) . '" target="_blank" rel="noopener">' . e($l['label']) . ' →</a><span class="w-text small">' . e($l['text']) . '</span></li>';
        }
        return $h . '</ul>';
    }
}
