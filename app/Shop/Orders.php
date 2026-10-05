<?php
declare(strict_types=1);

namespace App\Shop;

use App\Core\JsonStore;
use App\Services\Mailer;
use App\Services\Payments;

/**
 * Boutique : réglages (imprimeur, frais de port, délai) et commandes. Fabrication à la demande :
 * une commande payée part chez l'imprimeur local avec un PDF d'impression par article ; il la
 * fabrique, l'expédie (numéro de suivi) et répond aux clients. L'association encaisse (Stripe).
 * Stockage hors dépôt : storage/shop/ (config.json, orders.json, pdf/).
 */
final class Orders
{
    public const STATUSES = [
        'pending' => 'En attente de paiement', 'paid' => 'Payée, à fabriquer', 'production' => 'En fabrication',
        'shipped' => 'Expédiée', 'delivered' => 'Livrée', 'canceled' => 'Annulée', 'refunded' => 'Remboursée',
    ];
    /** Étapes visibles du client, dans l'ordre. */
    public const STEPS = ['paid' => 'Commande payée', 'production' => 'En fabrication', 'shipped' => 'Expédiée', 'delivered' => 'Livrée'];
    public const COUNTRIES = ['FR' => 'France', 'BE' => 'Belgique', 'LU' => 'Luxembourg', 'CH' => 'Suisse', 'DE' => 'Allemagne'];
    public const MAX_ITEMS = 20;

    /** Faux Stripe et faux courrier des tests. */
    public static $stripe = null;
    public static $mail = null;

    private static function dir(): string
    {
        return STORAGE_PATH . '/shop';
    }

    private static function file(): string
    {
        return self::dir() . '/orders.json';
    }

    // ------------------------------------------------------------------ réglages

    /** Conditions de vente proposées par défaut (modifiables dans Boutique › Réglages). */
    public const CGV = "Vendeur : association Sochaux Rétro (loi 1901). Contact : depuis la page de suivi de votre commande, ou par le formulaire de contact du site.\n\nProduits : objets aux couleurs de l’association, fabriqués à la demande pour vous par notre imprimeur partenaire, près de Sochaux. Les visuels sont des aperçus : de légères différences de teinte sont possibles à l’impression.\n\nPrix et paiement : prix en euros, toutes taxes comprises. Paiement sécurisé par carte bancaire (Stripe) ; la commande est fabriquée après la confirmation du paiement.\n\nFabrication et livraison : expédition sous 5 jours ouvrés environ après le paiement, à l’adresse indiquée (France, Belgique, Luxembourg, Suisse, Allemagne). Vous recevez un e-mail à chaque étape, avec le numéro de suivi du colis.\n\nRétractation : les articles étant personnalisés et fabriqués spécialement pour vous, le droit de rétractation ne s’applique pas (article L221-28 du Code de la consommation).\n\nDéfaut ou erreur : si un article arrive abîmé, mal imprimé ou différent de votre commande, écrivez-nous dans les 14 jours suivant la réception, avec une photo : nous le refaisons ou le remboursons, à votre choix.\n\nDonnées personnelles : vos coordonnées servent uniquement à fabriquer, expédier et suivre votre commande ; elles sont transmises à l’imprimeur pour la livraison et ne sont jamais revendues.\n\nLitiges : en cas de désaccord, contactez-nous d’abord ; vous pouvez aussi recourir gratuitement à un médiateur de la consommation.";

    public const CONFIG_DEFAULTS = [
        'open' => false, 'printer_name' => '', 'printer_email' => '', 'shipping' => 590, 'free_from' => 0,
        'delay' => 'Fabriqué à la demande, expédié sous 5 jours ouvrés.', 'alert_email' => '', 'cgv' => self::CGV, 'ship_cost' => 590,
        'anec_daily' => 500,
    ];

    public static function config(): array
    {
        $c = (array) JsonStore::read(self::dir() . '/config.json', []);
        $c = array_replace(self::CONFIG_DEFAULTS, array_intersect_key($c, self::CONFIG_DEFAULTS));
        // Conditions de vente vides (enregistrées avant le texte proposé) : le texte proposé.
        if (trim((string) $c['cgv']) === '') {
            $c['cgv'] = self::CGV;
        }
        return $c;
    }

