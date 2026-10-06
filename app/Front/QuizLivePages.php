<?php
declare(strict_types=1);

namespace App\Front;

use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Services\Carnet;
use App\Services\I18n;
use App\Services\QuizChampionship;
use App\Services\QuizLive;

/**
 * Quiz du club-house : page des joueurs (/interactif/quiz-live/, rejoindre avec le code puis
 * répondre sur le téléphone), grand écran de l'animateur (/interactif/quiz-live/ecran/{code}/{clé}/)
 * et l'API /api/quiz-live interrogée chaque seconde. Jamais dans le cache des pages.
 * Les parties se créent dans le back-office (Interactif › Quiz du club-house).
 *
 * Championnat (/interactif/quiz-live/championnat/) : les joueurs qui ont un compte supporter
 * (carnet : e-mail et lien sécurisé, cookie sr_carnet) jouent sous leur pseudo et marquent des
 * points à chaque partie ; sans compte, on joue en invité.
 */
final class QuizLivePages
{
    public static function join(Request $req): Response
    {
        $code = preg_replace('/\D/', '', $req->str('code'));
        $c = Carnet::current();
        $me = $c ? QuizChampionship::player($c['id']) : null;
        $res = Pages::render('interactif/quiz-live', ['code' => QuizLive::validCode((string) $code) ? $code : '', 'account' => (bool) $c,
            'me' => $me ? ['pseudo' => $me['pseudo'], 'rank' => QuizChampionship::rankOf($c['id'])] : null], [
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

    /** Classement public du championnat (saison en cours, ou ?saison=). */
    public static function championship(Request $req): Response
    {
        $seasons = QuizChampionship::seasons();
        $current = QuizChampionship::season();
        if (!in_array($current, $seasons, true)) {
            array_unshift($seasons, $current);
        }
        $season = in_array($req->str('saison'), $seasons, true) ? $req->str('saison') : $current;
        $c = Carnet::current();
        $me = $c ? QuizChampionship::player($c['id']) : null;
        $res = Pages::render('interactif/quiz-championnat', [
            'season' => $season, 'seasons' => $seasons, 'rows' => array_slice(QuizChampionship::ranking($season), 0, 200),
            'stats' => QuizChampionship::stats($season), 'account' => (bool) $c, 'welcome' => isset($req->query['bienvenue']),
            'me' => $me ? ['id' => $c['id'], 'pseudo' => $me['pseudo'], 'rank' => QuizChampionship::rankOf($c['id'], $season)] : null,
        ], [
            'title' => t('Championnat du club-house {s}', ['s' => $season]),
            'description' => t('Le classement des soirées quiz du club-house : chaque partie rapporte des points selon votre rang.'),
            'active' => 'interactif', 'body_class' => 'page-quizchamp', 'no_cookie' => true,
            'styles' => ['css/mosaic.css', 'css/interactif.css', 'css/carnet.css', 'css/quizlive.css'], 'scripts' => ['js/quizchamp.js'],
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
            $name = (string) ($in['name'] ?? '');
            $email = trim((string) ($in['email'] ?? ''));
            $c = Carnet::current();
            $guest = !empty($in['guest']);
            $mailed = null;
            if ($c && !$guest) {
                // Compte ouvert sur ce téléphone : il joue sous son pseudo du championnat.
                $p = QuizChampionship::player($c['id']);
                if (!$p && ($err = QuizChampionship::setPseudo($c['id'], $name))) {
                    return Response::json(['error' => $err], 422);
                }
                $r = QuizLive::join($code, (string) QuizChampionship::player($c['id'])['pseudo'], $c['id']);
            } elseif (!$c && $email !== '' && !$guest) {
                $acc = self::account($req, $email, $name, $code);
                if (isset($acc['error'])) {
                    return Response::json(['error' => $acc['error']], $acc['status']);
                }
                $mailed = $acc['mailed'];
                $r = $acc['cid'] !== null ? QuizLive::join($code, $acc['pseudo'], $acc['cid']) : QuizLive::join($code, $name, null, $acc['claim']);
            } else {
                $r = QuizLive::join($code, $name);
            }
            if (is_string($r)) {
                return Response::json(['error' => $r], 422);
            }
            return Response::json(['ok' => true, 'mailed' => $mailed] + $r + QuizLive::playerState(QuizLive::get($code) ?? $g, $r['pid']));
        }
        if ($action === 'championnat') {
            // Page du championnat : entrer au championnat (e-mail + pseudo) ou changer de pseudo.
            $c = Carnet::current();
            $name = (string) ($in['name'] ?? '');
            if ($c) {
                $err = QuizChampionship::setPseudo($c['id'], $name);
                return $err ? Response::json(['error' => $err], 422) : Response::json(['ok' => true, 'pseudo' => QuizChampionship::player($c['id'])['pseudo']]);
            }
            $acc = self::account($req, trim((string) ($in['email'] ?? '')), $name, 'championnat');
            if (isset($acc['error'])) {
                return Response::json(['error' => $acc['error']], $acc['status']);
            }
            return Response::json(['ok' => true, 'mailed' => $acc['mailed'], 'member' => $acc['cid'] !== null]);
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
            // Lien de l'e-mail ouvert sur ce téléphone pendant la partie : elle compte pour ce compte.
            $claim = (string) ($g['players'][$pid]['claim'] ?? '');
            if ($claim !== '' && ($c = Carnet::current()) && $c['id'] === $claim) {
                $p = QuizChampionship::player($claim);
                if (!$p) {
                    QuizChampionship::setPseudo($claim, (string) $g['players'][$pid]['name']);
                    $p = QuizChampionship::player($claim);
                }
                QuizLive::claim($code, $pid, $claim, (string) ($p['pseudo'] ?? ''));
                $g = QuizLive::get($code) ?? $g;
            }
            return Response::json(['ok' => true] + QuizLive::playerState($g, $pid));
        }
        return Response::json(['error' => t('Ressource introuvable')], 404);
    }

    /**
     * Entrée au championnat avec un e-mail (pas encore de compte sur ce téléphone). Nouvel e-mail :
     * le compte est créé, ouvert tout de suite sur ce téléphone (cookie) et le lien part à
     * l'adresse pour les autres appareils. E-mail déjà connu : le lien part à cette adresse
     * seulement ; la partie est rattachée au compte quand le lien est ouvert sur ce téléphone.
     * @return array{cid:?string,claim:?string,pseudo:string,mailed:bool}|array{error:string,status:int}
     */
    private static function account(Request $req, string $email, string $name, string $quiz): array
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 120) {
            return ['error' => t('Adresse e-mail invalide.'), 'status' => 422];
        }
        // Une salle entière s'inscrit souvent depuis la même connexion : limite large par adresse IP.
        if (!RateLimiter::hit('quizlive-email', $req->ip(), 60, 3600) || !RateLimiter::hit('carnet-email-adr', Carnet::emailKey($email), 3, 3600)) {
            return ['error' => t('Trop de demandes, réessayez plus tard.'), 'status' => 429];
        }
        $pseudo = QuizLive::cleanName($name);
        $known = Carnet::byEmail($email);
        if (!$known) {
            if (mb_strlen($pseudo) < 2) {
                return ['error' => t('Choisissez un pseudo de 2 à 20 caractères.'), 'status' => 422];
            }
            if (!QuizChampionship::free($pseudo)) {
                return ['error' => t('Ce pseudo est déjà pris au championnat : choisissez-en un autre.'), 'status' => 422];
            }
            $r = Carnet::create($email, I18n::lang());
            if (!$r['existing'] && $r['credential']) {
                CarnetPages::setCookie($r['credential']);
                QuizChampionship::setPseudo($r['carnet']['id'], $pseudo);
                Carnet::sendLink($r['carnet'], $r['credential'], $quiz);
                return ['cid' => $r['carnet']['id'], 'claim' => null, 'pseudo' => $pseudo, 'mailed' => true];
            }
            $known = $r['carnet'];
        }
        if ($known && ($cred = Carnet::newCredential($known['id']))) {
            Carnet::sendLink($known, $cred, $quiz);
        }
        return ['cid' => null, 'claim' => $known['id'] ?? null, 'pseudo' => $pseudo, 'mailed' => true];
    }
}
