<?php
declare(strict_types=1);

namespace App\Widgets\Items;

use App\Data\Derived;
use App\Front\Site;
use App\Widgets\Context;
use App\Widgets\Frame;
use App\Widgets\Photos;
use App\Widgets\Widget;

/** Ce jour-là : les matchs joués à cette date dans l'histoire (Derived::onThisDay). */
final class CeJourLa extends Widget
{
    public function key(): string
    {
        return 'ce-jour-la';
    }

    public function name(string $lang = 'fr'): string
    {
        return $lang === 'en' ? 'On this day' : 'Ce jour-là';
    }

    public function description(string $lang = 'fr'): string
    {
        return $lang === 'en' ? 'The match played on this date in the club’s history: score, competition, photo and link to the match page.'
            : 'Le match joué à cette date dans l’histoire du club : score, compétition, photo et lien vers la fiche.';
    }

    public function options(): array
    {
        return [
            'date' => ['label' => 'Date (MM-JJ, vide : aujourd’hui)', 'label_en' => 'Date (MM-DD, empty: today)', 'type' => 'text', 'default' => '', 'pattern' => '/^(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01])$/'],
            'nombre' => ['label' => 'Matchs listés', 'label_en' => 'Matches listed', 'type' => 'select', 'default' => '3', 'choices' => ['1' => '1', '3' => '3', '5' => '5']],
        ];
    }

    public function height(): int
    {
        return 460;
    }

    public function data(array $opt, Context $c): ?array
    {
        $mmdd = $opt['date'] !== '' ? $opt['date'] : date('m-d');
        $year = (int) date('Y');
        $list = [];
        foreach (array_slice(Derived::onThisDay($mmdd), 0, (int) $opt['nombre']) as $m) {
            $photo = Photos::safe((string) ($m['image'] ?? ''), 800);
            $list[] = [
                'label' => Site::matchLabel($m),
                'date' => (string) $m['date'],
                'years_ago' => $year - (int) substr((string) $m['date'], 0, 4),
                'competition' => (string) (($m['label'] ?? '') ?: ($m['comp'] ?? '')),
                'round' => (string) ($m['round'] ?? ''),
                'result' => (string) ($m['result'] ?? ''),
                'home' => (string) ($m['home'] ?? ''),
                'away' => (string) ($m['away'] ?? ''),
                'spectators' => (int) ($m['spectators'] ?? 0) ?: null,
                'href' => (string) $m['path'],
                'image' => $photo ? ['url' => $photo['url'], 'credit' => $photo['credit'], 'license' => $photo['license']] : null,
            ];
        }
        return ['date' => $mmdd, 'date_label' => self::dayLabel($mmdd, $c), 'matches' => $list, 'more' => ['href' => '/saisons/']];
    }

    public function body(array $d, Context $c): string
    {
        $h = '<p class="w-eyebrow">' . e($c->tr('Ce jour-là', 'On this day')) . ' · ' . e($d['date_label']) . '</p>';
        if (!$d['matches']) {
            return $h . '<p class="w-title">' . e($c->tr('Aucun match fiché à cette date.', 'No match on record for this date.')) . '</p>'
                . '<p class="w-text">' . e($c->tr('Explorez toutes les saisons du club dans le musée.', 'Explore every season of the club in the museum.')) . '</p>'
                . '<p><a class="w-btn" href="' . e($d['more']['href']) . '" target="_blank" rel="noopener">' . e($c->tr('Toutes les saisons', 'All seasons')) . ' →</a></p>';
        }
        $m = $d['matches'][0];
        $h .= Frame::media($m['image'], $m['label']);
        $h .= '<p class="w-kicker">' . e(self::ago($m['years_ago'], $c)) . ' · ' . e(date_fr($m['date'])) . '</p>';
        $h .= '<p class="w-title"><a href="' . e($m['href']) . '" target="_blank" rel="noopener">' . e($m['label']) . '</a></p>';
        $h .= '<p class="w-text">' . e(trim($m['competition'] . ($m['round'] !== '' ? ' · ' . $m['round'] : ''))) . ($m['result'] !== '' ? ' <span class="w-res w-res--' . e(strtolower($m['result'])) . '">' . e(self::result($m['result'], $c)) . '</span>' : '') . '</p>';
        if (count($d['matches']) > 1) {
            $h .= '<ul class="w-list">';
            foreach (array_slice($d['matches'], 1) as $x) {
                $h .= '<li><a href="' . e($x['href']) . '" target="_blank" rel="noopener"><b>' . e(substr($x['date'], 0, 4)) . '</b> ' . e($x['label']) . '</a></li>';
            }
            $h .= '</ul>';
        }
        return $h;
    }

    private static function dayLabel(string $mmdd, Context $c): string
    {
        $ts = strtotime('2000-' . $mmdd);
        if ($c->en()) {
            return date('F j', $ts);
        }
        $mois = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
        $d = (int) date('j', $ts);
        return ($d === 1 ? '1er' : (string) $d) . ' ' . $mois[(int) date('n', $ts) - 1];
    }

    public static function ago(int $n, Context $c): string
    {
        if ($n <= 0) {
            return $c->tr('Cette année', 'This year');
        }
        return $c->en() ? ($n === 1 ? '1 year ago' : $n . ' years ago') : ($n === 1 ? 'Il y a 1 an' : 'Il y a ' . $n . ' ans');
    }

    private static function result(string $r, Context $c): string
    {
        return match ($r) {
            'V' => $c->tr('Victoire', 'Win'),
            'N' => $c->tr('Nul', 'Draw'),
            'D' => $c->tr('Défaite', 'Defeat'),
            default => $r,
        };
    }
}
