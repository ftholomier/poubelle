<?php
declare(strict_types=1);

namespace App\Widgets;

use App\Core\JsonStore;
use App\Core\Settings;

/**
 * Réglages des widgets, partenaires et statistiques, propres à chaque musée (STORAGE_PATH/widgets/) :
 * - reglages.json : accès de chaque widget (libre, réservé, payant, désactivé) et « actif même site fermé » ;
 * - partenaires.json : partenaires (nom, contact, domaines autorisés, clé chiffrée et son empreinte,
 *   statut actif ou suspendu, offre gratuite ou payante et son échéance) ;
 * - stats/AAAA-MM-JJ.json : affichages, appels de l'API et clics vers le musée, par widget et par
 *   partenaire (aucune adresse IP, aucun cookie, aucune donnée personnelle) ;
 * - cache/ : données des widgets déjà calculées.
 */
final class Store
{
    /** Accès possibles d'un widget. */
    public const ACCESS = [
        'libre' => 'Libre (tout site)',
        'reserve' => 'Réservé (partenaires avec clé)',
        'payant' => 'Payant (partenaires avec offre payante)',
        'desactive' => 'Désactivé',
    ];

    /** Dossier de travail (les tests le remplacent par un dossier temporaire). */
    public static ?string $dir = null;

    public static function dir(): string
    {
        return self::$dir ?? STORAGE_PATH . '/widgets';
    }

    // ------------------------------------------------------------------ réglages des widgets

    /**
     * Réglages d'un widget : accès, « actif même site fermé » et réglages propres (Widget::settings,
     * valeurs validées, défaut sinon).
     * @return array{access:string,closed_ok:bool,settings:array<string,string>}
     */
    public static function widget(string $key): array
    {
        $all = (array) (JsonStore::read(self::dir() . '/reglages.json', []) ?: []);
        $w = (array) ($all['widgets'][$key] ?? []);
        $access = (string) ($w['access'] ?? '');
        $obj = Registry::get($key);
        if (!isset(self::ACCESS[$access])) {
            $access = $obj?->defaultAccess() ?? 'desactive';
        }
        return ['access' => $access, 'closed_ok' => !empty($w['closed_ok']), 'settings' => $obj ? self::cleanSettings($obj, (array) ($w['settings'] ?? [])) : []];
    }

    /** Réglages propres d'un widget, validés (valeur inconnue : valeur par défaut). @return array<string,string> */
    public static function cleanSettings(Widget $w, array $in): array
    {
        $out = [];
        foreach ($w->settings() as $name => $o) {
            $v = $in[$name] ?? null;
            $out[$name] = is_string($v) && isset($o['choices'][$v]) ? $v : (string) $o['default'];
        }
        return $out;
    }

    /** @param array<string,string>|null $settings réglages propres (null : inchangés) */
    public static function saveWidget(string $key, string $access, bool $closedOk, ?array $settings = null): void
    {
        if (!isset(self::ACCESS[$access])) {
            throw new \InvalidArgumentException('Accès inconnu.');
        }
        $obj = Registry::get($key);
        JsonStore::update(self::dir() . '/reglages.json', function ($all) use ($key, $access, $closedOk, $settings, $obj) {
            $all = is_array($all) ? $all : [];
            $keep = (array) ($all['widgets'][$key]['settings'] ?? []);
            $set = $obj ? self::cleanSettings($obj, $settings ?? $keep) : [];
            $all['widgets'][$key] = ['access' => $access, 'closed_ok' => $closedOk, 'at' => date('c')] + ($set ? ['settings' => $set] : []);
            return $all;
        }, []);
    }

    // ------------------------------------------------------------------ partenaires

    /** @return array<string,array> par identifiant public (« p » + 8 caractères) */
    public static function partners(): array
    {
        $all = JsonStore::read(self::dir() . '/partenaires.json', []);
        return is_array($all) ? $all : [];
    }

    public static function partner(string $id): ?array
    {
        return self::partners()[$id] ?? null;
    }