    public static function saveConfig(array $c): array
    {
        $c = array_replace(self::config(), array_intersect_key($c, self::CONFIG_DEFAULTS));
        $c['open'] = !empty($c['open']);
        foreach (['printer_email', 'alert_email'] as $k) {
            $c[$k] = filter_var(trim((string) $c[$k]), FILTER_VALIDATE_EMAIL) ? strtolower(trim((string) $c[$k])) : '';
        }
        $c['shipping'] = max(0, min(10000, (int) $c['shipping']));
        $c['free_from'] = max(0, min(1000000, (int) $c['free_from']));
        $c['ship_cost'] = max(0, min(10000, (int) $c['ship_cost']));
        $c['anec_daily'] = max(0, min(20000, (int) $c['anec_daily']));
        foreach (['printer_name' => 80, 'delay' => 200, 'cgv' => 4000] as $k => $max) {
            $c[$k] = mb_substr(trim((string) $c[$k]), 0, $max);
        }
        JsonStore::write(self::dir() . '/config.json', $c);
        return $c;
    }

    /** Boutique ouverte au public (et paiement possible). */
    public static function open(): bool
    {
        return self::config()['open'];
    }

    public static function shipping(int $subtotal): int
    {
        $c = self::config();
        return $c['free_from'] > 0 && $subtotal >= $c['free_from'] ? 0 : $c['shipping'];
    }

    public static function money(int $cents): string
    {
        return number_format($cents / 100, 2, ',', "\u{202F}") . "\u{00A0}€";
    }

    // ------------------------------------------------------------------ panier → commande

    /**
     * Ligne de panier contrôlée : modèle en vente, choix et réponses valides, quantité bornée.
     * @return array{item?:array,error?:string}
     */
    public static function line(array $in): array
    {
        $m = Catalog::find((string) ($in['model'] ?? ''));
        if (!$m || !Catalog::sellable($m)) {
            return ['error' => 'Cet article n’est plus en vente.'];
        }
        $sup = Catalog::support($m['support']);
        $size = (string) ($in['size'] ?? '');
        if ($sup['sizes'] && !in_array($size, $sup['sizes'], true)) {
            return ['error' => 'Choisissez une taille.'];
        }
        [$mm, $opt] = Catalog::applyOptions($m, (array) ($in['opts'] ?? []));
        if ($opt['pos'] !== '' && !isset(Catalog::positions($m)[$opt['pos']])) {
            $opt['pos'] = '';
            [$mm, $opt] = Catalog::applyOptions($m, ['pos' => ''] + $opt);
        }
        $input = (array) ($in['values'] ?? []);
        $auto = [];
        if (TonMatch::isFor($m)) {
            if ($opt['date'] === '') {
                return ['error' => 'Indiquez la date de votre match (au moins l’année).'];
            }
            $auto = array_filter(TonMatch::values($opt['date']), fn ($k) => !str_starts_with($k, '_'), ARRAY_FILTER_USE_KEY);
        }
        $chk = Catalog::check($mm, $input);
        if ($chk['errors']) {
            return ['error' => implode(' ', $chk['errors'])];
        }
        $chk['values'] += array_intersect_key($auto, Catalog::fields($mm));
        foreach (Catalog::fields($mm) as $k => $f) {
            if (!$f['auto'] && !isset($chk['values'][$k])) {
                return ['error' => 'Complétez « ' . $f['label'] . ' ».'];
            }
        }
        $qty = Poster::isFor($m) ? 1 : max(1, min(20, (int) ($in['qty'] ?? 1))); // poster dédicacé et numéroté : un exemplaire
        $unit = Catalog::price($m, $size);
        return ['item' => [
            'model' => $m['id'], 'name' => $m['name'], 'support' => $sup['name'], 'size' => $sup['sizes'] ? $size : '',
            'color' => $opt['color'], 'color_name' => (string) (array_search($opt['color'], $sup['colors'], true) ?: ''),
            'opts' => $opt, 'values' => $chk['values'], 'qty' => $qty, 'unit' => $unit, 'total' => $unit * $qty,
            'cost' => Catalog::printerShare($m, $sup, $unit), 'rate' => Catalog::rate($m, $sup),
        ]];
    }

