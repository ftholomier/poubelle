<?php
declare(strict_types=1);

namespace App\Widgets\Items;

use App\Services\RetroDirect;
use App\Widgets\Context;
use App\Widgets\Frame;
use App\Widgets\Photos;
use App\Widgets\Widget;

/**
 * Rétro-Direct : le grand match rejoué minute par minute (programme du Rétro-Direct, page
 * /interactif/retro-direct/) — celui en direct maintenant ou du jour, sinon le prochain au
 * programme ; sans programme, un classique à revivre en accéléré (RetroDirect::classics, un par
 * jour). Le score final n'est pas donné : il se découvre pendant le direct.
 */
final class RetroDirectWidget extends Widget
{
    public function key(): string
    {
        return 'retro-direct';
    }

    public function name(string $lang = 'fr'): string
    {
        return $lang === 'en' ? 'Retro-Live' : 'Rétro-Direct';
    }

    public function description(string $lang = 'fr'): string
    {
        return $lang === 'en' ? 'A great match replayed minute by minute on its anniversary: today’s, otherwise the next one.'
            : 'Un grand match rejoué minute par minute le jour anniversaire : celui du jour, sinon le prochain.';
    }

    public function height(): int
    {
        return 520;
    }

    public function ttl(): int
    {
        return 600;
    }

    public function data(array $opt, Context $c): ?array
    {
        $e = RetroDirect::next();
        $state = 'classique';
        if ($e) {
            $s = $e['s'];
            $state = $e['state'] === 'direct' ? 'direct' : ($e['date'] === date('Y-m-d') ? 'aujourdhui' : 'avenir');
        } else {
            $cl = RetroDirect::classics(8);
            if (!$cl) {
                return null;
            }
            $s = $cl[crc32('retro|' . date('Y-m-d')) % count($cl)]['s'];
        }
        $m = (array) ($s['m'] ?? []);
        $date = (string) ($m['date'] ?? $s['date'] ?? '');
        $intro = $e ? (string) ($c->en() && $e['intro_en'] !== '' ? $e['intro_en'] : $e['intro']) : '';
        $photo = Photos::safe((string) ($s['image'] ?? ''), 800);
        return [
            'state' => $state,
            'home' => (string) ($m['home'] ?? ''),
            'away' => (string) ($m['away'] ?? ''),
            'competition' => trim((string) (($m['label'] ?? '') ?: ($m['competition'] ?? '')) . (!empty($m['round']) ? ' · ' . $m['round'] : '')),
            'played' => $date,
            'years' => $date !== '' ? (int) date('Y') - (int) substr($date, 0, 4) : null,
            'start' => $e ? date('c', (int) $e['start']) : null,
            'day' => $e ? $e['date'] : null,
            'time' => $e ? $e['time'] : null,
            'intro' => self::excerpt($intro !== '' ? $intro : (string) ($s['excerpt'] ?? ''), 240),
            'image' => $photo ? ['url' => $photo['url'], 'credit' => $photo['credit'], 'license' => $photo['license'], 'rel' => $photo['rel']] : null,
            'href' => self::local(RetroDirect::url($s)),
        ];
    }

    /** Phrase d'annonce (direct, aujourd'hui à…, le … à…, à revivre). */
    public static function when(array $d, Context $c): string
    {
        $day = fn (string $iso) => $c->en() ? date('l j F', strtotime($iso)) : date_fr($iso);
        return match ($d['state']) {
            'direct' => $c->tr('En direct maintenant', 'Live now'),
            'aujourdhui' => $c->tr('Aujourd’hui à ' . str_replace(':', ' h ', (string) $d['time']), 'Today at ' . $d['time']),
            'avenir' => $c->tr('Le ' . $day((string) $d['day']) . ' à ' . str_replace(':', ' h ', (string) $d['time']), 'On ' . $day((string) $d['day']) . ' at ' . $d['time']),
            default => $c->tr('À revivre en accéléré', 'Relive it in fast forward'),
        };
    }

    public function body(array $d, Context $c): string
    {
        $h = '<p class="w-eyebrow">' . ($d['state'] === 'direct' ? '<span class="w-live"><i></i>' : '<span>') . e(self::when($d, $c)) . '</span></p>';
        $h .= Frame::media($d['image'], trim($d['home'] . ' – ' . $d['away']));
        $h .= '<div class="w-match"><a href="' . e($d['href']) . '" target="_blank" rel="noopener"><span>' . e($d['home']) . '</span><b>×</b><span>' . e($d['away']) . '</span></a></div>';
        $h .= '<p class="w-text w-center small">' . e($d['competition'] . ($d['played'] !== '' ? ' · ' . ($c->en() ? date('j F Y', strtotime($d['played'])) : date_fr($d['played'])) : '')) . ($d['years'] ? ' · ' . e($c->tr('il y a ' . $d['years'] . ' ans', $d['years'] . ' years ago')) : '') . '</p>';
        if ($d['intro'] !== '') {
            $h .= '<p class="w-text w-clamp">' . e($d['intro']) . '</p>';
        }
        return $h . '<p><a class="w-btn" href="' . e($d['href']) . '" target="_blank" rel="noopener">' . e($d['state'] === 'direct' ? $c->tr('Rejoindre le direct', 'Join the live') : $c->tr('Le Rétro-Direct', 'The Retro-Live')) . ' →</a></p>';
    }
}
