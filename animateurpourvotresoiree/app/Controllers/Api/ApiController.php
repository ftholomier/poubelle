<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Controllers\Front\HomeController;
use App\Core\Controller;
use App\Core\Auth;
use App\Core\Logger;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Str;
use App\Core\View;
use App\Services\AntiSpam;
use App\Services\Categories;
use App\Services\Geo;
use App\Services\Pros;
use App\Services\Push;
use App\Services\Search;
use App\Services\Settings;
use App\Services\Stats;
use App\Services\Store;

/** API JSON publique (explorateur, autocomplétion, favoris, anti-spam, statistiques). */
final class ApiController extends Controller
{
    private function limit(string $key, int $max, int $window): ?Response
    {
        if (!RateLimiter::attempt('api:' . $key . ':' . Request::ip(), $max, $window)) {
            return Response::json(['error' => 'Trop de requêtes, réessayez dans un instant.'], 429)->header('Retry-After', (string) RateLimiter::retryAfter('api:' . $key . ':' . Request::ip()));
        }
        return null;
    }

    /** Recherche de l'explorateur : total, identifiants (pour la carte) et une page de cartes HTML. */
    public function pros(): Response
    {
        if ($r = $this->limit('pros', 240, 60)) {
            return $r;
        }
        $c = SearchCriteria::fromRequest();
        $res = Search::run($c);
        $html = '';
        foreach ($res['items'] as $i => $p) {
            $html .= View::partial('front/partials/result-card', ['p' => $p]);
            $every = (int) Settings::get('ads.slots.listing.every', 8);
            if ($every > 0 && ($i + 1) % $every === 0 && ($c['offset'] ?? 0) === 0) {
                $html .= \App\Services\Ads::slot('listing', 'ad-inline');
            }
        }
        if (($c['offset'] ?? 0) === 0 && !empty($c['q'])) {
            Stats::hit('search', 0, (string) $c['q']);
        }
        return Response::json([
            'total' => $res['total'],
            'ids' => $res['ids'],
            'html' => $html,
            'count' => count($res['items']),
            'center' => $res['center'],
        ]);
    }

    public function homePros(): Response
    {
        $cat = Request::str('cat', 60);
        $cat = Categories::get($cat) ? $cat : null;
        $list = HomeController::featured($cat, (int) Settings::get('home.featured_count', 9));
        $html = '';
        foreach ($list as $p) {
            $html .= View::partial('front/partials/pro-card', ['p' => $p]);
        }
        $n = $cat ? (Stats::publicNumbers()['by_cat'][$cat] ?? count($list)) : Pros::publicCount();
        return Response::json(['html' => $html, 'count' => count($list), 'label' => nf($n) . ' ' . ($n > 1 ? 'pros trouvés' : 'pro trouvé') . ($cat ? ' · ' . Categories::name($cat) : '')]);
    }

    public function communes(): Response
    {
        if ($r = $this->limit('communes', 300, 60)) {
            return $r;
        }
        $q = Request::str('q', 60);
        return Response::json(['items' => Geo::search($q, 8)], 200, ['Cache-Control' => 'public, max-age=86400']);
    }

    public function favoris(): Response
    {
        $ids = array_slice(array_filter(array_map('intval', explode(',', (string) Request::query('ids', '')))), 0, 60);
        $index = Pros::publicIndex();
        $html = '';
        $found = [];
        foreach ($ids as $id) {
            if (isset($index[$id])) {
                $html .= View::partial('front/partials/pro-card', ['p' => $index[$id]]);
                $found[] = $id;
            }
        }
        return Response::json(['html' => $html, 'ids' => $found]);
    }

    public function challenge(): Response
    {
        if ($r = $this->limit('challenge', 60, 600)) {
            return $r;
        }
        $form = preg_replace('/[^a-z_]/', '', (string) Request::input('form', 'form')) ?: 'form';
        return Response::json(AntiSpam::challenge($form));
    }

    public function track(): Response
    {
        if (RateLimiter::attempt('track:' . Request::ip(), 120, 60)) {
            $type = (string) (Request::input('type') ?? '');
            $pro = (int) (Request::input('pro') ?? 0);
            if (in_array($type, ['fav', 'site', 'share', 'pro_view', 'devis_click', 'install'], true)) {
                Stats::hit($type, $pro, Str::limit((string) (Request::input('extra') ?? ''), 80, ''));
            }
        }
        return new Response('', 204);
    }

    /** Affiche le numéro d'un pro (anti-aspiration : limité par IP, compté dans ses statistiques). */
    public function phone(int $id): Response
    {
        if (!Settings::get('features.phone_reveal', true)) {
            return Response::json(['error' => 'Contactez ce pro via le formulaire.'], 403);
        }
        [$max, $window] = Settings::get('antispam.rates.reveal', [30, 3600]);
        if ($r = $this->limit('reveal', (int) $max, (int) $window)) {
            return $r;
        }
        $pro = Store::pros()->get($id);
        if (!$pro || !Pros::isPublished($pro) || empty($pro['phone'])) {
            return Response::json(['error' => 'Numéro indisponible : utilisez le formulaire de contact.'], 404);
        }
        Stats::hit('phone', $id);
        $digits = preg_replace('/\D/', '', (string) $pro['phone']) ?? '';
        $tel = strlen($digits) === 10 && $digits[0] === '0' ? '+33' . substr($digits, 1) : '+' . ltrim($digits, '+');
        return Response::json(['phone' => Str::phone((string) $pro['phone']), 'tel' => $tel]);
    }

    public function pushSubscribe(): Response
    {
        Session::resume();
        $pro = Auth::pro();
        if (!$pro) {
            return Response::json(['error' => 'Connexion requise'], 401);
        }
        if (!\App\Core\Csrf::check()) {
            return Response::json(['error' => 'Session expirée, rechargez la page.'], 419);
        }
        $sub = (array) Request::input('subscription', []);
        $res = Push::subscribe('pro', (int) $pro['id'], $sub, Request::userAgent());
        if (!$res) {
            return Response::json(['error' => 'Abonnement invalide'], 422);
        }
        Logger::info('Abonnement push pro', ['pro' => $pro['id']]);
        return Response::json(['ok' => true]);
    }

    public function pushUnsubscribe(): Response
    {
        $endpoint = (string) Request::input('endpoint', '');
        if ($endpoint !== '') {
            Push::unsubscribe($endpoint);
        }
        return Response::json(['ok' => true]);
    }
}
