<?php
declare(strict_types=1);

namespace LD;

/**
 * Contenus éditables des pages (un fichier JSON par page dans storage/content).
 * Les valeurs par défaut (app/defaults/content) reprennent les textes de la maquette.
 */
final class Content
{
    /** Pages éditables depuis le back-office : clé => libellé. */
    public const PAGES = [
        'global'         => 'Commun : en-tête, pied de page, assistant',
        'home'           => 'Accueil',
        'sur-mesure'     => 'Sur mesure + IA',
        'formation'      => 'Formation',
        'accompagnement' => "L'accompagnement",
        'solutions'      => 'Mes solutions',
        'qui'            => 'Qui suis-je',
        'avis'           => 'Avis (page)',
        'blog'           => 'Blog (page et thèmes)',
        'rdv'            => 'On prend RdV ?',
        'zones'          => "Zones d'intervention",
        'mentions'       => 'Mentions légales & accessibilité',
    ];

    private static array $loaded = [];

    public static function defaults(string $name): array
    {
        $file = LD_APP . '/defaults/content/' . basename($name) . '.json';
        if (!is_file($file)) {
            return [];
        }
        $data = json_decode((string) file_get_contents($file), true);
        return is_array($data) ? $data : [];
    }

    public static function get(string $name): array
    {
        if (!isset(self::$loaded[$name])) {
            self::$loaded[$name] = self::merge(self::defaults($name), Store::read('content/' . basename($name) . '.json', []));
        }
        return self::$loaded[$name];
    }

    public static function save(string $name, array $data): void
    {
        Store::write('content/' . basename($name) . '.json', $data);
        unset(self::$loaded[$name]);
        PageCache::clear();
    }

    public static function reset(string $name): void
    {
        Store::write('content/' . basename($name) . '.json', self::defaults($name));
        unset(self::$loaded[$name]);
        PageCache::clear();
    }

    /**
     * Fusion récursive : les tableaux associatifs sont fusionnés clé par clé (les nouvelles clés
     * par défaut apparaissent), les listes sont reprises telles quelles depuis la version enregistrée.
     */
    public static function merge(mixed $default, mixed $saved): mixed
    {
        if (!is_array($default) || !is_array($saved)) {
            return $saved ?? $default;
        }
        if (array_is_list($default) && $default !== []) {
            return array_is_list($saved) ? $saved : $default;
        }
        if ($default === [] ) {
            return $saved;
        }
        $out = $default;
        foreach ($saved as $k => $v) {
            $out[$k] = array_key_exists($k, $default) ? self::merge($default[$k], $v) : $v;
        }
        return $out;
    }
}
