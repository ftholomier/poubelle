<?php
declare(strict_types=1);

namespace LD;

/**
 * Agenda de prise de rendez-vous : créneaux disponibles, réservations, annulation, fichiers .ics.
 */
final class Booking
{
    private const FILE = 'data/bookings.json';

    public static function config(): array
    {
        return (array) Settings::get('booking', []);
    }

    public static function all(): array
    {
        return Store::read(self::FILE, []);
    }

    private static function takenMap(): array
    {
        $map = [];
        foreach (self::all() as $b) {
            if (($b['status'] ?? '') === 'confirmed') {
                $map[$b['date'] . ' ' . $b['time']] = true;
            }
        }
        return $map;
    }

    /** Créneaux horaires d'une date (réglage par jour de la semaine, sinon liste commune). */
    private static function slotsFor(string $date): array
    {
        $c = self::config();
        $wd = (int) date('N', strtotime($date));
        $per = $c['slots_by_day'][(string) $wd] ?? null;
        $slots = is_array($per) && $per ? $per : ($c['slots'] ?? []);
        $slots = array_values(array_filter(array_map('trim', $slots), static fn($s) => preg_match('/^\d{2}:\d{2}$/', $s)));
        sort($slots);
        return $slots;
    }

    /**
     * Prochains jours ouvrés proposés, avec l'état de chaque créneau.
     * @return array<int, array{date:string, wd:string, n:int, slots: array<int, array{t:string, free:bool}>}>
     */
    public static function days(): array
    {
        $c = self::config();
        $shown = max(1, min(10, (int) ($c['days_shown'] ?? 5)));
        $weekdays = array_map('intval', $c['weekdays'] ?? [1, 2, 3, 4, 5]);
        $blocked = array_flip($c['blocked_dates'] ?? []);
        $minTs = time() + 3600 * (int) ($c['min_notice_hours'] ?? 12);
        $maxAhead = max($shown, (int) ($c['max_days_ahead'] ?? 30));
        $taken = self::takenMap();
        $wdNames = ['dim.', 'lun.', 'mar.', 'mer.', 'jeu.', 'ven.', 'sam.'];
        $days = [];
        for ($i = 1; $i <= $maxAhead && count($days) < $shown; $i++) {
            $ts = strtotime("+$i day 00:00");
            $date = date('Y-m-d', $ts);
            if (!in_array((int) date('N', $ts), $weekdays, true) || isset($blocked[$date])) {
                continue;
            }
            $slots = [];
            $anyFree = false;
            foreach (self::slotsFor($date) as $t) {
                $free = !isset($taken[$date . ' ' . $t]) && strtotime($date . ' ' . $t) >= $minTs;
                $anyFree = $anyFree || $free;
                $slots[] = ['t' => $t, 'free' => $free];
            }
            if (!$anyFree) {
                continue;
            }
            $days[] = ['date' => $date, 'wd' => $wdNames[(int) date('w', $ts)], 'n' => (int) date('j', $ts), 'label' => date_fr($date, true), 'slots' => $slots];
        }
        return $days;
    }

    public static function isAvailable(string $date, string $time): bool
    {
        foreach (self::days() as $d) {
            if ($d['date'] === $date) {
                foreach ($d['slots'] as $s) {
                    if ($s['t'] === $time) {
                        return $s['free'];
                    }
                }
            }
        }
        return false;
    }

    public static function book(array $in): array
    {
        $date = (string) $in['date'];
        $time = (string) $in['time'];
        $booking = null;
        Store::update(self::FILE, static function (array &$all) use ($in, $date, $time, &$booking): void {
            foreach ($all as $b) {
                if (($b['status'] ?? '') === 'confirmed' && $b['date'] === $date && $b['time'] === $time) {
                    throw new \RuntimeException('Oups, ce créneau vient d’être pris. Choisis-en un autre 🙂');
                }
            }
            $booking = [
                'id'         => bin2hex(random_bytes(6)),
                'token'      => bin2hex(random_bytes(12)),
                'status'     => 'confirmed',
                'date'       => $date,
                'time'       => $time,
                'duration'   => (int) (Settings::get('booking.duration', 45)),
                'mode'       => (string) $in['mode'],
                'name'       => (string) $in['name'],
                'email'      => (string) $in['email'],
                'phone'      => (string) $in['phone'],
                'sms'        => (bool) $in['sms'],
                'note'       => (string) ($in['note'] ?? ''),
                'created_at' => date('c'),
                'reminded'   => false,
            ];
            $all[] = $booking;
        });
        return $booking;
    }