    /** Résumé lisible des choix d'un article (taille, couleur, textes). */
    public static function describe(array $it): string
    {
        $p = [];
        if ($it['size'] !== '') {
            $p[] = (isset(Catalog::PAPER[$it['size']]) ? 'Format ' : 'Taille ') . $it['size'];
        }
        if ($it['color_name'] !== '') {
            $p[] = $it['color_name'];
        }
        if (($it['opts']['tsize'] ?? 'm') !== 'm') {
            $p[] = 'texte ' . mb_strtolower(Catalog::TEXT_SIZES[$it['opts']['tsize']][1]);
        }
        if (($it['opts']['pos'] ?? '') !== '') {
            $p[] = 'texte ' . mb_strtolower(Catalog::POSITIONS[$it['opts']['pos']]);
        }
        if (($it['opts']['date'] ?? '') !== '' && isset($it['values']['match_affiche'])) {
            $p[] = 'Ton match : ' . $it['values']['match_affiche'] . ', ' . ($it['values']['match_date'] ?? '');
        }
        if (isset($it['values'][Poster::FIELD])) {
            $d = Poster::data((string) $it['values'][Poster::FIELD]);
            $p[] = 'Poster : ' . ($d ? Poster::label($d['dm']) : 'match n° ' . $it['values'][Poster::FIELD]);
            $p[] = 'pour ' . trim(($it['values']['poster_prenom'] ?? '') . ' ' . ($it['values']['poster_nom'] ?? ''));
        }
        foreach ($it['values'] as $k => $v) {
            if (!str_starts_with((string) $k, 'match_') && !str_starts_with((string) $k, 'poster_')) {
                $p[] = '« ' . $v . ' »';
            }
        }
        return implode(' · ', $p);
    }

