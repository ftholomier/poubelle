<?php
declare(strict_types=1);

namespace App\Front;

use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Data\Fiches;
use App\Data\Index;
use App\Services\I18n;
use App\Services\RetroDirect;

/**
 * Rétro-Direct (rubrique INTERACTIF) : programme des directs, page d'un match rejoué en direct
 * ou en accéléré, agenda (.ics) et API des réactions et du compteur de spectateurs.
 */
final class Retro
{
    public static function landing(Request $req): Response
    {
        $now = time();
        $prog = RetroDirect::program($now);
        $past = array_reverse(array_values(array_filter($prog, fn ($e) => $e['state'] === 'termine')));
        foreach ($past as &$p) {
            $p['stats'] = RetroDirect::stats($p['id'], $p['date']);
        }
        unset($p);
        $soon = array_values(array_filter($prog, fn ($e) => $e['state'] === 'avenir'));
        return Pages::render('interactif/retro-direct', [
            'live' => array_values(array_filter($prog, fn ($e) => $e['state'] === 'direct')),
            'soon' => array_slice($soon, 0, 12),
            'past' => array_slice($past, 0, 9),
            'classics' => RetroDirect::classics(8),
            'now' => $now,
        ], [
            'title' => t('Rétro-Direct : les grands matchs rejoués en direct'),
            'description' => t('Le jour anniversaire d’un grand match, à l’heure du coup d’envoi, le musée le rejoue minute par minute : score, buts, remplacements, réactions. Et tous les matchs à revivre en accéléré.'),
            'active' => 'interactif',
            'body_class' => 'page-retro',
            'styles' => ['css/mosaic.css', 'css/interactif.css', 'css/retro.css'],
            'scripts' => ['js/retro.js'],
        ]);
    }

