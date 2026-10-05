<?php
declare(strict_types=1);

namespace App\Shop;

use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Auth;
use App\Front\Pages;

/**
 * Boutique en ligne, sur le site du musée (celui qui a le trafic ; le site de l'association y renvoie) : catalogue, personnalisation (choix calibrés,
 * aperçu en direct rendu par le serveur), panier, commande payée par carte (Stripe Checkout),
 * page de suivi de commande avec messages à l'imprimeur. Fermée tant que l'administrateur ne
 * l'ouvre pas (Boutique › Réglages) ; l'équipe connectée au back-office la voit déjà.
 */
final class ShopPages
{
    private const CART = 'shop_cart';

    /** Adresse d'une page de la boutique (site du musée). */
    public static function u(string $path): string
    {
        return url($path);
    }

    /** Ouverte au public, ou visible par l'équipe connectée (essais avant l'ouverture). */
    public static function visible(): bool
    {
        return Orders::open() || Auth::user() !== null;
    }

    private static function closed(): ?Response
    {
        return !self::visible() ? Pages::render('boutique/fermee', [], ['title' => 'Boutique', 'active' => 'boutique', 'styles' => ['css/boutique.css']]) : null;
    }

    private static function page(string $tpl, array $vars, array $page): Response
    {
        return Pages::render('boutique/' . $tpl, $vars, $page + ['active' => 'boutique', 'styles' => ['css/boutique.css'], 'scripts' => ['js/boutique.js']]);
    }

    private static function notFound(): Response
    {
        return Pages::notFound();
    }

    /** @return list<array> modèles en vente */
    private static function products(): array
    {
        return array_values(array_filter(Catalog::models(), fn ($m) => Catalog::sellable($m)));
    }

    /** Aperçu (SVG) d'un modèle avec les choix du client, sur sa première face dessinée. */
    public static function preview(array $m, array $opts = [], array $values = [], string $face = ''): string
    {
        [$mm] = Catalog::applyOptions($m, $opts);
        $sup = Catalog::support($mm['support']);
        $drawn = array_keys(array_filter($mm['faces'], fn ($f) => $f['layers'] || $f['bg']));
        $fk = in_array($face, $drawn, true) ? $face : ($drawn[0] ?? array_key_first($mm['faces']));
        return Mockup::render($sup['mockup'], (string) $fk, $mm['faces'][$fk], $mm['color'], $values)['svg'];
    }

    // ------------------------------------------------------------------ catalogue, produit

    public static function index(Request $req): Response
    {
        if ($r = self::closed()) {
            return $r;
        }
        $cards = [];
        foreach (self::products() as $m) {
            $sup = Catalog::support($m['support']);
            $from = $m['sale']['price'] + ($m['sale']['extra'] ? 0 : 0);
            $cards[] = ['m' => $m, 'sup' => $sup, 'svg' => self::preview($m, [], self::samples($m)), 'from' => $from];
        }
        return self::page('index', ['cards' => $cards, 'config' => Orders::config(), 'count' => self::count()], ['title' => 'Boutique', 'description' => 'Les objets de Sochaux Rétro, personnalisés et fabriqués à la demande près de chez nous. Chaque achat soutient l’association.']);
    }

    public static function product(Request $req, string $id): Response
    {
        if ($r = self::closed()) {
            return $r;
        }
        $m = Catalog::find($id);
        if (!$m || !Catalog::sellable($m)) {
            return self::notFound();
        }
        $sup = Catalog::support($m['support']);
        return self::page('produit', [
            'm' => $m, 'sup' => $sup, 'fields' => Catalog::fields($m), 'positions' => Catalog::positions($m),
            'svg' => self::preview($m, [], self::samples($m)), 'config' => Orders::config(), 'count' => self::count(),
            'faces' => array_keys(array_filter($m['faces'], fn ($f) => $f['layers'] || $f['bg'])), 'flash' => Session::pull('shop_flash'),
        ], ['title' => $m['name'], 'description' => $m['sale']['desc'] ?: $m['name'] . ' · boutique Sochaux Rétro']);
    }

