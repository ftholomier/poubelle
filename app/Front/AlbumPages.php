<?php
declare(strict_types=1);

namespace App\Front;

use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Services\Carnet;
use App\Services\DailyQuiz;
use App\Services\I18n;
use App\Services\Vignettes;

/**
 * Page unique de l'Album Sochini / Parini (/interactif/album-vignettes/) : le kiosque des albums,
 * l'album choisi (?album=type&v=valeur&p=page), et « Mon album » (collection personnelle, API JSON).
 */
final class AlbumPages
{
    public static function page(Request $req): Response
    {
        $cat = AlbumBook::catalog();
        $type = (string) ($req->query['album'] ?? '');
        $v = (string) ($req->query['v'] ?? '');
        $p = max(1, (int) ($req->query['p'] ?? 1));
        $status = 200;
        $default = false;
        if ($type === '') {
            // Sans choix : la saison la plus récente avec compositions.
            $default = true;
            $type = isset($cat['saison']) ? 'saison' : (string) array_key_first($cat);
            $v = $type !== '' ? (string) array_key_last($cat[$type]) : '';
        } elseif (in_array($type, AlbumBook::SINGLE, true)) {
            $v = '';
        } elseif ($v === '' && isset($cat[$type])) {
            $v = (string) array_key_last($cat[$type]);
        }
        $album = $type !== '' ? AlbumBook::build($type, $v, $p) : null;
        if (!$album && !$default) {
            $status = 404;
        }
        // Suite d'un album chargée sans recharger la page : seulement les vignettes.
        if ($album && ($req->query['part'] ?? '') === 'suite') {
            return Response::html(\App\Core\View::partial('partials/album-book-items', ['album' => $album]));
        }
        [$prev, $next] = $album ? AlbumBook::neighbours($type, $v) : [null, null];
        $name = AlbumBook::name();
        $title = $album ? AlbumBook::title($album) . ' · ' . $name : $name;
        $res = Pages::render('interactif/album-vignettes', [
            'cat' => $cat, 'type' => $type, 'v' => $v, 'album' => $album, 'prev' => $prev, 'next' => $next, 'name' => $name, 'missing' => $status === 404,
        ], [
            'title' => $title,
            'description' => $album
                ? t('{t} : {s}. Vignettes calculées d’après les compositions des matchs officiels.', ['t' => AlbumBook::title($album), 's' => AlbumBook::subtitle($album, $album['total'])])
                : t('Les albums de vignettes du musée : par saison, décennie, entraîneur, épopée, et votre propre collection.'),
            'canonical' => $default ? url(AlbumBook::PATH) : ($album ? AlbumBook::url($type, $v, $album['page']) : null),
            'noindex' => $status === 404,
            'active' => 'interactif',
            'body_class' => 'page-sochini',
            'no_cookie' => true,
            'prev' => $album && $album['page'] > 1 ? AlbumBook::url($type, $v, $album['page'] - 1) : null,
            'next' => $album && $album['page'] < $album['pages'] ? AlbumBook::url($type, $v, $album['page'] + 1) : null,
            'styles' => ['css/explore.css', 'css/sochini.css'],
            'scripts' => ['js/sochini.js'],
        ]);
        $res->status = $status;
        return $res;
    }

    /** Vignettes à collectionner et chapitres (données publiques, mises en cache par le navigateur). */
    public static function pool(Request $req): Response
    {
        if (($req->query['lang'] ?? '') === 'en') {
            I18n::set('en');
        }
        $out = [];
        foreach (Vignettes::pool() as $pid => $p) {
            $out[] = [$pid, $p['no'], $p['name'], $p['r'], $p['image'] ? img($p['image'], 320) : null, url($p['href']), $p['line']];
        }
        $res = Response::json(['v' => $out]);
        $res->headers['Cache-Control'] = 'public, max-age=3600';
        return $res;
    }

    /** Image de partage d'une page complétée. */
    public static function share(Request $req, string $k): Response
    {
        $ch = Vignettes::chapters()[$k] ?? null;
        if (!$ch) {
            return Response::html('', 404);
        }
        return Share::albumPage(AlbumBook::name(), $ch['title'], count($ch['ids']));
    }

    private static function setCookie(string $value): void
    {
        if (headers_sent()) {
            return;
        }
        setcookie(Vignettes::COOKIE, $value, [
            'expires' => $value === '' ? time() - 3600 : time() + Vignettes::INACTIVE_MONTHS * 31 * 86400,
            'path' => '/', 'httponly' => true, 'samesite' => 'Lax', 'secure' => (($_SERVER['HTTPS'] ?? '') === 'on'),
        ]);
    }

