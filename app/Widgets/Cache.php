<?php
declare(strict_types=1);

namespace App\Widgets;

use App\Core\JsonStore;

/**
 * Données des widgets déjà calculées (Store::dir()/cache/) : une entrée par widget, options et
 * langue, gardée le temps du widget (Widget::ttl) et jamais au-delà de minuit. Les données sont
 * celles d'avant Frame::finalize (sans partenaire) : la même entrée sert à tous.
 */
final class Cache
{
    /** @return array{at:int,data:?array} */
    public static function get(Widget $w, array $opt, Context $c): array
    {
        ksort($opt);
        $file = Store::dir() . '/cache/' . $w->key() . '-' . substr(md5(json_encode([$opt, $c->lang])), 0, 12) . '.json';
        $hit = JsonStore::read($file);
        $now = time();
        if (is_array($hit) && ($hit['day'] ?? '') === date('Y-m-d') && $now - (int) ($hit['at'] ?? 0) < $w->ttl() && array_key_exists('data', $hit)) {
            return ['at' => (int) $hit['at'], 'data' => $hit['data']];
        }
        $data = $w->data($opt, $c);
        try {
            JsonStore::write($file, ['at' => $now, 'day' => date('Y-m-d'), 'data' => $data]);
        } catch (\Throwable $e) {
            error_log('Cache des widgets : ' . $e->getMessage());
        }
        return ['at' => $now, 'data' => $data];
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
