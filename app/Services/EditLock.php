<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\JsonStore;

/**
 * Verrou de modification : qui modifie quelle fiche en ce moment.
 *
 * Sans base de données, deux personnes pourraient ouvrir la même fiche et écraser le travail
 * l'une de l'autre. L'éditeur ouvert signale sa présence toutes les 30 secondes ; la personne
 * qui ouvre la fiche ensuite voit « Marie modifie cette fiche depuis 10 h 12 » et la consulte
 * en lecture seule, ou « prend la main » : Marie en est prévenue et ne peut plus enregistrer.
 * Un verrou sans nouvelles depuis 2 minutes (onglet fermé, ordinateur en veille) est libéré ;
 * l'éditeur le rend aussi de lui-même après 30 minutes d'inactivité.
 *
 * Clés : « fiche:123 », « collection:quiz », « ecran:accueil », « ecran:rubrique-… ».
 * Un même membre peut avoir la fiche ouverte dans plusieurs onglets (un identifiant par
 * onglet) : le verrou tombe quand le dernier se ferme. Fichier : storage/verrous.json.
 */
final class EditLock
{
    /** Secondes sans nouvelles d'un onglet avant qu'il ne compte plus. */
    public const TTL = 120;

    public static string $file = STORAGE_PATH . '/verrous.json';

    public static function validKey(string $key): bool
    {
        return (bool) preg_match('/^(fiche:\d{1,9}|collection:[a-z0-9_-]{1,40}|ecran:[a-z0-9_-]{1,60})$/', $key);
    }

    public static function newTab(): string
    {
        return bin2hex(random_bytes(6));
    }

    /**
     * Signal de présence d'un éditeur. $mode :
     *  - hold : prend le verrou s'il est libre (ou déjà à soi) ;
     *  - watch : observe seulement (lecture seule, en attendant que la fiche se libère) ;
     *  - take : prend la main sur la personne qui modifie ;
     *  - release : libère (fermeture de l'onglet).
     * $idle : secondes depuis la dernière action de la personne (frappe, clic).
     *
     * @param array{id:string,name:string} $user
     * @return array{mine:bool,holder:?array,taken:?array,took:?string} holder : autre personne qui
     *         tient le verrou (name, since, active en horodatage) ; taken : on vient de vous prendre
     *         la main ; took : nom de la personne à qui vous venez de prendre la main
     */
    public static function ping(string $key, array $user, string $mode = 'hold', string $tab = '', int $idle = 0): array
    {
        $now = time();
        $uid = (string) $user['id'];
        $tab = preg_match('/^[a-f0-9]{6,32}$/', $tab) ? $tab : 'x';
        $out = ['mine' => false, 'holder' => null, 'taken' => null, 'took' => null];
        JsonStore::update(self::$file, function ($all) use ($key, $user, $uid, $mode, $tab, $idle, $now, &$out) {
            $all = self::purge(is_array($all) ? $all : [], $now);
            $l = $all[$key] ?? null;
            $other = $l !== null && $l['uid'] !== $uid;
            if ($mode === 'release') {
                if ($l && !$other) {
                    unset($l['tabs'][$tab]);
                    if ($l['tabs']) {
                        $all[$key] = $l;
                    } else {
                        unset($all[$key]);
                    }
                }
                return $all;
            }
            if ($other && $mode !== 'take') {
                $out['holder'] = self::info($l);
                if (($l['prev']['uid'] ?? null) === $uid) {
                    $out['taken'] = ['by' => $l['name'], 'at' => $l['prev']['at']];
                }
                return $all;
            }
            if ($mode === 'watch') {
                $out['mine'] = $l !== null; // déjà ouverte par soi-même dans un autre onglet
                return $all;
            }
            // Libre, déjà à soi (autre onglet compris), ou prise de main.
            $mine = $l && !$other ? $l : ['uid' => $uid, 'since' => $now, 'tabs' => []];
            if ($other) {
                $mine['prev'] = ['uid' => $l['uid'], 'name' => $l['name'], 'at' => $now];
                $out['took'] = (string) $l['name'];
            }
            $mine['name'] = (string) $user['name'];
            $mine['tabs'][$tab] = $now;
            $mine['active'] = max((int) ($mine['active'] ?? 0), $now - max(0, min($idle, 86400)));
            $all[$key] = $mine;
            $out['mine'] = true;
            return $all;
        }, []);
        return $out;
    }

    /** La personne qui tient le verrou, si ce n'est pas $uid (contrôle à l'enregistrement). */
    public static function holder(string $key, string $uid): ?array
    {
        $l = self::active()[$key] ?? null;
        return $l && $l['uid'] !== $uid ? self::info($l) : null;
    }

    /** Verrous en cours : clé => [uid, name, since, active]. */
    public static function active(): array
    {
        $all = JsonStore::read(self::$file, []) ?: [];
        $out = [];
        foreach (self::purge(is_array($all) ? $all : [], time()) as $k => $l) {
            $out[$k] = self::info($l) + ['uid' => $l['uid']];
        }
        return $out;
    }

    /** Fiches verrouillées (identifiant => nom de la personne), pour les listes. */
    public static function fiches(): array
    {
        $out = [];
        foreach (self::active() as $k => $l) {
            if (preg_match('/^fiche:(\d+)$/', $k, $m)) {
                $out[(int) $m[1]] = $l;
            }
        }
        return $out;
    }

    private static function info(array $l): array
    {
        return ['name' => (string) $l['name'], 'since' => (int) $l['since'], 'active' => (int) ($l['active'] ?? $l['since'])];
    }

    /** Retire les onglets muets depuis plus de TTL, puis les verrous sans onglet. */
    private static function purge(array $all, int $now): array
    {
        foreach ($all as $k => $l) {
            $tabs = array_filter((array) ($l['tabs'] ?? []), fn ($t) => (int) $t >= $now - self::TTL);
            if (!$tabs || !isset($l['uid'])) {
                unset($all[$k]);
                continue;
            }
            $all[$k]['tabs'] = $tabs;
        }
        return $all;
    }
}
