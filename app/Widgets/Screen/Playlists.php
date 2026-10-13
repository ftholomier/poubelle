<?php
declare(strict_types=1);

namespace App\Widgets\Screen;

use App\Core\JsonStore;
use App\Widgets\Store;

/**
 * Playlists du mode écran (écrans de stade, télés, bornes) : 3 prêtes à l'emploi (avant-match,
 * mi-temps, buvette) et celles créées dans le back-office (Store::dir()/ecrans.json), chacune
 * une suite de slides (type, durée, options), un format (paysage 16:9 ou vertical 9:16), une
 * langue, un adversaire du jour, l'horloge et le rechargement automatique.
 */
final class Playlists
{
    /**
     * Types de slides : clé => [nom, durée par défaut (s), options permises]. Chaque slide réutilise
     * les données d'un widget (même clé, sauf « quiz » et « photo », propres à l'écran).
     */
    public const TYPES = [
        'histoire-du-duel' => ['Histoire du duel (adversaire du jour)', 15, ['adversaire']],
        'ce-jour-la' => ['Ce jour-là', 12, []],
        'carte-legende' => ['Carte de légende', 12, []],
        'chiffre-du-jour' => ['Chiffre du jour', 10, []],
        'le-saviez-vous' => ['Le saviez-vous ?', 10, []],
        'quiz' => ['Quiz écran (question, compte à rebours, réponse)', 30, []],
        'photo' => ['Photo d’archives', 10, ['n']],
        'frise' => ['Frise (cinq dates)', 14, ['n']],
        'records' => ['Records', 15, ['cat']],
        'palmares' => ['Palmarès', 12, []],
        'il-y-a' => ['Il y a 5, 10, 20, 30 ans', 12, []],
        'dernier-match' => ['Dernier match fiché', 12, []],
        'saison' => ['Saison en un coup d’œil', 12, ['saison']],
        'compte-a-rebours' => ['Compte à rebours', 10, []],
        'maillot' => ['Maillot de l’époque', 10, []],
        'retro-direct' => ['Rétro-Direct', 10, []],
        'carte-stades' => ['Carte des stades', 12, []],
        'soutenir' => ['Soutenir le musée (QR codes)', 10, []],
    ];

    public const FORMATS = ['paysage' => 'Paysage 16:9 (1920 × 1080)', 'vertical' => 'Vertical 9:16 (1080 × 1920)'];

    /** Durée d'une slide : bornes (secondes). */
    public const MIN = 5;
    public const MAX = 120;

    /** Les 3 playlists prêtes à l'emploi (non modifiables ; à dupliquer pour les personnaliser). */
    public static function presets(): array
    {
        $s = fn (string $type, ?int $d = null, array $opt = []) => ['type' => $type, 'duration' => $d ?? self::TYPES[$type][1], 'opt' => $opt];
        return [
            'avant-match' => ['id' => 'avant-match', 'name' => 'Avant-match', 'preset' => true,
                'note' => 'Les 15 minutes avant le coup d’envoi, en boucle (une boucle ≈ 3 min 40, répétée) : l’histoire du duel contre l’adversaire du jour, ce jour-là, une légende, un chiffre, une anecdote, un quiz.',
                'slides' => [$s('histoire-du-duel', 20), $s('ce-jour-la'), $s('carte-legende'), $s('quiz', 35), $s('chiffre-du-jour'), $s('le-saviez-vous'),
                    $s('histoire-du-duel', 15), $s('records', 15, ['cat' => 'buteurs']), $s('photo', 10, ['n' => '1']), $s('quiz', 35), $s('palmares'), $s('dernier-match'), $s('compte-a-rebours'), $s('soutenir')]],
            'mi-temps' => ['id' => 'mi-temps', 'name' => 'Mi-temps', 'preset' => true,
                'note' => 'Deux à trois minutes pendant la pause : le duel, un chiffre, un quiz, ce jour-là, une anecdote.',
                'slides' => [$s('histoire-du-duel', 25), $s('chiffre-du-jour', 15), $s('quiz', 45), $s('ce-jour-la', 18), $s('le-saviez-vous', 15), $s('soutenir', 12)]],
            'buvette' => ['id' => 'buvette', 'name' => 'Buvette', 'preset' => true,
                'note' => 'En continu, sans limite : photos d’archives, la frise, les records, le palmarès, les maillots, la carte des stades.',
                'slides' => [$s('photo', 12, ['n' => '1']), $s('frise', 14, ['n' => '1']), $s('records', 15, ['cat' => 'buteurs']), $s('photo', 12, ['n' => '2']), $s('maillot', 12),
                    $s('records', 15, ['cat' => 'matchs']), $s('frise', 14, ['n' => '2']), $s('photo', 12, ['n' => '3']), $s('palmares', 12), $s('il-y-a', 12), $s('carte-stades', 14),
                    $s('photo', 12, ['n' => '4']), $s('frise', 14, ['n' => '3']), $s('chiffre-du-jour', 10), $s('le-saviez-vous', 10), $s('compte-a-rebours', 10)]],
        ];
    }

