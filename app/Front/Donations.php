<?php
declare(strict_types=1);

namespace App\Front;

use App\Core\JsonStore;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Settings;
use App\Data\Collections;
use App\Services\I18n;
use App\Services\Mailer;
use App\Services\Payments;
use App\Services\Pdf;

/**
 * Dons en ligne (maquette « Faire un don ») : ponctuels ou mensuels, par carte
 * (Stripe Checkout) ou PayPal, jauge de la collecte, mur des donateurs, e-mail de
 * remerciement, gestion du don mensuel par lien personnel, reçus fiscaux (désactivés
 * par défaut). Aucun paiement n'est jamais validé sur la seule foi du navigateur :
 * chaque statut est relu chez le prestataire ou reçu par webhook signé.
 *
 * Données : storage/dons/dons.json (hors du dossier public). Un don = une intention
 * (ponctuelle ou abonnement) et la liste de ses paiements, chacun identifié par la
 * référence du prestataire (traitement idempotent : page de retour, webhook et
 * synchronisation peuvent passer plusieurs fois sans doublon).
 */
final class Donations
{
    public const FILE = STORAGE_PATH . '/dons/dons.json';
    private const STATS = STORAGE_PATH . '/dons/stats.json';
    private const EVENTS = STORAGE_PATH . '/dons/events.json';
    public const RECEIPTS = STORAGE_PATH . '/dons/recus';

    public const STATUS = ['pending' => 'En attente', 'paid' => 'Payé', 'active' => 'Mensuel actif', 'canceled' => 'Arrêté', 'failed' => 'Échec', 'refunded' => 'Remboursé', 'abandoned' => 'Abandonné'];
    public const PROVIDERS = ['stripe' => 'Carte bancaire', 'paypal' => 'PayPal', 'manuel' => 'Chèque, virement ou espèces'];

    // ------------------------------------------------------------------ configuration

    /** Moyens de paiement réellement utilisables (clés présentes et cohérentes avec le mode). */
    public static function methods(): array
    {
        if (!Settings::get('donations.enabled', false)) {
            return [];
        }
        $m = [];
        if (Payments::stripeReady()) {
            $m['stripe'] = t('Carte bancaire');
        }
        if (Payments::paypalReady()) {
            $m['paypal'] = 'PayPal';
        }
        return $m;
    }

    public static function testMode(): bool
    {
        return Settings::get('donations.mode', 'test') !== 'live';
    }

    /** Contenus de la page tels que la maquette les propose (modifiables dans le back-office). */
    public static function defaultContent(): array
    {
        return [
            'title' => 'Sauvons|100 ans|d’archives.',
            'lead' => 'Affiches qui jaunissent, photos qui pâlissent, programmes qui s’effritent : votre don finance leur numérisation et leur mise en ligne.',
            'tiers' => [
                ['amount' => 10, 'impact' => 'Numériser une affiche ou un programme', 'impact_en' => 'Digitise a poster or a matchday programme'],
                ['amount' => 30, 'impact' => 'Restaurer et scanner une photo d’équipe', 'impact_en' => 'Restore and scan a team photo'],
                ['amount' => 50, 'impact' => 'Mettre en ligne une fiche match complète', 'impact_en' => 'Publish a complete match record'],
                ['amount' => 100, 'impact' => 'Sauvegarder une saison entière d’archives', 'impact_en' => 'Preserve a whole season of archives'],
            ],
            'default_tier' => 1,
            'steps' => [
                ['title' => 'On collecte', 'title_en' => 'We collect', 'text' => 'Supporters, anciens joueurs et familles nous confient leurs documents.', 'text_en' => 'Supporters, former players and families entrust us with their documents.', 'image' => ''],
                ['title' => 'On numérise', 'title_en' => 'We digitise', 'text' => 'Scan haute définition, restauration et légendage de chaque pièce.', 'text_en' => 'High-definition scanning, restoration and captioning of every item.', 'image' => ''],
                ['title' => 'On partage', 'title_en' => 'We share', 'text' => 'Tout est mis en ligne gratuitement, pour tous les Lionceaux.', 'text_en' => 'Everything is published online for free, for every Lionceau.', 'image' => ''],
            ],
            'wall_text' => 'Votre nom ici, si vous le souhaitez.',
        ];
    }

    /** Contenus de la page dans la langue courante (champ « _en » s'il est rempli, sinon dictionnaire). */
    public static function content(): array
    {
        $c = Collections::get('dons', []) + self::defaultContent();
        $en = I18n::lang() === 'en';
        $pick = function (array $row, string $k) use ($en): string {
            $fr = (string) ($row[$k] ?? '');
            if (!$en) {
                return $fr;
            }
            $v = (string) ($row[$k . '_en'] ?? '');
            return $v !== '' ? $v : t($fr);
        };
        return [
            'title' => $pick($c, 'title'),
            'lead' => $pick($c, 'lead'),
            'tiers' => array_values(array_map(fn ($t) => ['amount' => max(1, (int) $t['amount']), 'impact' => $pick($t, 'impact')], array_filter($c['tiers'] ?: self::defaultContent()['tiers'], fn ($t) => (int) ($t['amount'] ?? 0) > 0))),
            'default_tier' => (int) ($c['default_tier'] ?? 1),
            'steps' => array_map(fn ($s) => ['title' => $pick($s, 'title'), 'text' => $pick($s, 'text'), 'image' => $s['image'] ?? ''], $c['steps'] ?: self::defaultContent()['steps']),
            'wall_text' => $pick($c, 'wall_text'),
        ];
    }

    // ------------------------------------------------------------------ pages

    public static function page(Request $req): Response
    {
        $methods = self::methods();
        $content = self::content();
        $flash = Session::pull('flash_don');
        if ($req->str('annule') === '1' && !$flash) {
            $flash = ['type' => 'info', 'msg' => t('Paiement annulé : aucun montant n’a été prélevé. Vous pouvez réessayer quand vous le souhaitez.')];
        }
        return Pages::render('dons/page', [
            'dc' => $content,
            'gauge' => self::gauge(),
            'wall' => self::wall(),
            'methods' => $methods,
            'test' => self::testMode(),
            'receipts' => (bool) Settings::get('donations.tax_receipts', false),
            'min' => max(1, (int) Settings::get('donations.min_amount', 2)),
            'max' => max(10, (int) Settings::get('donations.max_amount', 5000)),
            'flash' => $flash,
            'old' => Session::pull('old_don', []),
        ], [
            'title' => t('Faire un don au musée'),
            'description' => t('Soutenez Sochaux Rétro : votre don finance la numérisation et la mise en ligne des archives du FC Sochaux-Montbéliard avant ses 100 ans.'),
            'styles' => ['css/community.css', 'css/don.css'],
            'scripts' => ['js/don.js'],
        ]);
    }

    /** Envoi du formulaire sans JavaScript : redirection vers le prestataire. */
    public static function start(Request $req): Response
    {
        $r = self::create($req->post, $req);
        if (isset($r['url'])) {
            return Response::redirect($r['url'], 303);
        }
        Session::set('flash_don', ['type' => 'error', 'msg' => $r['error']]);
        $old = array_intersect_key($req->post, array_flip(['frequency', 'amount', 'custom', 'first', 'last', 'email', 'address', 'zip', 'city', 'country', 'wall', 'wall_name', 'receipt', 'method']));
        Session::set('old_don', $old);
        return Response::redirect(url('/faire-un-don/') . '#don', 303);
    }

