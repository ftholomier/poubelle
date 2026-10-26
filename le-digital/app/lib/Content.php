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

    /**
     * Enregistre une page. Seul ce qui diffère des textes par défaut est conservé : un texte jamais
     * retouché dans le back-office suit donc les mises à jour du site (nouveaux textes livrés par GitHub).
     */
    public static function save(string $name, array $data): void
    {
        Store::write('content/' . basename($name) . '.json', self::changes(self::defaults($name), $data));
        unset(self::$loaded[$name]);
        PageCache::clear();
    }

    public static function reset(string $name): void
    {
        Store::delete('content/' . basename($name) . '.json');
        unset(self::$loaded[$name]);
        PageCache::clear();
    }

    /**
     * Différence avec les valeurs par défaut : les tableaux associatifs sont comparés clé par clé,
     * les listes et les textes en entier. Retourne [] si rien ne change.
     */
    public static function changes(array $default, array $value): array
    {
        $out = [];
        foreach ($value as $k => $v) {
            if (!array_key_exists($k, $default)) {
                $out[$k] = $v;
            } elseif (is_array($v) && is_array($default[$k]) && $v !== [] && $default[$k] !== [] && !array_is_list($v) && !array_is_list($default[$k])) {
                $sub = self::changes($default[$k], $v);
                if ($sub !== []) {
                    $out[$k] = $sub;
                }
            } elseif ($v !== $default[$k]) {
                $out[$k] = $v;
            }
        }
        return $out;
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
