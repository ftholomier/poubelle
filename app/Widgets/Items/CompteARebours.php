<?php
declare(strict_types=1);

namespace App\Widgets\Items;

use App\Core\Club;
use App\Front\Site;
use App\Widgets\Context;
use App\Widgets\Widget;

/**
 * Compte à rebours jusqu'à un anniversaire : la date choisie (?date=AAAA-MM-JJ, avec ?titre=…),
 * sinon le centenaire du club quand il est mis en avant (Site::centenaryCountdown, page
 * /centenaire/), sinon le prochain anniversaire de la fondation. Le décompte (jours, heures,
 * minutes) avance dans la page (js/widget.js) ; les jours sont calculés par le musée.
 */
final class CompteARebours extends Widget
{
    public function key(): string
    {
        return 'compte-a-rebours';
    }

    public function name(string $lang = 'fr'): string
    {
        return $lang === 'en' ? 'Countdown' : 'Compte à rebours';
    }

    public function description(string $lang = 'fr'): string
    {
        return $lang === 'en' ? 'Countdown to an anniversary of your choice, or to the club’s centenary.'
            : 'Le compte à rebours jusqu’à un anniversaire choisi, ou jusqu’au centenaire du club.';
    }

    public function options(): array
    {
        return [
            'date' => ['label' => 'Date (vide : centenaire ou anniversaire de la fondation)', 'label_en' => 'Date (empty: centenary or founding anniversary)', 'type' => 'date', 'min' => 'today', 'default' => ''],
            'titre' => ['label' => 'Titre de l’évènement (avec une date)', 'label_en' => 'Event title (with a date)', 'type' => 'text', 'default' => '', 'pattern' => '/^[^<>{}]{2,80}$/u', 'placeholder' => 'Le grand jour'],
        ];
    }

    public function height(): int
    {
        return 360;
    }

    /** Titre libre : seulement avec une clé partenaire (sinon « Le grand jour »). */
    public function restrict(array $opt): array
    {
        return ['titre' => ''] + $opt;
    }

    /**
     * Date choisie (avec ou sans titre) : une variante par date et par titre, sans limite ; calculée
     * à chaque appel (rien à lire en base), jamais gardée dans le cache des widgets.
     */
    public function cacheable(array $opt): bool
    {
        return ($opt['date'] ?? '') === '' && ($opt['titre'] ?? '') === '';
    }

    public function ttl(): int
    {
        return 900;
    }

    public function data(array $opt, Context $c): ?array
    {
        $today = date('Y-m-d');
        $target = null;
        $title = '';
        $href = '/';
        if ($opt['date'] !== '' && checkdate((int) substr($opt['date'], 5, 2), (int) substr($opt['date'], 8, 2), (int) substr($opt['date'], 0, 4)) && $opt['date'] > $today) {
            $target = $opt['date'];
            $title = $opt['titre'] !== '' ? $opt['titre'] : $c->tr('Le grand jour', 'The big day');
        } elseif (Site::centenaryCountdown() && Site::centenaryDate() > $today) {
            $target = Site::centenaryDate();
            $title = $c->tr('Le centenaire du club', 'The club’s centenary');
            $href = '/centenaire/';
        } else {
            $f = (string) Club::s('founded_date');
            if (preg_match('/^\d{4}-(\d\d-\d\d)$/', $f, $m)) {
                $y = (int) date('Y');
                $target = $y . '-' . $m[1] <= $today ? ($y + 1) . '-' . $m[1] : $y . '-' . $m[1];
                $n = (int) substr($target, 0, 4) - (int) substr($f, 0, 4);
                $title = $c->tr($n . ' ans du club', 'The club turns ' . $n);
            }
        }
        if ($target === null) {
            return null;
        }
        $at = (int) strtotime($target . ' 00:00:00');
        return [
            'date' => $target,
            'title' => $title,
            'days' => max(0, (int) ceil(($at - time()) / 86400)),
            'timestamp' => $at,
            'href' => $href,
        ];
    }

    public function body(array $d, Context $c): string
    {
        $date = $c->en() ? date('j F Y', strtotime($d['date'])) : date_fr($d['date']);
        $h = '<p class="w-eyebrow">' . e($c->tr('Compte à rebours', 'Countdown')) . '</p>';
        $h .= '<p class="w-title"><a href="' . e($d['href']) . '" target="_blank" rel="noopener">' . e($d['title']) . '</a></p>';
        $h .= '<div class="w-cd" data-countdown="' . (int) $d['timestamp'] . '"><span><b data-cd="j">' . $d['days'] . '</b>' . e($c->tr('jours', 'days')) . '</span><span><b data-cd="h">–</b>' . e($c->tr('heures', 'hours')) . '</span><span><b data-cd="m">–</b>' . e($c->tr('minutes', 'minutes')) . '</span></div>';
        return $h . '<p class="w-text w-center">' . e($c->tr('Rendez-vous le ', 'See you on ') . $date) . '</p>';
    }
}
