<?php
declare(strict_types=1);

namespace App\Services;

use App\Data\Fiches;
use App\Data\Index;
use App\Data\Paths;
use App\Front\Site;

/**
 * Les 100 moments du centenaire. Les historiens choisissent la date de parution de chaque
 * moment (statut « Planifié » et sa date, ou « Publié » tout de suite) : c'est la validation,
 * une seule suffit. Le numéro (1 à 100) suit l'ordre des dates et ne bouge plus une fois le
 * moment en ligne ; brouillons et moments à relire n'en ont pas. Le calendrier signale les
 * jours en double, les longs trous et le rythme à tenir jusqu'au centenaire, et propose pour
 * chaque moment la date anniversaire de l'événement.
 */
final class Moments
{
    /** Un trou plus long dans le calendrier est signalé (jours). */
    public const GAP_DAYS = 28;
    /** Heure de parution proposée. */
    public const HOUR = '08:00';
    private const ROBOT = ['name' => 'Calendrier des 100 moments'];

    /** Dernier jour de la série : le centenaire (14 juin 2028). */
    public static function end(): string
    {
        return Site::centenaryDate();
    }

    /**
     * Moments hors corbeille, dans l'ordre des dates (les moments sans date à la fin).
     * @return list<array{id:int,title:string,status:string,number:?int,date:?string,visible:bool,year:?int,event:?string,ai:?array,validated:?array,image:?string,path:string}>
     */
    public static function all(): array
    {
        $out = [];
        foreach (Index::all() as $id => $s) {
            if ($s['type'] === 'moment' && $s['status'] !== 'corbeille' && ($doc = Fiches::get((int) $id))) {
                $out[] = self::row($doc);
            }
        }
        usort($out, fn ($a, $b) => [$a['date'] === null, (string) $a['date'], $a['id']] <=> [$b['date'] === null, (string) $b['date'], $b['id']]);
        return $out;
    }

    /** Ce que le calendrier montre d'un moment. */
    public static function row(array $doc): array
    {
        $mo = $doc['moment'] ?? [];
        return [
            'id' => (int) $doc['id'],
            'title' => (string) ($doc['title'] ?? ''),
            'status' => (string) ($doc['status'] ?? 'brouillon'),
            'number' => isset($mo['number']) ? (int) $mo['number'] : null,
            'date' => self::dateOf($doc),
            'visible' => Fiches::isVisible($doc),
            'year' => isset($mo['year']) ? (int) $mo['year'] : null,
            'event' => self::eventDate($doc),
            'ai' => is_array($mo['ai'] ?? null) ? $mo['ai'] : null,
            'validated' => is_array($mo['validated'] ?? null) ? $mo['validated'] : null,
            'image' => $doc['featured_image'] ?? null,
            'path' => (string) ($doc['path'] ?? ''),
        ];
    }

    /** Date de parution d'un moment validé (planifié ou publié), sinon null. */
    public static function dateOf(array $doc): ?string
    {
        $status = $doc['status'] ?? '';
        if ($status === 'planifie') {
            return !empty($doc['publish_at']) ? date('c', (int) strtotime((string) $doc['publish_at'])) : null;
        }
        if ($status === 'publie') {
            // Paru à sa date programmée (publish_at passée), sinon publié à la main : date de publication.
            $p = !empty($doc['publish_at']) ? strtotime((string) $doc['publish_at']) : false;
            $d = !empty($doc['date']) ? strtotime((string) $doc['date']) : false;
            $ts = $p && $p <= time() ? $p : ($d ?: $p);
            return $ts ? date('c', (int) $ts) : null;
        }
        return null;
    }