    /** POST /boutique/apercu/ (JSON) : aperçu et prix pendant que le client choisit. */
    public static function livePreview(Request $req): Response
    {
        if (!RateLimiter::hit('boutique-apercu', $req->ip(), 900, 3600)) {
            return Response::json(['error' => 'Trop de demandes.'], 429);
        }
        $in = $req->json();
        $m = Catalog::find((string) ($in['model'] ?? ''));
        if (!$m || !self::visible() || (!Catalog::sellable($m) && Auth::user() === null)) {
            return Response::json(['error' => 'Article indisponible.'], 404);
        }
        $values = [];
        foreach (Catalog::fields($m) as $k => $f) {
            $v = trim(mb_substr((string) ($in['values'][$k] ?? ''), 0, 200));
            $values[$k] = $v !== '' ? $v : $f['default'];
        }
        $opts = array_map(fn ($v) => mb_substr((string) $v, 0, 20), (array) ($in['opts'] ?? []));
        [$mm, $opt] = Catalog::applyOptions($m, $opts);
        $note = '';
        if (TonMatch::isFor($m) && $opt['date'] !== '') {
            $tm = TonMatch::values($opt['date']);
            $note = TonMatch::note($tm, $opt['date']);
            $values = array_replace($values, array_intersect_key($tm, $values));
        }
        $chk = Catalog::check($mm, array_filter(array_map('strval', (array) ($in['values'] ?? []))));
        $size = (string) ($in['size'] ?? '');
        return Response::json(['ok' => true, 'svg' => self::preview($m, $opts, $values, (string) ($in['face'] ?? '')), 'errors' => $chk['errors'], 'note' => $note,
            'price' => Orders::money(Catalog::price($m, $size) * max(1, min(20, (int) ($in['qty'] ?? 1))))]);
    }

    /** Réponses d'exemple : le texte d'exemple de chaque champ. */
    private static function samples(array $m): array
    {
        return array_map(fn ($f) => $f['default'], Catalog::fields($m));
    }

    // ------------------------------------------------------------------ panier

    private static function cart(): array
    {
        return array_values(array_filter((array) Session::get(self::CART, []), 'is_array'));
    }

    private static function count(): int
    {
        return (int) array_sum(array_map(fn ($l) => (int) ($l['qty'] ?? 1), self::cart()));
    }

    /**
     * Nombre d'articles du panier dans un petit cookie lisible par le script du site : la pastille
     * du bouton « Boutique » s'affiche sur toutes les pages, même mises en cache.
     */
    private static function syncBadge(): void
    {
        if (headers_sent()) {
            return;
        }
        $n = self::count();
        setcookie('sr_cart', (string) $n, ['expires' => $n ? time() + 30 * 86400 : time() - 3600, 'path' => '/', 'samesite' => 'Lax', 'secure' => (($_SERVER['HTTPS'] ?? '') === 'on')]);
    }

    public static function cartPage(Request $req): Response
    {
        if ($r = self::closed()) {
            return $r;
        }
        $lines = [];
        $keep = [];
        foreach (self::cart() as $in) {
            $r = Orders::line($in);
            if (isset($r['item'])) {
                $m = Catalog::find($in['model']);
                $lines[] = $r['item'] + ['svg' => self::preview($m, $r['item']['opts'], $r['item']['values'])];
                $keep[] = $in;
            }
        }
        if (count($keep) !== count(self::cart())) {
            Session::set(self::CART, $keep);
            self::syncBadge();
        }
        $sub = array_sum(array_column($lines, 'total'));
        $promo = null;
        if (($code = (string) Session::get('shop_promo', '')) !== '' && $lines) {
            $promo = Promos::apply($code, $lines);
            if (isset($promo['error'])) {
                Session::forget('shop_promo');
                $promo = null;
            }
        }
        $discount = (int) ($promo['discount'] ?? 0);
        $flash = Session::pull('shop_flash');
        if (!$flash && $req->str('annule') === '1') {
            $flash = ['type' => 'info', 'msg' => 'Paiement annulé : rien n’a été prélevé. Votre panier vous attend.'];
        }
        return self::page('panier', [
            'lines' => $lines, 'sub' => $sub, 'promo' => $promo, 'discount' => $discount,
            'ship' => $lines ? (!empty($promo['free_shipping']) ? 0 : Orders::shipping($sub - $discount)) : 0, 'config' => Orders::config(), 'count' => self::count(),
            'flash' => $flash, 'old' => Session::pull('shop_old', []), 'payable' => Orders::payable(), 'test' => \App\Core\Settings::get('donations.mode', 'test') !== 'live',
        ], ['title' => 'Votre panier', 'noindex' => true]);
    }

