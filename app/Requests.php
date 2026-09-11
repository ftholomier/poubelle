<?php
declare(strict_types=1);

namespace App;

/** Demandes entrantes (contact, réservation, rappel de dispos) — content/requests.json. */
final class Requests
{
    public const FILE = 'requests.json';
    public const STATUSES = ['new' => 'Nouveau', 'visit' => 'Visite', 'answered' => 'Répondu', 'followup' => 'Relancer', 'closed' => 'Clos'];

    public static function all(): array
    {
        $data = Store::read(self::FILE);
        $items = \is_array($data['requests'] ?? null) ? $data['requests'] : [];
        usort($items, static fn (array $a, array $b): int => strcmp((string) ($b['at'] ?? ''), (string) ($a['at'] ?? '')));
        return $items;
    }

    /** @return array{ref:string} */
    public static function add(array $payload): array
    {
        $items = self::all();
        $ref = self::makeRef($payload['type'] ?? 'contact');
        array_unshift($items, array_replace([
            'ref' => $ref,
            'type' => 'contact',
            'name' => '',
            'email' => '',
            'phone' => '',
            'subject' => '',
            'message' => '',
            'officeId' => '',
            'startDate' => '',
            'lang' => I18n::lang(),
            'source' => '',
            'status' => 'new',
            // Quarantaine : la demande est conservée et visible au back-office,
            // mais n'a déclenché aucun email tant qu'elle n'est pas validée.
            'spam' => false,
            'spamScore' => 0,
            'spamReasons' => [],
            'at' => (new \DateTimeImmutable())->format(\DATE_ATOM),
            'ip' => hash('sha256', RateLimit::ip() . '|ioio'), // pseudonymisée : jamais l'IP en clair
        ], $payload, ['ref' => $ref]));

        // Rétention : 24 mois annoncés dans les mentions légales.
        $limit = (new \DateTimeImmutable('-24 months'))->format(\DATE_ATOM);
        $items = array_values(array_filter($items, static fn (array $r): bool => (string) ($r['at'] ?? '') >= $limit));

        Store::write(self::FILE, ['_schema' => Config::SCHEMA, 'requests' => \array_slice($items, 0, 2000)]);
        return ['ref' => $ref];
    }

    /** Demandes normales (false) ou mises de côté (true). */
    public static function filterSpam(array $items, bool $spam): array
    {
        return array_values(array_filter($items, static fn (array $r): bool => (bool) ($r['spam'] ?? false) === $spam));
    }

    public static function countSpam(): int
    {
        return \count(self::filterSpam(self::all(), true));
    }

    /** Sort une demande de la quarantaine, ou l'y remet. */
    public static function setSpam(string $ref, bool $spam, string $by): bool
    {
        $items = self::all();
        foreach ($items as $i => $item) {
            if ((string) ($item['ref'] ?? '') === $ref) {
                $items[$i]['spam'] = $spam;
                return Store::write(self::FILE, ['_schema' => Config::SCHEMA, 'requests' => $items], $by);
            }
        }
        return false;
    }

    public static function setStatus(string $ref, string $status, string $by): bool
    {
        if (!isset(self::STATUSES[$status])) {
            return false;
        }
        $items = self::all();
        foreach ($items as $i => $item) {
            if ((string) ($item['ref'] ?? '') === $ref) {
                $items[$i]['status'] = $status;
                $items[$i]['statusAt'] = (new \DateTimeImmutable())->format(\DATE_ATOM);
                return Store::write(self::FILE, ['_schema' => Config::SCHEMA, 'requests' => $items], $by);
            }
        }
        return false;
    }

    public static function delete(string $ref, string $by): bool
    {
        $items = array_values(array_filter(self::all(), static fn (array $r): bool => (string) ($r['ref'] ?? '') !== $ref));
        return Store::write(self::FILE, ['_schema' => Config::SCHEMA, 'requests' => $items], $by);
    }

    public static function countSince(string $modifier = '-30 days'): int
    {
        $limit = (new \DateTimeImmutable($modifier))->format(\DATE_ATOM);
        return \count(array_filter(self::all(), static fn (array $r): bool => (string) ($r['at'] ?? '') >= $limit));
    }

    public static function statusLabel(string $status): string
    {
        return self::STATUSES[$status] ?? $status;
    }

    /** Couleur de pastille, conforme au design du back-office. */
    public static function statusColor(string $status): string
    {
        return match ($status) {
            'new' => '#FFD100',
            'visit' => '#12B39A',
            'followup' => '#FFD100',
            default => '#EDE5D5',
        };
    }

    private static function makeRef(string $type): string
    {
        $prefix = match ($type) {
            'reserve' => 'R',
            'lead' => 'L',
            default => 'C',
        };
        return $prefix . '-' . date('dm') . '-' . str_pad((string) random_int(1, 999), 3, '0', STR_PAD_LEFT);
    }
}
