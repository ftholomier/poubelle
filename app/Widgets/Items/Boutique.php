<?php
declare(strict_types=1);

namespace App\Widgets\Items;

use App\Shop\Catalog;
use App\Shop\Orders;
use App\Shop\ShopPages;
use App\Widgets\Context;
use App\Widgets\Widget;

/**
 * Boutique du musée : quelques objets en vente (Catalog::sellable), avec l'aperçu du produit
 * (le même fichier que le catalogue : ShopPages::previewUrl), le nom, le type d'objet, le prix
 * (« à partir de » s'il dépend de la taille) et le lien vers la fiche. Un objet choisi
 * (?produit=<identifiant de la fiche /boutique/<id>/>) ; sinon trois objets, différents chaque
 * jour. Rien à montrer si la boutique est fermée au public ou n'a aucun objet en vente.
 */
final class Boutique extends Widget
{
    /** Essais seulement : force l'ouverture (true) ou la fermeture (false) de la boutique. */
    public static ?bool $open = null;

    public const COUNT = 3;

    public function key(): string
    {
        return 'boutique';
    }

    public function name(string $lang = 'fr'): string
    {
        return $lang === 'en' ? 'Museum shop' : 'La boutique du musée';
    }

    public function description(string $lang = 'fr'): string
    {
        return $lang === 'en' ? 'A few items from the museum shop, with their price and a link to buy them.'
            : 'Quelques objets de la boutique du musée, avec leur prix et le lien pour les commander.';
    }

    public function options(): array
    {
        return [
            'produit' => ['label' => 'Objet (adresse de sa fiche : /boutique/<objet>/ ; vide : trois objets par jour)', 'label_en' => 'Item (from its page: /boutique/<item>/; empty: three items a day)', 'type' => 'text', 'default' => '', 'pattern' => '/^[a-z0-9][a-z0-9-]{0,59}$/'],
            'affichage' => ['label' => 'Affichage', 'label_en' => 'Layout', 'type' => 'select', 'choices' => ['carrousel' => 'Carrousel', 'grille' => 'Grille'], 'default' => 'carrousel'],
        ];
    }

    public function height(): int
    {
        return 460;
    }

    public function ttl(): int
    {
        return 900;
    }

    public static function isOpen(): bool
    {
        return self::$open ?? Orders::open();
    }

    /** Objets en vente, dans l'ordre du catalogue. @return list<array> */
    public static function products(): array
    {
        return array_values(array_filter(Catalog::models(), fn ($m) => Catalog::sellable($m)));
    }

    public function data(array $opt, Context $c): ?array
    {
        if (!self::isOpen()) {
            return null;
        }
        $all = self::products();
        if (!$all) {
            return null;
        }
        $pick = [];
        if ($opt['produit'] !== '') {
            foreach ($all as $m) {
                if ($m['id'] === $opt['produit']) {
                    $pick = [$m];
                }
            }
        }
        if (!$pick) {
            // Sélection du jour : même tirage pour tous dans la journée.
            $day = date('Y-m-d');
            usort($all, fn ($a, $b) => crc32('boutique|' . $day . '|' . $a['id']) <=> crc32('boutique|' . $day . '|' . $b['id']));
            $pick = array_slice($all, 0, self::COUNT);
        }
        $items = [];
        foreach ($pick as $m) {
            $img = ShopPages::previewUrl($m);
            $items[] = [
                'id' => $m['id'],
                'name' => (string) $m['name'],
                'kind' => (string) (Catalog::support($m['support'])['name'] ?? ''),
                'price' => (int) $m['sale']['price'],
                'from' => $m['sale']['extra'] !== [],
                'price_label' => ($m['sale']['extra'] !== [] ? $c->tr('à partir de', 'from') . ' ' : '') . Orders::money((int) $m['sale']['price']),
                'image' => str_starts_with($img, 'data:') ? $img : Context::abs($img),
                'href' => '/boutique/' . $m['id'] . '/',
            ];
        }
        return ['items' => $items, 'layout' => $opt['affichage'], 'count' => count($all), 'href' => '/boutique/'];
    }

    public function body(array $d, Context $c): string
    {
        $h = '<p class="w-eyebrow">' . e($c->tr('La boutique du musée', 'The museum shop')) . '</p>';
        $h .= '<ul class="w-shop w-shop--' . e($d['layout']) . (count($d['items']) === 1 ? ' w-shop--one' : '') . '">';
        foreach ($d['items'] as $it) {
            $h .= '<li><a href="' . e($it['href']) . '" target="_blank" rel="noopener">'
                . '<span class="w-shop__img"><img src="' . e($it['image']) . '" alt="" loading="lazy" decoding="async"></span>'
                . '<b>' . e($it['name']) . '</b>'
                . ($it['kind'] !== '' ? '<small>' . e($it['kind']) . '</small>' : '')
                . '<span class="w-shop__price">' . e($it['price_label']) . '</span></a></li>';
        }
        $h .= '</ul>';
        return $h . '<p><a class="w-btn" href="' . e($d['href']) . '" target="_blank" rel="noopener">' . e($c->tr('Toute la boutique', 'The whole shop')) . ' →</a></p>';
    }
}