    public static function show(Request $req, string $slug): ?Response
    {
        $s = RetroDirect::bySlug($slug);
        $doc = $s ? Fiches::get((int) $s['id']) : null;
        if (!$s || !$doc) {
            return null;
        }
        if (!RetroDirect::playable($doc)) {
            return Response::redirect(url($s['path']), 302);
        }
        $doc = Fiche::localizeDoc($doc);
        $m = $doc['match'];
        $now = time();
        $entry = RetroDirect::entryFor((int) $s['id'], $now);
        $mode = match ($entry['state'] ?? null) {
            'direct' => 'live',
            'avenir' => 'upcoming',
            default => 'replay',
        };
        $tl = RetroDirect::timelineFor((int) $s['id']);
        $rows = Fiche::lineupRows($doc, $m['lineup']['rows'] ?? []);
        $year = (int) substr((string) ($m['date'] ?? ''), 0, 4);
        // « Il y a N ans » : par rapport au direct à venir ou en cours, sinon à aujourd'hui (rediffusion).
        $refDate = $entry && $entry['state'] !== 'termine' ? $entry['date'] : date('Y-m-d', $now);
        $ago = $year ? (int) substr($refDate, 0, 4) - $year : 0;
        $sameDay = $year && substr((string) $m['date'], 5) === substr($refDate, 5);
        $home = (string) ($m['home']['name'] ?? '');
        $away = (string) ($m['away']['name'] ?? '');
        $intro = $entry ? trim(I18n::isEn() && $entry['intro_en'] !== '' ? $entry['intro_en'] : $entry['intro']) : '';
        $gallery = array_slice(array_values(array_filter($doc['gallery'] ?? [], fn ($g) => !empty($g['image']))), 0, 4);
        $labels = [
            'kickoff' => t('Coup d’envoi !'), 'halftime' => t('Mi-temps'), 'kickoff2' => t('Reprise de la seconde période'),
            'fulltime90' => t('Fin du temps réglementaire'), 'extratime' => t('Début de la prolongation'), 'pens' => t('Séance de tirs au but'),
            'fulltime' => t('Coup de sifflet final'), 'goal' => t('But !'), 'sub' => t('Remplacement'), 'yellow' => t('Carton jaune'), 'red' => t('Carton rouge'),
            'replaces' => t('{a} remplace {b}'), 'enters' => t('Entrée de {a}'), 'ht' => t('MT'), 'end' => t('Terminé'),
            'pensWin' => t('{team} l’emporte aux tirs au but ({a}-{b})'), 'aet' => t('Prolongation'), 'aetDone' => t('après prolongation'), 'pause' => t('Pause'),
            'tabShort' => t('TAB'), 'soon' => t('Le direct commence…'),
            'days' => t('j'), 'viewers1' => t('{n} personne suit le direct'), 'viewersN' => t('{n} personnes suivent le direct'),
            'etais1' => t('{n} supporter y était'), 'etaisN' => t('{n} supporters y étaient'),
            'play' => t('Lancer le match'), 'pauseBtn' => t('Pause'), 'resume' => t('Reprendre'), 'restart' => t('Revoir depuis le début'),
            'goalFor' => t('But de {who} pour {team}'), 'goalTeam' => t('But pour {team}'),
            'radioOn' => t('Couper le commentaire radio'), 'radioOff' => t('Écouter le commentaire radio'),
            'radioSpeed' => t('Le commentaire radio se joue à vitesse normale (×1).'), 'radioSoon' => t('Le commentaire radio démarrera au coup d’envoi.'),
            'radioBlocked' => t('Touchez à nouveau le bouton pour lancer le son.'),
        ];
        // Commentaire radio d'époque, s'il a été préparé pour ce match (dans la langue de la page, sinon en français).
        $lang = I18n::isEn() ? 'en' : 'fr';
        $radio = \App\Services\RetroRadio::playlist((int) $s['id'], $lang) ?? ($lang === 'en' ? \App\Services\RetroRadio::playlist((int) $s['id'], 'fr') : null);
        $data = [
            'mode' => $mode,
            'id' => (int) $s['id'],
            'date' => $entry['date'] ?? null,
            'now' => $now,
            'start' => $entry['start'] ?? null,
            'close' => $entry['end'] ?? null,
            'home' => $home,
            'away' => $away,
            'sh' => !empty($m['sochaux_home']),
            'events' => $tl['events'],
            'marks' => $tl['marks'],
            'aet' => $tl['aet'],
            'labels' => $labels,
            'lang' => I18n::isEn() ? 'en' : 'fr',
            'radio' => $radio,
            'ambiance' => $radio ? \App\Services\RetroRadio::ambianceUrl() : null,
        ];
        $when = $entry ? self::when((int) $entry['start']) : '';
        $title = trim("$home – $away", ' –');
        $page = [
            'title' => t('Rétro-Direct') . ' : ' . $title . ($m['date'] ? ', ' . date_fr($m['date']) : ''),
            'description' => $mode === 'upcoming'
                ? t('{match} rejoué en direct, minute par minute, {when}. Rendez-vous sur le site du musée.', ['match' => $title . ' (' . $year . ')', 'when' => $when])
                : t('Revivez {match} minute par minute, comme si vous y étiez : buts, remplacements, temps forts.', ['match' => $title . ' (' . date_fr($m['date']) . ')']),
            'image' => !empty($doc['featured_image']) ? img($doc['featured_image'], 1200) : null,
            'active' => 'interactif',
            'body_class' => 'page-retro',
            'styles' => ['css/fiche.css', 'css/interactif.css', 'css/retro.css'],
            'scripts' => ['js/retro.js'],
        ];
        return Pages::render('interactif/retro-match', [
            'doc' => $doc, 'm' => $m, 's' => $s, 'entry' => $entry, 'mode' => $mode, 'tl' => $tl, 'data' => $data,
            'rows' => $rows, 'pitch' => Fiche::pitch($rows), 'ago' => $ago, 'sameDay' => $sameDay, 'intro' => $intro, 'when' => $when,
            'gallery' => $gallery, 'stats' => $entry ? RetroDirect::stats((int) $s['id'], $entry['date']) : null,
            'etais' => RetroDirect::etais((int) $s['id']), 'home' => $home, 'away' => $away, 'title' => $title,
        ], $page);
    }

    /** « samedi 4 octobre à 20 h », « Saturday 4 October at 8:00 pm » (sans l'année si c'est celle-ci). */
    public static function when(int $ts): string
    {
        $date = date_fr(date('Y-m-d', $ts), true);
        if (date('Y', $ts) === date('Y')) {
            $date = preg_replace('/\s+\d{4}$/', '', $date);
        }
        if (I18n::isEn()) {
            return $date . ' at ' . date('g:i a', $ts);
        }
        return mb_strtolower(mb_substr($date, 0, 1)) . mb_substr($date, 1) . ' à ' . (date('i', $ts) === '00' ? date('G \h', $ts) : date('G \h i', $ts));
    }

