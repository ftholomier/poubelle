<?php
declare(strict_types=1);

namespace App\Shop;

use App\Core\JsonStore;

/**
 * Codes promo de la boutique (Boutique › Codes promo) : pourcentage, montant fixe ou livraison
 * offerte ; dates de validité, montant minimum, nombre d'utilisations maximal, une fois par
 * client (e-mail) si voulu, limité à certains modèles si voulu. Une utilisation est réservée sous
 * verrou dès la création de la commande (state « reserved »), confirmée au paiement (« used ») et
 * libérée si la commande est annulée sans paiement. Stockage : storage/shop/promos.json.
 */
final class Promos
{
    public const TYPES = ['percent' => 'Pourcentage', 'amount' => 'Montant fixe', 'shipping' => 'Livraison offerte'];

    private static function file(): string
    {
        return STORAGE_PATH . '/shop/promos.json';
    }

    /** @return array<string,array> code => promo */
    public static function all(): array
    {
        $all = array_map([self::class, 'normalize'], array_filter((array) JsonStore::read(self::file(), []), 'is_array'));
        ksort($all);
        return $all;
    }

    public static function find(string $code): ?array
    {
        return self::all()[self::code($code)] ?? null;
    }

    public static function code(string $c): string
    {
        return substr((string) preg_replace('/[^A-Z0-9_-]/', '', strtoupper(trim($c))), 0, 30);
    }

