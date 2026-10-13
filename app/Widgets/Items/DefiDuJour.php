<?php
declare(strict_types=1);

namespace App\Widgets\Items;

use App\Services\DailyQuiz;
use App\Widgets\Context;
use App\Widgets\Widget;

/**
 * Défi du jour : invitation au défi quotidien du musée (App\Services\DailyQuiz, /interactif/defi/ :
 * 10 questions chronométrées, classements). On ne joue pas dans l'iframe : le défi demande une
 * partie suivie par le serveur, un compte facultatif et une seule tentative par jour, ce qui
 * suppose la page du musée. Le widget montre le défi du jour, le nombre de joueurs classés et le
 * meilleur score (un nombre : jamais de pseudo), puis envoie jouer dans le musée.
 * Les questions ne sont pas montrées (elles sont secrètes jusqu'à la partie).
 */
final class DefiDuJour extends Widget
{
    public function key(): string
    {
        return 'defi-du-jour';
    }

    public function name(string $lang = 'fr'): string
    {
        return $lang === 'en' ? 'Daily challenge' : 'Défi du jour';
    }

    public function description(string $lang = 'fr'): string
    {
        return $lang === 'en' ? 'The museum’s daily challenge: 10 timed questions, the same for everyone, with today’s number of players and best score.'
            : 'Le défi quotidien du musée : 10 questions chronométrées, les mêmes pour tous, avec le nombre de joueurs et le meilleur score du jour.';
    }

    public function ttl(): int
    {
        return 600;
    }

    public function height(): int
    {
        return 400;
    }

    public function data(array $opt, Context $c): ?array
    {
        $date = DailyQuiz::today();
        $best = null;
        try {
            $best = (DailyQuiz::ranking('jour', $date)[0]['pts'] ?? null);
        } catch (\Throwable $e) {
            $best = null;
        }
        return [
            'date' => $date,
            'questions' => DailyQuiz::COUNT,
            'seconds' => DailyQuiz::DURATION,
            'players' => DailyQuiz::players($date),
            'best' => $best === null ? null : (int) $best,
            'href' => '/interactif/defi/',
        ];
    }

    public function body(array $d, Context $c): string
    {
        $ts = strtotime($d['date']);
        $h = '<p class="w-eyebrow">' . e($c->tr('Le défi du jour', 'Daily challenge')) . ' · ' . e($c->en() ? date('F j', $ts) : date_fr($d['date'])) . '</p>';
        $h .= '<p class="w-big">' . $d['questions'] . ' <small>' . e($c->tr('questions', 'questions')) . '</small></p>';
        $h .= '<p class="w-title">' . e($c->tr('Les mêmes pour tous, une seule partie par jour', 'Same for everyone, one game a day')) . '</p>';
        $h .= '<p class="w-text">' . e($c->tr($d['seconds'] . ' secondes par question : plus vous répondez vite, plus vous marquez de points. Classements du jour, du mois et de la saison.', $d['seconds'] . ' seconds per question: the faster you answer, the more points you score. Daily, monthly and season rankings.')) . '</p>';
        $h .= '<div class="w-stats"><span><b>' . number_format($d['players'], 0, ',', "\u{202F}") . '</b>' . e($c->tr($d['players'] > 1 ? 'joueurs classés aujourd’hui' : 'joueur classé aujourd’hui', 'ranked players today')) . '</span>';
        $h .= '<span><b>' . ($d['best'] === null ? '—' : number_format($d['best'], 0, ',', "\u{202F}")) . '</b>' . e($c->tr('meilleur score du jour', 'best score today')) . '</span></div>';
        return $h . '<p><a class="w-btn" href="' . e($d['href']) . '" target="_blank" rel="noopener">' . e($c->tr('Relever le défi', 'Take the challenge')) . ' →</a></p>';
    }
}
