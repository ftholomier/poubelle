<?php
declare(strict_types=1);

namespace App\Widgets\Items;

use App\Data\Collections;
use App\Data\Seeds;
use App\Widgets\Context;
use App\Widgets\Widget;

/**
 * Quiz du jour : une question du quiz du site (collection « quiz », celle de /interactif/quiz/),
 * la même pour tous dans la journée, jouable dans l'iframe sans compte ni cookie.
 *
 * La bonne réponse et l'explication ne sont PAS dans les données (API JSON, page du widget) :
 * le choix est vérifié par POST /api/v1/widgets/quiz-du-jour/verifier (js/widget.js), qui ne
 * répond que pour la question du jour (ou de la veille). Une réponse hachée aurait été devinée
 * en essayant les 3 ou 4 choix ; la vérification au serveur ne coûte qu'une petite requête.
 */
final class QuizDuJour extends Widget
{
    public function key(): string
    {
        return 'quiz-du-jour';
    }

    public function name(string $lang = 'fr'): string
    {
        return $lang === 'en' ? 'Quiz of the day' : 'Quiz du jour';
    }

    public function description(string $lang = 'fr'): string
    {
        return $lang === 'en' ? 'One multiple-choice question on the club’s history each day, playable right in the widget: answer and explanation.'
            : 'Une question à choix multiples sur l’histoire du club, chaque jour, jouable dans le widget : bonne réponse et explication.';
    }

    public function height(): int
    {
        return 470;
    }

    /**
     * Question d'un jour, dans une langue, AVEC la bonne réponse (« c ») : réservé au serveur.
     * Tirage fixe pour la date (même question et même ordre des choix en français et en anglais).
     * @return array{q:string,a:list<string>,c:int,fact:string,n:int}|null
     */
    public static function question(string $date, string $lang): ?array
    {
        $list = [];
        foreach (Collections::get('quiz', Seeds::quiz()) as $q) {
            if (is_array($q) && ($q['active'] ?? true) && !empty($q['q']) && count($q['a'] ?? []) >= 2 && isset($q['a'][(int) ($q['c'] ?? 0)])) {
                $list[] = $q;
            }
        }
        if (!$list) {
            return null;
        }
        $pick = $list[crc32('quiz-du-jour|' . $date . '|' . site_key('quiz-du-jour')) % count($list)];
        $loc = $lang === 'en' ? Collections::loc($pick, ['q', 'a', 'fact']) : $pick;
        $a = array_values(array_map('strval', (array) $loc['a']));
        if (count($a) !== count((array) $pick['a'])) {
            $a = array_values(array_map('strval', (array) $pick['a']));
        }
        $order = array_keys($a);
        usort($order, fn ($x, $y) => crc32($date . '|' . $x) <=> crc32($date . '|' . $y));
        $choices = array_map(fn ($i) => $a[$i], $order);
        return ['q' => (string) $loc['q'], 'a' => $choices, 'c' => (int) array_search((int) $pick['c'], $order, true), 'fact' => (string) ($loc['fact'] ?? ''), 'n' => count($list)];
    }

    public function data(array $opt, Context $c): ?array
    {
        $date = date('Y-m-d');
        $q = self::question($date, $c->lang);
        if (!$q) {
            return null;
        }
        return [
            'date' => $date,
            'question' => $q['q'],
            'choices' => $q['a'],
            'verify' => Context::abs('/api/v1/widgets/quiz-du-jour/verifier'),
            'href' => '/interactif/quiz/',
        ];
    }

    public function body(array $d, Context $c): string
    {
        $h = '<div class="w-qz" data-quiz data-date="' . e($d['date']) . '" data-verify="' . e($d['verify']) . '" data-lang="' . e($c->lang) . '"'
            . ' data-ok="' . e($c->tr('Bonne réponse !', 'Correct!')) . '" data-ko="' . e($c->tr('Raté… la bonne réponse :', 'Wrong… the right answer:')) . '" data-err="' . e($c->tr('Vérification impossible pour le moment : réessayez.', 'Cannot check right now: try again.')) . '">';
        $h .= '<p class="w-eyebrow">' . e($c->tr('Le quiz du jour', 'Quiz of the day')) . '</p>';
        $h .= '<p class="w-qz__q">' . e($d['question']) . '</p><div class="w-qz__a" role="group" aria-label="' . e($c->tr('Réponses', 'Answers')) . '">';
        foreach ($d['choices'] as $i => $a) {
            $h .= '<button type="button" class="w-qz__c" data-i="' . $i . '"><span>' . chr(65 + $i) . '</span>' . e($a) . '</button>';
        }
        $h .= '</div><p class="w-qz__r" aria-live="polite" hidden></p><p class="w-text w-qz__f" hidden></p>';
        return $h . '<p><a class="w-btn" href="' . e($d['href']) . '" target="_blank" rel="noopener">' . e($c->tr('Tout le quiz du musée', 'The full museum quiz')) . ' →</a></p></div>';
    }
}