    /** API de « Mon album » : etat, ouvrir, bonus, offrir, voir, accepter, annuler, sauvegarde, restaurer. */
    public static function api(Request $req): Response
    {
        $in = $req->json() ?: [];
        if (($in['lang'] ?? '') === 'en') {
            I18n::set('en');
        }
        if (!RateLimiter::hit('vignettes', $req->ip(), 600, 3600)) {
            return Response::json(['error' => t('Trop de demandes, réessayez plus tard.')], 429);
        }
        $action = (string) ($in['action'] ?? '');
        $carnet = Carnet::current();
        // Copie de secours de l'identifiant gardée par le navigateur (localStorage) : si le cookie a été
        // perdu (navigateur qui l'efface, appli installée…), la collection est retrouvée ; si une
        // nouvelle collection a été créée entre-temps, l'ancienne y est versée.
        $ck = isset($_COOKIE[Vignettes::COOKIE]) && Vignettes::open((string) $_COOKIE[Vignettes::COOKIE]) ? (string) $_COOKIE[Vignettes::COOKIE] : null;
        $bk = is_string($in['cred'] ?? null) && ($b = Vignettes::open($in['cred'])) ? $b : null;
        $cred = null;
        if ($bk && !$ck) {
            $ck = $bk['id'] . '.' . substr(strtolower(str_replace(['-', ' ', '.'], '', $in['cred'])), 17);
            self::setCookie($ck);
            $cred = $ck;
        } elseif ($bk && !str_starts_with($ck, $bk['id'] . '.')) {
            Vignettes::merge($bk, substr($ck, 0, 17));
            $cred = $ck;
        }
        [$c, $cookie] = Vignettes::current($ck, $carnet);
        if ($cookie !== null) {
            self::setCookie($cookie);
            $cred = $cookie;
        }
        // Première action qui écrit : on crée la collection (cookie de l'appareil, ou carnet).
        $writes = ['ouvrir', 'bonus', 'offrir', 'accepter', 'annuler'];
        if (!$c && in_array($action, $writes, true)) {
            if ($carnet) {
                $c = ['id' => 'k' . $carnet['id']];
            } else {
                [$c, $cred] = Vignettes::create();
                self::setCookie($cred);
            }
        }
        $id = $c['id'] ?? null;
        $out = match ($action) {
            'etat' => ['ok' => true],
            'ouvrir' => Vignettes::openPack($id),
            'bonus' => Vignettes::bonus($id, (string) ($in['kind'] ?? ''), [
                'pid' => (int) ($in['pid'] ?? 0),
                'ok' => ($in['kind'] ?? '') === 'defi' && (Vignettes::defiPassed(DailyQuiz::key(null, (string) ($in['tok'] ?? ''))) || ($carnet && Vignettes::defiPassed($carnet['id']))),
            ]),
            'offrir' => Vignettes::offer($id, (int) ($in['give'] ?? 0), (int) ($in['want'] ?? 0)),
            'voir' => ($t = Vignettes::trade((string) ($in['code'] ?? ''))) && empty($t['used']) ? ['trade' => ['give' => $t['give'], 'want' => $t['want']]] : ['error' => t('Code d’échange inconnu, expiré ou déjà utilisé.')],
            'accepter' => Vignettes::accept($id, (string) ($in['code'] ?? '')),
            'annuler' => Vignettes::cancel($id, (string) ($in['code'] ?? '')) ? ['ok' => true] : ['error' => t('Offre introuvable.')],
            'sauvegarde' => $c && str_starts_with($c['id'], 'a') && $ck ? ['code' => Vignettes::saveCode($ck)] : ['error' => t('Rien à sauvegarder pour le moment.')],
            'restaurer' => ($r = Vignettes::open((string) ($in['code'] ?? ''))) ? self::restore($r, (string) $in['code'], $carnet) : ['error' => t('Code de sauvegarde inconnu.')],
            default => ['error' => t('Ressource introuvable')],
        };
        if (isset($out['error'])) {
            return Response::json($out, 422);
        }
        $sid = $action === 'restaurer' ? $out['id'] : $id;
        $c = $sid ? Vignettes::get($sid) : null;
        unset($out['id']);
        if ($cred !== null && !isset($out['cred'])) {
            $out['cred'] = $cred;
        }
        return Response::json(['ok' => true] + $out + ['state' => Vignettes::state($c)]);
    }

    private static function restore(array $r, string $code, ?array $carnet): array
    {
        if ($carnet) {
            $c = Vignettes::merge($r, 'k' . $carnet['id']);
            return ['id' => $c['id'], 'restored' => true];
        }
        $cred = $r['id'] . '.' . substr(strtolower(str_replace(['-', ' ', '.'], '', $code)), 17);
        self::setCookie($cred);
        return ['id' => $r['id'], 'restored' => true, 'cred' => $cred];
    }
}