    /** POST /boutique/panier/ : ajouter (depuis la page produit), changer une quantité, retirer. */
    public static function cartAction(Request $req): Response
    {
        if ($r = self::closed()) {
            return $r;
        }
        if (!Session::checkCsrf((string) ($req->post['_csrf'] ?? ''))) {
            Session::set('shop_flash', ['type' => 'error', 'msg' => 'Votre session a expiré : recommencez.']);
            return Response::redirect(self::u('/boutique/panier/'));
        }
        $cart = self::cart();
        $do = (string) ($req->post['do'] ?? 'add');
        if ($do === 'add') {
            $in = ['model' => (string) ($req->post['model'] ?? ''), 'size' => (string) ($req->post['size'] ?? ''), 'qty' => (int) ($req->post['qty'] ?? 1),
                'values' => array_map(fn ($v) => mb_substr((string) $v, 0, 200), (array) ($req->post['values'] ?? [])),
                'opts' => array_map(fn ($v) => mb_substr((string) $v, 0, 20), (array) ($req->post['opts'] ?? []))];
            $r = Orders::line($in);
            if (isset($r['error'])) {
                Session::set('shop_flash', ['type' => 'error', 'msg' => $r['error']]);
                return Response::redirect(self::u('/boutique/' . rawurlencode($in['model']) . '/'));
            }
            if (count($cart) >= Orders::MAX_ITEMS) {
                Session::set('shop_flash', ['type' => 'error', 'msg' => 'Votre panier est plein (' . Orders::MAX_ITEMS . ' articles).']);
                return Response::redirect(self::u('/boutique/panier/'));
            }
            $cart[] = ['model' => $r['item']['model'], 'size' => $r['item']['size'], 'qty' => $r['item']['qty'], 'values' => $r['item']['values'], 'opts' => $r['item']['opts']];
            Session::set(self::CART, $cart);
            self::syncBadge();
            Session::set('shop_flash', ['type' => 'ok', 'msg' => '« ' . $r['item']['name'] . ' » ajouté au panier.']);
            return Response::redirect(self::u('/boutique/panier/'));
        }
        if ($do === 'promo') {
            $code = Promos::code((string) ($req->post['code'] ?? ''));
            $items = array_values(array_filter(array_map(fn ($in) => Orders::line($in)['item'] ?? null, $cart)));
            $r = $code !== '' ? Promos::apply($code, $items) : ['error' => 'Saisissez un code promo.'];
            if (!RateLimiter::hit('boutique-promo', $req->ip(), 30, 3600)) {
                $r = ['error' => 'Trop d’essais : réessayez dans une heure.'];
            }
            if (isset($r['error'])) {
                Session::set('shop_flash', ['type' => 'error', 'msg' => $r['error']]);
            } else {
                Session::set('shop_promo', $r['code']);
                Session::set('shop_flash', ['type' => 'ok', 'msg' => 'Code ' . $r['code'] . ' appliqué : ' . $r['label'] . '.']);
            }
            return Response::redirect(self::u('/boutique/panier/'));
        }
        if ($do === 'unpromo') {
            Session::forget('shop_promo');
            return Response::redirect(self::u('/boutique/panier/'));
        }
        $i = (int) ($req->post['line'] ?? -1);
        if (isset($cart[$i])) {
            if ($do === 'remove') {
                array_splice($cart, $i, 1);
            } elseif ($do === 'qty') {
                $cart[$i]['qty'] = max(1, min(20, (int) ($req->post['qty'] ?? 1)));
            }
            Session::set(self::CART, $cart);
            self::syncBadge();
        }
        return Response::redirect(self::u('/boutique/panier/'));
    }

