<?php
declare(strict_types=1);

namespace App\Widgets\Items;

use App\Data\Derived;
use App\Data\Index;
use App\Front\Pages;
use App\Widgets\Context;
use App\Widgets\Photos;
use App\Widgets\Widget;

/**
 * Carte de légende façon Panini : photo (si diffusable), poste, période, matchs, buts, saisons.
 * Au hasard parmi les légendes du musée (une autre chaque heure), ou le joueur choisi (?joueur=<slug>).
 */
final class CarteLegende extends Widget
{
    public function key(): string
    {
        return 'carte-legende';
    }

    public function name(string $lang = 'fr'): string
    {
        return $lang === 'en' ? 'Legend card' : 'Carte de légende';
    }

    public function description(string $lang = 'fr'): string
    {
        return $lang === 'en' ? 'A sticker-album card of a club legend: position, years, matches, goals. Random or chosen player.'
            : 'Une carte façon album Panini d’une légende du club : poste, période, matchs, buts. Au hasard ou joueur choisi.';
    }

    public function options(): array
    {
        return ['joueur' => ['label' => 'Joueur (adresse de sa fiche, ex. mecha-bazdarevic ; vide : au hasard)', 'label_en' => 'Player (page slug; empty: random)', 'type' => 'text', 'default' => '', 'pattern' => '/^[a-z0-9][a-z0-9-]{1,79}$/']];
    }

    public function ttl(): int
    {
        return 3600;
    }

    public function height(): int
    {
        return 520;
    }

    /** Légendes tirées au hasard : celles du musée (ou les plus capés, voir Pages::legends). @return list<array> */
    public static function pool(): array
    {
        $all = array_values(array_filter(Pages::legends(24), fn ($s) => in_array('joueur', (array) ($s['p']['roles'] ?? []), true)));
        // De préférence les légendes dont la photo peut sortir du musée (sinon : initiales sur la carte).
        $withPhoto = array_values(array_filter($all, fn ($s) => Photos::safe((string) ($s['image'] ?? '')) !== null));
        return count($withPhoto) >= 4 ? $withPhoto : $all;
    }

    /** Fiche publiée d'un joueur d'après le dernier segment de son adresse. */
    public static function bySlug(string $slug): ?array
    {
        foreach (Index::published('personne') as $s) {
            if (basename(rtrim((string) $s['path'], '/')) === $slug && in_array('joueur', (array) ($s['p']['roles'] ?? []), true)) {
                return $s;
            }
        }
        return null;
    }

    public function data(array $opt, Context $c): ?array
    {
        $s = $opt['joueur'] !== '' ? self::bySlug($opt['joueur']) : null;
        if (!$s) {
            $pool = self::pool();
            if (!$pool) {
                return null;
            }
            $s = $pool[crc32(date('Y-m-d H')) % count($pool)];
        }
        $p = (array) $s['p'];
        $tot = Derived::part('person_totals')[$s['id']] ?? [];
        $photo = Photos::safe((string) ($s['image'] ?? ''), 480);
        $name = (string) ($p['name'] ?? $s['title']);
        $arr = (int) ($p['arrival'] ?? 0);
        $dep = (int) ($p['departure'] ?? 0);
        return [
            'id' => (int) $s['id'],
            'name' => $name,
            'first' => (string) ($p['first'] ?? ''),
            'last' => (string) (($p['last'] ?? '') ?: $name),
            'position' => (string) ($p['position'] ?? ''),
            'period' => $arr ? $arr . ($dep && $dep !== $arr ? '–' . $dep : '') : '',
            'matches' => (int) ($tot['matches'] ?? 0),
            'goals' => (int) ($tot['goals'] ?? 0),
            'seasons' => (int) ($tot['seasons'] ?? 0),
            'international' => !empty($p['intl']),
            'initials' => mb_strtoupper(mb_substr((string) ($p['first'] ?? $name), 0, 1) . mb_substr((string) (($p['last'] ?? '') ?: $name), 0, 1)),
            'href' => (string) $s['path'],
            'image' => $photo ? ['url' => $photo['url'], 'credit' => $photo['credit'], 'license' => $photo['license']] : null,
        ];
    }

    public function body(array $d, Context $c): string
    {
        $img = $d['image']
            ? '<img src="' . e($d['image']['url']) . '" alt="' . e($d['name']) . '" loading="lazy" decoding="async">'
            : '<span class="w-card__ini" aria-hidden="true">' . e($d['initials']) . '</span>';
        $stat = fn (int $n, string $fr, string $en) => '<span><b>' . number_format($n, 0, ',', "\u{202F}") . '</b>' . e($c->tr($fr, $en)) . '</span>';
        $h = '<div class="w-card">';
        $h .= '<div class="w-card__top"><span>N° ' . e((string) $d['id']) . '</span>' . ($d['international'] ? '<span class="w-card__intl">' . e($c->tr('International', 'International')) . '</span>' : '') . '</div>';
        $h .= '<a class="w-card__photo" href="' . e($d['href']) . '" target="_blank" rel="noopener">' . $img . '</a>';
        if ($d['image'] && $d['image']['credit'] !== '') {
            $h .= '<p class="w-credit">© ' . e($d['image']['credit']) . '</p>';
        }
        $h .= '<div class="w-card__name"><small>' . e($d['first']) . '</small><b>' . e($d['last']) . '</b></div>';
        $h .= '<p class="w-card__pos">' . e(trim($d['position'] . ($d['period'] !== '' ? ' · ' . $d['period'] : ''), ' ·')) . '</p>';
        $h .= '<div class="w-card__stats">' . $stat($d['matches'], 'matchs', 'matches') . $stat($d['goals'], 'buts', 'goals') . $stat($d['seasons'], 'saisons', 'seasons') . '</div>';
        $h .= '</div>';
        return $h;
    }
}