    /** Date de l'événement raconté : saisie dans la fiche, sinon celle du premier match lié. */
    public static function eventDate(array $doc): ?string
    {
        $d = (string) ($doc['moment']['event_date'] ?? '');
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
            return $d;
        }
        foreach ($doc['moment']['linked'] ?? [] as $id) {
            $s = Index::get((int) $id);
            if ($s && $s['type'] === 'match' && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($s['m']['date'] ?? ''))) {
                return (string) $s['m']['date'];
            }
        }
        return null;
    }

    /**
     * Date anniversaire proposée : le prochain anniversaire de l'événement d'ici le centenaire,
     * de préférence un jour encore libre. @param list<string> $taken jours déjà pris (AAAA-MM-JJ)
     * @return array{date:string,years:int,taken:bool}|null
     */
    public static function anniversary(?string $event, array $taken = [], ?int $now = null): ?array
    {
        if (!$event || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $event, $m)) {
            return null;
        }
        $now ??= time();
        $today = date('Y-m-d', $now);
        $end = self::end();
        $found = [];
        for ($y = (int) date('Y', $now); $y <= (int) substr($end, 0, 4); $y++) {
            $day = $m[2] === '02' && $m[3] === '29' && !checkdate(2, 29, $y) ? '28' : $m[3];
            $d = sprintf('%04d-%s-%s', $y, $m[2], $day);
            if ($d > $today && $d <= $end && $y > (int) $m[1]) {
                $found[] = ['date' => $d, 'years' => $y - (int) $m[1], 'taken' => in_array($d, $taken, true)];
            }
        }
        foreach ($found as $f) {
            if (!$f['taken']) {
                return $f;
            }
        }
        return $found[0] ?? null;
    }

    /**
     * Contrôle d'un moment avant enregistrement (la date choisie est la validation).
     * @return string|null message d'erreur
     */
    public static function check(array $doc, ?array $before = null): ?string
    {
        if (($doc['type'] ?? '') !== 'moment' || !in_array($doc['status'] ?? '', ['planifie', 'publie'], true)) {
            return null;
        }
        if ($doc['status'] === 'planifie') {
            $at = !empty($doc['publish_at']) ? strtotime((string) $doc['publish_at']) : false;
            if (!$at) {
                return 'Choisissez la date de parution du moment (statut « Planifié », dans Publication).';
            }
            $same = $before && ($before['status'] ?? '') === 'planifie' && strtotime((string) ($before['publish_at'] ?? '')) === $at;
            if ($at < time() - 300 && !$same) {
                return 'Cette date est passée : choisissez une date à venir, ou « Publié » pour le mettre en ligne tout de suite.';
            }
            if (date('Y-m-d', $at) > self::end()) {
                return 'La série se termine au centenaire, le ' . date_fr(self::end()) . ' : choisissez une date avant.';
            }
        }
        $wasDated = $before && in_array($before['status'] ?? '', ['planifie', 'publie'], true);
        if (!$wasDated) {
            $dated = count(array_filter(self::all(), fn ($r) => $r['date'] !== null && $r['id'] !== (int) ($doc['id'] ?? 0)));
            if ($dated >= 100) {
                return 'Les 100 moments ont déjà une date : remettez-en un en brouillon avant d’en valider un autre.';
            }
        }
        return null;
    }

    /**
     * Note de validation : la personne qui a daté le moment (planifié ou publié) ; retirée quand il
     * repasse en brouillon ou à relire.
     */
    public static function stamp(array $doc, ?array $before, ?array $user): array
    {
        if (($doc['type'] ?? '') !== 'moment') {
            return $doc;
        }
        $dated = in_array($doc['status'] ?? '', ['planifie', 'publie'], true);
        $wasDated = $before && in_array($before['status'] ?? '', ['planifie', 'publie'], true);
        if ($dated && (!$wasDated || empty($doc['moment']['validated']))) {
            $doc['moment']['validated'] = ['by' => (string) ($user['name'] ?? 'Équipe'), 'at' => date('c')];
        } elseif (!$dated) {
            $doc['moment']['validated'] = null;
        }
        return $doc;
    }

    /**
     * Numéros dans l'ordre des dates : un moment en ligne garde le sien ; les moments datés pas
     * encore en ligne prennent les suivants, dans l'ordre de leur date ; les autres (brouillons, à
     * relire, corbeille) n'en ont pas, ni les moments datés au-delà de 100.
     * @param array<int,array> $docs fiches « moment »
     * @return array<int,?int> numéro de fiche => numéro du moment
     */
    public static function numbering(array $docs): array
    {
        $frozen = [];
        $queue = [];
        foreach ($docs as $doc) {
            if (self::dateOf($doc) === null) {
                continue;
            }
            $n = (int) ($doc['moment']['number'] ?? 0);
            if (Fiches::isVisible($doc) && $n >= 1 && $n <= 100) {
                $frozen[$n][] = $doc;
            } else {
                $queue[] = $doc;
            }
        }
        $byDate = fn ($a, $b) => [(string) self::dateOf($a), (int) $a['id']] <=> [(string) self::dateOf($b), (int) $b['id']];
        $assign = array_fill_keys(array_map(fn ($d) => (int) $d['id'], $docs), null);
        foreach ($frozen as $n => $list) {
            // Deux moments en ligne avec le même numéro (données anciennes) : le plus ancien le garde.
            usort($list, $byDate);
            $assign[(int) $list[0]['id']] = $n;
            array_push($queue, ...array_slice($list, 1));
        }
        usort($queue, $byDate);
        $n = $frozen ? max(array_keys($frozen)) : 0;
        foreach ($queue as $doc) {
            $n++;
            $assign[(int) $doc['id']] = $n <= 100 ? $n : null;
        }
        return $assign;
    }

    /**
     * Applique numbering() aux fiches : numéro et, tant que le moment n'est pas en ligne, adresse
     * (qui contient le numéro). @return int fiches renumérotées
     */
    public static function renumber(?array $user = null): int
    {
        $docs = [];
        foreach (Index::all() as $id => $s) {
            if ($s['type'] === 'moment' && ($doc = Fiches::fresh((int) $id))) {
                $docs[(int) $id] = $doc;
            }
        }
        $assign = self::numbering($docs);
        $changed = 0;
        Fiches::batch(function () use ($docs, $assign, $user, &$changed) {
            foreach ($docs as $id => $doc) {
                $new = $assign[$id] ?? null;
                $old = isset($doc['moment']['number']) ? (int) $doc['moment']['number'] : null;
                if ($old === $new) {
                    continue;
                }
                $doc['moment']['number'] = $new;
                if (!Fiches::isVisible($doc) && empty($doc['published_once'])) {
                    $doc['path'] = Paths::unique(Paths::suggest($doc), (int) $id);
                    $doc['slug'] = basename(rtrim($doc['path'], '/'));
                }
                Fiches::save($doc, $user ?? self::ROBOT, $new ? 'Moment n° ' . $new . ' (ordre des dates de parution)' : 'Sans numéro (pas de date de parution)');
                $changed++;
            }
        });
        return $changed;
    }

    /**
     * Ce que le calendrier doit signaler : deux moments le même jour, un long trou, un numéro en
     * ligne retiré, des moments au-delà des 100, le rythme à tenir.
     * @param list<array> $rows Moments::all()
     * @return list<array{level:string,text:string,ids:list<int>}>
     */
    public static function alerts(array $rows, ?int $now = null): array
    {
        $now ??= time();
        $out = [];
        $dated = array_values(array_filter($rows, fn ($r) => $r['date'] !== null));
        $byDay = [];
        foreach ($dated as $r) {
            $byDay[substr($r['date'], 0, 10)][] = $r;
        }
        foreach ($byDay as $day => $list) {
            if (count($list) > 1) {
                $out[] = ['level' => 'warn', 'text' => count($list) . ' moments le même jour, le ' . date_fr($day) . ' : ' . implode(', ', array_map(fn ($r) => '« ' . $r['title'] . ' »', $list)) . '.', 'ids' => array_column($list, 'id')];
            }
        }
        // Trous : entre aujourd'hui, les moments à venir et le centenaire.
        $future = array_values(array_filter($dated, fn ($r) => strtotime($r['date']) > $now));
        $marks = array_merge([['date' => date('c', $now), 'title' => null]], $future);
        if (count($dated) < 100) {
            $marks[] = ['date' => self::end() . 'T23:59:00', 'title' => null, 'end' => true];
        }
        for ($i = 1; $i < count($marks); $i++) {
            $a = strtotime($marks[$i - 1]['date']);
            $b = strtotime($marks[$i]['date']);
            $days = (int) floor(($b - $a) / 86400);
            if ($days > self::GAP_DAYS) {
                $out[] = ['level' => 'info', 'text' => 'Aucun moment ' . ($i === 1 ? 'd’ici' : 'entre le ' . date_fr(date('Y-m-d', $a)) . ' et') . ' le ' . date_fr(date('Y-m-d', $b)) . (!empty($marks[$i]['end']) ? ' (centenaire)' : '') . ' : ' . (int) round($days / 7) . ' semaines.', 'ids' => []];
            }
        }
        $online = array_filter($dated, fn ($r) => $r['visible'] && $r['number']);
        $max = $online ? max(array_column($online, 'number')) : 0;
        $missing = array_diff(range(1, max(1, $max)), array_column($online, 'number'));
        if ($max && $missing) {
            $out[] = ['level' => 'warn', 'text' => 'Numéro' . (count($missing) > 1 ? 's' : '') . ' sans moment en ligne (moment retiré) : ' . implode(', ', array_map(fn ($n) => 'n° ' . $n, $missing)) . '. La case reste « À venir » sur le site.', 'ids' => []];
        }
        $over = array_filter($dated, fn ($r) => $r['number'] === null);
        if ($over) {
            $out[] = ['level' => 'warn', 'text' => count($over) . ' moment' . (count($over) > 1 ? 's' : '') . ' daté' . (count($over) > 1 ? 's' : '') . ' au-delà des 100 : ' . implode(', ', array_map(fn ($r) => '« ' . $r['title'] . ' »', $over)) . '.', 'ids' => array_column($over, 'id')];
        }
        $late = array_filter($dated, fn ($r) => substr($r['date'], 0, 10) > self::end());
        if ($late) {
            $out[] = ['level' => 'warn', 'text' => 'Après le centenaire : ' . implode(', ', array_map(fn ($r) => '« ' . $r['title'] . ' »', $late)) . '.', 'ids' => array_column($late, 'id')];
        }
        $noImage = array_filter($future, fn ($r) => !$r['image']);
        if ($noImage) {
            $out[] = ['level' => 'info', 'text' => 'Sans image (case vide sur le site) : ' . implode(', ', array_map(fn ($r) => '« ' . $r['title'] . ' »', $noImage)) . '.', 'ids' => array_column($noImage, 'id')];
        }
        return $out;
    }

    /** Rythme à tenir : moments encore à dater, semaines restantes jusqu'au centenaire. @return array{left:int,weeks:int,per_week:float} */
    public static function pace(array $rows, ?int $now = null): array
    {
        $now ??= time();
        $left = max(0, 100 - count(array_filter($rows, fn ($r) => $r['date'] !== null)));
        $weeks = max(0, (int) floor((strtotime(self::end() . ' 23:59:00') - $now) / (7 * 86400)));
        return ['left' => $left, 'weeks' => $weeks, 'per_week' => $weeks ? round($left / $weeks, 1) : (float) $left];
    }
}