    /** Réglages communs par défaut d'une playlist. */
    public static function defaults(): array
    {
        return ['format' => 'paysage', 'lang' => 'fr', 'adversaire' => '', 'clock' => false, 'reload' => 30];
    }

    /** Toutes les playlists : prêtes à l'emploi d'abord, puis celles du musée. @return array<string,array> */
    public static function all(): array
    {
        $out = [];
        foreach (self::presets() as $id => $p) {
            $out[$id] = $p + self::defaults();
        }
        foreach (self::custom() as $id => $p) {
            $out[$id] = $p + ['preset' => false] + self::defaults();
        }
        return $out;
    }

    public static function get(string $id): ?array
    {
        return self::all()[$id] ?? null;
    }

    /** @return array<string,array> */
    public static function custom(): array
    {
        $all = JsonStore::read(self::file(), []);
        return is_array($all) ? array_filter($all, 'is_array') : [];
    }

    private static function file(): string
    {
        return Store::dir() . '/ecrans.json';
    }

    /** Durée d'une boucle, en secondes. */
    public static function duration(array $p): int
    {
        return array_sum(array_map(fn ($s) => (int) $s['duration'], $p['slides']));
    }

    /**
     * Crée ou modifie une playlist du musée (jamais une prête à l'emploi). Champs : name, format,
     * lang, adversaire, clock, reload, slides [{type, duration, opt}].
     * @return array la playlist enregistrée
     */
    public static function save(?string $id, array $in): array
    {
        $name = trim(mb_substr(preg_replace('/\s+/u', ' ', (string) ($in['name'] ?? '')) ?? '', 0, 60));
        if ($name === '') {
            throw new \InvalidArgumentException('Donnez un nom à la playlist.');
        }
        $slides = self::cleanSlides((array) ($in['slides'] ?? []));
        if (!$slides) {
            throw new \InvalidArgumentException('Ajoutez au moins une slide.');
        }
        $p = [
            'name' => $name,
            'format' => isset(self::FORMATS[$in['format'] ?? '']) ? (string) $in['format'] : 'paysage',
            'lang' => ($in['lang'] ?? 'fr') === 'en' ? 'en' : 'fr',
            'adversaire' => self::slug((string) ($in['adversaire'] ?? '')),
            'clock' => !empty($in['clock']),
            'reload' => max(5, min(240, (int) ($in['reload'] ?? 30))),
            'slides' => $slides,
            'updated' => date('c'),
        ];
        $saved = null;
        JsonStore::update(self::file(), function ($all) use ($id, $p, &$saved) {
            $all = is_array($all) ? $all : [];
            if ($id === null || !isset($all[$id])) {
                $base = trim(slugify($p['name']), '-') ?: 'ecran';
                $base = substr($base, 0, 30);
                $id = $base;
                for ($i = 2; isset($all[$id]) || isset(self::presets()[$id]); $i++) {
                    $id = $base . '-' . $i;
                }
            }
            $all[$id] = ['id' => $id] + $p;
            $saved = $all[$id];
            return $all;
        }, []);
        return $saved;
    }

    public static function delete(string $id): void
    {
        JsonStore::update(self::file(), function ($all) use ($id) {
            $all = is_array($all) ? $all : [];
            unset($all[$id]);
            return $all;
        }, []);
    }

    /** Slides validées (type connu, durée bornée, options permises et bien formées). @return list<array> */
    public static function cleanSlides(array $list): array
    {
        $out = [];
        foreach (array_slice($list, 0, 60) as $s) {
            $type = is_array($s) ? (string) ($s['type'] ?? '') : '';
            if (!isset(self::TYPES[$type])) {
                continue;
            }
            $d = (int) ($s['duration'] ?? 0);
            $opt = [];
            foreach (self::TYPES[$type][2] as $o) {
                $v = trim((string) ($s['opt'][$o] ?? $s[$o] ?? ''));
                $v = match ($o) {
                    'adversaire' => self::slug($v),
                    'cat' => in_array($v, ['buteurs', 'matchs'], true) ? $v : 'buteurs',
                    'n' => (string) max(1, min(20, (int) $v ?: 1)),
                    'saison' => preg_match('/^(18|19|20)\d\d-(18|19|20)\d\d$/', $v) ? $v : '',
                    default => '',
                };
                if ($v !== '') {
                    $opt[$o] = $v;
                }
            }
            $out[] = ['type' => $type, 'duration' => $d >= self::MIN ? min(self::MAX, $d) : self::TYPES[$type][1], 'opt' => $opt];
        }
        return $out;
    }

    /** Clé de club (adresse du face-à-face) bien formée, sinon ''. */
    public static function slug(string $v): string
    {
        $v = strtolower(trim($v));
        return preg_match('/^[a-z0-9-]{2,60}$/', $v) ? $v : '';
    }
}
