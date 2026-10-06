<?php
declare(strict_types=1);

namespace App\Front;

use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Services\Carnet;
use App\Services\DailyQuiz;
use App\Services\I18n;
use App\Services\QuizChampionship;

/**
 * Défi du jour : la page /interactif/defi/ (jeu en solo, résultat du jour, classements du jour,
 * du mois et de la saison) et l'API /api/defi. Les joueurs classés ont un compte supporter
 * (carnet : e-mail et lien sécurisé) et un pseudo (celui du championnat du club-house) ; sans
 * compte, on joue en invité (jeton gardé sur l'appareil), sans classement. Jamais en cache.
 */
final class DailyQuizPages
{
    public const PERIODS = ['jour', 'mois', 'saison'];

    public static function page(Request $req): Response
    {
        $date = DailyQuiz::today();
        $period = in_array($req->str('classement'), self::PERIODS, true) ? $req->str('classement') : 'jour';
        $value = match ($period) {
            'jour' => $date,
            'mois' => substr($date, 0, 7),
            default => QuizChampionship::season(),
        };
        $c = Carnet::current();
        $me = $c ? QuizChampionship::player($c['id']) : null;
        $res = Pages::render('interactif/defi', [
            'date' => $date, 'period' => $period, 'value' => $value, 'rows' => array_slice(DailyQuiz::ranking($period, $value), 0, 100),
            'account' => (bool) $c, 'welcome' => isset($req->query['bienvenue']),
            'me' => $me ? ['id' => $c['id'], 'pseudo' => $me['pseudo'], 'result' => DailyQuiz::result($date, $c['id']),
                'rank' => DailyQuiz::rankOf('jour', $date, $c['id']), 'streak' => DailyQuiz::streak($c['id'])] : null,
            'players' => DailyQuiz::players($date),
        ], [
            'title' => t('Le défi du jour'),
            'description' => t('Dix questions sur l’histoire du FC Sochaux-Montbéliard, les mêmes pour tout le monde, chaque jour. Un essai par jour, un classement.'),
            'active' => 'interactif', 'body_class' => 'page-defi', 'no_cookie' => true,
            'styles' => ['css/mosaic.css', 'css/interactif.css', 'css/carnet.css', 'css/quizlive.css'], 'scripts' => ['js/defi.js'],
        ]);
        $res->headers['Cache-Control'] = 'private, no-store';
        return $res;
    }

    public static function api(Request $req): Response
    {
        $in = $req->json() ?: [];
        if (($in['lang'] ?? '') === 'en') {
            I18n::set('en');
        }
        $date = DailyQuiz::today();
        $action = (string) ($in['action'] ?? '');
        $c = Carnet::current();
        $cid = $c && QuizChampionship::player($c['id']) ? $c['id'] : null;
        $key = DailyQuiz::key($cid, (string) ($in['tok'] ?? ''));

        if ($action === 'inscrire') {
            // Pseudo (compte ouvert sans pseudo) ou entrée au classement avec e-mail + pseudo.
            $name = (string) ($in['name'] ?? '');
            if ($c) {
                $err = QuizChampionship::setPseudo($c['id'], $name);
                return $err ? Response::json(['error' => $err], 422) : Response::json(['ok' => true, 'member' => true]);
            }
            $acc = QuizLivePages::account($req, trim((string) ($in['email'] ?? '')), $name, 'defi');
            if (isset($acc['error'])) {
                return Response::json(['error' => $acc['error']], $acc['status']);
            }
            return Response::json(['ok' => true, 'member' => $acc['cid'] !== null, 'mailed' => $acc['mailed']]);
        }
        if ($key === null) {
            return Response::json(['error' => t('Rejoignez d’abord la partie.')], 422);
        }
        switch ($action) {
            case 'etat':
                return Response::json(['ok' => true, 'member' => $cid !== null] + DailyQuiz::state($date, $key));
            case 'commencer':
                // Un réseau partagé (bar, club-house) : limite large par adresse.
                if (!RateLimiter::hit('defi', $req->ip(), 300, 3600)) {
                    return Response::json(['error' => t('Trop de demandes, réessayez plus tard.')], 429);
                }
                return Response::json(['ok' => true, 'member' => $cid !== null] + DailyQuiz::start($date, $key, I18n::lang()));
            case 'repondre':
                return Response::json(['ok' => true] + DailyQuiz::answer($date, $key, (int) ($in['choice'] ?? -1)));
            case 'suivante':
                $s = DailyQuiz::next($date, $key, $cid);
                if ($s['phase'] === 'end' && $cid !== null) {
                    $r = DailyQuiz::rankOf('jour', $date, $cid);
                    $st = DailyQuiz::streak($cid);
                    $s += ['rankLabel' => $r ? t('{r} sur {n} aujourd’hui', ['r' => ordinal($r['rank']), 'n' => DailyQuiz::players($date)]) : '',
                        'streakLabel' => $st['cur'] > 1 ? t('{n} jours d’affilée', ['n' => $st['cur']]) : ''];
                }
                return Response::json(['ok' => true] + $s);
        }
        return Response::json(['error' => t('Ressource introuvable')], 404);
    }
}