    /** Partenaire d'une clé (comparaison de l'empreinte, en temps constant), sinon null. */
    public static function partnerByKey(string $key): ?array
    {
        $key = trim($key);
        if (!preg_match('/^mk_[a-f0-9]{32}$/', $key)) {
            return null;
        }
        $h = self::hash($key);
        foreach (self::partners() as $p) {
            if (hash_equals((string) ($p['key_hash'] ?? ''), $h)) {
                return $p;
            }
        }
        return null;
    }

    /** Clé en clair d'un partenaire (pour l'afficher à l'administrateur), sinon ''. */
    public static function keyOf(array $p): string
    {
        return (string) (Settings::decrypt((string) ($p['key_enc'] ?? '')) ?? '');
    }

    /**
     * Crée ou modifie un partenaire. Champs : name, contact, domains (texte, un par ligne ou séparés
     * par des virgules), status (actif|suspendu), offer (gratuite|payante), until (AAAA-MM-JJ ou vide), note.
     * @return array le partenaire enregistré
     */
    public static function savePartner(?string $id, array $f): array
    {
        $name = trim(mb_substr((string) ($f['name'] ?? ''), 0, 80));
        if ($name === '') {
            throw new \InvalidArgumentException('Le nom du partenaire est obligatoire.');
        }
        $until = trim((string) ($f['until'] ?? ''));
        if ($until !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $until)) {
            throw new \InvalidArgumentException('Date d’échéance invalide (AAAA-MM-JJ).');
        }
        $p = [
            'name' => $name,
            'contact' => trim(mb_substr((string) ($f['contact'] ?? ''), 0, 160)),
            'domains' => self::domains((string) ($f['domains'] ?? '')),
            'status' => ($f['status'] ?? 'actif') === 'suspendu' ? 'suspendu' : 'actif',
            'offer' => ($f['offer'] ?? 'gratuite') === 'payante' ? 'payante' : 'gratuite',
            'until' => $until,
            'note' => trim(mb_substr((string) ($f['note'] ?? ''), 0, 500)),
        ];
        $saved = null;
        JsonStore::update(self::dir() . '/partenaires.json', function ($all) use ($id, $p, &$saved) {
            $all = is_array($all) ? $all : [];
            if ($id !== null && isset($all[$id])) {
                $all[$id] = $p + $all[$id];
            } else {
                $id = 'p' . bin2hex(random_bytes(4));
                $key = self::newKey();
                $all[$id] = ['id' => $id] + $p + ['key_hash' => self::hash($key), 'key_enc' => Settings::encrypt($key), 'key_end' => substr($key, -4), 'created' => date('c')];
            }
            $all[$id]['updated'] = date('c');
            $saved = $all[$id];
            return $all;
        }, []);
        return $saved;
    }

    /** Nouvelle clé pour ce partenaire (l'ancienne cesse aussitôt de fonctionner). @return string la nouvelle clé */
    public static function regenerate(string $id): string
    {
        $key = self::newKey();
        $ok = false;
        JsonStore::update(self::dir() . '/partenaires.json', function ($all) use ($id, $key, &$ok) {
            $all = is_array($all) ? $all : [];
            if (isset($all[$id])) {
                $all[$id]['key_hash'] = self::hash($key);
                $all[$id]['key_enc'] = Settings::encrypt($key);
                $all[$id]['key_end'] = substr($key, -4);
                $all[$id]['updated'] = date('c');
                $ok = true;
            }
            return $all;
        }, []);
        if (!$ok) {
            throw new \InvalidArgumentException('Partenaire introuvable.');
        }
        return $key;
    }

    public static function deletePartner(string $id): void
    {
        JsonStore::update(self::dir() . '/partenaires.json', function ($all) use ($id) {
            $all = is_array($all) ? $all : [];
            unset($all[$id]);
            return $all;
        }, []);
    }

    /** L'offre payante du partenaire est-elle en cours (offre payante, échéance non dépassée) ? */
    public static function paid(array $p): bool
    {
        return ($p['offer'] ?? '') === 'payante' && (($p['until'] ?? '') === '' || (string) $p['until'] >= date('Y-m-d'));
    }

    /** « exemple.fr », « *.exemple.fr » : noms de domaine propres, sans protocole ni chemin. @return list<string> */
    public static function domains(string $text): array
    {
        $out = [];
        foreach (preg_split('/[\s,;]+/', strtolower($text)) ?: [] as $d) {
            $d = (string) preg_replace('#^[a-z]+://#', '', trim($d));
            $d = (string) preg_replace('#[/:?].*$#', '', $d);
            $d = (string) preg_replace('/^\*\./', '', $d);
            $d = (string) preg_replace('/^www\./', '', $d);
            if ($d !== '' && preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+$/', $d) && !in_array($d, $out, true)) {
                $out[] = $d;
            }
        }
        return array_slice($out, 0, 20);
    }

    /** L'hôte (Origin ou Referer) fait-il partie des domaines du partenaire (sous-domaines compris) ? */
    public static function hostAllowed(string $host, array $domains): bool
    {
        $host = strtolower(trim($host));
        if ($host === '') {
            return false;
        }
        foreach ($domains as $d) {
            if ($host === $d || str_ends_with($host, '.' . $d)) {
                return true;
            }
        }
        return false;
    }

    private static function newKey(): string
    {
        return 'mk_' . bin2hex(random_bytes(16));
    }

    private static function hash(string $key): string
    {
        return hash('sha256', 'musee-widgets|' . $key);
    }

    // ------------------------------------------------------------------ statistiques

    /**
     * Compte un affichage (« vues »), un appel de l'API (« api ») ou un clic vers le musée (« clics »).
     * $who : identifiant du partenaire, ou « libre » sans clé.
     */
    public static function hit(string $kind, string $widget, string $who): void
    {
        if (!in_array($kind, ['vues', 'api', 'clics'], true)) {
            return;
        }
        $file = self::dir() . '/stats/' . date('Y-m-d') . '.json';
        try {
            JsonStore::update($file, function ($c) use ($kind, $widget, $who) {
                $c = is_array($c) ? $c : [];
                $c[$kind][$widget][$who] = (int) ($c[$kind][$widget][$who] ?? 0) + 1;
                return $c;
            }, []);
        } catch (\Throwable $e) {
            error_log('Statistiques des widgets : ' . $e->getMessage());
        }
    }

    /**
     * Totaux des $days derniers jours.
     * @return array{days:array<string,array{vues:int,api:int,clics:int}>,widgets:array<string,array{vues:int,api:int,clics:int}>,partners:array<string,array{vues:int,api:int,clics:int}>,total:array{vues:int,api:int,clics:int}}
     */
    public static function stats(int $days = 30): array
    {
        $zero = ['vues' => 0, 'api' => 0, 'clics' => 0];
        $out = ['days' => [], 'widgets' => [], 'partners' => [], 'total' => $zero];
        for ($i = $days - 1; $i >= 0; $i--) {
            $day = date('Y-m-d', strtotime("-$i days"));
            $out['days'][$day] = $zero;
            $c = JsonStore::read(self::dir() . '/stats/' . $day . '.json', []);
            foreach (is_array($c) ? $c : [] as $kind => $byWidget) {
                if (!isset($zero[$kind]) || !is_array($byWidget)) {
                    continue;
                }
                foreach ($byWidget as $w => $byWho) {
                    foreach ((array) $byWho as $who => $n) {
                        $n = (int) $n;
                        $out['days'][$day][$kind] += $n;
                        $out['widgets'][$w] ??= $zero;
                        $out['widgets'][$w][$kind] += $n;
                        $out['partners'][$who] ??= $zero;
                        $out['partners'][$who][$kind] += $n;
                        $out['total'][$kind] += $n;
                    }
                }
            }
        }
        return $out;
    }
}