    public static function find(string $id): ?array
    {
        foreach (self::all() as $b) {
            if ($b['id'] === $id) {
                return $b;
            }
        }
        return null;
    }

    public static function setStatus(string $id, string $status): ?array
    {
        $found = null;
        Store::update(self::FILE, static function (array &$all) use ($id, $status, &$found): void {
            foreach ($all as &$b) {
                if ($b['id'] === $id) {
                    $b['status'] = $status;
                    $b['updated_at'] = date('c');
                    $found = $b;
                }
            }
        });
        return $found;
    }

    public static function markReminded(string $id): void
    {
        Store::update(self::FILE, static function (array &$all) use ($id): void {
            foreach ($all as &$b) {
                if ($b['id'] === $id) {
                    $b['reminded'] = true;
                }
            }
        });
    }

    public static function upcoming(): array
    {
        $now = date('Y-m-d H:i');
        $list = array_filter(self::all(), static fn($b) => ($b['status'] ?? '') === 'confirmed' && ($b['date'] . ' ' . $b['time']) >= $now);
        usort($list, static fn($a, $b) => strcmp($a['date'] . $a['time'], $b['date'] . $b['time']));
        return array_values($list);
    }

    public static function past(int $limit = 50): array
    {
        $now = date('Y-m-d H:i');
        $list = array_filter(self::all(), static fn($b) => ($b['date'] . ' ' . $b['time']) < $now || ($b['status'] ?? '') !== 'confirmed');
        usort($list, static fn($a, $b) => strcmp($b['date'] . $b['time'], $a['date'] . $a['time']));
        return array_slice(array_values($list), 0, $limit);
    }

    public static function label(array $b): string
    {
        return $b['mode'] . ', ' . date_fr($b['date'], true, false) . ' à ' . $b['time'];
    }

    /** Fichier iCalendar d'un ou plusieurs rendez-vous. */
    public static function ics(array $bookings, bool $owner = false): string
    {
        $esc = static fn(string $s) => str_replace(['\\', ';', ',', "\n"], ['\\\\', '\;', '\,', '\n'], $s);
        $lines = ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//LE-DIGITAL//RdV//FR', 'CALSCALE:GREGORIAN', 'METHOD:PUBLISH'];
        if ($owner) {
            $lines[] = 'X-WR-CALNAME:RdV LE-DIGITAL.com';
        }
        $address = (string) Settings::get('booking.address', '');
        $visio = (string) Settings::get('booking.visio_link', '');
        foreach ($bookings as $b) {
            $start = new \DateTime($b['date'] . ' ' . $b['time'], new \DateTimeZone('Europe/Paris'));
            $start->setTimezone(new \DateTimeZone('UTC'));
            $end = (clone $start)->modify('+' . (int) ($b['duration'] ?? 45) . ' minutes');
            $summary = $owner
                ? 'Café · ' . $b['name'] . ' (' . $b['mode'] . ')'
                : 'Café avec Fred · LE-DIGITAL.com (' . $b['mode'] . ')';
            $desc = $owner
                ? trim($b['name'] . ' · ' . $b['phone'] . ' · ' . $b['email'] . ($b['note'] ? ' · ' . $b['note'] : ''))
                : 'Premier échange gratuit. Ton business et le digital.' . ($visio && str_contains(mb_strtolower($b['mode']), 'visio') ? ' Lien visio : ' . $visio : '');
            $lines[] = 'BEGIN:VEVENT';
            $lines[] = 'UID:' . $b['id'] . '@le-digital.com';
            $lines[] = 'DTSTAMP:' . gmdate('Ymd\THis\Z');
            $lines[] = 'DTSTART:' . $start->format('Ymd\THis\Z');
            $lines[] = 'DTEND:' . $end->format('Ymd\THis\Z');
            $lines[] = 'SUMMARY:' . $esc($summary);
            $lines[] = 'DESCRIPTION:' . $esc($desc);
            if ($address !== '' && !str_contains(mb_strtolower($b['mode']), 'visio')) {
                $lines[] = 'LOCATION:' . $esc($address);
            }
            if (($b['status'] ?? 'confirmed') !== 'confirmed') {
                $lines[] = 'STATUS:CANCELLED';
            }
            $lines[] = 'END:VEVENT';
        }
        $lines[] = 'END:VCALENDAR';
        return implode("\r\n", $lines) . "\r\n";
    }

    public static function icsKey(): string
    {
        return substr(hash_hmac('sha256', 'ics-feed', Settings::appSecret()), 0, 24);
    }
}
