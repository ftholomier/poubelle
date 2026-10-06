<?php
declare(strict_types=1);

namespace App\Front;

use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Services\Carnet;
use App\Services\RetroDirect;

/**
 * Carnet du supporter : pages /carnet/ (bilan ou présentation), /carnet/saisons/ (saisie rapide),
 * /carnet/acces/{lien}/ (lien reçu par e-mail), /carnet/p/{pseudo}/ (page publique), cartes à
 * partager, et l'API /api/carnet (ajout depuis les fiches de match, création, page publique…).
 * Pages personnelles : jamais dans le cache des pages ni dans celui du navigateur.
 */
final class CarnetPages
{
    private const STYLES = ['css/mosaic.css', 'css/interactif.css', 'css/carnet.css'];

    private static function page(string $tpl, array $vars, array $page): Response
    {
        $res = Pages::render('carnet/' . $tpl, $vars, $page + ['active' => 'interactif', 'body_class' => 'page-carnet', 'styles' => self::STYLES, 'scripts' => ['js/carnet.js']]);
        $res->headers['Cache-Control'] = 'private, no-store';
        return $res;
    }

    public static function setCookie(string $credential): void
    {
        setcookie(Carnet::COOKIE, $credential, [
            'expires' => time() + 400 * 86400, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax',
            'secure' => (($_SERVER['HTTPS'] ?? '') === 'on') || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https',
        ]);
        $_COOKIE[Carnet::COOKIE] = $credential;
    }