    private static function normalize(array $p): array
    {
        $type = isset(self::TYPES[$p['type'] ?? '']) ? $p['type'] : 'percent';
        return [
            'code' => self::code((string) ($p['code'] ?? '')), 'type' => $type,
            'value' => $type === 'percent' ? max(1, min(100, (int) ($p['value'] ?? 10))) : max(0, min(100000, (int) ($p['value'] ?? 0))),
            'min' => max(0, (int) ($p['min'] ?? 0)), 'from' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($p['from'] ?? '')) ? $p['from'] : '',
            'to' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($p['to'] ?? '')) ? $p['to'] : '', 'max' => max(0, (int) ($p['max'] ?? 0)),
            'once' => !empty($p['once']), 'models' => array_values(array_filter(array_map('strval', (array) ($p['models'] ?? [])))),
            'active' => !isset($p['active']) || !empty($p['active']), 'note' => mb_substr(trim((string) ($p['note'] ?? '')), 0, 200),
            'uses' => array_values(array_filter((array) ($p['uses'] ?? []), 'is_array')),
        ];
    }

    public static function save(array $p, string $old = ''): array
    {
        $p = self::normalize($p);
        if ($p['code'] === '') {
            throw new \InvalidArgumentException('Indiquez un code (lettres et chiffres).');
        }
        JsonStore::update(self::file(), function ($all) use ($p, $old) {
            $all = is_array($all) ? $all : [];
            if ($old !== '' && $old !== $p['code'] && isset($all[$old])) {
                $p['uses'] = $all[$old]['uses'] ?? [];
                unset($all[$old]);
            } else {
                $p['uses'] = $all[$p['code']]['uses'] ?? [];
            }
            $all[$p['code']] = $p;
            return $all;
        }, []);
        return $p;
    }

    public static function delete(string $code): void
    {
        JsonStore::update(self::file(), function ($all) use ($code) {
            unset($all[self::code($code)]);
            return $all;
        }, []);
    }

    /**
     * Remise d'un code pour un panier (articles contrôlés par Orders::line).
     * @return array{code?:string,discount?:int,free_shipping?:bool,label?:string,error?:string}
     */
    public static function apply(string $code, array $items, string $email = ''): array
    {
        $p = self::find($code);
        $today = date('Y-m-d');
        if (!$p || !$p['active']) {
            return ['error' => 'Ce code promo n’existe pas.'];
        }
        if (($p['from'] !== '' && $today < $p['from']) || ($p['to'] !== '' && $today > $p['to'])) {
            return ['error' => 'Ce code promo n’est pas valable aujourd’hui.'];
        }
        // Les réservations de ce même client (commande non payée qu'il recommence) ne le bloquent
        // pas ici : Orders::create annule alors son ancienne commande avant de réserver (sous verrou).
        if ($err = self::limits($p, $email, '', true)) {
            return ['error' => $err];
        }
        $eligible = 0;
        foreach ($items as $it) {
            if (!$p['models'] || in_array($it['model'], $p['models'], true)) {
                $eligible += (int) $it['total'];
            }
        }
        $sub = array_sum(array_column($items, 'total'));
        if ($eligible <= 0) {
            return ['error' => 'Ce code promo ne s’applique à aucun article de votre panier.'];
        }
        if ($sub < $p['min']) {
            return ['error' => 'Ce code promo demande au moins ' . Orders::money($p['min']) . ' d’achats.'];
        }
        $discount = match ($p['type']) {
            'percent' => (int) round($eligible * $p['value'] / 100),
            'amount' => min($eligible, $p['value']),
            default => 0,
        };
        $label = match ($p['type']) {
            'percent' => '−' . $p['value'] . ' %',
            'amount' => '−' . Orders::money($p['value']),
            default => 'livraison offerte',
        };
        return ['code' => $p['code'], 'discount' => $discount, 'free_shipping' => $p['type'] === 'shipping', 'label' => $label];
    }

    /** Nombre maximal et « une fois par client » (utilisations confirmées et réservations comprises). */
    private static function limits(array $p, string $email, string $order = '', bool $ownReservations = false): ?string
    {
        $email = strtolower($email);
        $uses = array_values(array_filter((array) ($p['uses'] ?? []), fn ($u) => is_array($u) && ($u['order'] ?? '') !== $order
            && !($ownReservations && $email !== '' && ($u['state'] ?? 'used') === 'reserved' && strtolower((string) ($u['email'] ?? '')) === $email)));
        if ($p['max'] > 0 && count($uses) >= $p['max']) {
            return 'Ce code promo a déjà été utilisé le nombre de fois prévu.';
        }
        if ($p['once'] && $email !== '' && in_array($email, array_map('strtolower', array_column($uses, 'email')), true)) {
            return 'Vous avez déjà utilisé ce code promo.';
        }
        return null;
    }

    /**
     * Réserve une utilisation pour une commande qui vient d'être créée : limites revérifiées sous
     * verrou, pour que deux commandes simultanées ne dépassent pas le nombre prévu.
     * @return array{ok?:bool,error?:string}
     */
    public static function reserve(string $code, string $order, string $email): array
    {
        $err = null;
        JsonStore::update(self::file(), function ($all) use ($code, $order, $email, &$err) {
            $all = is_array($all) ? $all : [];
            $code = self::code($code);
            if (!isset($all[$code]) || !is_array($all[$code])) {
                $err = 'Ce code promo n’existe pas.';
                return $all;
            }
            $p = self::normalize($all[$code]);
            if (!$p['active']) {
                $err = 'Ce code promo n’existe pas.';
                return $all;
            }
            if (in_array($order, array_column($p['uses'], 'order'), true)) {
                return $all;
            }
            if ($err = self::limits($p, $email, $order)) {
                return $all;
            }
            $all[$code]['uses'] = $p['uses'];
            $all[$code]['uses'][] = ['order' => $order, 'email' => strtolower($email), 'at' => date('c'), 'state' => 'reserved'];
            return $all;
        }, []);
        return $err !== null ? ['error' => $err] : ['ok' => true];
    }

    /** Commande annulée sans paiement : sa réservation est retirée (une utilisation confirmée reste). */
    public static function release(string $code, string $order): void
    {
        JsonStore::update(self::file(), function ($all) use ($code, $order) {
            $all = is_array($all) ? $all : [];
            $code = self::code($code);
            if (isset($all[$code]['uses']) && is_array($all[$code]['uses'])) {
                $all[$code]['uses'] = array_values(array_filter($all[$code]['uses'], fn ($u) => !is_array($u) || ($u['order'] ?? '') !== $order || ($u['state'] ?? 'used') !== 'reserved'));
            }
            return $all;
        }, []);
    }

    /** Utilisation confirmée au paiement de la commande (réservation confirmée, ou ajoutée si elle manquait). */
    public static function used(string $code, string $order, string $email): void
    {
        JsonStore::update(self::file(), function ($all) use ($code, $order, $email) {
            $all = is_array($all) ? $all : [];
            $code = self::code($code);
            if (!isset($all[$code])) {
                return $all;
            }
            $uses = array_values(array_filter((array) ($all[$code]['uses'] ?? []), 'is_array'));
            $i = array_search($order, array_column($uses, 'order'), true);
            if ($i === false) {
                $uses[] = ['order' => $order, 'email' => strtolower($email), 'at' => date('c'), 'state' => 'used'];
            } else {
                $uses[$i]['state'] = 'used';
            }
            $all[$code]['uses'] = $uses;
            return $all;
        }, []);
    }
}