    /** API : création de la session de paiement, renvoie l'adresse du paiement sécurisé. */
    public static function checkout(Request $req): Response
    {
        $r = self::create($req->json() ?: $req->post, $req);
        return isset($r['url']) ? Response::json(['ok' => true, 'url' => $r['url']]) : Response::json(['ok' => false, 'error' => $r['error']], $r['status'] ?? 422);
    }

    /** @return array{url?:string,error?:string,status?:int} */
    private static function create(array $in, Request $req): array
    {
        $methods = self::methods();
        if (!$methods) {
            return ['error' => t('Les dons en ligne ne sont pas encore ouverts.'), 'status' => 503];
        }
        if (!Session::checkCsrf((string) ($in['_csrf'] ?? ''))) {
            return ['error' => t('Votre session a expiré : rechargez la page puis réessayez.'), 'status' => 419];
        }
        if (trim((string) ($in['website'] ?? '')) !== '') {
            return ['error' => t('Envoi refusé.'), 'status' => 400];
        }
        $ts = (int) ($in['_ts'] ?? 0);
        if ($ts > 0 && time() - $ts < 2) {
            return ['error' => t('Merci de prendre le temps de remplir le formulaire.')];
        }
        if (!RateLimiter::hit('don', $req->ip(), 12, 3600)) {
            return ['error' => t('Trop de tentatives depuis votre connexion : réessayez dans une heure.'), 'status' => 429];
        }
        $s = fn (string $k, int $max = 120) => trim(preg_replace('/\s+/u', ' ', strip_tags(mb_substr((string) ($in[$k] ?? ''), 0, $max))));
        $month = ($in['frequency'] ?? '') === 'month';
        $amount = (int) preg_replace('/\D/', '', (string) ($in['custom'] ?? '')) ?: (int) ($in['amount'] ?? 0);
        $min = max(1, (int) Settings::get('donations.min_amount', 2));
        $max = max(10, (int) Settings::get('donations.max_amount', 5000));
        $method = (string) ($in['method'] ?? '');
        if (!isset($methods[$method])) {
            $method = (string) array_key_first($methods);
        }
        $receipts = (bool) Settings::get('donations.tax_receipts', false);
        $d = [
            'first' => $s('first', 60),
            'last' => $s('last', 80),
            'email' => mb_strtolower($s('email', 160)),
            'address' => $s('address', 160),
            'zip' => $s('zip', 12),
            'city' => $s('city', 80),
            'country' => $s('country', 60) ?: 'France',
        ];
        $wantReceipt = $receipts && !empty($in['receipt']);
        $wallName = self::cleanWallName($s('wall_name', 40));
        $err = match (true) {
            $amount < $min => t('Le montant minimum est de {n} €.', ['n' => $min]),
            $amount > $max => t('Au-delà de {n} € en ligne, contactez-nous : nous vous proposerons un chèque ou un virement.', ['n' => number_format($max, 0, ',', ' ')]),
            $d['first'] === '' => t('Merci d’indiquer votre prénom.'),
            !filter_var($d['email'], FILTER_VALIDATE_EMAIL) => t('Adresse e-mail invalide.'),
            $wantReceipt && ($d['last'] === '' || $d['address'] === '' || $d['zip'] === '' || $d['city'] === '') => t('Pour recevoir un reçu fiscal, indiquez vos nom et adresse complète.'),
            default => null,
        };
        if ($err) {
            return ['error' => $err];
        }
        $id = 'D' . date('ymd') . '-' . bin2hex(random_bytes(3));
        $cents = $amount * 100;
        $don = [
            'id' => $id,
            'created' => date('c'),
            'updated' => date('c'),
            'mode' => self::testMode() ? 'test' : 'live',
            'provider' => $method,
            'frequency' => $month ? 'month' : 'once',
            'amount' => $cents,
            'currency' => 'eur',
            'status' => 'pending',
            'donor' => $d,
            'wall' => !empty($in['wall']) && $wallName !== '',
            'wall_name' => $wallName,
            'wall_hidden' => false,
            'receipt' => $wantReceipt,
            'lang' => I18n::lang(),
            'manage' => bin2hex(random_bytes(16)),
            'ext' => [],
            'payments' => [],
            'ip' => hash('sha256', $req->ip() . date('Y-m-d') . (Settings::get('general.salt', '') ?: 'sr')),
        ];
        self::put($don);
        // La page de retour n'affiche le détail qu'au navigateur qui a créé le don.
        Session::set('dons', array_slice(array_merge((array) Session::get('dons', []), [$id]), -10));
        $base = base_url();
        $back = $base . url('/faire-un-don/') . '?annule=1';
        $ok = $base . url('/faire-un-don/merci/') . '?don=' . $id;
        $org = (string) Settings::get('donations.org_name', 'Sochaux Rétro');
        $label = $month ? t('Don mensuel à {org}', ['org' => $org]) : t('Don à {org}', ['org' => $org]);
        try {
            if ($method === 'stripe') {
                $p = [
                    'mode' => $month ? 'subscription' : 'payment',
                    'line_items' => [['quantity' => 1, 'price_data' => ['currency' => 'eur', 'unit_amount' => $cents, 'product_data' => ['name' => $label]] + ($month ? ['recurring' => ['interval' => 'month']] : [])]],
                    'customer_email' => $d['email'],
                    'client_reference_id' => $id,
                    'metadata' => ['don' => $id],
                    'success_url' => $ok . '&session_id={CHECKOUT_SESSION_ID}',
                    'cancel_url' => $back,
                    'locale' => I18n::lang() === 'en' ? 'en' : 'fr',
                ];
                if ($month) {
                    $p['subscription_data'] = ['metadata' => ['don' => $id], 'description' => $label];
                } else {
                    $p['submit_type'] = 'donate';
                    $p['payment_intent_data'] = ['metadata' => ['don' => $id], 'description' => $label];
                }
                $session = Payments::stripe('POST', 'checkout/sessions', $p);
                self::update($id, fn ($x) => array_replace_recursive($x, ['ext' => ['stripe_session' => $session['id']]]));
                return ['url' => (string) $session['url']];
            }
            $locale = I18n::lang() === 'en' ? 'en-GB' : 'fr-FR';
            $value = number_format($amount, 2, '.', '');
            if ($month) {
                $r = Payments::paypal('POST', '/v1/billing/subscriptions', [
                    'plan_id' => Payments::paypalPlan($cents),
                    'custom_id' => $id,
                    'subscriber' => ['name' => array_filter(['given_name' => $d['first'], 'surname' => $d['last']]), 'email_address' => $d['email']],
                    'application_context' => ['brand_name' => mb_substr($org, 0, 127), 'locale' => $locale, 'shipping_preference' => 'NO_SHIPPING', 'user_action' => 'SUBSCRIBE_NOW', 'return_url' => $ok . '&pp=sub', 'cancel_url' => $back],
                ], ['PayPal-Request-Id: ' . $id]);
                self::update($id, fn ($x) => array_replace_recursive($x, ['ext' => ['paypal_sub' => $r['id'] ?? null]]));
            } else {
                $r = Payments::paypal('POST', '/v2/checkout/orders', [
                    'intent' => 'CAPTURE',
                    'purchase_units' => [['reference_id' => $id, 'custom_id' => $id, 'description' => $label, 'amount' => ['currency_code' => 'EUR', 'value' => $value]]],
                    'payment_source' => ['paypal' => ['experience_context' => ['brand_name' => mb_substr($org, 0, 127), 'locale' => $locale, 'shipping_preference' => 'NO_SHIPPING', 'user_action' => 'PAY_NOW', 'return_url' => $ok . '&pp=order', 'cancel_url' => $back]]],
                ], ['PayPal-Request-Id: ' . $id]);
                self::update($id, fn ($x) => array_replace_recursive($x, ['ext' => ['paypal_order' => $r['id'] ?? null]]));
            }
            $link = Payments::paypalApproveLink($r);
            if (!$link) {
                throw new \RuntimeException('PayPal : lien de paiement absent');
            }
            return ['url' => $link];
        } catch (\Throwable $e) {
            error_log('[dons] ' . $e->getMessage());
            self::update($id, fn ($x) => ['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 300)] + $x);
            return ['error' => t('Le service de paiement ne répond pas pour le moment. Réessayez dans quelques minutes ou choisissez un autre moyen de paiement.'), 'status' => 502];
        }
    }

    /** Retour du prestataire : on relit le paiement chez lui avant de remercier. */
    public static function thanks(Request $req): Response
    {
        $id = $req->str('don');
        $don = preg_match('/^D\d{6}-[a-f0-9]{6}$/', $id) ? self::get($id) : null;
        if (!$don) {
            return Pages::render('dons/merci', ['don' => null, 'state' => 'unknown'], ['title' => t('Merci pour votre soutien'), 'noindex' => true, 'styles' => ['css/community.css', 'css/don.css']]);
        }
        try {
            if ($don['provider'] === 'stripe' && ($sid = $req->str('session_id')) !== '' && $sid === ($don['ext']['stripe_session'] ?? '')) {
                self::syncStripeSession(Payments::stripe('GET', 'checkout/sessions/' . rawurlencode($sid)));
            } elseif ($don['provider'] === 'paypal' && $req->str('pp') === 'order' && $req->str('token') === ($don['ext']['paypal_order'] ?? '-')) {
                self::capturePaypalOrder($don);
            } elseif ($don['provider'] === 'paypal' && $req->str('pp') === 'sub' && $req->str('subscription_id') === ($don['ext']['paypal_sub'] ?? '-')) {
                self::syncPaypalSubscription($don);
            }
        } catch (\Throwable $e) {
            error_log('[dons] retour ' . $id . ' : ' . $e->getMessage());
        }
        $don = self::get($id);
        if (!in_array($id, (array) Session::get('dons', []), true)) {
            return Pages::render('dons/merci', ['don' => null, 'state' => 'unknown'], ['title' => t('Merci pour votre soutien'), 'noindex' => true, 'styles' => ['css/community.css', 'css/don.css']]);
        }
        $state = match (true) {
            in_array($don['status'], ['paid', 'active'], true) => 'ok',
            $don['status'] === 'pending' => 'pending',
            default => 'failed',
        };
        return Pages::render('dons/merci', [
            'don' => $don,
            'state' => $state,
            'manage' => base_url() . url('/faire-un-don/gerer/' . $don['manage'] . '/'),
        ], ['title' => t('Merci pour votre soutien'), 'noindex' => true, 'styles' => ['css/community.css', 'css/don.css']]);
    }

    /** API : capture d'une commande PayPal (si le retour a été interrompu). */
    public static function paypalCapture(Request $req): Response
    {
        if (!RateLimiter::hit('don-capture', $req->ip(), 30, 3600)) {
            return Response::json(['ok' => false], 429);
        }
        $id = $req->str('don');
        $don = preg_match('/^D\d{6}-[a-f0-9]{6}$/', $id) ? self::get($id) : null;
        if (!$don || $don['provider'] !== 'paypal' || $req->str('order') !== ($don['ext']['paypal_order'] ?? '-')) {
            return Response::json(['ok' => false], 404);
        }
        self::capturePaypalOrder($don);
        $don = self::get($id);
        return Response::json(['ok' => $don['status'] === 'paid', 'status' => $don['status']]);
    }

    // ------------------------------------------------------------------ Stripe

    private static function syncStripeSession(array $s): void
    {
        $id = (string) ($s['metadata']['don'] ?? $s['client_reference_id'] ?? '');
        $don = $id !== '' ? self::get($id) : null;
        if (!$don || ($s['id'] ?? '') !== ($don['ext']['stripe_session'] ?? '')) {
            return;
        }
        if (!in_array($s['payment_status'] ?? '', ['paid', 'no_payment_required'], true)) {
            if (($s['status'] ?? '') === 'expired') {
                self::update($id, fn ($x) => $x['status'] === 'pending' ? ['status' => 'abandoned'] + $x : $x);
            }
            return;
        }
        $ext = array_filter(['stripe_customer' => $s['customer'] ?? null, 'stripe_pi' => $s['payment_intent'] ?? null, 'stripe_sub' => $s['subscription'] ?? null]);
        self::update($id, fn ($x) => array_replace_recursive($x, ['ext' => $ext]));
        $amount = (int) ($s['amount_total'] ?? $don['amount']);
        if (($s['mode'] ?? '') === 'subscription') {
            self::update($id, fn ($x) => $x['status'] === 'pending' ? ['status' => 'active', 'started' => date('c')] + $x : $x);
            if (!empty($s['invoice'])) {
                self::recordPayment($id, (string) $s['invoice'], $amount);
            }
        } elseif (!empty($s['payment_intent'])) {
            self::recordPayment($id, (string) $s['payment_intent'], $amount);
        }
    }

    public static function stripeWebhook(Request $req): Response
    {
        if (!Payments::stripeVerify($req->body, (string) $req->header('Stripe-Signature'))) {
            return Response::json(['error' => 'signature'], 400);
        }
        $ev = json_decode($req->body, true) ?: [];
        if (!self::firstTime('stripe:' . ($ev['id'] ?? ''))) {
            return Response::json(['ok' => true, 'duplicate' => true]);
        }
        $o = $ev['data']['object'] ?? [];
        switch ($ev['type'] ?? '') {
            case 'checkout.session.completed':
            case 'checkout.session.async_payment_succeeded':
            case 'checkout.session.expired':
                self::syncStripeSession($o);
                break;
            case 'invoice.paid':
                $subId = (string) ($o['subscription'] ?? $o['parent']['subscription_details']['subscription'] ?? '');
                $don = $subId !== '' ? self::findBy('stripe_sub', $subId) : null;
                $don ??= self::get((string) ($o['subscription_details']['metadata']['don'] ?? $o['parent']['subscription_details']['metadata']['don'] ?? ''));
                if ($don && (int) ($o['amount_paid'] ?? 0) > 0) {
                    if ($subId !== '' && empty($don['ext']['stripe_sub'])) {
                        self::update($don['id'], fn ($x) => array_replace_recursive($x, ['ext' => ['stripe_sub' => $subId]]));
                    }
                    self::recordPayment($don['id'], (string) $o['id'], (int) $o['amount_paid'], isset($o['status_transitions']['paid_at']) ? date('c', (int) $o['status_transitions']['paid_at']) : null);
                }
                break;
            case 'customer.subscription.deleted':
                $don = self::findBy('stripe_sub', (string) ($o['id'] ?? '')) ?? self::get((string) ($o['metadata']['don'] ?? ''));
                if ($don) {
                    self::update($don['id'], fn ($x) => ['status' => 'canceled', 'ended' => date('c')] + $x);
                }
                break;
            case 'charge.refunded':
                $ref = (string) ($o['invoice'] ?? '') ?: (string) ($o['payment_intent'] ?? '');
                if ($ref !== '' && ($o['refunded'] ?? false)) {
                    self::markRefunded($ref);
                }
                break;
        }
        return Response::json(['ok' => true]);
    }

    // ------------------------------------------------------------------ PayPal

    private static function capturePaypalOrder(array $don): void
    {
        $order = (string) ($don['ext']['paypal_order'] ?? '');
        if ($order === '' || $don['status'] !== 'pending') {
            return;
        }
        try {
            $r = Payments::paypal('POST', '/v2/checkout/orders/' . rawurlencode($order) . '/capture', null, ['PayPal-Request-Id: ' . $don['id'] . '-capture', 'Prefer: return=representation']);
        } catch (\Throwable $e) {
            // Déjà capturée ou refusée : on relit la commande.
            $r = Payments::paypal('GET', '/v2/checkout/orders/' . rawurlencode($order));
        }
        $unit = $r['purchase_units'][0] ?? [];
        if (($unit['custom_id'] ?? $unit['reference_id'] ?? $don['id']) !== $don['id']) {
            return;
        }
        foreach ($unit['payments']['captures'] ?? [] as $c) {
            if (($c['status'] ?? '') === 'COMPLETED') {
                self::recordPayment($don['id'], (string) $c['id'], (int) round(((float) $c['amount']['value']) * 100), $c['create_time'] ?? null);
            }
        }
    }

    /** Relit un abonnement PayPal et ses prélèvements (retour, webhook, tâche planifiée). */
    public static function syncPaypalSubscription(array $don): void
    {
        $sub = (string) ($don['ext']['paypal_sub'] ?? '');
        if ($sub === '') {
            return;
        }
        $s = Payments::paypal('GET', '/v1/billing/subscriptions/' . rawurlencode($sub));
        if (($s['custom_id'] ?? $don['id']) !== $don['id']) {
            return;
        }
        $status = (string) ($s['status'] ?? '');
        self::update($don['id'], function ($x) use ($status) {
            if (in_array($status, ['ACTIVE', 'APPROVED'], true) && $x['status'] === 'pending') {
                return ['status' => 'active', 'started' => date('c')] + $x;
            }
            if (in_array($status, ['CANCELLED', 'EXPIRED'], true) && $x['status'] !== 'canceled') {
                return ['status' => 'canceled', 'ended' => date('c')] + $x;
            }
            return $x;
        });
        $from = gmdate('Y-m-d\TH:i:s\Z', strtotime($don['created']) - 86400);
        $to = gmdate('Y-m-d\TH:i:s\Z', time() + 3600);
        try {
            $tx = Payments::paypal('GET', '/v1/billing/subscriptions/' . rawurlencode($sub) . '/transactions?start_time=' . $from . '&end_time=' . $to);
        } catch (\Throwable) {
            return; // l'historique n'est pas encore disponible juste après l'accord
        }
        foreach ($tx['transactions'] ?? [] as $t) {
            if (($t['status'] ?? '') === 'COMPLETED') {
                self::recordPayment($don['id'], (string) $t['id'], (int) round(((float) ($t['amount_with_breakdown']['gross_amount']['value'] ?? 0)) * 100), $t['time'] ?? null);
            } elseif (in_array($t['status'] ?? '', ['REFUNDED', 'PARTIALLY_REFUNDED'], true)) {
                self::markRefunded((string) $t['id']);
            }
        }
    }

    public static function paypalWebhook(Request $req): Response
    {
        $ev = json_decode($req->body, true) ?: [];
        $headers = [];
        foreach (['PAYPAL-AUTH-ALGO', 'PAYPAL-CERT-URL', 'PAYPAL-TRANSMISSION-ID', 'PAYPAL-TRANSMISSION-SIG', 'PAYPAL-TRANSMISSION-TIME'] as $h) {
            $headers[$h] = (string) $req->header($h);
        }
        if (!$ev || !Payments::paypalReady() || !Payments::paypalVerify($headers, $ev)) {
            return Response::json(['error' => 'signature'], 400);
        }
        if (!self::firstTime('paypal:' . ($ev['id'] ?? ''))) {
            return Response::json(['ok' => true, 'duplicate' => true]);
        }
        $o = $ev['resource'] ?? [];
        $type = (string) ($ev['event_type'] ?? '');
        switch ($type) {
            case 'PAYMENT.CAPTURE.COMPLETED':
                $don = self::get((string) ($o['custom_id'] ?? ''));
                if ($don && $don['provider'] === 'paypal') {
                    self::recordPayment($don['id'], (string) $o['id'], (int) round(((float) ($o['amount']['value'] ?? 0)) * 100), $o['create_time'] ?? null);
                }
                break;
            case 'PAYMENT.CAPTURE.REFUNDED':
                foreach ($o['links'] ?? [] as $l) {
                    if (($l['rel'] ?? '') === 'up' && preg_match('#/captures/([^/]+)$#', (string) $l['href'], $m)) {
                        self::markRefunded($m[1]);
                    }
                }
                break;
            case 'PAYMENT.SALE.COMPLETED':
                $don = self::findBy('paypal_sub', (string) ($o['billing_agreement_id'] ?? '')) ?? self::get((string) ($o['custom'] ?? ''));
                if ($don) {
                    self::recordPayment($don['id'], (string) $o['id'], (int) round(((float) ($o['amount']['total'] ?? 0)) * 100), $o['create_time'] ?? null);
                }
                break;
            case 'PAYMENT.SALE.REFUNDED':
                self::markRefunded((string) ($o['sale_id'] ?? ''));
                break;
            case 'BILLING.SUBSCRIPTION.ACTIVATED':
            case 'BILLING.SUBSCRIPTION.CANCELLED':
            case 'BILLING.SUBSCRIPTION.EXPIRED':
            case 'BILLING.SUBSCRIPTION.SUSPENDED':
                $don = self::findBy('paypal_sub', (string) ($o['id'] ?? '')) ?? self::get((string) ($o['custom_id'] ?? ''));
                if ($don) {
                    $new = $type === 'BILLING.SUBSCRIPTION.ACTIVATED' ? 'active' : 'canceled';
                    self::update($don['id'], fn ($x) => in_array($x['status'], ['pending', 'active'], true) ? ['status' => $new] + ($new === 'canceled' ? ['ended' => date('c')] : ['started' => $x['started'] ?? date('c')]) + $x : $x);
                }
                break;
        }
        return Response::json(['ok' => true]);
    }

    // ------------------------------------------------------------------ don mensuel : gestion par le donateur

    public static function manage(Request $req, string $token): Response
    {
        $don = preg_match('/^[a-f0-9]{32}$/', $token) ? self::findBy('manage', $token) : null;
        if (!$don) {
            return Pages::render('community/message', ['title' => t('Lien invalide'), 'text' => t('Ce lien de gestion de don n’est pas valable.')], ['title' => t('Lien invalide'), 'noindex' => true, 'styles' => ['css/community.css']]);
        }
        $flash = null;
        if ($req->method === 'POST') {
            if (!Session::checkCsrf((string) ($req->post['_csrf'] ?? '')) || !RateLimiter::hit('don-manage', $req->ip(), 20, 3600)) {
                $flash = ['type' => 'error', 'msg' => t('Votre session a expiré : merci de réessayer.')];
            } elseif (($req->post['action'] ?? '') === 'stop' && $don['frequency'] === 'month' && $don['status'] === 'active') {
                $flash = self::cancelSubscription($don, 'donateur')
                    ? ['type' => 'ok', 'msg' => t('Votre don mensuel est arrêté. Aucun nouveau prélèvement ne sera effectué. Merci pour votre fidélité !')]
                    : ['type' => 'error', 'msg' => t('L’arrêt n’a pas pu être confirmé par le prestataire de paiement. Réessayez ou écrivez-nous.')];
            } elseif (($req->post['action'] ?? '') === 'wall') {
                $name = self::cleanWallName(trim(strip_tags(mb_substr((string) ($req->post['wall_name'] ?? ''), 0, 40))));
                $show = !empty($req->post['wall']) && $name !== '';
                self::update($don['id'], fn ($x) => ['wall' => $show, 'wall_name' => $name] + $x);
                self::rebuildStats();
                $flash = ['type' => 'ok', 'msg' => t('Vos préférences pour le mur des donateurs sont enregistrées.')];
            }
            $don = self::get($don['id']);
        }
        return Pages::render('dons/gerer', ['don' => $don, 'flash' => $flash, 'token' => $token], ['title' => t('Mon don'), 'noindex' => true, 'styles' => ['css/community.css', 'css/don.css']]);
    }

    /** Téléchargement d'un reçu fiscal depuis le lien personnel du donateur. */
    public static function receiptDownload(Request $req, string $token, string $num): Response
    {
        $don = preg_match('/^[a-f0-9]{32}$/', $token) ? self::findBy('manage', $token) : null;
        if (!$don || !preg_match('/^RF-\d{4}-\d{4,6}$/', $num) || !in_array($num, self::receiptNumbers($don), true)) {
            return Response::notFound();
        }
        $file = self::RECEIPTS . '/' . substr($num, 3, 4) . "/$num.pdf";
        if (!is_file($file)) {
            return Response::notFound();
        }
        $res = new Response((string) file_get_contents($file), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="recu-fiscal-' . $num . '.pdf"', 'X-Robots-Tag' => 'noindex']);
        return $res;
    }

    /** Arrête un abonnement chez le prestataire puis localement. */
    public static function cancelSubscription(array $don, string $by): bool
    {
        try {
            if ($don['provider'] === 'stripe' && !empty($don['ext']['stripe_sub'])) {
                Payments::stripe('DELETE', 'subscriptions/' . rawurlencode($don['ext']['stripe_sub']));
            } elseif ($don['provider'] === 'paypal' && !empty($don['ext']['paypal_sub'])) {
                Payments::paypal('POST', '/v1/billing/subscriptions/' . rawurlencode($don['ext']['paypal_sub']) . '/cancel', ['reason' => 'Arrêt demandé par le ' . $by]);
            }
        } catch (\Throwable $e) {
            error_log('[dons] arrêt ' . $don['id'] . ' : ' . $e->getMessage());
            return false;
        }
        self::update($don['id'], fn ($x) => ['status' => 'canceled', 'ended' => date('c'), 'ended_by' => $by] + $x);
        $first = $don['donor']['first'] ?? '';
        Mailer::send((string) $don['donor']['email'], t('Votre don mensuel est arrêté'), '<p>' . e(t('Bonjour {prenom},', ['prenom' => $first])) . '</p><p>' . e(t('Votre don mensuel de {montant} à Sochaux Rétro est arrêté : aucun nouveau prélèvement ne sera effectué.', ['montant' => self::money($don['amount'])])) . '</p><p>' . e(t('Merci du fond du cœur pour votre soutien.')) . '</p>');
        return true;
    }

    // ------------------------------------------------------------------ jauge et mur

    public static function gaugeJson(): Response
    {
        $g = self::gauge();
        $res = Response::json($g);
        $res->headers['Cache-Control'] = 'public, max-age=60';
        return $res;
    }

    /** @return array{raised:int,goal:int,donors:int,pct:float,label:string} montants en euros */
    public static function gauge(): array
    {
        $st = JsonStore::read(self::STATS, null);
        $mode = self::testMode() ? 'test' : 'live';
        if (!is_array($st) || ($st['mode'] ?? '') !== $mode || ($st['start'] ?? '') !== (string) Settings::get('donations.goal_start', '')) {
            $st = self::rebuildStats();
        }
        $raised = (int) floor(($st['raised'] ?? 0) / 100) + max(0, (int) Settings::get('donations.offset_amount', 0));
        $donors = (int) ($st['donors'] ?? 0) + max(0, (int) Settings::get('donations.offset_donors', 0));
        $goal = max(0, (int) Settings::get('donations.goal_amount', 10000));
        return [
            'raised' => $raised,
            'goal' => $goal,
            'donors' => $donors,
            'pct' => $goal > 0 ? min(100, round($raised / $goal * 100, 1)) : 0,
            'label' => (string) Settings::get('donations.goal_label', 'Objectif centenaire 2028'),
        ];
    }

    /** Mur des donateurs : noms choisis (les plus récents d'abord) et nombre de donateurs discrets. */
    public static function wall(int $max = 80): array
    {
        $st = JsonStore::read(self::STATS, null);
        if (!is_array($st) || !isset($st['wall'])) {
            $st = self::rebuildStats();
        }
        return ['names' => array_slice($st['wall'] ?? [], 0, $max), 'anonymous' => (int) ($st['anonymous'] ?? 0)];
    }

    /** Recalcule la jauge et le mur (après chaque paiement, remboursement ou modération). */
    public static function rebuildStats(): array
    {
        $mode = self::testMode() ? 'test' : 'live';
        $start = (string) Settings::get('donations.goal_start', '');
        $from = $start !== '' ? strtotime($start) : 0;
        $raised = 0;
        $donors = [];
        $wall = [];
        $anon = [];
        $all = self::all();
        uasort($all, fn ($a, $b) => strcmp((string) ($b['last_paid'] ?? $b['created']), (string) ($a['last_paid'] ?? $a['created'])));
        foreach ($all as $d) {
            if ($d['mode'] !== $mode && $d['provider'] !== 'manuel') {
                continue;
            }
            $paid = false;
            foreach ($d['payments'] ?? [] as $p) {
                if (($p['status'] ?? '') === 'paid') {
                    $paid = true;
                    if (strtotime((string) $p['at']) >= $from) {
                        $raised += (int) $p['amount'];
                    }
                }
            }
            if (!$paid) {
                continue;
            }
            $who = hash('sha256', mb_strtolower((string) ($d['donor']['email'] ?? '')) ?: $d['id']);
            $donors[$who] = true;
            if (!empty($d['wall']) && empty($d['wall_hidden']) && ($d['wall_name'] ?? '') !== '') {
                $wall[$who] ??= $d['wall_name'];
            } else {
                $anon[$who] = true;
            }
        }
        $anon = array_diff_key($anon, $wall);
        $st = ['mode' => $mode, 'start' => $start, 'raised' => $raised, 'donors' => count($donors), 'wall' => array_values($wall), 'anonymous' => count($anon), 'at' => date('c')];
        JsonStore::write(self::STATS, $st);
        return $st;
    }

    // ------------------------------------------------------------------ données

    public static function all(): array
    {
        return JsonStore::read(self::FILE, []) ?: [];
    }

    public static function get(string $id): ?array
    {
        return $id !== '' ? (self::all()[$id] ?? null) : null;
    }

    private static function findBy(string $key, string $value): ?array
    {
        if ($value === '') {
            return null;
        }
        foreach (self::all() as $d) {
            if (($key === 'manage' ? ($d['manage'] ?? '') : ($d['ext'][$key] ?? '')) === $value) {
                return $d;
            }
        }
        return null;
    }

    public static function put(array $don): void
    {
        JsonStore::update(self::FILE, function ($all) use ($don) {
            $all = $all ?: [];
            $all[$don['id']] = $don;
            return $all;
        }, []);
    }

    public static function update(string $id, callable $fn): ?array
    {
        $out = null;
        JsonStore::update(self::FILE, function ($all) use ($id, $fn, &$out) {
            $all = $all ?: [];
            if (isset($all[$id])) {
                $all[$id] = $fn($all[$id]);
                $all[$id]['updated'] = date('c');
                $out = $all[$id];
            }
            return $all;
        }, []);
        return $out;
    }

    /**
     * Enregistre un paiement (idempotent sur la référence du prestataire), met à jour
     * la jauge, puis remercie le donateur au premier paiement et émet le reçu fiscal.
     */
    public static function recordPayment(string $id, string $ref, int $cents, ?string $at = null): void
    {
        if ($ref === '' || $cents <= 0) {
            return;
        }
        $isNew = false;
        $first = false;
        $don = self::update($id, function ($x) use ($ref, $cents, $at, &$isNew, &$first) {
            foreach ($x['payments'] ?? [] as $p) {
                if ($p['ref'] === $ref) {
                    return $x;
                }
            }
            $isNew = true;
            $first = !array_filter($x['payments'] ?? [], fn ($p) => ($p['status'] ?? '') === 'paid');
            $x['payments'][] = ['ref' => $ref, 'amount' => $cents, 'at' => $at ? date('c', strtotime($at)) : date('c'), 'status' => 'paid'];
            $x['last_paid'] = date('c');
            if ($x['frequency'] === 'once') {
                $x['status'] = 'paid';
            } elseif (in_array($x['status'], ['pending', 'failed'], true)) {
                $x['status'] = 'active';
                $x['started'] ??= date('c');
            }
            return $x;
        });
        if (!$don || !$isNew) {
            return;
        }
        self::rebuildStats();
        $receipt = null;
        if ($don['receipt'] && Settings::get('donations.tax_receipts', false) && $don['frequency'] === 'once') {
            $receipt = self::issueReceipt($don['id'], [$ref]);
        }
        if ($first) {
            self::thankYou(self::get($id), $receipt);
            self::notifyTeam(self::get($id));
        }
    }

    private static function markRefunded(string $ref): void
    {
        if ($ref === '') {
            return;
        }
        foreach (self::all() as $d) {
            foreach ($d['payments'] ?? [] as $i => $p) {
                if ($p['ref'] === $ref && $p['status'] !== 'refunded') {
                    self::update($d['id'], function ($x) use ($i) {
                        $x['payments'][$i]['status'] = 'refunded';
                        $x['payments'][$i]['refunded'] = date('c');
                        if ($x['frequency'] === 'once') {
                            $x['status'] = 'refunded';
                        }
                        return $x;
                    });
                    self::rebuildStats();
                    return;
                }
            }
        }
    }

    /** Un événement de webhook n'est traité qu'une fois (on garde les 1 000 derniers). */
    private static function firstTime(string $key): bool
    {
        $new = false;
        JsonStore::update(self::EVENTS, function ($seen) use ($key, &$new) {
            $seen = $seen ?: [];
            if (!isset($seen[$key])) {
                $new = true;
                $seen[$key] = time();
                if (count($seen) > 1000) {
                    asort($seen);
                    $seen = array_slice($seen, -1000, null, true);
                }
            }
            return $seen;
        }, []);
        return $new;
    }

    /** Synchronisation périodique (tâche planifiée) : abonnements actifs et paiements en attente. */
    public static function sync(): array
    {
        $n = ['checked' => 0, 'errors' => 0];
        foreach (self::all() as $d) {
            $age = time() - strtotime($d['created']);
            try {
                if ($d['provider'] === 'paypal' && !empty($d['ext']['paypal_sub']) && in_array($d['status'], ['pending', 'active'], true)) {
                    if ($d['status'] === 'pending' && $age > 3 * 86400) {
                        self::update($d['id'], fn ($x) => ['status' => 'abandoned'] + $x);
                        continue;
                    }
                    self::syncPaypalSubscription($d);
                    $n['checked']++;
                } elseif ($d['provider'] === 'stripe' && $d['status'] === 'active' && !empty($d['ext']['stripe_sub']) && Payments::stripeReady()) {
                    $inv = Payments::stripe('GET', 'invoices', ['subscription' => $d['ext']['stripe_sub'], 'status' => 'paid', 'limit' => 24]);
                    foreach ($inv['data'] ?? [] as $i) {
                        if ((int) ($i['amount_paid'] ?? 0) > 0) {
                            self::recordPayment($d['id'], (string) $i['id'], (int) $i['amount_paid'], isset($i['status_transitions']['paid_at']) ? date('c', (int) $i['status_transitions']['paid_at']) : null);
                        }
                    }
                    $sub = Payments::stripe('GET', 'subscriptions/' . rawurlencode($d['ext']['stripe_sub']));
                    if (in_array($sub['status'] ?? '', ['canceled', 'incomplete_expired'], true)) {
                        self::update($d['id'], fn ($x) => ['status' => 'canceled', 'ended' => date('c')] + $x);
                    }
                    $n['checked']++;
                } elseif ($d['provider'] === 'stripe' && $d['status'] === 'pending' && !empty($d['ext']['stripe_session']) && $age < 2 * 86400 && Payments::stripeReady()) {
                    self::syncStripeSession(Payments::stripe('GET', 'checkout/sessions/' . rawurlencode($d['ext']['stripe_session'])));
                    $n['checked']++;
                } elseif ($d['status'] === 'pending' && $age > 2 * 86400) {
                    self::update($d['id'], fn ($x) => ['status' => 'abandoned'] + $x);
                }
            } catch (\Throwable $e) {
                $n['errors']++;
                error_log('[dons] synchro ' . $d['id'] . ' : ' . $e->getMessage());
            }
        }
        self::rebuildStats();
        return $n;
    }

    // ------------------------------------------------------------------ e-mails

    private static function thankYou(?array $don, ?string $receipt): void
    {
        if (!$don) {
            return;
        }
        $tpl = (string) Settings::get('donations.thanks_email', '');
        if (!str_contains($tpl, '<')) {
            $tpl = '<p>' . implode('</p><p>', array_map('e', preg_split('/\n{2,}/', trim($tpl)))) . '</p>';
            $tpl = str_replace("\n", '<br>', $tpl);
        }
        $html = strtr(safe_html($tpl), [
            '{prenom}' => e($don['donor']['first'] ?? ''),
            '{nom}' => e($don['donor']['last'] ?? ''),
            '{montant}' => e(self::money($don['amount']) . ($don['frequency'] === 'month' ? ' ' . t('par mois') : '')),
        ]);
        $manage = base_url() . url('/faire-un-don/gerer/' . $don['manage'] . '/');
        $html .= '<p style="font-size:15px;color:#3A4A75">' . e($don['frequency'] === 'month' ? t('Pour suivre, modifier votre nom sur le mur des donateurs ou arrêter votre don mensuel à tout moment :') : t('Pour retrouver votre don et vos préférences pour le mur des donateurs :')) . '<br><a href="' . e($manage) . '">' . e(t('Gérer mon don')) . '</a></p>';
        $att = [];
        if ($receipt && is_file($f = self::RECEIPTS . '/' . substr($receipt, 3, 4) . "/$receipt.pdf")) {
            $html .= '<p>' . e(t('Votre reçu fiscal est joint à ce message.')) . '</p>';
            $att[] = ['name' => "recu-fiscal-$receipt.pdf", 'type' => 'application/pdf', 'data' => (string) file_get_contents($f)];
        }
        Mailer::send((string) $don['donor']['email'], t('Merci pour votre don à Sochaux Rétro'), $html, null, $att);
    }

    private static function notifyTeam(?array $don): void
    {
        $to = (string) Settings::get('general.contact_email', '');
        if (!$don || $to === '' || !Settings::get('donations.notify', true)) {
            return;
        }
        $what = self::money($don['amount']) . ($don['frequency'] === 'month' ? ' / mois' : '');
        Mailer::send($to, '[Don] ' . $what . ' · ' . ($don['donor']['first'] ?? '') . ($don['mode'] === 'test' ? ' (TEST)' : ''), '<p>Nouveau don de <b>' . e($what) . '</b> par ' . e(trim(($don['donor']['first'] ?? '') . ' ' . ($don['donor']['last'] ?? ''))) . ' via ' . e(self::PROVIDERS[$don['provider']] ?? $don['provider']) . '.</p><p><a href="' . e(base_url()) . '/admin/dons/' . e($don['id']) . '">Voir dans le back-office</a></p>');
    }

    public static function money(int $cents): string
    {
        return number_format($cents / 100, $cents % 100 ? 2 : 0, ',', "\u{202f}") . "\u{a0}€";
    }

    /** Nom pour le mur : texte simple, sans adresse web ni e-mail. */
    public static function cleanWallName(string $s): string
    {
        $s = trim(preg_replace('/[^\p{L}\p{N}\s.,&\'’\-()!]/u', '', $s));
        if (preg_match('/(https?:|www\.|@|\.(com|fr|net|org|io)\b)/i', $s)) {
            return '';
        }
        return mb_substr($s, 0, 40);
    }

    // ------------------------------------------------------------------ reçus fiscaux

    /** Numéros des reçus émis pour un don. */
    public static function receiptNumbers(array $don): array
    {
        $out = [];
        foreach ($don['payments'] ?? [] as $p) {
            if (!empty($p['receipt'])) {
                $out[] = $p['receipt'];
            }
        }
        return array_values(array_unique(array_merge($out, $don['receipts'] ?? [])));
    }

    /**
     * Émet un reçu fiscal (modèle Cerfa n° 11580) pour des paiements d'un don.
     * Numérotation continue par année : RF-AAAA-0001. Le PDF est conservé et ne change plus.
     */
    public static function issueReceipt(string $id, array $refs, ?int $year = null): ?string
    {
        $don = self::get($id);
        if (!$don) {
            return null;
        }
        $pays = array_values(array_filter($don['payments'] ?? [], fn ($p) => in_array($p['ref'], $refs, true) && $p['status'] === 'paid' && empty($p['receipt'])));
        if (!$pays) {
            return null;
        }
        $year ??= (int) date('Y', strtotime(end($pays)['at']));
        $num = null;
        JsonStore::update(self::RECEIPTS . '/compteur.json', function ($c) use ($year, &$num) {
            $c = $c ?: [];
            $c[$year] = (int) ($c[$year] ?? 0) + 1;
            $num = sprintf('RF-%d-%04d', $year, $c[$year]);
            return $c;
        }, []);
        $total = array_sum(array_column($pays, 'amount'));
        $dates = array_map(fn ($p) => date('d/m/Y', strtotime($p['at'])), $pays);
        $pdf = self::receiptPdf($num, $don, $total, $dates);
        $dir = self::RECEIPTS . "/$year";
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        file_put_contents("$dir/$num.pdf", $pdf, LOCK_EX);
        self::update($id, function ($x) use ($refs, $num) {
            foreach ($x['payments'] as $i => $p) {
                if (in_array($p['ref'], $refs, true) && empty($p['receipt'])) {
                    $x['payments'][$i]['receipt'] = $num;
                }
            }
            return $x;
        });
        return $num;
    }

    /** Reçus annuels des dons mensuels (à lancer en janvier pour l'année écoulée). */
    public static function annualReceipts(int $year): array
    {
        $done = [];
        if (!Settings::get('donations.tax_receipts', false)) {
            return $done;
        }
        foreach (self::all() as $d) {
            if ($d['frequency'] !== 'month' || empty($d['receipt']) || $d['mode'] !== 'live') {
                continue;
            }
            $refs = array_column(array_filter($d['payments'] ?? [], fn ($p) => $p['status'] === 'paid' && empty($p['receipt']) && (int) substr($p['at'], 0, 4) === $year), 'ref');
            if ($refs && ($num = self::issueReceipt($d['id'], $refs, $year))) {
                $done[] = $num;
                $f = self::RECEIPTS . "/$year/$num.pdf";
                Mailer::send((string) $d['donor']['email'], t('Votre reçu fiscal {annee} · Sochaux Rétro', ['annee' => $year]), '<p>' . e(t('Bonjour {prenom},', ['prenom' => $d['donor']['first'] ?? ''])) . '</p><p>' . e(t('Veuillez trouver ci-joint le reçu fiscal de vos dons mensuels de l’année {annee}. Merci pour votre fidélité !', ['annee' => $year])) . '</p>', null, [['name' => "recu-fiscal-$num.pdf", 'type' => 'application/pdf', 'data' => (string) file_get_contents($f)]]);
            }
        }
        return $done;
    }

    private static function receiptPdf(string $num, array $don, int $cents, array $dates): string
    {
        $g = fn (string $k, string $def = '') => (string) Settings::get('donations.' . $k, $def);
        $navy = [0.055, 0.122, 0.302];
        $muted = [0.227, 0.29, 0.459];
        $p = (new Pdf())->addPage();
        $logo = PUBLIC_PATH . '/assets/img/logo-sochaux-retro.png';
        if (is_file($logo) && ($src = @imagecreatefrompng($logo))) {
            $w = imagesx($src);
            $h = imagesy($src);
            $bg = imagecreatetruecolor($w, $h);
            imagefill($bg, 0, 0, imagecolorallocate($bg, 255, 255, 255));
            imagecopy($bg, $src, 0, 0, 0, 0, $w, $h);
            ob_start();
            imagejpeg($bg, null, 88);
            $p->jpeg((string) ob_get_clean(), 42, 36, 58, 58 * $h / max(1, $w));
        }
        $org = $g('org_name', 'Sochaux Rétro');
        $p->text(112, 52, $org, 14, 'B');
        $y = 66;
        foreach (array_filter(array_map('trim', preg_split('/\R|<br\s*\/?>|<\/p>/i', html_entity_decode(strip_tags($g('org_address'), '<br><p>'), ENT_QUOTES, 'UTF-8')))) as $line) {
            $p->text(112, $y, strip_tags($line), 9, 'R', $muted);
            $y += 11;
        }
        $p->textAlign(553, 50, 'Reçu n° ' . $num, 11, 'B', 'R');
        $p->textAlign(553, 64, 'Cerfa n° 11580*05', 8, 'R', 'R', $muted);

        $p->rect(42, 120, 511, 46, [0.965, 0.769, 0], null);
        $p->textAlign(297.6, 140, 'Reçu au titre des dons à certains organismes d’intérêt général', 13, 'B', 'C');
        $p->textAlign(297.6, 156, 'Articles 200, 238 bis et 978 du code général des impôts', 9, 'R', 'C');

        $box = function (float $y, string $title, array $rows) use ($p, $muted): float {
            $h = 26 + count($rows) * 15;
            $p->rect(42, $y, 511, $h);
            $p->rect(42, $y, 511, 18, [0.055, 0.122, 0.302], null);
            $p->text(52, $y + 13, mb_strtoupper($title), 9, 'B', [0.965, 0.769, 0]);
            $yy = $y + 34;
            foreach ($rows as [$k, $v]) {
                $p->text(52, $yy, $k, 9, 'R', $muted);
                $w = Pdf::width($v, 10, 'B');
                $p->text(190, $yy, $w > 355 ? mb_strimwidth($v, 0, 140, '…') : $v, $w > 355 ? max(7, 10 * 355 / $w) : 10, 'B');
                $yy += 15;
            }
            return $y + $h + 14;
        };
        $types = ['oig' => 'Œuvre ou organisme d’intérêt général', 'rup' => 'Association reconnue d’utilité publique', 'musee' => 'Musée de France'];
        $addr = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], ', ', $g('org_address'))), ENT_QUOTES, 'UTF-8')), ', ');
        $y = $box(184, 'Bénéficiaire des versements', [
            ['Nom ou dénomination', $org],
            ['Adresse', $addr ?: '—'],
            ['N° RNA ou SIREN', $g('org_rna') ?: '—'],
            ['Objet', $g('org_object')],
            ['Qualité', $types[$g('org_type', 'oig')] ?? $types['oig']],
        ]);
        $d = $don['donor'];
        $y = $box($y, 'Donateur', [
            ['Nom, prénom', trim(mb_strtoupper($d['last'] ?? '') . ' ' . ($d['first'] ?? ''))],
            ['Adresse', $d['address'] ?? ''],
            ['Code postal, commune', trim(($d['zip'] ?? '') . ' ' . ($d['city'] ?? ''))],
            ['Pays', $d['country'] ?? 'France'],
        ]);

        $p->text(42, $y + 6, 'Le bénéficiaire reconnaît avoir reçu au titre des dons et versements ouvrant droit à réduction d’impôt la somme de :', 9.5);
        $p->rect(42, $y + 16, 511, 56, [1, 0.992, 0.965]);
        $euros = intdiv($cents, 100);
        $c = $cents % 100;
        $p->text(56, $y + 40, '*** ' . number_format($cents / 100, 2, ',', ' ') . ' € ***', 16, 'B');
        $words = class_exists(\NumberFormatter::class) ? (new \NumberFormatter('fr', \NumberFormatter::SPELLOUT))->format($euros) . ' euro' . ($euros > 1 ? 's' : '') . ($c ? ' et ' . (new \NumberFormatter('fr', \NumberFormatter::SPELLOUT))->format($c) . ' centime' . ($c > 1 ? 's' : '') : '') : '';
        $p->text(56, $y + 60, mb_strimwidth('Somme en toutes lettres : ' . $words, 0, 110, '…'), 9.5, 'I');
        $y += 90;
        $p->text(42, $y, count($dates) > 1 ? 'Dates des versements : ' : 'Date du versement : ', 9.5, 'R', $muted);
        $y = $p->paragraph(160, $y, 393, implode(', ', $dates), 9.5, 'B');
        $y += 6;
        $y = $p->paragraph(42, $y, 511, 'Le bénéficiaire certifie sur l’honneur que les dons et versements qu’il reçoit ouvrent droit à la réduction d’impôt prévue à l’article :', 9.5) + 8;
        foreach ([['200 du CGI', true], ['238 bis du CGI', false], ['978 du CGI', false]] as $i => [$lbl, $on]) {
            $p->checkbox(52 + $i * 140, $y - 8, $on)->text(66 + $i * 140, $y, $lbl, 9.5);
        }
        $y += 22;
        $method = $don['provider'] === 'manuel' ? ($don['manual_method'] ?? 'Chèque') : 'Virement, prélèvement, carte bancaire';
        foreach ([['Forme du don', 'Déclaration de don manuel'], ['Nature du don', 'Numéraire'], ['Mode de versement', $method]] as [$k, $v]) {
            $p->text(42, $y, $k, 9.5, 'R', $muted)->text(160, $y, $v, 9.5, 'B');
            $y += 14;
        }
        $y += 18;
        $p->text(340, $y, 'Fait le ' . date('d/m/Y'), 9.5);
        $p->text(340, $y + 14, $g('org_signatory') ?: 'Le président', 9.5, 'B');
        $p->rect(340, $y + 22, 213, 54, null, $muted, 0.5);
        $p->text(346, $y + 34, 'Signature', 8, 'I', $muted);
        $p->paragraph(42, 790, 511, 'Le don ouvre droit à une réduction d’impôt sur le revenu égale à 66 % de son montant, dans la limite de 20 % du revenu imposable (article 200 du CGI). Conservez ce reçu : il peut vous être demandé par l’administration fiscale.', 8, 'R', 1.35, $muted);
        $p->line(42, 780, 553, 780, 0.5, $navy);
        return $p->output('Reçu fiscal ' . $num);
    }
}