    /** POST /boutique/commander/ : coordonnées, commande, puis paiement chez Stripe. */
    public static function checkout(Request $req): Response
    {
        if ($r = self::closed()) {
            return $r;
        }
        $back = self::u('/boutique/panier/') . '#commander';
        $p = $req->post;
        Session::set('shop_old', array_intersect_key(array_map(fn ($v) => is_string($v) ? mb_substr($v, 0, 160) : '', $p), array_flip(['name', 'email', 'phone', 'line1', 'line2', 'zip', 'city', 'country'])));
        $err = null;
        if (!Session::checkCsrf((string) ($p['_csrf'] ?? ''))) {
            $err = 'Votre session a expiré : renvoyez le formulaire.';
        } elseif (trim((string) ($p['website'] ?? '')) !== '') {
            $err = 'Envoi refusé.';
        } elseif (($age = form_ts_age((string) ($p['_ts'] ?? ''))) === null || $age < 3) {
            $err = 'Merci de prendre le temps de remplir le formulaire.';
        } elseif (!RateLimiter::hit('vt-boutique', $req->ip(), 20, 3600)) {
            $err = 'Trop de commandes depuis votre connexion : réessayez dans une heure.';
        } elseif (($p['cgv'] ?? '') !== '1') {
            $err = 'Merci d’accepter les conditions de vente.';
        } elseif (!Orders::payable()) {
            $err = 'Le paiement en ligne n’est pas encore ouvert.';
        }
        if ($err) {
            Session::set('shop_flash', ['type' => 'error', 'msg' => $err]);
            return Response::redirect($back);
        }
        $r = Orders::create(self::cart(), $p, (string) Session::get('shop_promo', ''));
        if (isset($r['error'])) {
            Session::set('shop_flash', ['type' => 'error', 'msg' => $r['error']]);
            return Response::redirect($back);
        }
        $base = base_url();
        $pay = Orders::checkout($r['order'], $base);
        if (isset($pay['error'])) {
            Session::set('shop_flash', ['type' => 'error', 'msg' => $pay['error']]);
            return Response::redirect($back);
        }
        Session::forget('shop_old');
        Session::forget('shop_promo');
        Session::set('shop_pending', $r['order']['id']);
        return Response::redirect($pay['url']);
    }

    // ------------------------------------------------------------------ suivi de commande

    public static function track(Request $req, string $token): Response
    {
        $o = Orders::byToken($token);
        if (!$o) {
            return self::notFound();
        }
        if ($o['status'] === 'pending' && ($sid = $req->str('session_id')) !== '') {
            Orders::syncReturn($o, $sid);
            $o = Orders::get($o['id']);
        }
        // Commande payée : le panier est vidé.
        if ($o['status'] !== 'pending' && Session::get('shop_pending') === $o['id']) {
            Session::forget(self::CART);
            self::syncBadge();
            Session::forget('shop_pending');
        }
        $previews = [];
        foreach ($o['items'] as $it) {
            $m = Catalog::find($it['model']);
            $previews[] = $m ? self::preview($m, $it['opts'], $it['values']) : '';
        }
        return self::page('suivi', ['o' => $o, 'previews' => $previews, 'flash' => Session::pull('shop_flash'), 'config' => Orders::config(), 'count' => self::count()],
            ['title' => 'Commande ' . $o['id'], 'noindex' => true]);
    }

    public static function trackMessage(Request $req, string $token): Response
    {
        $o = Orders::byToken($token);
        if (!$o) {
            return self::notFound();
        }
        $back = self::u('/boutique/commande/' . $token . '/') . '#messages';
        if (!Session::checkCsrf((string) ($req->post['_csrf'] ?? '')) || trim((string) ($req->post['website'] ?? '')) !== '') {
            Session::set('shop_flash', ['type' => 'error', 'msg' => 'Votre session a expiré : renvoyez le message.']);
        } elseif (!RateLimiter::hit('vt-boutique-msg', $req->ip(), 10, 3600)) {
            Session::set('shop_flash', ['type' => 'error', 'msg' => 'Trop de messages : réessayez dans une heure.']);
        } elseif (Orders::message($o['id'], 'client', (string) ($req->post['text'] ?? ''))) {
            Session::set('shop_flash', ['type' => 'ok', 'msg' => 'Message envoyé : l’imprimeur vous répond par e-mail et sur cette page.']);
        }
        return Response::redirect($back);
    }
}
