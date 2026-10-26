<?php
declare(strict_types=1);

namespace LD;

/**
 * Solutions présentées sur le site (page « Mes solutions », accueil, pied de page, assistant IA).
 *
 * Chaque solution peut être affichée ou masquée et réordonnée depuis le back-office (Solutions).
 * - « intensiti » : bloc riche dont les textes sont dans Contenus › Mes solutions ;
 * - « standard »  : bloc générique (nom, accroche, texte, points forts, capture, liens) créé dans le back-office.
 */
final class Solutions
{
    private const FILE = 'data/solutions.json';
    public const THEMES = ['clair' => 'Clair (fond rose)', 'sombre' => 'Sombre (fond nuit)'];

    /** Toutes les solutions, dans l'ordre d'affichage. */
    public static function all(): array
    {
        $all = Store::read(self::FILE, null);
        if (!is_array($all)) {
            $all = json_decode((string) @file_get_contents(LD_APP . '/defaults/data/solutions.json'), true) ?: [];
        }
        $all = array_map([self::class, 'normalize'], $all);
        usort($all, static fn($a, $b) => $a['order'] <=> $b['order'] ?: strcmp($a['id'], $b['id']));
        return $all;
    }

    /** Solutions affichées sur le site, avec leurs textes prêts à l'affichage. */
    public static function enabled(): array
    {
        return array_values(array_map([self::class, 'view'], array_filter(self::all(), static fn($s) => $s['enabled'])));
    }

    public static function find(string $id): ?array
    {
        foreach (self::all() as $s) {
            if ($s['id'] === $id) {
                return $s;
            }
        }
        return null;
    }

    /** Champs d'affichage : Intensiti reprend ses textes depuis les contenus de la page. */
    public static function view(array $s): array
    {
        if ($s['kind'] === 'intensiti') {
            return (Content::get('solutions')['intensiti'] ?? []) + ['id' => $s['id'], 'kind' => 'intensiti', 'theme' => 'sombre', 'enabled' => $s['enabled']];
        }
        return $s;
    }

    public static function name(array $s): string
    {
        return (string) (self::view($s)['name'] ?? $s['id']);
    }

    /** « Intensiti et Formaliance » : noms des solutions affichées (titre de page, description). */
    public static function names(): string
    {
        $n = array_map(static fn($s) => (string) $s['name'], self::enabled());
        return count($n) > 1 ? implode(', ', array_slice($n, 0, -1)) . ' et ' . end($n) : ($n[0] ?? '');
    }

    /**
     * Centres d'intérêt du formulaire de contact : sans les solutions masquées, avec les solutions affichées
     * qui n'y figurent pas encore (ajoutées avant le dernier choix).
     */
    public static function interests(array $list): array
    {
        $hidden = array_map(static fn($s) => mb_strtolower(self::name($s)), array_filter(self::all(), static fn($s) => !$s['enabled']));
        $list = array_values(array_filter($list, static fn($it) => !in_array(mb_strtolower((string) $it), $hidden, true)));
        $lower = array_map('mb_strtolower', $list);
        $add = [];
        foreach (self::enabled() as $s) {
            if (!in_array(mb_strtolower((string) $s['name']), $lower, true)) {
                $add[] = (string) $s['name'];
            }
        }
        if ($add) {
            $last = array_pop($list);
            $list = [...$list, ...$add];
            if ($last !== null) {
                $list[] = $last;
            }
        }
        return $list;
    }

    /** Résumé pour l'assistant IA : « Intensiti (accroche, lien) ; … ». */
    public static function summary(): string
    {
        $out = [];
        foreach (self::enabled() as $s) {
            $about = trim(trim((string) ($s['tagline'] ?? '')) . ' ' . trim((string) ($s['tagline_accent'] ?? '')));
            $about = $about !== '' ? $about : trim((string) ($s['short'] ?? ''));
            $extra = array_filter([$about, trim((string) ($s['price'] ?? '')), trim((string) ($s['url'] ?? ''))]);
            $out[] = $s['name'] . ($extra ? ' (' . implode(', ', $extra) . ')' : '');
        }
        return $out ? implode(' ; ', $out) : 'aucune solution présentée pour le moment';
    }

    public static function save(array $s): array
    {
        $s = self::normalize($s);
        Store::update(self::FILE, static function (mixed &$all) use (&$s): void {
            // Fichier absent (première modification) : on part des solutions par défaut.
            $all = is_array($all) ? $all : self::all();
            if ($s['id'] === '') {
                $base = slugify($s['name']) ?: 'solution';
                $ids = array_column($all, 'id');
                $id = $base;
                for ($i = 2; in_array($id, $ids, true) || in_array($id, ['nouvelle', 'intensiti'], true); $i++) {
                    $id = $base . '-' . $i;
                }
                $s['id'] = $id;
                $s['order'] = $s['order'] ?: (int) max([0, ...array_column($all, 'order')]) + 1;
                $all[] = $s;
                return;
            }
            foreach ($all as $i => $old) {
                if (($old['id'] ?? '') === $s['id']) {
                    $all[$i] = array_merge($old, $s);
                    return;
                }
            }
            $all[] = $s;
        }, null);
        PageCache::clear();
        return $s;
    }

    public static function setEnabled(string $id, bool $on): void
    {
        self::patch($id, ['enabled' => $on]);
    }

    /** Monte (-1) ou descend (+1) une solution dans l'ordre d'affichage. */
    public static function move(string $id, int $dir): void
    {
        $all = self::all();
        $pos = array_search($id, array_column($all, 'id'), true);
        $to = $pos === false ? false : $pos + ($dir < 0 ? -1 : 1);
        if ($pos === false || !isset($all[$to])) {
            return;
        }
        [$all[$pos], $all[$to]] = [$all[$to], $all[$pos]];
        foreach ($all as $i => &$s) {
            $s['order'] = $i + 1;
        }
        unset($s);
        Store::write(self::FILE, $all);
        PageCache::clear();
    }

    public static function delete(string $id): void
    {
        if ($id === 'intensiti') {
            return;
        }
        Store::write(self::FILE, array_values(array_filter(self::all(), static fn($s) => $s['id'] !== $id)));
        PageCache::clear();
    }

    private static function patch(string $id, array $changes): void
    {
        $all = self::all();
        foreach ($all as &$s) {
            if ($s['id'] === $id) {
                $s = array_merge($s, $changes);
            }
        }
        unset($s);
        Store::write(self::FILE, $all);
        PageCache::clear();
    }

    private static function normalize(array $s): array
    {
        $s += [
            'id' => '', 'kind' => 'standard', 'enabled' => true, 'order' => 0, 'name' => '', 'short' => '', 'tagline' => '', 'text' => '',
            'strengths' => [], 'image' => '', 'logo' => '', 'theme' => 'clair', 'url' => '', 'cta' => '', 'demo_cta' => '', 'demo_url' => '/on-prend-rdv',
        ];
        $s['kind'] = $s['kind'] === 'intensiti' ? 'intensiti' : 'standard';
        $s['enabled'] = (bool) $s['enabled'];
        $s['order'] = (int) $s['order'];
        $s['theme'] = isset(self::THEMES[$s['theme']]) ? $s['theme'] : 'clair';
        $s['strengths'] = array_values(array_filter(array_map('strval', (array) $s['strengths']), static fn($v) => trim($v) !== ''));
        return $s;
    }
}