    /** Agenda (.ics) : tous les directs à venir, ou un seul (?match=…&date=…). */
    public static function ics(Request $req): Response
    {
        $id = (int) $req->str('match');
        $date = $req->str('date');
        $lines = ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//Sochaux Retro//Retro-Direct//FR', 'CALSCALE:GREGORIAN', 'METHOD:PUBLISH',
            'X-WR-CALNAME:' . self::icsText(t('Rétro-Direct · Sochaux Rétro')), 'X-WR-TIMEZONE:Europe/Paris', 'REFRESH-INTERVAL;VALUE=DURATION:PT12H'];
        $host = parse_url(base_url(), PHP_URL_HOST) ?: 'fcsochauxretro.com';
        $n = 0;
        foreach (RetroDirect::program() as $e) {
            if ($e['state'] === 'termine' || ($id && ($e['id'] !== $id || $e['date'] !== $date))) {
                continue;
            }
            $s = $e['s'];
            $year = substr((string) ($s['m']['date'] ?? ''), 0, 4);
            $link = base_url() . RetroDirect::url($s);
            $intro = trim(I18n::isEn() && $e['intro_en'] !== '' ? $e['intro_en'] : $e['intro']);
            array_push($lines,
                'BEGIN:VEVENT',
                'UID:retro-' . $e['id'] . '-' . $e['date'] . '@' . $host,
                'DTSTAMP:' . gmdate('Ymd\THis\Z'),
                'DTSTART:' . gmdate('Ymd\THis\Z', $e['start']),
                'DTEND:' . gmdate('Ymd\THis\Z', $e['whistle']),
                'SUMMARY:' . self::icsText(t('Rétro-Direct') . ' : ' . ($s['m']['home'] ?? '') . ' – ' . ($s['m']['away'] ?? '') . " ($year)"),
                'DESCRIPTION:' . self::icsText(($intro !== '' ? $intro . "\n\n" : '') . t('Le match rejoué minute par minute sur le site du musée :') . "\n" . $link),
                'URL:' . $link,
                'END:VEVENT');
            $n++;
        }
        if ($id && !$n) {
            return Response::notFound();
        }
        $lines[] = 'END:VCALENDAR';
        $body = implode("\r\n", array_map([self::class, 'icsFold'], $lines)) . "\r\n";
        return new Response($body, 200, [
            'Content-Type' => 'text/calendar; charset=UTF-8',
            'Content-Disposition' => ($id ? 'attachment' : 'inline') . '; filename="' . ($id ? 'retro-direct-' . $id . '.ics' : 'retro-direct.ics') . '"',
            'Cache-Control' => 'public, max-age=900',
        ]);
    }

    private static function icsText(string $s): string
    {
        return str_replace(["\\", ';', ',', "\r\n", "\n"], ["\\\\", '\;', '\,', '\n', '\n'], $s);
    }

    /** Lignes de 75 octets au plus (RFC 5545), sans couper un caractère UTF-8. */
    private static function icsFold(string $line): string
    {
        $out = '';
        $cur = '';
        foreach (mb_str_split($line) as $ch) {
            if (strlen($cur) + strlen($ch) > ($out === '' ? 75 : 74)) {
                $out .= ($out === '' ? '' : "\r\n ") . $cur;
                $cur = '';
            }
            $cur .= $ch;
        }
        return $out === '' ? $cur : $out . "\r\n " . $cur;
    }

    /**
     * API (POST JSON /api/retro-direct) : action « presence » (compteur de spectateurs),
     * « react » (⚽ 👏 😱) pendant un direct ; « etais » (J'y étais !) pour tout match rejouable.
     */
    public static function api(Request $req): Response
    {
        // Présence toutes les 30 s + réactions : large, pour un groupe derrière un même Wi-Fi (club des anciens…).
        if (!RateLimiter::hit('retro', $req->ip(), 300, 60)) {
            return Response::json(['ok' => false, 'error' => t('Trop de requêtes, patientez un instant.')], 429);
        }
        $d = $req->json();
        $id = (int) ($d['id'] ?? 0);
        $action = (string) ($d['action'] ?? '');
        $s = $id ? Index::get($id) : null;
        if (!$s || $s['type'] !== 'match' || !Index::visible($s)) {
            return Response::json(['ok' => false], 404);
        }
        if ($action === 'etais') {
            if (!RateLimiter::hit('retro-etais', $req->ip() . '|' . $id, 2, 86400)) {
                return Response::json(['ok' => false, 'etais' => RetroDirect::etais($id)], 429);
            }
            return Response::json(['ok' => true, 'etais' => RetroDirect::addEtais($id)]);
        }
        $date = (string) ($d['date'] ?? '');
        $e = RetroDirect::entryFor($id, null, true); // fin exacte (chronologie), comme la page du direct
        if (!$e || $e['date'] !== $date || $e['state'] !== 'direct') {
            return Response::json(['ok' => false, 'error' => t('Ce direct n’est pas en cours.')], 409);
        }
        $kind = (string) ($d['kind'] ?? '');
        $counts = match (true) {
            $action === 'presence' => RetroDirect::presence($id, $date, (string) ($d['token'] ?? '')),
            $action === 'react' && isset(RetroDirect::REACTIONS[$kind]) => RetroDirect::react($id, $date, $kind),
            default => null,
        };
        if ($counts === null) {
            return Response::json(['ok' => false], 422);
        }
        return Response::json(['ok' => true] + $counts + ['etais' => RetroDirect::etais($id)]);
    }
}