    private static function clearCookie(): void
    {
        setcookie(Carnet::COOKIE, '', ['expires' => time() - 3600, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
        unset($_COOKIE[Carnet::COOKIE]);
    }

    // ------------------------------------------------------------------ pages

    public static function home(Request $req): Response
    {
        $c = Carnet::current();
        $meta = ['title' => t('Mon carnet du supporter'), 'description' => t('Cochez les matchs du FCSM que vous avez vus au stade : votre bilan, vos badges, votre porte-bonheur et une carte à partager.'), 'noindex' => true];
        if (!$c) {
            return self::page('accueil', ['seasons' => array_slice(Carnet::seasons(), 0, 6, true)], $meta);
        }
        // Poster « Ma vie en jaune et bleu » en vente (boutique ouverte) : proposé à partir de 5 matchs.
        $poster = null;
        if (\App\Shop\Orders::open()) {
            foreach (\App\Shop\Catalog::models() as $m) {
                if (\App\Shop\Poster::kind($m) === 'carnet' && \App\Shop\Catalog::sellable($m)) {
                    $poster = \App\Shop\ShopPages::u('/boutique/' . $m['id'] . '/');
                    break;
                }
            }
        }
        return self::page('carnet', ['c' => $c, 's' => Carnet::stats(array_keys($c['matches'])), 'welcome' => isset($req->query['bienvenue']), 'poster' => $poster], $meta);
    }

    public static function seasons(Request $req): Response
    {
        $all = Carnet::seasons();
        $season = $req->str('saison');
        if (!isset($all[$season])) {
            $season = (string) array_key_first($all);
        }
        $c = Carnet::current();
        return self::page('saisons', ['c' => $c, 'all' => $all, 'season' => $season, 'matches' => Carnet::seasonMatches($season), 'mine' => $c ? array_map('intval', array_keys($c['matches'])) : []],
            ['title' => t('Mon carnet : saison {s}', ['s' => $season]), 'noindex' => true]);
    }

    public static function access(Request $req, string $credential): Response
    {
        $c = Carnet::open($credential);
        if (!$c) {
            return self::page('lien', [], ['title' => t('Lien du carnet'), 'noindex' => true]);
        }
        self::setCookie($credential);
        Carnet::confirm($c['id']);
        // Lien demandé depuis le quiz du club-house : retour à la partie (ou au championnat).
        $quiz = $req->str('quiz');
        if (preg_match('/^\d{5}$/', $quiz)) {
            return Response::redirect(url('/interactif/quiz-live/') . '?code=' . $quiz);
        }
        if ($quiz === 'championnat') {
            return Response::redirect(url('/interactif/quiz-live/championnat/') . '?bienvenue=1');
        }
        return Response::redirect(url('/carnet/') . '?bienvenue=1');
    }

    public static function publicPage(Request $req, string $slug): Response
    {
        $c = Carnet::bySlug($slug);
        if (!$c) {
            return Pages::notFound();
        }
        $s = Carnet::stats(array_keys($c['matches']));
        return self::page('public', ['c' => $c, 's' => $s], [
            'title' => t('Le carnet de supporter de {p}', ['p' => $c['pseudo']]),
            'description' => t('{n} matchs du FCSM vus au stade, {v} victoires.', ['n' => $s['n'], 'v' => $s['v']]),
            'image' => url('/carnet/p/' . $slug . '/carte.png'), 'noindex' => true,
        ]);
    }

    /** Lien « Ne plus recevoir ces rappels » de l'e-mail d'anniversaire (sans avoir à ouvrir le carnet). */
    public static function stop(Request $req, string $id, string $sig): Response
    {
        $ok = preg_match('/^[a-f0-9]{16}$/', $id) && hash_equals(Carnet::stopSig($id), $sig) && Carnet::get($id);
        if ($ok) {
            Carnet::setReminders($id, false);
        }
        return self::page('arret', ['ok' => (bool) $ok], ['title' => t('Rappels d’anniversaire'), 'noindex' => true]);
    }

    /** Carte à partager (1200 × 630) : la sienne (cookie) ou celle d'une page publique. */
    public static function card(Request $req, ?string $slug = null): Response
    {
        $c = $slug !== null ? Carnet::bySlug($slug) : Carnet::current();
        if (!$c) {
            return Pages::notFound();
        }
        $s = Carnet::stats(array_keys($c['matches']));
        $res = Share::carnet($s, $slug !== null ? (string) $c['pseudo'] : '');
        if ($slug === null) {
            $res->headers['Cache-Control'] = 'private, max-age=60';
            if (isset($req->query['telecharger'])) {
                $res->headers['Content-Disposition'] = 'attachment; filename="mon-carnet-sochaux-retro.png"';
            }
        }
        return $res;
    }

    // ------------------------------------------------------------------ API

    public static function api(Request $req): Response
    {
        $in = $req->json() ?: [];
        if (($in['lang'] ?? '') === 'en') {
            \App\Services\I18n::set('en'); // messages et e-mail du lien dans la langue de la page
        }
        $action = (string) ($in['action'] ?? '');
        $c = Carnet::current();
        $ids = fn (?array $c) => $c ? array_map('intval', array_keys($c['matches'])) : [];
        switch ($action) {
            case 'etat':
                return Response::json(['ok' => true, 'has' => (bool) $c, 'ids' => $ids($c)]);

            case 'ajouter':
            case 'retirer':
                $mid = (int) ($in['id'] ?? 0);
                if (!$c) {
                    return Response::json(['ok' => false, 'needEmail' => true]);
                }
                if (!RateLimiter::hit('carnet', $c['id'], 600, 3600)) {
                    return Response::json(['error' => t('Trop de demandes, réessayez plus tard.')], 429);
                }
                $list = Carnet::setMatches($c['id'], [$mid], $action === 'ajouter', $added);
                // Compteur public « J'y étais ! » de la fiche : une fois par carnet.
                $etais = $added && !empty($in['count']) ? RetroDirect::addEtais($mid) : null;
                return Response::json(['ok' => true, 'has' => true, 'ids' => $list ?? [], 'etais' => $etais]);

            case 'lot':
                if (!$c) {
                    return Response::json(['ok' => false, 'needEmail' => true]);
                }
                $add = array_slice(array_map('intval', (array) ($in['add'] ?? [])), 0, 200);
                $remove = array_slice(array_map('intval', (array) ($in['remove'] ?? [])), 0, 200);
                Carnet::setMatches($c['id'], $remove, false);
                $list = Carnet::setMatches($c['id'], $add, true);
                return Response::json(['ok' => true, 'has' => true, 'ids' => $list ?? []]);

            case 'creer':
            case 'renvoyer':
                $email = trim((string) ($in['email'] ?? ''));
                if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 120) {
                    return Response::json(['error' => t('Adresse e-mail invalide.')], 422);
                }
                if (trim((string) ($in['website'] ?? '')) !== '') {
                    return Response::json(['ok' => true, 'sent' => true]); // robot
                }
                if (!RateLimiter::hit('carnet-email', $req->ip(), 8, 3600) || !RateLimiter::hit('carnet-email-adr', Carnet::emailKey($email), 3, 3600)) {
                    return Response::json(['error' => t('Trop de demandes, réessayez plus tard.')], 429);
                }
                if ($action === 'creer' && !$c) {
                    $r = Carnet::create($email, \App\Services\I18n::lang());
                    if (!$r['existing'] && $r['credential']) {
                        self::setCookie($r['credential']);
                        $mid = (int) ($in['id'] ?? 0);
                        $list = $mid ? Carnet::setMatches($r['carnet']['id'], [$mid], true, $added) : [];
                        if (!empty($added) && !empty($in['count'])) {
                            RetroDirect::addEtais($mid);
                        }
                        Carnet::sendLink($r['carnet'], $r['credential']);
                        return Response::json(['ok' => true, 'has' => true, 'created' => true, 'ids' => $list ?? []]);
                    }
                }
                // E-mail déjà connu (ou lien perdu) : un lien part vers cette adresse, et seulement elle.
                // Même réponse que l'adresse ait un carnet ou non.
                if ($known = Carnet::byEmail($email)) {
                    if ($cred = Carnet::newCredential($known['id'])) {
                        Carnet::sendLink($known, $cred);
                    }
                }
                return Response::json(['ok' => true, 'sent' => true, 'has' => (bool) $c, 'ids' => $ids($c)]);

            case 'public':
                if (!$c) {
                    return Response::json(['ok' => false, 'needEmail' => true]);
                }
                $err = Carnet::setPublic($c['id'], (string) ($in['pseudo'] ?? ''), !empty($in['on']));
                if ($err) {
                    return Response::json(['error' => $err], 422);
                }
                $c = Carnet::get($c['id']);
                return Response::json(['ok' => true, 'url' => $c['public'] ? url('/carnet/p/' . $c['slug'] . '/') : null]);

            case 'rappels':
                if (!$c) {
                    return Response::json(['ok' => false, 'needEmail' => true]);
                }
                $sub = null;
                $endpoint = trim((string) ($in['endpoint'] ?? ''));
                if ($endpoint !== '') {
                    if (!\App\Services\Notifications::find($endpoint)) {
                        return Response::json(['error' => t('Activez d’abord les notifications du musée sur cet appareil.'), 'appli' => url('/appli/')], 422);
                    }
                    $sub = \App\Services\Notifications::idOf($endpoint);
                }
                $c = Carnet::setReminders($c['id'], isset($in['email']) ? (bool) $in['email'] : null, $sub, !empty($in['pushOff']));
                return Response::json(['ok' => true, 'email' => !empty($c['remind_email']), 'push' => count((array) ($c['remind_push'] ?? []))]);

            case 'supprimer':
                if ($c && !empty($in['confirm'])) {
                    Carnet::delete($c['id']);
                }
                self::clearCookie();
                return Response::json(['ok' => true, 'has' => false, 'ids' => []]);
        }
        return Response::json(['error' => t('Ressource introuvable')], 404);
    }
}
