<?php
declare(strict_types=1);

namespace App\Front;

use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Core\Settings;
use App\Services\I18n;
use App\Services\Notifications;
use App\Services\WebPush;

/**
 * « L'appli du musée » (/appli/) : installer le musée sur l'écran d'accueil (bouton sur Android
 * et ordinateur, marche à suivre sur iPhone), ce que l'appli apporte, et les notifications.
 * Seulement le musée : le site de l'association n'est pas une application.
 *
 * API des notifications (/api/push/…, JSON) : cle (clé publique VAPID), etat, abonner, sujets,
 * desabonner, renouveler (appelée par le service worker), essai, ouverture (clic compté).
 * L'adresse d'abonnement, longue et impossible à deviner, tient lieu de preuve : seul le
 * navigateur abonné la connaît.
 */
final class Appli
{
    public static function page(Request $req): Response
    {
        if (!Settings::get('app.enabled', true)) {
            return Pages::notFound();
        }
        $push = Notifications::enabled();
        return Pages::render('appli', ['push' => $push, 'topics' => $push ? Notifications::topics() : []], [
            'title' => t('L’appli du musée'),
            'description' => t('Installez Sochaux Rétro sur l’écran d’accueil de votre téléphone : le musée en plein écran, plus rapide, et lisible même sans réseau.'),
            'active' => '',
            'styles' => ['css/appli.css'],
            'scripts' => ['js/appli.js'],
        ]);
    }

    public static function api(Request $req, string $action): Response
    {
        if (!Notifications::enabled()) {
            return Response::json(['error' => t('Les notifications ne sont pas proposées pour le moment.')], 404);
        }
        if ($action === 'cle') {
            return Response::json(['key' => WebPush::publicKeyB64()]);
        }
        if ($req->method !== 'POST') {
            return Response::json(['error' => t('Ressource introuvable')], 404);
        }
        if (!RateLimiter::hit('push', $req->ip(), 60, 3600)) {
            return Response::json(['error' => t('Trop de demandes, réessayez plus tard.')], 429);
        }
        $in = $req->json() ?: [];
        $endpoint = trim((string) ($in['endpoint'] ?? $in['sub']['endpoint'] ?? ''));
        $lang = ($in['lang'] ?? '') === 'en' ? 'en' : 'fr';
        switch ($action) {
            case 'etat':
                $s = Notifications::find($endpoint);
                return Response::json(['subscribed' => (bool) $s, 'topics' => $s['t'] ?? Notifications::defaults()]);
            case 'abonner':
                if (!RateLimiter::hit('push-abonner', $req->ip(), 20, 3600)) {
                    return Response::json(['error' => t('Trop de demandes, réessayez plus tard.')], 429);
                }
                $r = Notifications::subscribe((array) ($in['sub'] ?? []), (array) ($in['topics'] ?? Notifications::defaults()), $lang, ($in['src'] ?? '') === 'attente');
                return $r['ok'] ? Response::json(['ok' => true]) : Response::json(['error' => t('Cet abonnement n’a pas pu être enregistré.')], 422);
            case 'sujets':
                return Notifications::setTopics($endpoint, (array) ($in['topics'] ?? [])) ? Response::json(['ok' => true]) : Response::json(['error' => t('Abonnement introuvable.')], 404);
            case 'desabonner':
                Notifications::unsubscribe($endpoint);
                return Response::json(['ok' => true]);
            case 'renouveler':
                $r = Notifications::renew(trim((string) ($in['old'] ?? '')), (array) ($in['sub'] ?? []), $lang);
                return $r['ok'] ? Response::json(['ok' => true]) : Response::json(['error' => 'refusé'], 422);
            case 'essai':
                $s = Notifications::find($endpoint);
                if (!$s) {
                    return Response::json(['error' => t('Abonnement introuvable.')], 404);
                }
                if (!RateLimiter::hit('push-essai', Notifications::idOf($endpoint), 3, 3600)) {
                    return Response::json(['error' => t('Trois essais par heure au plus.')], 429);
                }
                $msg = self::testMessage();
                $r = Notifications::enqueue('essai:' . Notifications::idOf($endpoint) . ':' . microtime(true), 'essai', $msg, ['only' => [Notifications::idOf($endpoint)], 'hidden' => true, 'ttl' => 600]);
                $p = $r['ok'] ? Notifications::process(15) : null;
                return Response::json(['ok' => $r['ok'], 'delivered' => (bool) ($p['ok'] ?? 0)]);
            case 'ouverture':
                if (RateLimiter::hit('push-ouverture', $req->ip(), 30, 3600)) {
                    Notifications::opened((string) ($in['id'] ?? ''));
                }
                return Response::json(['ok' => true]);
        }
        return Response::json(['error' => t('Ressource introuvable')], 404);
    }

    private static function testMessage(): array
    {
        $prev = I18n::lang();
        $out = [];
        foreach (['fr', 'en'] as $l) {
            I18n::set($l);
            $out[$l] = ['title' => t('Sochaux Rétro · notification d’essai'), 'body' => t('Tout fonctionne : le musée pourra vous prévenir.'), 'url' => url('/appli/')];
        }
        I18n::set($prev);
        return $out;
    }
}
