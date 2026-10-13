<?php
declare(strict_types=1);

namespace App\Widgets\Items;

use App\Data\Derived;
use App\Data\Index;
use App\Data\Totals;
use App\Widgets\Context;
use App\Widgets\Photos;
use App\Widgets\Store;
use App\Widgets\Widget;

/**
 * Carte de légende façon Panini : photo (si diffusable), poste, période, matchs, buts, saisons.
 * Au hasard parmi les légendes du musée (une autre chaque heure), ou le joueur choisi (?joueur=<slug>).
 *
 * Réglage de l'administrateur « source » (Widget::settings, back-office) : « legendes » (seulement
 * les fiches cochées « Légende ») ou « capes » (par défaut, proposition à valider : les légendes
 * cochées, et si aucune ne l'est, les 24 joueurs les plus capés d'après les totaux de référence
 * du club, App\Data\Totals, à défaut d'après les matchs fichés).
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

    public function settings(): array
    {
        return ['source' => [
            'label' => 'Joueurs tirés au hasard',
            'choices' => ['capes' => 'Légendes cochées ; si aucune, les plus capés (totaux de référence)', 'legendes' => 'Seulement les fiches cochées « Légende »'],
            'default' => 'capes',
            'help' => 'Proposition à valider : sans légende cochée, la carte montre les joueurs les plus capés.',
        ]];
    }

    public function ttl(): int
    {
        return 3600;
    }

    public function height(): int
    {
        return 520;
    }

    /** Joueurs tirés au hasard : les légendes cochées, ou les plus capés (réglage « source »). @return list<array> */
    public static function pool(): array
    {
        $isPlayer = fn ($s) => in_array('joueur', (array) ($s['p']['roles'] ?? []), true);
        $all = array_values(array_filter(Index::published('personne'), fn ($s) => !empty($s['p']['legend']) && $isPlayer($s)));
        $source = Store::widget('carte-legende')['settings']['source'] ?? 'capes';
        if (!$all && $source === 'capes') {
            $all = self::mostCapped(24);
        }
        // De préférence les légendes dont la photo peut sortir du musée (sinon : initiales sur la carte).
        $withPhoto = array_values(array_filter($all, fn ($s) => Photos::safe((string) ($s['image'] ?? '')) !== null));
        return count($withPhoto) >= 4 ? $withPhoto : $all;
    }

    /** Les joueurs les plus capés : totaux de référence du club, sinon matchs fichés. @return list<array> */
    public static function mostCapped(int $n): array
    {
        $ref = Totals::all();
        $tot = Derived::part('person_totals');
        $caps = fn ($s) => (int) ($ref[$s['id']]['matchs'] ?? $tot[$s['id']]['matches'] ?? 0);
        $list = array_values(array_filter(Index::published('personne'), fn ($s) => in_array('joueur', (array) ($s['p']['roles'] ?? []), true) && $caps($s) > 0));
        usort($list, fn ($a, $b) => $caps($b) <=> $caps($a) ?: $a['id'] <=> $b['id']);
        return array_slice($list, 0, $n);
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
        $ref = Totals::all()[$s['id']] ?? [];
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
            'matches' => (int) ($ref['matchs'] ?? $tot['matches'] ?? 0),
            'goals' => (int) ($ref['buts'] ?? $tot['goals'] ?? 0),
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
