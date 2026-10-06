<?php
declare(strict_types=1);

namespace App\Front;

use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Services\I18n;
use App\Services\QuizLive;

/**
 * Quiz du club-house : page des joueurs (/interactif/quiz-live/, rejoindre avec le code puis
 * répondre sur le téléphone), grand écran de l'animateur (/interactif/quiz-live/ecran/{code}/{clé}/)
 * et l'API /api/quiz-live interrogée chaque seconde. Jamais dans le cache des pages.
 * Les parties se créent dans le back-office (Interactif › Quiz du club-house).
 */
final class QuizLivePages
{
    public static function join(Request $req): Response
    {
        $code = preg_replace('/\D/', '', $req->str('code'));
        $res = Pages::render('interactif/quiz-live', ['code' => QuizLive::validCode((string) $code) ? $code : ''], [
            'title' => t('Quiz du club-house'),
            'description' => t('Rejoignez la partie en cours : saisissez le code affiché sur le grand écran et répondez depuis votre téléphone.'),
            'noindex' => true, 'active' => 'interactif', 'body_class' => 'page-quizlive',
            // Ni vidéo ni don sur cette page : pas de bandeau cookies par-dessus les boutons de réponse.
            'no_cookie' => true,
            'styles' => ['css/interactif.css', 'css/quizlive.css'], 'scripts' => ['js/quizlive.js'],
        ]);
        $res->headers['Cache-Control'] = 'private, no-store';
        return $res;
    }

    public static function screen(Request $req, string $code, string $key): Response
    {
        $g = QuizLive::get($code);
        if (!QuizLive::isHost($g, $key)) {
            return Pages::notFound();
        }
        I18n::set($g['lang']);
        $join = base_url() . ($g['lang'] === 'en' ? '/en' : '') . '/interactif/quiz-live/?code=' . $code;
        $res = Response::html(View::render('interactif/quiz-live-ecran', ['g' => $g, 'key' => $key, 'join' => $join,
            'qr' => \App\Services\Qr::svg($join, 8)]));
        $res->headers['Cache-Control'] = 'private, no-store';
        $res->headers['X-Robots-Tag'] = 'noindex, nofollow';
        return $res;
    }

    public static function api(Request $req): Response
    {
        $in = $req->json() ?: [];
        if (($in['lang'] ?? '') === 'en') {
            I18n::set('en');
        }
        $code = (string) ($in['code'] ?? '');
        $action = (string) ($in['action'] ?? '');
        $g = QuizLive::get($code);
        // Écran de l'animateur : toutes ses actions portent la clé secrète de la partie.
        if (in_array($action, ['ecran', 'suivant', 'retirer'], true)) {
            if (!QuizLive::isHost($g, (string) ($in['key'] ?? ''))) {
                return Response::json(['error' => t('Ressource introuvable')], 404);
            }
            if ($action === 'suivant') {
                $g = QuizLive::next($code) ?? $g;
            } elseif ($action === 'retirer') {
                QuizLive::kick($code, (string) ($in['pid'] ?? ''));
                $g = QuizLive::get($code) ?? $g;
            }
            return Response::json(['ok' => true] + QuizLive::screenState($g));
        }
        if ($action === 'rejoindre') {
            // Un club-house partage souvent une seule connexion : limite large par adresse.
            if (!RateLimiter::hit('quizlive-join', $req->ip(), 400, 3600)) {
                return Response::json(['error' => t('Trop de demandes, réessayez plus tard.')], 429);
            }
            if (!$g) {
                if (!RateLimiter::hit('quizlive-code', $req->ip(), 60, 3600)) {
                    return Response::json(['error' => t('Trop de demandes, réessayez plus tard.')], 429);
                }
                return Response::json(['error' => t('Aucune partie avec ce code. Vérifiez le code affiché sur l’écran.')], 404);
            }
            $r = QuizLive::join($code, (string) ($in['name'] ?? ''));
            if (is_string($r)) {
                return Response::json(['error' => $r], 422);
            }
            return Response::json(['ok' => true] + $r + QuizLive::playerState(QuizLive::get($code) ?? $g, $r['pid']));
        }
        $pid = (string) ($in['pid'] ?? '');
        $tok = (string) ($in['tok'] ?? '');
        if (!$g || !QuizLive::player($g, $pid, $tok)) {
            return Response::json(['ok' => false, 'phase' => 'gone']);
        }
        if ($action === 'repondre') {
            $r = QuizLive::answer($code, $pid, $tok, (int) ($in['choice'] ?? -1));
            $g = QuizLive::get($code) ?? $g;
            return Response::json($r + QuizLive::playerState($g, $pid));
        }
        if ($action === 'etat') {
            return Response::json(['ok' => true] + QuizLive::playerState($g, $pid));
        }
        return Response::json(['error' => t('Ressource introuvable')], 404);
    }
}