    /**
     * Crée la commande (en attente de paiement). $customer : name, email, phone, line1, line2, zip, city, country.
     * @return array{order?:array,error?:string}
     */
    public static function create(array $items, array $customer, string $promo = ''): array
    {
        if (!$items) {
            return ['error' => 'Votre panier est vide.'];
        }
        $clean = [];
        foreach (array_slice($items, 0, self::MAX_ITEMS) as $in) {
            $r = self::line($in);
            if (isset($r['error'])) {
                return $r;
            }
            $clean[] = $r['item'];
        }
        $c = [];
        foreach (['name' => 80, 'email' => 120, 'phone' => 30, 'line1' => 120, 'line2' => 120, 'zip' => 12, 'city' => 80, 'country' => 2] as $k => $max) {
            $c[$k] = trim((string) preg_replace('/\s+/u', ' ', strip_tags(mb_substr((string) ($customer[$k] ?? ''), 0, $max))));
        }
        $c['email'] = strtolower($c['email']);
        if ($c['name'] === '' || !filter_var($c['email'], FILTER_VALIDATE_EMAIL) || $c['line1'] === '' || $c['zip'] === '' || $c['city'] === '' || !isset(self::COUNTRIES[$c['country']])) {
            return ['error' => 'Complétez votre nom, votre e-mail et l’adresse de livraison.'];
        }
        $sub = array_sum(array_column($clean, 'total'));
        $pr = null;
        if (trim($promo) !== '') {
            $pr = Promos::apply($promo, $clean, $c['email']);
            if (isset($pr['error'])) {
                return $pr;
            }
        }
        $discount = (int) ($pr['discount'] ?? 0);
        $ship = !empty($pr['free_shipping']) ? 0 : self::shipping($sub - $discount);
        $o = [
            'id' => 'SR' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(2))), 'token' => bin2hex(random_bytes(16)),
            'created' => date('c'), 'status' => 'pending', 'customer' => $c, 'items' => $clean,
            'subtotal' => $sub, 'discount' => $discount, 'promo' => $pr ? ['code' => $pr['code'], 'label' => $pr['label']] : null,
            'shipping' => $ship, 'total' => $sub - $discount + $ship, 'paid' => 0, 'refunded' => 0,
            'tracking' => ['carrier' => '', 'number' => '', 'url' => ''], 'history' => [['at' => date('c'), 'status' => 'pending', 'by' => 'client', 'note' => '']],
            'messages' => [], 'ext' => [],
        ];
        JsonStore::update(self::file(), function ($all) use ($o) {
            $all = is_array($all) ? $all : [];
            $all[$o['id']] = $o;
            return $all;
        }, []);
        return ['order' => $o];
    }

    // ------------------------------------------------------------------ lecture, mise à jour

    /** @return list<array> de la plus récente à la plus ancienne */
    public static function all(): array
    {
        $all = array_values((array) (JsonStore::read(self::file(), []) ?: []));
        usort($all, fn ($a, $b) => strcmp((string) $b['created'], (string) $a['created']));
        return $all;
    }

    public static function get(string $id): ?array
    {
        $all = (array) (JsonStore::read(self::file(), []) ?: []);
        return isset($all[$id]) && is_array($all[$id]) ? $all[$id] : null;
    }

    public static function byToken(string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
            return null;
        }
        foreach ((array) (JsonStore::read(self::file(), []) ?: []) as $o) {
            if (hash_equals((string) $o['token'], $token)) {
                return $o;
            }
        }
        return null;
    }

    public static function update(string $id, callable $fn): ?array
    {
        $out = null;
        JsonStore::update(self::file(), function ($all) use ($id, $fn, &$out) {
            $all = is_array($all) ? $all : [];
            if (isset($all[$id])) {
                $all[$id] = $out = $fn($all[$id]);
            }
            return $all;
        }, []);
        return $out;
    }

    // ------------------------------------------------------------------ paiement (Stripe Checkout)

    private static function stripeCall(string $method, string $path, array $params = []): array
    {
        return self::$stripe ? (self::$stripe)($method, $path, $params) : Payments::stripe($method, $path, $params);
    }

    public static function payable(): bool
    {
        return self::$stripe !== null || Payments::stripeReady();
    }

    /** @return array{url?:string,error?:string} */
    public static function checkout(array $o, string $base): array
    {
        $lines = [];
        foreach ($o['items'] as $it) {
            $lines[] = ['quantity' => $it['qty'], 'price_data' => ['currency' => 'eur', 'unit_amount' => $it['unit'], 'product_data' => ['name' => mb_substr($it['name'] . ' (' . $it['support'] . ')', 0, 120), 'description' => mb_substr(self::describe($it), 0, 250) ?: null]]];
        }
        if ($o['shipping'] > 0) {
            $lines[] = ['quantity' => 1, 'price_data' => ['currency' => 'eur', 'unit_amount' => $o['shipping'], 'product_data' => ['name' => 'Livraison']]];
        }
        $lines = array_map(fn ($l) => array_filter($l, fn ($v) => $v !== null), $lines);
        // Code promo : un coupon Stripe à usage unique, du montant exact de la remise.
        $discounts = [];
        if ((int) ($o['discount'] ?? 0) > 0) {
            try {
                $cp = self::stripeCall('POST', 'coupons', ['amount_off' => (int) $o['discount'], 'currency' => 'eur', 'duration' => 'once', 'max_redemptions' => 1, 'name' => mb_substr('Code ' . ($o['promo']['code'] ?? ''), 0, 40)]);
                $discounts = [['coupon' => (string) $cp['id']]];
            } catch (\Throwable $e) {
                error_log('[boutique] coupon ' . $o['id'] . ' : ' . $e->getMessage());
                return ['error' => 'Le paiement en ligne ne répond pas pour le moment : réessayez dans quelques minutes.'];
            }
        }
        foreach ($lines as &$l) {
            $l['price_data']['product_data'] = array_filter($l['price_data']['product_data'], fn ($v) => $v !== null && $v !== '');
        }
        unset($l);
        try {
            $s = self::stripeCall('POST', 'checkout/sessions', [
                'mode' => 'payment', 'line_items' => $lines, 'customer_email' => $o['customer']['email'],
                ...($discounts ? ['discounts' => $discounts] : []),
                'client_reference_id' => $o['id'], 'metadata' => ['commande' => $o['id']],
                'payment_intent_data' => ['metadata' => ['commande' => $o['id']], 'description' => 'Boutique Sochaux Rétro · commande ' . $o['id']],
                'success_url' => $base . '/boutique/commande/' . $o['token'] . '/?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => $base . '/boutique/panier/?annule=1', 'locale' => 'fr',
            ]);
            if (empty($s['url']) || empty($s['id'])) {
                throw new \RuntimeException('Stripe : lien de paiement absent');
            }
            self::update($o['id'], fn ($x) => array_replace_recursive($x, ['ext' => ['stripe_session' => (string) $s['id']]]));
            return ['url' => (string) $s['url']];
        } catch (\Throwable $e) {
            error_log('[boutique] ' . $e->getMessage());
            return ['error' => 'Le paiement en ligne ne répond pas pour le moment : réessayez dans quelques minutes.'];
        }
    }

    /** Retour du client après paiement : relit la session chez Stripe. */
    public static function syncReturn(array $o, string $sessionId): void
    {
        if ($sessionId === '' || $sessionId !== ($o['ext']['stripe_session'] ?? '')) {
            return;
        }
        try {
            self::stripeSession(self::stripeCall('GET', 'checkout/sessions/' . rawurlencode($sessionId)));
        } catch (\Throwable $e) {
            error_log('[boutique] retour ' . $o['id'] . ' : ' . $e->getMessage());
        }
    }

    /** Session Stripe Checkout (retour, webhook) : paiement confirmé ou abandon. */
    public static function stripeSession(array $s): void
    {
        $o = self::get((string) ($s['metadata']['commande'] ?? $s['client_reference_id'] ?? ''));
        if (!$o || ($s['id'] ?? '') !== ($o['ext']['stripe_session'] ?? '')) {
            return;
        }
        if (in_array($s['payment_status'] ?? '', ['paid', 'no_payment_required'], true)) {
            self::markPaid($o['id'], (string) ($s['payment_intent'] ?? ''), (int) ($s['amount_total'] ?? $o['total']));
        } elseif (($s['status'] ?? '') === 'expired' && $o['status'] === 'pending') {
            self::setStatus($o['id'], 'canceled', 'système', 'Paiement non abouti.', false);
        }
    }

    /**
     * Paiement reçu (une seule fois) : fichiers d'impression, e-mails au client, à l'imprimeur et à
     * l'association. $ref : référence Stripe (payment_intent) ou « hors ligne ».
     */
    public static function markPaid(string $id, string $ref, int $cents, string $by = 'Stripe'): bool
    {
        $first = false;
        $o = self::update($id, function ($x) use ($ref, $cents, $by, &$first) {
            if ($x['status'] !== 'pending' && $x['status'] !== 'canceled') {
                return $x;
            }
            $first = true;
            $x['status'] = 'paid';
            $x['paid'] = $cents;
            $x['paid_at'] = date('c');
            if ($ref !== '') {
                $x['ext']['stripe_pi'] = $ref;
            }
            $x['ship_cost'] = self::config()['ship_cost'];
            $x['history'][] = ['at' => date('c'), 'status' => 'paid', 'by' => $by, 'note' => self::money($cents) . ' reçus'];
            return $x;
        });
        if (!$o || !$first) {
            return false;
        }
        Accounts::fetchFee($id);
        if (!empty($o['promo']['code'])) {
            Promos::used($o['promo']['code'], $o['id'], $o['customer']['email']);
        }
        foreach ($o['items'] as $it) { // anecdotes vendues : plus jamais proposées
            if (($it['values'][Anecdotes::FIELD] ?? '') !== '') {
                Anecdotes::sold((string) $it['values'][Anecdotes::FIELD]);
            }
        }
        self::buildPdfs($o);
        $c = self::config();
        $link = self::trackingUrl($o);
        self::mail($o['customer']['email'], 'Votre commande ' . $o['id'] . ' est confirmée', '<p>Bonjour ' . e($o['customer']['name']) . ',</p><p>Merci ! Votre commande <b>' . e($o['id']) . '</b> (' . e(self::money($o['total'])) . ') est payée. Elle part en fabrication chez notre imprimeur. ' . e($c['delay']) . '</p>' . self::itemsHtml($o) . '<p>Suivez-la et posez vos questions ici : <a href="' . e($link) . '">' . e($link) . '</a></p>', $c['printer_email'] ?: null);
        if ($c['printer_email'] !== '') {
            self::mail($c['printer_email'], 'Nouvelle commande ' . $o['id'] . ' à fabriquer', '<p>Bonjour,</p><p>Une nouvelle commande de la boutique Sochaux Rétro est payée et prête à fabriquer : <b>' . e($o['id']) . '</b>, ' . count($o['items']) . ' article(s).</p>' . self::itemsHtml($o) . '<p>Fichiers d’impression, adresse de livraison et suivi : <a href="' . e(base_url() . '/imprimeur/commande/' . $o['id']) . '">espace imprimeur</a>.</p>');
        }
        if ($c['alert_email'] !== '') {
            self::mail($c['alert_email'], 'Boutique : commande ' . $o['id'] . ' payée (' . self::money($o['total']) . ')', '<p>Commande <b>' . e($o['id']) . '</b> de ' . e($o['customer']['name']) . ' payée : ' . e(self::money($o['total'])) . '.</p>' . self::itemsHtml($o) . '<p><a href="' . e(base_url() . '/admin/boutique/commandes/' . $o['id']) . '">Voir dans le back-office</a></p>');
        }
        return true;
    }

    /** Fichier d'impression de chaque article (storage/shop/pdf/{commande}-{n}.pdf). */
    public static function buildPdfs(array $o): void
    {
        @mkdir(self::dir() . '/pdf', 0775, true);
        foreach ($o['items'] as $n => $it) {
            $m = Catalog::find($it['model']);
            if (!$m) {
                continue;
            }
            [$mm] = Catalog::applyOptions($m, $it['opts']);
            $extra = array_filter([$it['size'] !== '' ? (isset(Catalog::PAPER[$it['size']]) ? 'format ' : 'taille ') . $it['size'] : '', 'quantité ' . $it['qty'], 'commande ' . $o['id'] . ' article ' . ($n + 1)]);
            $values = $it['values'];
            if (Poster::isFor($m)) {
                // Poster souvenir : numéro de pièce attribué une fois pour toutes à cet article.
                $values['_poster_no'] = Poster::number($o['id'] . '-' . ($n + 1));
                $extra[] = 'poster n° ' . $values['_poster_no'];
            }
            $pdf = Catalog::printPdf($mm, $values, $it['name'], $extra, (string) $it['size']);
            if ($pdf !== '') {
                file_put_contents(self::pdfPath($o['id'], $n), $pdf);
            }
        }
    }

    public static function pdfPath(string $id, int $n): string
    {
        return self::dir() . '/pdf/' . preg_replace('/[^A-Z0-9-]/', '', $id) . '-' . ($n + 1) . '.pdf';
    }

    /** Fichier d'impression (refait s'il manque). */
    public static function pdf(array $o, int $n): ?string
    {
        if (!isset($o['items'][$n]) || !in_array($o['status'], ['paid', 'production', 'shipped', 'delivered'], true)) {
            return null;
        }
        $p = self::pdfPath($o['id'], $n);
        if (!is_file($p)) {
            self::buildPdfs($o);
        }
        return is_file($p) ? (string) file_get_contents($p) : null;
    }

    // ------------------------------------------------------------------ fabrication, expédition, messages

    /** Changement d'étape ; le client est prévenu par e-mail (sauf $notify = false). */
    public static function setStatus(string $id, string $status, string $by, string $note = '', bool $notify = true, array $tracking = []): ?array
    {
        if (!isset(self::STATUSES[$status])) {
            return null;
        }
        $o = self::update($id, function ($x) use ($status, $by, $note, $tracking) {
            $x['status'] = $status;
            if ($tracking) {
                $x['tracking'] = array_replace($x['tracking'], array_map(fn ($v) => mb_substr(trim((string) $v), 0, 300), array_intersect_key($tracking, $x['tracking'])));
                if (!preg_match('#^https://#i', $x['tracking']['url'])) {
                    $x['tracking']['url'] = '';
                }
            }
            $x['history'][] = ['at' => date('c'), 'status' => $status, 'by' => $by, 'note' => mb_substr($note, 0, 300)];
            return $x;
        });
        if ($o && $notify) {
            $t = $o['tracking'];
            $msg = [
                'production' => 'Votre commande est en cours de fabrication chez notre imprimeur.',
                'shipped' => 'Votre commande est expédiée !' . ($t['number'] !== '' ? ' Transporteur : ' . e($t['carrier'] ?: '—') . ', numéro de suivi : <b>' . e($t['number']) . '</b>' . ($t['url'] !== '' ? ' (<a href="' . e($t['url']) . '">suivre le colis</a>)' : '') . '.' : ''),
                'delivered' => 'Votre commande est indiquée comme livrée. Bonne réception, et merci de soutenir l’association !',
                'canceled' => 'Votre commande est annulée.' . ($note !== '' ? ' ' . e($note) : ''),
                'refunded' => 'Votre commande est remboursée (' . e(self::money($o['refunded'])) . ') sur le moyen de paiement utilisé.',
            ][$status] ?? null;
            if ($msg) {
                self::mail($o['customer']['email'], 'Commande ' . $o['id'] . ' : ' . mb_strtolower(self::STATUSES[$status]), '<p>Bonjour ' . e($o['customer']['name']) . ',</p><p>' . $msg . '</p><p>Suivi et questions : <a href="' . e(self::trackingUrl($o)) . '">' . e(self::trackingUrl($o)) . '</a></p>', self::config()['printer_email'] ?: null);
            }
        }
        return $o;
    }

    /** Message sur une commande : client ↔ imprimeur (le service client), copie lisible par l'association. */
    public static function message(string $id, string $from, string $text): ?array
    {
        $text = trim(mb_substr(strip_tags($text), 0, 2000));
        if ($text === '' || !in_array($from, ['client', 'imprimeur', 'association'], true)) {
            return null;
        }
        $o = self::update($id, function ($x) use ($from, $text) {
            $x['messages'][] = ['at' => date('c'), 'from' => $from, 'text' => $text];
            return $x;
        });
        if (!$o) {
            return null;
        }
        $c = self::config();
        $body = '<p>' . nl2br(e($text)) . '</p>';
        if ($from === 'client') {
            if ($c['printer_email'] !== '') {
                self::mail($c['printer_email'], 'Question du client · commande ' . $o['id'], '<p>' . e($o['customer']['name']) . ' écrit :</p>' . $body . '<p><a href="' . e(base_url() . '/imprimeur/commande/' . $o['id']) . '">Répondre dans l’espace imprimeur</a></p>', $o['customer']['email']);
            }
        } else {
            self::mail($o['customer']['email'], 'Réponse à propos de votre commande ' . $o['id'], '<p>Bonjour ' . e($o['customer']['name']) . ',</p>' . $body . '<p>Pour répondre : <a href="' . e(self::trackingUrl($o)) . '">' . e(self::trackingUrl($o)) . '</a></p>', $from === 'imprimeur' ? ($c['printer_email'] ?: null) : null);
        }
        return $o;
    }

    /** Remboursement (total ou partiel, centimes) par Stripe, demandé depuis le back-office. */
    public static function refund(string $id, int $cents, string $by): array
    {
        $o = self::get($id);
        if (!$o || $o['paid'] <= 0) {
            return ['error' => 'Commande non payée.'];
        }
        $left = $o['paid'] - $o['refunded'];
        $cents = $cents > 0 ? min($cents, $left) : $left;
        if ($cents <= 0) {
            return ['error' => 'Déjà entièrement remboursée.'];
        }
        $pi = (string) ($o['ext']['stripe_pi'] ?? '');
        if ($pi !== '') {
            try {
                self::stripeCall('POST', 'refunds', ['payment_intent' => $pi, 'amount' => $cents, 'metadata' => ['commande' => $id]]);
            } catch (\Throwable $e) {
                return ['error' => $e->getMessage()];
            }
        }
        $o = self::update($id, function ($x) use ($cents, $by, $pi) {
            $x['refunded'] += $cents;
            $x['history'][] = ['at' => date('c'), 'status' => $x['refunded'] >= $x['paid'] ? 'refunded' : $x['status'], 'by' => $by, 'note' => 'Remboursement de ' . self::money($cents) . ($pi === '' ? ' (hors ligne, à faire à la main)' : '')];
            return $x;
        });
        if ($o['refunded'] >= $o['paid']) {
            self::setStatus($id, 'refunded', $by, '', true);
        } else {
            self::mail($o['customer']['email'], 'Remboursement partiel · commande ' . $o['id'], '<p>Bonjour ' . e($o['customer']['name']) . ',</p><p>Nous vous avons remboursé ' . e(self::money($cents)) . ' sur votre commande ' . e($o['id']) . '.</p>');
        }
        return ['ok' => true, 'cents' => $cents];
    }

    /** Remboursement fait directement chez Stripe (webhook « charge.refunded »). */
    public static function refundedAtStripe(string $pi, int $amountRefunded): void
    {
        foreach ((array) (JsonStore::read(self::file(), []) ?: []) as $o) {
            if ($pi !== '' && ($o['ext']['stripe_pi'] ?? '') === $pi && $amountRefunded > $o['refunded']) {
                $diff = $amountRefunded - $o['refunded'];
                self::update($o['id'], function ($x) use ($amountRefunded, $diff) {
                    $x['refunded'] = $amountRefunded;
                    $x['history'][] = ['at' => date('c'), 'status' => $x['status'], 'by' => 'Stripe', 'note' => 'Remboursement de ' . self::money($diff) . ' fait chez Stripe'];
                    return $x;
                });
                if ($amountRefunded >= $o['paid']) {
                    self::setStatus($o['id'], 'refunded', 'Stripe');
                }
            }
        }
    }

    /** Commandes jamais payées : annulées après deux jours (tâche planifiée). */
    public static function expire(): int
    {
        $n = 0;
        foreach (self::all() as $o) {
            if ($o['status'] === 'pending' && strtotime($o['created']) < time() - 172800) {
                self::setStatus($o['id'], 'canceled', 'système', 'Paiement non abouti.', false);
                $n++;
            }
        }
        return $n;
    }

    // ------------------------------------------------------------------ utilitaires

    public static function trackingUrl(array $o): string
    {
        return base_url() . '/boutique/commande/' . $o['token'] . '/';
    }

    public static function itemsHtml(array $o): string
    {
        $h = '<table cellpadding="6" style="border-collapse:collapse;width:100%">';
        foreach ($o['items'] as $it) {
            $h .= '<tr style="border-bottom:1px solid #ddd"><td>' . $it['qty'] . ' × <b>' . e($it['name']) . '</b> (' . e($it['support']) . ')<br><small>' . e(self::describe($it)) . '</small></td><td align="right">' . e(self::money($it['total'])) . '</td></tr>';
        }
        if ((int) ($o['discount'] ?? 0) > 0) {
            $h .= '<tr><td>Code promo ' . e($o['promo']['code'] ?? '') . '</td><td align="right">−' . e(self::money((int) $o['discount'])) . '</td></tr>';
        }
        $h .= '<tr><td>Livraison</td><td align="right">' . e($o['shipping'] ? self::money($o['shipping']) : 'offerte') . '</td></tr>';
        return $h . '<tr><td><b>Total</b></td><td align="right"><b>' . e(self::money($o['total'])) . '</b></td></tr></table>';
    }

    /** E-mail de la boutique (alertes de la gestion). */
    public static function notify(string $to, string $subject, string $html): void
    {
        self::mail($to, $subject, $html);
    }

    private static function mail(string $to, string $subject, string $html, ?string $replyTo = null): void
    {
        if (self::$mail) {
            (self::$mail)($to, $subject, $html, $replyTo);
            return;
        }
        Mailer::send($to, $subject, $html, $replyTo);
    }
}
