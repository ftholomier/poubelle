<?php
declare(strict_types=1);

namespace App\Widgets;

use App\Core\JsonStore;

/**
 * Données des widgets déjà calculées (Store::dir()/cache/) : une entrée par widget, options et
 * langue, gardée le temps du widget (Widget::ttl) et jamais au-delà de minuit. Les données sont
 * celles d'avant Widget::personalize et Frame::finalize (sans partenaire) : la même entrée sert à tous. Les réglages propres
 * du widget (Widget::settings) font partie de la clé : un changement de réglage prend effet aussitôt.
 * Les variantes à texte libre (Widget::cacheable) ne sont pas gardées ; les entrées des jours passés
 * sont effacées de temps en temps, et le cache est plafonné (MAX).
 */
final class Cache
{
    /** Entrées au plus dans le cache des widgets. */
    public const MAX = 3000;

    /** @return array{at:int,data:?array} */
    public static function get(Widget $w, array $opt, Context $c): array
    {
        if (!$w->cacheable($opt)) {
            return ['at' => time(), 'data' => $w->data($opt, $c)];
        }
        ksort($opt);
        $file = Store::dir() . '/cache/' . $w->key() . '-' . substr(md5(json_encode([$opt, $c->lang, Store::widget($w->key())['settings']])), 0, 12) . '.json';
        $hit = JsonStore::read($file);
        $now = time();
        if (is_array($hit) && ($hit['day'] ?? '') === date('Y-m-d') && $now - (int) ($hit['at'] ?? 0) < $w->ttl() && array_key_exists('data', $hit)) {
            return ['at' => (int) $hit['at'], 'data' => $hit['data']];
        }
        $data = $w->data($opt, $c);
        try {
            // Plafond : au-delà de MAX entrées, les entrées des jours passés sont effacées ; si le cache
            // est encore plein, la donnée est servie sans être gardée (jamais de disque rempli).
            if (!is_file($file) && self::count() >= self::MAX && self::prune() === 0 && self::count() >= self::MAX) {
                return ['at' => $now, 'data' => $data];
            }
            JsonStore::write($file, ['at' => $now, 'day' => date('Y-m-d'), 'data' => $data]);
            if (random_int(1, 200) === 1) {
                self::prune();
            }
        } catch (\Throwable $e) {
            error_log('Cache des widgets : ' . $e->getMessage());
        }
        return ['at' => $now, 'data' => $data];
    }

    private static function count(): int
    {
        return count(glob(Store::dir() . '/cache/*.json') ?: []);
    }

    /** Ménage : entrées d'un jour passé (jamais resservies, voir get). @return int entrées effacées */
    public static function prune(): int
    {
        $n = 0;
        $today = strtotime('today');
        foreach (glob(Store::dir() . '/cache/*.json') ?: [] as $f) {
            if ((int) @filemtime($f) < $today && @unlink($f)) {
                JsonStore::forget($f);
                $n++;
            }
        }
        return $n;
    }

    /** Vide le cache des widgets (après un réglage, et pour les tests). */
    public static function clear(): int
    {
        $n = 0;
        foreach (glob(Store::dir() . '/cache/*.json') ?: [] as $f) {
            $n += @unlink($f) ? 1 : 0;
            JsonStore::forget($f);
        }
        return $n;
    }
}
