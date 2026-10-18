<?php
declare(strict_types=1);

namespace App\Shop;

use App\Core\Club;
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

    /** Faux Stripe et faux courrier des tests ; $newId : identifiants imposés (collision simulée). */
    public static $stripe = null;
    public static $mail = null;
    public static $newId = null;

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
    public const CGV = "Vendeur : l’association qui édite le musée {site} (loi 1901). Contact : depuis la page de suivi de votre commande, ou par le formulaire de contact du site.\n\nProduits : objets aux couleurs de l’association, fabriqués à la demande pour vous par notre imprimeur partenaire. Les visuels sont des aperçus : de légères différences de teinte sont possibles à l’impression.\n\nPrix et paiement : prix en euros, toutes taxes comprises. Paiement sécurisé par carte bancaire (Stripe) ; la commande est fabriquée après la confirmation du paiement.\n\nFabrication et livraison : expédition sous 5 jours ouvrés environ après le paiement, à l’adresse indiquée (France, Belgique, Luxembourg, Suisse, Allemagne). Vous recevez un e-mail à chaque étape, avec le numéro de suivi du colis.\n\nRétractation : les articles étant personnalisés et fabriqués spécialement pour vous, le droit de rétractation ne s’applique pas (article L221-28 du Code de la consommation).\n\nDéfaut ou erreur : si un article arrive abîmé, mal imprimé ou différent de votre commande, écrivez-nous dans les 14 jours suivant la réception, avec une photo : nous le refaisons ou le remboursons, à votre choix.\n\nDonnées personnelles : vos coordonnées servent uniquement à fabriquer, expédier et suivre votre commande ; elles sont transmises à l’imprimeur pour la livraison et ne sont jamais revendues.\n\nLitiges : en cas de désaccord, contactez-nous d’abord ; vous pouvez aussi recourir gratuitement à un médiateur de la consommation.";

    /** Conditions de vente proposées par défaut : celles du club (config/clubs/<id>/cgv.php), sinon le texte type. */
    public static function cgv(): string
    {
        return (string) Club::content('cgv', club(self::CGV));
    }

    public const CONFIG_DEFAULTS = [
        'open' => false, 'printer_name' => '', 'printer_email' => '', 'shipping' => 590, 'free_from' => 0,
        'delay' => 'Fabriqué à la demande, expédié sous 5 jours ouvrés.', 'alert_email' => '', 'cgv' => '', 'ship_cost' => 590,
        'anec_daily' => 500,
    ];

    public static function config(): array
    {
        $c = (array) JsonStore::read(self::dir() . '/config.json', []);
        $c = array_replace(self::CONFIG_DEFAULTS, array_intersect_key($c, self::CONFIG_DEFAULTS));
        // Conditions de vente vides (enregistrées avant le texte proposé) : le texte proposé.
        if (trim((string) $c['cgv']) === '') {
            $c['cgv'] = self::cgv();
        }
        return $c;
    }

    public static function saveConfig(array $in): array
    {
        // Sous verrou : deux enregistrements simultanés ne s'écrasent pas (chacun part des réglages
        // relus à l'intérieur du verrou).
        return JsonStore::update(self::dir() . '/config.json', function ($cur) use ($in) {
            $cur = is_array($cur) ? $cur : [];
            $c = array_replace(self::CONFIG_DEFAULTS, array_intersect_key($cur, self::CONFIG_DEFAULTS), array_intersect_key($in, self::CONFIG_DEFAULTS));
            if (trim((string) $c['cgv']) === '') {
                $c['cgv'] = self::cgv();
            }
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
            return $c;
        }, []);
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

    /** Au moins un article à fabriquer et expédier (le livre numérique ne l'est pas). */
    public static function physical(array $items): bool
    {
        foreach ($items as $it) {
            if (!BookShop::digital($it)) {
                return true;
            }
        }
        return false;
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
        if (($in['model'] ?? '') === BookShop::MODEL) {
            return BookShop::line($in);
        }
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
                if (($f['poster'] ?? '') === 'carnet') {
                    // Pas de carnet ouvert sur cet appareil : le poster (ou la carte) se compose d'après lui.
                    return ['error' => (isset($f['cmin']) ? 'Votre carte' : 'Votre poster') . ' se compose d’après votre carnet du supporter : créez-le (un match suffit) ou ouvrez-le sur cet appareil avec le lien reçu par e-mail, puis revenez sur cette page.'];
                }
                return ['error' => 'Complétez « ' . $f['label'] . ' ».'];
            }
        }
        // Poster du carnet : la liste des matchs est figée dans la commande (le fichier d'impression
        // ne change plus, même si le carnet est modifié ou supprimé ensuite).
        if (isset($chk['values'][CarnetPoster::FIELD])) {
            $chk['values']['_carnet_ids'] = implode(',', CarnetPoster::idsFor((string) $chk['values'][CarnetPoster::FIELD]) ?? []);
            // Carte du carnet : le pseudo est figé lui aussi.
            if (CarnetCard::isFor($mm) && ($cc = \App\Services\Carnet::get((string) $chk['values'][CarnetPoster::FIELD]))) {
                $chk['values']['_carnet_pseudo'] = CarnetCard::pseudo($cc);
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
        if (($it['model'] ?? '') === BookShop::MODEL) {
            return BookShop::describe($it);
        }
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
        } elseif (isset($it['values'][CarnetPoster::FIELD]) && !isset($it['values']['poster_prenom'])) {
            $n = count(array_filter(explode(',', (string) ($it['values']['_carnet_ids'] ?? ''))));
            $p[] = 'Carte du carnet : ' . $n . ' match' . ($n > 1 ? 's' : '') . (($it['values']['_carnet_pseudo'] ?? '') !== '' ? ', ' . $it['values']['_carnet_pseudo'] : '');
        } elseif (isset($it['values'][CarnetPoster::FIELD])) {
            $n = count(array_filter(explode(',', (string) ($it['values']['_carnet_ids'] ?? ''))));
            $p[] = club('Poster « Ma vie en {colors} » : ') . $n . ' match' . ($n > 1 ? 's' : '');
            $p[] = 'pour ' . trim(($it['values']['poster_prenom'] ?? '') . ' ' . ($it['values']['poster_nom'] ?? ''));
        } elseif (isset($it['values'][PlayerPoster::FIELD])) {
            $d = PlayerPoster::data((string) $it['values'][PlayerPoster::FIELD]);
            $p[] = 'Poster joueur : ' . ($d ? PlayerPoster::label($d) : 'fiche n° ' . $it['values'][PlayerPoster::FIELD]);
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
        $anec = array_filter(array_map(fn ($it) => (string) ($it['values'][Anecdotes::FIELD] ?? ''), $clean));
        if (count($anec) !== count(array_unique($anec))) {
            return ['error' => 'Une anecdote est une pièce unique : tirez-en une autre pour le second article.'];
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
        $ship = !empty($pr['free_shipping']) || !self::physical($clean) ? 0 : self::shipping($sub - $discount);
        $o = [
            'id' => self::newId(), 'token' => bin2hex(random_bytes(16)),
            'created' => date('c'), 'status' => 'pending', 'customer' => $c, 'items' => $clean,
            'subtotal' => $sub, 'discount' => $discount, 'promo' => $pr ? ['code' => $pr['code'], 'label' => $pr['label']] : null,
            'shipping' => $ship, 'total' => $sub - $discount + $ship, 'paid' => 0, 'refunded' => 0,
            'tracking' => ['carrier' => '', 'number' => '', 'url' => ''], 'history' => [['at' => date('c'), 'status' => 'pending', 'by' => 'client', 'note' => '']],
            'messages' => [], 'ext' => [],
        ];
        JsonStore::update(self::file(), function ($all) use (&$o) {
            $all = is_array($all) ? $all : [];
            // Jamais une commande existante écrasée : nouvel identifiant tiré sous le verrou si besoin.
            while (isset($all[$o['id']])) {
                $o['id'] = self::newId();
            }
            $all[$o['id']] = $o;
            return $all;
        }, []);
        if ($pr) {
            // Code promo réservé dès la création (sous verrou) : compté tout de suite dans les
            // utilisations, libéré si la commande est annulée sans paiement.
            $res = Promos::reserve($pr['code'], $o['id'], $c['email']);
            if (isset($res['error'])) {
                // Le même client recommence sa commande (paiement abandonné) : ses commandes encore
                // en attente avec ce code sont annulées, ce qui libère le code, puis on réessaie.
                $mine = array_filter(self::all(), fn ($x) => $x['id'] !== $o['id'] && $x['status'] === 'pending' && ($x['customer']['email'] ?? '') === $c['email'] && Promos::code((string) ($x['promo']['code'] ?? '')) === Promos::code($pr['code']));
                foreach ($mine as $x) {
                    self::setStatus($x['id'], 'canceled', 'système', 'Remplacée par la commande ' . $o['id'] . '.', false);
                }
                $res = $mine ? Promos::reserve($pr['code'], $o['id'], $c['email']) : $res;
            }
            if (isset($res['error'])) {
                // Commande jamais montrée au client : retirée.
                JsonStore::update(self::file(), function ($all) use ($o) {
                    $all = is_array($all) ? $all : [];
                    unset($all[$o['id']]);
                    return $all;
                }, []);
                return $res;
            }
        }
        return ['order' => $o];
    }

    /** Identifiant de commande lisible et imprévisible : SR251018-1A2B3C4D-5E6F7A8B (8 octets aléatoires). */
    public static function newId(): string
    {
        if (self::$newId) {
            return (string) (self::$newId)();
        }
        $h = strtoupper(bin2hex(random_bytes(8)));
        return 'SR' . date('ymd') . '-' . substr($h, 0, 8) . '-' . substr($h, 8);
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
                'payment_intent_data' => ['metadata' => ['commande' => $o['id']], 'description' => 'Boutique ' . Club::s('site') . ' · commande ' . $o['id']],
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
        } elseif (($s['status'] ?? '') === 'expired') {
            // Transition conditionnelle (sous verrou dans setStatus) : jamais une commande payée entre-temps.
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
            // Coût d'expédition de l'imprimeur : seulement s'il y a quelque chose à expédier.
            $x['ship_cost'] = self::physical($x['items']) ? self::config()['ship_cost'] : 0;
            $x['history'][] = ['at' => date('c'), 'status' => 'paid', 'by' => $by, 'note' => self::money($cents) . ' reçus'];
            return $x;
        });
        if (!$o || !$first) {
            return false;
        }
        if (!empty($o['promo']['code'])) {
            Promos::used($o['promo']['code'], $o['id'], $o['customer']['email']);
        }
        // Anecdotes (pièces uniques) : réservées sous verrou. Déjà vendue entre-temps à une autre
        // commande (deux paiements presque simultanés) : commande signalée, à rembourser ou refaire.
        $taken = [];
        $seen = [];
        foreach ($o['items'] as $n => $it) {
            $t = (string) ($it['values'][Anecdotes::FIELD] ?? '');
            if ($t === '') {
                continue;
            }
            if (isset($seen[$t]) || !Anecdotes::claim($t)) { // la même anecdote deux fois dans la commande : une seule pièce
                $taken[] = $n + 1;
            }
            $seen[$t] = true;
        }
        if ($taken) {
            $note = 'Anecdote déjà vendue à une autre commande (article ' . implode(', ', $taken) . ') : à rembourser ou à refaire avec une autre anecdote.';
            $o = self::update($o['id'], function ($x) use ($note, $taken) {
                $x['anecdote_conflict'] = $taken;
                $x['history'][] = ['at' => date('c'), 'status' => $x['status'], 'by' => 'système', 'note' => $note];
                return $x;
            }) ?? $o;
            $alert = self::config()['alert_email'];
            if ($alert !== '') {
                $url = self::mailUrl('/admin/boutique/commandes/' . $o['id']);
                self::mail($alert, 'Boutique : commande ' . $o['id'] . ' à rembourser (anecdote déjà vendue)', '<p>' . e($note) . '</p>' . ($url !== '' ? '<p><a href="' . e($url) . '">Voir la commande</a></p>' : '<p>Commande ' . e($o['id']) . ' (Boutique › Commandes).</p>'));
            }
        }
        // Fichiers d'impression, frais Stripe et e-mails (plusieurs secondes) : après l'envoi de la
        // page au client ou de la réponse à Stripe. Un fichier manquant se refait à la demande.
        if (PHP_SAPI === 'cli') {
            self::afterPaid($o);
        } else {
            register_shutdown_function(function () use ($o) {
                \App\Core\Response::detach();
                @set_time_limit(300);
                self::afterPaid($o);
            });
        }
        return true;
    }

    private static function afterPaid(array $o): void
    {
        self::buildPdfs($o);
        $c = self::config();
        $phys = self::physical($o['items']);
        $dl = '';
        foreach ($o['items'] as $n => $it) {
            if (BookShop::digital($it)) {
                $u = self::mailUrl(url('/boutique/commande/' . $o['token'] . '/livre/' . ($n + 1) . '/'));
                $dl .= $u !== ''
                    ? '<p><b>Votre livre numérique est prêt :</b> <a href="' . e($u) . '">télécharger le PDF</a> (lien personnel, ne le partagez pas : ' . BookShop::MAX_DOWNLOADS . ' téléchargements possibles ; il reste aussi sur la page de votre commande, d’où vous pouvez écrire au musée si vous en avez besoin de plus).</p>'
                    : '<p><b>Votre livre numérique est prêt.</b> ' . self::NO_LINK_CLIENT . '</p>';
            }
        }
        $link = self::mailUrl('/boutique/commande/' . $o['token'] . '/');
        self::mail($o['customer']['email'], 'Votre commande ' . $o['id'] . ' est confirmée', '<p>Bonjour ' . e($o['customer']['name']) . ',</p><p>Merci ! Votre commande <b>' . e($o['id']) . '</b> (' . e(self::money($o['total'])) . ') est payée.' . ($phys ? ' Elle part en fabrication chez notre imprimeur. ' . e($c['delay']) : '') . '</p>' . $dl . self::itemsHtml($o) . self::followHtml($link, 'Suivez-la et posez vos questions ici'), $phys ? ($c['printer_email'] ?: null) : null);
        if (!$phys) {
            // Commande entièrement numérique : livrée dès que le PDF est composé.
            self::setStatus($o['id'], 'delivered', 'Boutique', 'Livre numérique disponible au téléchargement', false);
        }
        if ($phys && $c['printer_email'] !== '') {
            $u = self::mailUrl('/imprimeur/commande/' . $o['id']);
            self::mail($c['printer_email'], 'Nouvelle commande ' . $o['id'] . ' à fabriquer', '<p>Bonjour,</p><p>Une nouvelle commande de la boutique ' . e(Club::s('site')) . ' est payée et prête à fabriquer : <b>' . e($o['id']) . '</b>, ' . count($o['items']) . ' article(s).</p>' . self::itemsHtml($o) . ($u !== '' ? '<p>Fichiers d’impression, adresse de livraison et suivi : <a href="' . e($u) . '">espace imprimeur</a>.</p>' : '<p>Fichiers d’impression, adresse de livraison et suivi : dans l’espace imprimeur du site (lien non joint : adresse du site non réglée).</p>'));
        }
        if ($c['alert_email'] !== '') {
            $u = self::mailUrl('/admin/boutique/commandes/' . $o['id']);
            self::mail($c['alert_email'], 'Boutique : commande ' . $o['id'] . ' payée (' . self::money($o['total']) . ')', '<p>Commande <b>' . e($o['id']) . '</b> de ' . e($o['customer']['name']) . ' payée : ' . e(self::money($o['total'])) . '.</p>' . self::itemsHtml($o) . ($u !== '' ? '<p><a href="' . e($u) . '">Voir dans le back-office</a></p>' : '<p>' . e(self::NO_LINK_TEAM) . '</p>'));
        }
        Accounts::fetchFee($o['id']);
    }

    /**
     * Fichier d'impression de chaque article (storage/shop/pdf/{commande}-{n}.pdf), sous le verrou
     * de la commande : une seule composition à la fois (le livre prend plusieurs minutes). Les
     * fichiers déjà composés ne sont pas refaits ($all : tout recomposer).
     */
    public static function buildPdfs(array $o, bool $all = false): void
    {
        $lock = self::pdfLock($o['id'], true);
        try {
            self::composePdfs($o, $all);
        } finally {
            self::pdfUnlock($lock);
        }
    }

    /** Verrou de composition d'une commande ; $wait = false : null si une composition est en cours. @return resource|null */
    private static function pdfLock(string $id, bool $wait)
    {
        @mkdir(self::dir() . '/pdf', 0775, true);
        $fp = fopen(self::dir() . '/pdf/' . preg_replace('/[^A-Z0-9-]/', '', $id) . '.lock', 'c');
        if (!$fp) {
            throw new \RuntimeException('Verrou de composition impossible : ' . $id);
        }
        if (!flock($fp, $wait ? LOCK_EX : LOCK_EX | LOCK_NB)) {
            fclose($fp);
            return null;
        }
        return $fp;
    }

    /** @param resource|null $fp */
    private static function pdfUnlock($fp): void
    {
        if ($fp) {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }

    /** Écrit un fichier d'impression d'un coup (fichier temporaire puis rename) : jamais lu à moitié écrit. */
    private static function savePdf(string $path, string $pdf): void
    {
        $tmp = $path . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (file_put_contents($tmp, $pdf) === false || !rename($tmp, $path)) {
            @unlink($tmp);
            throw new \RuntimeException('Écriture impossible : ' . $path);
        }
    }

    private static function composePdfs(array $o, bool $all): void
    {
        foreach ($o['items'] as $n => $it) {
            if (!$all && is_file(self::pdfPath($o['id'], $n))) {
                continue;
            }
            if ($it['model'] === BookShop::MODEL) {
                // Livre : numéro d'exemplaire attribué une fois pour toutes, puis composition complète.
                $num = BookShop::number($o['id'] . '-' . ($n + 1));
                $pdf = BookShop::pdf($it['values'], $num);
                if ($pdf !== '') {
                    self::savePdf(self::pdfPath($o['id'], $n), $pdf);
                }
                continue;
            }
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
                self::savePdf(self::pdfPath($o['id'], $n), $pdf);
            }
        }
    }

    public static function pdfPath(string $id, int $n): string
    {
        return self::dir() . '/pdf/' . preg_replace('/[^A-Z0-9-]/', '', $id) . '-' . ($n + 1) . '.pdf';
    }

    /**
     * Fichier d'impression (refait s'il manque). Composition déjà en cours pour cette commande (un
     * autre clic, l'envoi après paiement) : null tout de suite, sans relancer une seconde composition.
     */
    public static function pdf(array $o, int $n): ?string
    {
        if (!isset($o['items'][$n]) || !in_array($o['status'], ['paid', 'production', 'shipped', 'delivered'], true)) {
            return null;
        }
        $p = self::pdfPath($o['id'], $n);
        if (!is_file($p)) {
            $lock = self::pdfLock($o['id'], false);
            if ($lock === null) {
                return null;
            }
            try {
                if (!is_file($p)) {
                    self::composePdfs($o, false);
                }
            } finally {
                self::pdfUnlock($lock);
            }
        }
        return is_file($p) ? (string) file_get_contents($p) : null;
    }

    // ------------------------------------------------------------------ fabrication, expédition, messages

    /** Étapes d'où l'on peut passer à chaque étape (contrôlé sous verrou, sur la commande relue). */
    private const FROM = [
        'canceled' => ['pending'], // une commande payée s'annule en la remboursant
        'paid' => ['paid', 'production', 'shipped', 'delivered'],
        'production' => ['paid', 'production', 'shipped', 'delivered'],
        'shipped' => ['paid', 'production', 'shipped', 'delivered'],
        'delivered' => ['paid', 'production', 'shipped', 'delivered'],
        'refunded' => ['paid', 'production', 'shipped', 'delivered'],
    ];

    /** Le passage de $from à $to est-il permis ? (« en attente » ne s'obtient qu'à la création, « payée » d'abord par markPaid) */
    public static function canMove(string $from, string $to): bool
    {
        return in_array($from, self::FROM[$to] ?? [], true);
    }

    /**
     * Changement d'étape ; le client est prévenu par e-mail (sauf $notify = false). Transition
     * vérifiée sous verrou : jamais une commande payée écrasée par « annulée » (expiration, Stripe
     * en retard, deux onglets). null si la commande est introuvable ou le passage refusé.
     */
    public static function setStatus(string $id, string $status, string $by, string $note = '', bool $notify = true, array $tracking = []): ?array
    {
        if (!isset(self::STATUSES[$status])) {
            return null;
        }
        $moved = false;
        $o = self::update($id, function ($x) use ($status, $by, $note, $tracking, &$moved) {
            if (!self::canMove((string) $x['status'], $status)) {
                return $x;
            }
            $moved = true;
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
        if (!$o || !$moved) {
            return null;
        }
        if ($status === 'canceled' && !empty($o['promo']['code'])) {
            Promos::release($o['promo']['code'], $o['id']); // commande jamais payée : le code redevient disponible
        }
        if ($notify) {
            $t = $o['tracking'];
            $msg = [
                'production' => 'Votre commande est en cours de fabrication chez notre imprimeur.',
                'shipped' => 'Votre commande est expédiée !' . ($t['number'] !== '' ? ' Transporteur : ' . e($t['carrier'] ?: '—') . ', numéro de suivi : <b>' . e($t['number']) . '</b>' . ($t['url'] !== '' ? ' (<a href="' . e($t['url']) . '">suivre le colis</a>)' : '') . '.' : ''),
                'delivered' => 'Votre commande est indiquée comme livrée. Bonne réception, et merci de soutenir l’association !',
                'canceled' => 'Votre commande est annulée.' . ($note !== '' ? ' ' . e($note) : ''),
                'refunded' => 'Votre commande est remboursée (' . e(self::money($o['refunded'])) . ') sur le moyen de paiement utilisé.',
            ][$status] ?? null;
            if ($msg) {
                self::mail($o['customer']['email'], 'Commande ' . $o['id'] . ' : ' . mb_strtolower(self::STATUSES[$status]), '<p>Bonjour ' . e($o['customer']['name']) . ',</p><p>' . $msg . '</p>' . self::followHtml(self::mailUrl('/boutique/commande/' . $o['token'] . '/'), 'Suivi et questions'), self::config()['printer_email'] ?: null);
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
                $u = self::mailUrl('/imprimeur/commande/' . $o['id']);
                self::mail($c['printer_email'], 'Question du client · commande ' . $o['id'], '<p>' . e($o['customer']['name']) . ' écrit :</p>' . $body . ($u !== '' ? '<p><a href="' . e($u) . '">Répondre dans l’espace imprimeur</a></p>' : '<p>Répondez dans l’espace imprimeur du site.</p>'), $o['customer']['email']);
            }
        } else {
            self::mail($o['customer']['email'], 'Réponse à propos de votre commande ' . $o['id'], '<p>Bonjour ' . e($o['customer']['name']) . ',</p>' . $body . self::followHtml(self::mailUrl('/boutique/commande/' . $o['token'] . '/'), 'Pour répondre'), $from === 'imprimeur' ? ($c['printer_email'] ?: null) : null);
        }
        return $o;
    }

    /** Remboursement (total ou partiel, centimes) par Stripe, demandé depuis le back-office. */
    public static function refund(string $id, int $cents, string $by): array
    {
        // Montant décidé sous verrou, sur la commande relue ; la cible (déjà remboursé + ce
        // remboursement) est notée avant l'appel à Stripe, pour que le webhook « charge.refunded »
        // arrivé entre-temps ne fasse pas compter ce remboursement deux fois.
        $err = null;
        $target = 0;
        $o = self::update($id, function ($x) use (&$cents, &$err, &$target) {
            if ((int) $x['paid'] <= 0) {
                $err = 'Commande non payée.';
                return $x;
            }
            if (!empty($x['ext']['refund_pending']) && time() - (int) ($x['ext']['refund_pending_at'] ?? 0) < 600) {
                $err = 'Un remboursement est déjà en cours sur cette commande : rechargez la page dans un instant.';
                return $x;
            }
            $left = (int) $x['paid'] - (int) $x['refunded'];
            $cents = $cents > 0 ? min($cents, $left) : $left;
            if ($cents <= 0) {
                $err = 'Déjà entièrement remboursée.';
                return $x;
            }
            $target = (int) $x['refunded'] + $cents;
            $x['ext']['refund_pending'] = $target;
            $x['ext']['refund_pending_at'] = time();
            return $x;
        });
        if (!$o) {
            return ['error' => 'Commande non payée.'];
        }
        if ($err !== null) {
            return ['error' => $err];
        }
        $pi = (string) ($o['ext']['stripe_pi'] ?? '');
        if ($pi !== '') {
            try {
                self::stripeCall('POST', 'refunds', ['payment_intent' => $pi, 'amount' => $cents, 'metadata' => ['commande' => $id]]);
            } catch (\Throwable $e) {
                self::update($id, function ($x) {
                    unset($x['ext']['refund_pending'], $x['ext']['refund_pending_at']);
                    return $x;
                });
                return ['error' => $e->getMessage()];
            }
        }
        $o = self::update($id, function ($x) use ($cents, $by, $pi, $target) {
            unset($x['ext']['refund_pending'], $x['ext']['refund_pending_at']);
            // Jamais plus que la cible : le webhook a pu déjà la noter (cumul Stripe).
            $x['refunded'] = max((int) $x['refunded'], $target);
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
        if ($pi === '') {
            return;
        }
        foreach ((array) (JsonStore::read(self::file(), []) ?: []) as $o) {
            if (($o['ext']['stripe_pi'] ?? '') !== $pi) {
                continue;
            }
            // $amountRefunded est le cumul remboursé chez Stripe : on garde le plus grand des deux,
            // comparé sous verrou (jamais ajouté au montant déjà noté).
            $o = self::update($o['id'], function ($x) use ($amountRefunded) {
                $before = (int) $x['refunded'];
                if ($amountRefunded <= $before) {
                    return $x;
                }
                $x['refunded'] = $amountRefunded;
                // Remboursement lancé depuis le back-office (en cours) : c'est lui qui l'inscrit à l'historique.
                if ($amountRefunded > (int) ($x['ext']['refund_pending'] ?? 0)) {
                    $x['history'][] = ['at' => date('c'), 'status' => $x['status'], 'by' => 'Stripe', 'note' => 'Remboursement de ' . self::money($amountRefunded - $before) . ' fait chez Stripe'];
                }
                return $x;
            });
            if ($o && (int) $o['refunded'] >= (int) $o['paid'] && empty($o['ext']['refund_pending'])) {
                self::setStatus($o['id'], 'refunded', 'Stripe'); // sans effet si déjà « remboursée »
            }
        }
    }

    /**
     * Commandes jamais payées : annulées après deux jours (tâche planifiée). Avant d'annuler, la
     * session de paiement est relue chez Stripe (paiement arrivé sans que le webhook soit passé) ;
     * Stripe injoignable : la commande attend la prochaine fois.
     */
    public static function expire(): int
    {
        $n = 0;
        foreach (self::all() as $o) {
            if ($o['status'] !== 'pending' || strtotime($o['created']) >= time() - 172800) {
                continue;
            }
            $sid = (string) ($o['ext']['stripe_session'] ?? '');
            if ($sid !== '') {
                if (!self::payable()) {
                    continue; // clés Stripe absentes : impossible de vérifier, on n'annule pas à l'aveugle
                }
                try {
                    self::stripeSession(self::stripeCall('GET', 'checkout/sessions/' . rawurlencode($sid)));
                } catch (\Throwable $e) {
                    error_log('[boutique] expiration ' . $o['id'] . ' : ' . $e->getMessage());
                    continue;
                }
                $now = self::get($o['id'])['status'] ?? '';
                if ($now !== 'pending') {
                    $n += $now === 'canceled' ? 1 : 0; // session expirée chez Stripe : annulée ; payée : rattrapée
                    continue;
                }
            }
            if (self::setStatus($o['id'], 'canceled', 'système', 'Paiement non abouti.', false)) {
                $n++;
            }
        }
        return $n;
    }

    // ------------------------------------------------------------------ utilitaires

    /** Lien de suivi du client (affichage dans le site ; dans un e-mail, mailUrl). */
    public static function trackingUrl(array $o): string
    {
        return base_url() . '/boutique/commande/' . $o['token'] . '/';
    }

    public const NO_LINK_CLIENT = 'Le lien n’a pas pu être joint à ce message : répondez-nous en indiquant le numéro de votre commande, nous vous l’enverrons.';
    public const NO_LINK_TEAM = 'Lien non joint : l’adresse du site n’est pas réglée (Réglages › Général). Commande à retrouver dans Boutique › Commandes.';

    /**
     * Adresse absolue pour un e-mail : uniquement l'adresse du site réglée (jamais l'en-tête Host
     * de la requête, qu'un tiers pourrait falsifier pour détourner un lien secret). Chaîne vide si
     * elle n'est pas réglée : le lien n'est alors pas envoyé.
     */
    public static function mailUrl(string $path): string
    {
        $b = mail_base_url();
        if ($b === '') {
            error_log('[boutique] adresse du site non réglée (Réglages › Général) : lien ' . $path . ' non envoyé');
            return '';
        }
        return $b . $path;
    }

    /** Paragraphe « suivi » d'un e-mail au client (ou le message de remplacement sans lien). */
    private static function followHtml(string $url, string $label): string
    {
        return $url !== '' ? '<p>' . e($label) . ' : <a href="' . e($url) . '">' . e($url) . '</a></p>' : '<p>' . e(self::NO_LINK_CLIENT) . '</p>';
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
        $html .= '<p style="font-size:12px;color:#777777">' . e(Club::disclaimer()) . '</p>';
        if (self::$mail) {
            (self::$mail)($to, $subject, $html, $replyTo);
            return;
        }
        Mailer::send($to, $subject, $html, $replyTo);
    }
}
