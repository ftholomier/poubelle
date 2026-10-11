<?php
declare(strict_types=1);

namespace App\Shop;

use App\Core\Club;
use App\Core\JsonStore;
use App\Data\Collections;

/**
 * Boutique d'un nouveau musée : reprise des produits du musée historique (supports, modèles, banque de
 * textes), tels qu'ils sont en ligne au moment du clic. Même mise en page ; le logo est celui du musée
 * servi (calque « logo ») ; textes et couleurs propres au club historique transposés d'après
 * config/clubs/<club>/boutique.php. Les produits déjà présents ici ne sont jamais écrasés.
 */
final class ClubCopy
{
    public static function available(): bool
    {
        return !Club::isDefault() && is_file(APP_ROOT . '/data/collections/' . Catalog::MODELS_FILE . '.json');
    }

    /** @return array{models:int, supports:int, texts:int} */
    public static function run(?array $user): array
    {
        if (Club::isDefault()) {
            throw new \RuntimeException('Le musée historique est la source : rien à reprendre.');
        }
        $map = (array) Club::content('boutique', []);
        $src = fn (string $name) => JsonStore::read(APP_ROOT . '/data/collections/' . $name . '.json', []) ?: [];
        $out = ['models' => 0, 'supports' => 0, 'texts' => 0];

        // Supports : réglages de l'admin (prix de revient, couleurs…), couleurs de textile transposées.
        $mine = (array) Collections::get(Catalog::SUPPORTS_FILE, []);
        foreach ((array) $src(Catalog::SUPPORTS_FILE) as $k => $s) {
            if (!isset($mine[$k]) && is_array($s)) {
                if (isset($s['colors']) && is_array($s['colors'])) {
                    $c = [];
                    foreach ($s['colors'] as $name => $hex) {
                        [$name, $hex] = $map['textile'][$name] ?? [$name, $hex];
                        $c[$name] = $hex;
                    }
                    $s['colors'] = $c;
                }
                $mine[$k] = self::adapt($s, $map);
                $out['supports']++;
            }
        }
        if ($out['supports']) {
            Collections::save(Catalog::SUPPORTS_FILE, $mine, $user, 'Supports repris du musée historique');
        }

        // Modèles : mêmes produits, textes et couleurs transposés ; nouvel identifiant.
        $models = array_values(array_filter((array) Collections::get(Catalog::MODELS_FILE, []), 'is_array'));
        $have = array_column($models, 'from');
        foreach ((array) $src(Catalog::MODELS_FILE) as $m) {
            if (!is_array($m) || in_array($m['id'] ?? '', $have, true)) {
                continue;
            }
            $n = self::adapt($m, $map);
            $n['from'] = (string) ($m['id'] ?? '');
            $n['id'] = bin2hex(random_bytes(5));
            $n['updated'] = date('c');
            $models[] = $n;
            $out['models']++;
        }
        if ($out['models']) {
            Collections::save(Catalog::MODELS_FILE, $models, $user, 'Produits repris du musée historique');
        }

        // Banque de textes : celle du musée (config), sinon rien (les phrases du club historique ne conviennent pas).
        if (!Collections::get(Texts::FILE, []) && !empty($map['banque'])) {
            $lists = [];
            foreach ((array) $map['banque'] as $l) {
                $items = [];
                foreach ((array) $l['items'] as $it) {
                    [$text, $note] = is_array($it) ? $it + [1 => ''] : [$it, ''];
                    $items[] = ['id' => bin2hex(random_bytes(4)), 'text' => (string) $text, 'note' => (string) $note, 'ok' => true];
                }
                $lists[] = ['id' => (string) $l['id'], 'name' => (string) $l['name'], 'note' => (string) ($l['note'] ?? ''), 'items' => $items];
                $out['texts'] += count($items);
            }
            Collections::save(Texts::FILE, $lists, $user, 'Banque de textes du musée');
        }
        // Réglages de la boutique (imprimeur, livraison, délais) : ceux du musée historique si ce musée n'en a pas.
        // Boutique laissée fermée au public tant que le paiement de ce musée n'est pas réglé.
        $cfg = STORAGE_PATH . '/shop/config.json';
        $srcCfg = JsonStore::read(APP_ROOT . '/storage/shop/config.json', []) ?: [];
        if (!is_file($cfg) && $srcCfg) {
            unset($srcCfg['cgv']);
            $srcCfg['open'] = false;
            @mkdir(dirname($cfg), 0775, true);
            JsonStore::write($cfg, $srcCfg);
        }
        return $out;
    }

    /** Reprise automatique quand ce musée n'a encore aucun produit (une seule fois). */
    public static function auto(): void
    {
        if (self::available() && !Collections::get(Catalog::MODELS_FILE, []) && !is_file(STORAGE_PATH . '/shop/reprise.flag')) {
            @mkdir(STORAGE_PATH . '/shop', 0775, true);
            @file_put_contents(STORAGE_PATH . '/shop/reprise.flag', date('c'));
            self::run(['name' => 'Reprise automatique']);
        }
    }

    /** Textes (correspondance exacte) et couleurs (#RRGGBB) transposés, partout dans la structure. */
    private static function adapt(mixed $v, array $map): mixed
    {
        if (is_array($v)) {
            $r = [];
            foreach ($v as $k => $x) {
                $r[$k] = self::adapt($x, $map);
            }
            return $r;
        }
        if (!is_string($v)) {
            return $v;
        }
        if (preg_match('/^#[0-9a-f]{6}$/i', $v)) {
            foreach ((array) ($map['couleurs'] ?? []) as $from => $to) {
                if (strcasecmp($from, $v) === 0) {
                    return $to;
                }
            }
            return $v;
        }
        $t = (array) ($map['textes'] ?? []);
        if (isset($t[$v])) {
            return $t[$v];
        }
        // Phrases de description : mots propres au club historique remplacés.
        return strtr($v, ['jaune et bleu' => 'rouge et bleu', 'Jaune et bleu' => 'Rouge et bleu', 'Sochaux Rétro' => (string) Club::s('site'), 'Bonal' => 'Parc', 'Sochaux' => (string) Club::s('city'), '1928' => (string) Club::s('founded')]);
    }
}
