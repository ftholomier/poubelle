<?php
declare(strict_types=1);

namespace App\Widgets\Items;

use App\Core\Club;
use App\Data\Collections;
use App\Data\Seeds;
use App\Shop\Texts;
use App\Widgets\Context;
use App\Widgets\Widget;

/**
 * Le saviez-vous ? Une anecdote courte et vérifiée, au hasard (une autre chaque heure). Rien n'est
 * rédigé ici : seulement ce que le musée a déjà validé ou publié :
 * - les anecdotes validées de la banque de textes de la boutique (Boutique › Banque de textes) ;
 * - les anecdotes rédigées dans la configuration du club (config/clubs/<club>/boutique.php, « banque ») ;
 * - les événements de la frise publiée (Interactif › Frise).
 */
final class LeSaviezVous extends Widget
{
    public function key(): string
    {
        return 'le-saviez-vous';
    }

    public function name(string $lang = 'fr'): string
    {
        return $lang === 'en' ? 'Did you know?' : 'Le saviez-vous ?';
    }

    public function description(string $lang = 'fr'): string
    {
        return $lang === 'en' ? 'A short, verified fact about the club’s history, picked at random.'
            : 'Une anecdote courte et vérifiée sur l’histoire du club, au hasard.';
    }

    public function height(): int
    {
        return 300;
    }

    /** Anecdotes vérifiées : [text, source, year, href]. @return list<array{text:string,source:string,year:int,href:string}> */
    public static function facts(string $lang = 'fr'): array
    {
        $out = [];
        $seen = [];
        $add = function (string $text, string $source, int $year, string $href) use (&$out, &$seen) {
            $text = trim((string) preg_replace('/\s+/u', ' ', strip_tags($text)));
            $k = mb_strtolower($text);
            if ($text !== '' && mb_strlen($text) <= 320 && !isset($seen[$k])) {
                $seen[$k] = true;
                $out[] = ['text' => $text, 'source' => $source, 'year' => $year, 'href' => $href];
            }
        };
        if ($lang === 'fr') {
            $bank = Texts::find('anecdotes');
            foreach ((array) ($bank['items'] ?? []) as $i) {
                if (!empty($i['ok'])) {
                    $add((string) $i['text'], (string) $i['note'], self::year((string) $i['note']), '/');
                }
            }
            foreach ((array) (Club::content('boutique', [])['banque'] ?? []) as $list) {
                if (($list['id'] ?? '') === 'anecdotes') {
                    foreach ((array) ($list['items'] ?? []) as $row) {
                        $add((string) ($row[0] ?? ''), (string) ($row[1] ?? ''), self::year((string) ($row[1] ?? '')), '/');
                    }
                }
            }
        }
        foreach (Collections::get('frise', Seeds::frise()) as $f) {
            $title = (string) ($lang === 'en' && ($f['title_en'] ?? '') !== '' ? $f['title_en'] : ($f['title'] ?? ''));
            $text = (string) ($lang === 'en' && ($f['text_en'] ?? '') !== '' ? $f['text_en'] : ($f['text'] ?? ''));
            $year = (int) ($f['year'] ?? 0);
            if ($title === '' || !$year || ($lang === 'en' && ($f['title_en'] ?? '') === '')) {
                continue;
            }
            $href = !empty($f['href']) && str_starts_with((string) $f['href'], '/') ? (string) $f['href'] : '/interactif/frise/';
            $add($year . ' : ' . rtrim($title, '. ') . '.' . ($text !== '' ? ' ' . $text : ''), $lang === 'en' ? 'Museum timeline' : 'Frise du musée', $year, $href);
        }
        return $out;
    }

    public function data(array $opt, Context $c): ?array
    {
        $facts = self::facts($c->lang);
        if (!$facts) {
            return null;
        }
        $f = $facts[crc32(date('Y-m-d H') . '|saviez') % count($facts)];
        return $f + ['count' => count($facts)];
    }

    public function body(array $d, Context $c): string
    {
        return '<p class="w-eyebrow">' . e($c->tr('Le saviez-vous ?', 'Did you know?')) . '</p>'
            . '<p class="w-quote">' . e($d['text']) . '</p>'
            . ($d['source'] !== '' ? '<p class="w-credit">' . e($c->tr('Source : ', 'Source: ') . $d['source']) . '</p>' : '')
            . '<p><a class="w-btn" href="' . e($d['href']) . '" target="_blank" rel="noopener">' . e($c->tr('Toute l’histoire au musée', 'The full story at the museum')) . ' →</a></p>';
    }

    private static function year(string $s): int
    {
        return preg_match('/\b(18|19|20)\d\d\b/', $s, $m) ? (int) $m[0] : 0;
    }
}
