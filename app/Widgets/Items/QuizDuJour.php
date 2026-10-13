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
 * répond que pour la question du jour (ou de la veille) ou, en mode « chargement » (option
 * rotation : une question au hasard à chaque chargement), pour la question d'un jeton signé
 * (HMAC du secret du site : identifiant, horodatage, ordre des choix ; valable 2 h). Une réponse hachée aurait été devinée
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

    /** Durée de validité d'un jeton de question (mode « chargement »), en secondes. */
    public const TOKEN_TTL = 7200;

    public function options(): array
    {
        return ['rotation' => ['label' => 'Question', 'label_en' => 'Question', 'type' => 'select', 'default' => 'jour',
            'choices' => ['jour' => 'Une par jour (la même pour tous)', 'chargement' => 'Nouvelle question à chaque chargement'],
            'help' => 'À chaque chargement : une question tirée au hasard, puis un bouton « Question suivante » après la réponse.',
            'help_en' => 'On each load: a random question, then a “Next question” button after answering.']];
    }

    /** Mode « chargement » : jamais en cache (sinon tout le monde aurait la même question pendant une heure). */
    public function cacheable(array $opt): bool
    {
        return ($opt['rotation'] ?? 'jour') !== 'chargement';
    }

    /** Questions jouables (actives, 2 réponses ou plus, bonne réponse valide). @return list<array> */
    public static function pool(): array
    {
        $list = [];
        foreach (Collections::get('quiz', Seeds::quiz()) as $q) {
            if (is_array($q) && ($q['active'] ?? true) && !empty($q['q']) && count($q['a'] ?? []) >= 2 && isset($q['a'][(int) ($q['c'] ?? 0)])) {
                $list[] = $q;
            }
        }
        return $list;
    }

    /** Identifiant court et stable d'une question (d'après son texte d'origine). */
    public static function qid(array $q): string
    {
        return substr(md5((string) $q['q']), 0, 10);
    }

    /**
     * Une question dans une langue, choix mélangés selon $order (même ordre en français et en
     * anglais), AVEC la bonne réponse (« c ») : réservé au serveur.
     * @return array{q:string,a:list<string>,c:int,fact:string,n:int,id:string}
     */
    private static function shape(array $pick, string $lang, string $order, int $n): array
    {
        $loc = $lang === 'en' ? Collections::loc($pick, ['q', 'a', 'fact']) : $pick;
        $a = array_values(array_map('strval', (array) $loc['a']));
        if (count($a) !== count((array) $pick['a'])) {
            $a = array_values(array_map('strval', (array) $pick['a']));
        }
        $keys = array_keys($a);
        usort($keys, fn ($x, $y) => crc32($order . '|' . $x) <=> crc32($order . '|' . $y));
        $choices = array_map(fn ($i) => $a[$i], $keys);
        return ['q' => (string) $loc['q'], 'a' => $choices, 'c' => (int) array_search((int) $pick['c'], $keys, true), 'fact' => (string) ($loc['fact'] ?? ''), 'n' => $n, 'id' => self::qid($pick)];
    }

    /**
     * Question d'un jour, dans une langue, AVEC la bonne réponse (« c ») : réservé au serveur.
     * Tirage fixe pour la date (même question et même ordre des choix en français et en anglais).
     * @return array{q:string,a:list<string>,c:int,fact:string,n:int,id:string}|null
     */
    public static function question(string $date, string $lang): ?array
    {
        $list = self::pool();
        if (!$list) {
            return null;
        }
        $pick = $list[crc32('quiz-du-jour|' . $date . '|' . site_key('quiz-du-jour')) % count($list)];
        return self::shape($pick, $lang, $date, count($list));
    }

    /**
     * Mode « chargement » : une question au hasard (en évitant $avoid, identifiants des dernières
     * questions vues si possible), avec son jeton signé. @return array{0:array,1:string}|null
     */
    public static function random(string $lang, array $avoid = [], ?int $now = null): ?array
    {
        $list = self::pool();
        if (!$list) {
            return null;
        }
        $fresh = array_values(array_filter($list, fn ($q) => !in_array(self::qid($q), $avoid, true)));
        $from = $fresh ?: $list;
        $pick = $from[random_int(0, count($from) - 1)];
        $nonce = bin2hex(random_bytes(4));
        return [self::shape($pick, $lang, $nonce, count($list)), self::token(self::qid($pick), $nonce, $now ?? time())];
    }

    /** Jeton d'une question affichée : « id.horodatage.ordre.signature » (HMAC avec le secret du site). */
    public static function token(string $qid, string $nonce, int $ts): string
    {
        $payload = $qid . '.' . $ts . '.' . $nonce;
        return $payload . '.' . substr(hash_hmac('sha256', $payload, site_key('quiz-du-jour-jeton')), 0, 32);
    }

    /**
     * Question d'un jeton (avec la bonne réponse), ou null : jeton mal formé, falsifié, expiré
     * (plus de TOKEN_TTL secondes) ou question retirée depuis.
     */
    public static function fromToken(string $tok, string $lang, ?int $now = null): ?array
    {
        if (!preg_match('/^([a-f0-9]{10})\.(\d{9,11})\.([a-f0-9]{8})\.([a-f0-9]{32})$/', $tok, $m)) {
            return null;
        }
        if (!hash_equals(substr(hash_hmac('sha256', $m[1] . '.' . $m[2] . '.' . $m[3], site_key('quiz-du-jour-jeton')), 0, 32), $m[4])) {
            return null;
        }
        $age = ($now ?? time()) - (int) $m[2];
        if ($age < -60 || $age > self::TOKEN_TTL) {
            return null;
        }
        $list = self::pool();
        foreach ($list as $q) {
            if (self::qid($q) === $m[1]) {
                return self::shape($q, $lang, $m[3], count($list));
            }
        }
        return null;
    }

    public function data(array $opt, Context $c): ?array
    {
        if (($opt['rotation'] ?? 'jour') === 'chargement') {
            $r = self::random($c->lang);
            if (!$r) {
                return null;
            }
            [$q, $tok] = $r;
            return [
                'rotation' => 'chargement',
                'token' => $tok,
                'id' => $q['id'],
                'question' => $q['q'],
                'choices' => $q['a'],
                'verify' => Context::abs('/api/v1/widgets/quiz-du-jour/verifier'),
                'href' => '/interactif/quiz/',
            ];
        }
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
        $load = ($d['rotation'] ?? '') === 'chargement';
        $h = '<div class="w-qz" data-quiz' . ($load ? ' data-token="' . e($d['token']) . '" data-qid="' . e($d['id']) . '" data-next="' . e($c->tr('Question suivante', 'Next question')) . '"' : ' data-date="' . e($d['date']) . '"') . ' data-verify="' . e($d['verify']) . '" data-lang="' . e($c->lang) . '"'
            . ' data-ok="' . e($c->tr('Bonne réponse !', 'Correct!')) . '" data-ko="' . e($c->tr('Raté… la bonne réponse :', 'Wrong… the right answer:')) . '" data-err="' . e($c->tr('Vérification impossible pour le moment : réessayez.', 'Cannot check right now: try again.')) . '">';
        $h .= '<p class="w-eyebrow">' . e($load ? $c->tr('Le quiz du musée', 'The museum quiz') : $c->tr('Le quiz du jour', 'Quiz of the day')) . '</p>';
        $h .= '<p class="w-qz__q">' . e($d['question']) . '</p><div class="w-qz__a" role="group" aria-label="' . e($c->tr('Réponses', 'Answers')) . '">';
        foreach ($d['choices'] as $i => $a) {
            $h .= '<button type="button" class="w-qz__c" data-i="' . $i . '"><span>' . chr(65 + $i) . '</span>' . e($a) . '</button>';
        }
        $h .= '</div><p class="w-qz__r" aria-live="polite" hidden></p><p class="w-text w-qz__f" hidden></p>';
        return $h . '<p><a class="w-btn" href="' . e($d['href']) . '" target="_blank" rel="noopener">' . e($c->tr('Tout le quiz du musée', 'The full museum quiz')) . ' →</a></p></div>';
    }
}
