<?php
/**
 * Boutique, dons et adhésions : corrections de la revue (lot 3). Identifiants de commande,
 * anecdotes pièces uniques, expédition des commandes numériques, liens des e-mails, Stripe
 * (webhook rejoué, expiration relue), codes promo réservés, transitions sous verrou,
 * remboursements, fichiers d'impression, reçus fiscaux, lien imprimeur en POST, adhésions.
 * Usage : php tests/lot3-boutique.php. Aucun appel réseau (faux Stripe) ; storage/shop, les dons,
 * les adhésions, le journal des e-mails et les modèles sont remis en place à la fin.
 */
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

\App\Core\Session::start();

use App\Core\JsonStore;
use App\Core\Request;
use App\Core\Settings;
use App\Data\Collections;
use App\Front\Donations;
use App\Shop\Accounts;
use App\Shop\Anecdotes;
use App\Shop\BookShop;
use App\Shop\Catalog;
use App\Shop\Orders;
use App\Shop\PrinterSpace;
use App\Shop\Promos;
use App\Vitrine\Membership;

$fail = 0;
$eq = function (string $label, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    if (!$ok) {
        $fail++;
    }
    echo ($ok ? 'OK   ' : 'FAIL ') . $label . ($ok ? '' : ' => ' . json_encode($got, JSON_UNESCAPED_UNICODE) . ' attendu ' . json_encode($exp, JSON_UNESCAPED_UNICODE)) . "\n";
};

// ---------------------------------------------------------------- sauvegardes (remises en place à la fin)
$mfile = Collections::DIR . '/' . Catalog::MODELS_FILE . '.json';
$mbackup = is_file($mfile) ? (string) file_get_contents($mfile) : null;
$shop = STORAGE_PATH . '/shop';
$sbackup = STORAGE_PATH . '/shop-test-backup-lot3-' . getmypid();
if (is_dir($shop)) {
    rename($shop, $sbackup);
}
$tmp = sys_get_temp_dir() . '/lot3-test-' . bin2hex(random_bytes(4));
mkdir($tmp, 0700, true);
$guarded = [STORAGE_PATH . '/dons', Membership::FILE, STORAGE_PATH . '/mail/' . date('Y-m') . '.jsonl', STORAGE_PATH . '/livres', STORAGE_PATH . '/activity/' . date('Y-m') . '.jsonl'];
$saved = [];
foreach ($guarded as $i => $p) {
    $saved[$i] = file_exists($p);
    if ($saved[$i]) {
        exec('cp -a ' . escapeshellarg($p) . ' ' . escapeshellarg("$tmp/$i"));
    }
}
register_shutdown_function(function () use ($guarded, $saved, $tmp, $shop, $sbackup, $mfile, $mbackup) {
    Orders::$stripe = Orders::$mail = Orders::$newId = null;
    Donations::$stripe = null;
    foreach ($guarded as $i => $p) {
        exec('rm -rf ' . escapeshellarg($p));
        foreach (glob($p . '.lock') ?: [] as $l) {
            @unlink($l);
        }
        if ($saved[$i]) {
            exec('cp -a ' . escapeshellarg("$tmp/$i") . ' ' . escapeshellarg($p));
        }
    }
    exec('rm -rf ' . escapeshellarg($tmp));
    exec('rm -rf ' . escapeshellarg($shop));
    if (is_dir($sbackup)) {
        rename($sbackup, $shop);
    }
    if ($mbackup !== null) {
        file_put_contents($mfile, $mbackup);
    }
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
});

// Réglages en mémoire seulement : adresse du site, aucun e-mail réellement envoyé.
$values = new ReflectionProperty(Settings::class, 'values');
$setting = function (array $changes) use ($values) {
    $values->setValue(null, $changes + Settings::all());
};
$setting(['general.base_url' => 'https://musee.example.org', 'mail.from_email' => '', 'general.contact_email' => '', 'donations.tax_receipts' => false, 'donations.mode' => 'test']);
$_SERVER['HTTP_HOST'] = 'pirate.example'; // en-tête Host falsifié : ne doit jamais finir dans un lien envoyé

$mails = [];
$mailThrow = false;
Orders::$mail = function ($to, $subject, $html) use (&$mails, &$mailThrow) {
    if ($mailThrow) {
        $mailThrow = false;
        throw new RuntimeException('serveur de courrier indisponible');
    }
    $mails[] = [$to, $subject, $html];
};
$calls = [];
$GLOBALS['sessions'] = [];
$GLOBALS['onRefund'] = null;
Orders::$stripe = function ($method, $path, $params) use (&$calls) {
    $calls[] = [$method, $path, $params];
    if ($path === 'checkout/sessions') {
        $id = 'cs_' . bin2hex(random_bytes(4));
        return ['id' => $id, 'url' => 'https://checkout.stripe.com/c/pay/' . $id];
    }
    if (str_starts_with($path, 'checkout/sessions/')) {
        $s = $GLOBALS['sessions'][basename($path)] ?? null;
        if ($s === null) {
            throw new RuntimeException('Stripe injoignable');
        }
        return $s;
    }
    if (str_starts_with($path, 'payment_intents/')) {
        return ['id' => basename($path), 'latest_charge' => ['id' => 'ch_1', 'balance_transaction' => ['fee' => 50, 'net' => 1000]]];
    }
    if ($path === 'refunds') {
        if ($GLOBALS['onRefund']) {
            ($GLOBALS['onRefund'])($params);
        }
        return ['id' => 're_1'];
    }
    if ($path === 'coupons') {
        return ['id' => 'co_1'];
    }
    return ['id' => 'x'];
};

Orders::saveConfig(['open' => true, 'printer_name' => 'Imprimerie essai', 'printer_email' => 'imprimeur@example.org', 'shipping' => 590, 'free_from' => 0, 'ship_cost' => 400, 'alert_email' => 'asso@example.org']);
$m = Catalog::saveModel(['name' => 'T-shirt lot 3', 'support' => 'tshirt', 'color' => '#0E1F4D', 'active' => true,
    'sale' => ['price' => 2000, 'colors' => ['#0E1F4D']],
    'faces' => ['avant' => ['layers' => [
        ['id' => 'p', 'type' => 'text', 'x' => 20, 'y' => 150, 'w' => 240, 'h' => 40, 'text' => 'Né pour rugir.', 'size' => 60, 'min' => 20, 'fit' => true, 'mode' => 'client', 'field' => 'phrase', 'label' => 'Votre slogan', 'list' => 'slogans'],
    ]]]]);
$an = Catalog::saveModel(['name' => 'Mug anecdote lot 3', 'support' => 'tshirt', 'color' => '#0E1F4D', 'active' => true,
    'sale' => ['price' => 1500, 'colors' => ['#0E1F4D']],
    'faces' => ['avant' => ['layers' => [
        ['id' => 'a', 'type' => 'text', 'x' => 0, 'y' => 0, 'w' => 250, 'h' => 60, 'text' => 'Anecdote', 'mode' => 'client', 'field' => 'anecdote', 'max' => 140, 'fit' => true, 'size' => 20, 'min' => 6, 'font' => 'serif-b'],
    ]]]]);
$cust = fn (string $who) => ['name' => ucfirst($who) . ' Essai', 'email' => $who . '@example.org', 'line1' => '1 rue du Stade', 'zip' => '25600', 'city' => 'Sochaux', 'country' => 'FR'];
$tee = [['model' => $m['id'], 'size' => 'M', 'qty' => 1, 'values' => ['phrase' => 'Né pour rugir.']]];
$file = STORAGE_PATH . '/shop/orders.json';
$hist = fn (string $id) => array_column(Orders::get($id)['history'], 'note');

// ---------------------------------------------------------------- 1. identifiant de commande
$o1 = Orders::create($tee, $cust('jeanne'))['order'];
$eq('1. identifiant : SRaammjj-8 hex-8 hex (8 octets aléatoires), accepté par l’espace imprimeur', [(bool) preg_match('/^SR\d{6}-[0-9A-F]{8}-[0-9A-F]{8}$/', $o1['id']), (bool) preg_match('#^/imprimeur/commande/([A-Z0-9-]{6,32})$#', '/imprimeur/commande/' . $o1['id'])], [true, true]);
Orders::markPaid($o1['id'], 'pi_1', $o1['total']);
$seq = [$o1['id'], 'SR' . date('ymd') . '-AAAAAAAA-BBBBBBBB'];
Orders::$newId = function () use (&$seq) { return array_shift($seq); };
$o2 = Orders::create($tee, $cust('paul'))['order'];
Orders::$newId = null;
$eq('1. collision d’identifiant : la commande payée n’est pas écrasée, la nouvelle prend un autre identifiant', [Orders::get($o1['id'])['status'], Orders::get($o1['id'])['customer']['email'], $o2['id'], Orders::get($o2['id'])['customer']['email']], ['paid', 'jeanne@example.org', 'SR' . date('ymd') . '-AAAAAAAA-BBBBBBBB', 'paul@example.org']);

// ---------------------------------------------------------------- 2. anecdote : pièce unique
$txt = 'Les Lionceaux ont un sacré palmarès, et un public fidèle.';
$anItem = fn (string $sig) => [['model' => $an['id'], 'size' => 'M', 'qty' => 1, 'values' => ['anecdote' => $txt, '_sig_anecdote' => $sig]]];
$eq('2. anecdote signée pour un autre modèle : refusée', isset(Orders::create($anItem(Anecdotes::sign($txt, $m['id'])), $cust('lea'))['error']), true);
$a1 = Orders::create($anItem(Anecdotes::sign($txt, $an['id'])), $cust('lea'))['order'] ?? null;
$a2 = Orders::create($anItem(Anecdotes::sign($txt, $an['id'])), $cust('max'))['order'] ?? null;
$eq('2. deux paniers avec la même anecdote encore libre : commandes créées', [$a1 !== null, $a2 !== null], [true, true]);
$eq('2. la même anecdote deux fois dans une commande : refusée', isset(Orders::create(array_merge($anItem(Anecdotes::sign($txt, $an['id'])), $anItem(Anecdotes::sign($txt, $an['id']))), $cust('zoe'))['error']), true);
$mails = [];
Orders::markPaid($a1['id'], 'pi_a1', $a1['total']);
Orders::markPaid($a2['id'], 'pi_a2', $a2['total']);
$eq('2. payées toutes deux : la première a l’anecdote, la seconde est signalée (à rembourser), alerte e-mail et tableau de bord', [
    isset(Orders::get($a1['id'])['anecdote_conflict']), Orders::get($a2['id'])['anecdote_conflict'] ?? null,
    count(array_filter($mails, fn ($x) => $x[0] === 'asso@example.org' && str_contains($x[1], 'anecdote déjà vendue'))),
    in_array('anecdote:' . $a2['id'], array_column(Accounts::alerts(), 'key'), true),
], [false, [1], 1, true]);
$eq('2. anecdote vendue : une nouvelle commande est refusée', Orders::create($anItem(Anecdotes::sign($txt, $an['id'])), $cust('ines'))['error'] ?? '', 'Cette anecdote vient d’être vendue : tirez-en une autre avec le bouton « Une anecdote ».');

// ---------------------------------------------------------------- 3. commandes numériques : ni expédition ni imprimeur
$book = ['model' => BookShop::MODEL, 'name' => BookShop::NAME, 'support' => 'Livre numérique (PDF), à télécharger', 'size' => '', 'color' => '', 'color_name' => '', 'opts' => [], 'values' => ['format' => 'numerique', 'nom' => 'Essai'], 'qty' => 1, 'unit' => 900, 'total' => 900, 'cost' => 0, 'rate' => 0.0, 'digital' => true];
$mk = function (string $id, array $items, string $email) use ($file) {
    $o = ['id' => $id, 'token' => bin2hex(random_bytes(16)), 'created' => date('c'), 'status' => 'pending', 'customer' => ['name' => 'Num Essai', 'email' => $email, 'phone' => '', 'line1' => '1 rue', 'line2' => '', 'zip' => '25600', 'city' => 'Sochaux', 'country' => 'FR'],
        'items' => $items, 'subtotal' => array_sum(array_column($items, 'total')), 'discount' => 0, 'promo' => null, 'shipping' => 0, 'total' => array_sum(array_column($items, 'total')), 'paid' => 0, 'refunded' => 0,
        'tracking' => ['carrier' => '', 'number' => '', 'url' => ''], 'history' => [], 'messages' => [], 'ext' => []];
    JsonStore::update($file, function ($all) use ($o) { $all[$o['id']] = $o; return $all; }, []);
    @mkdir(dirname(Orders::pdfPath($id, 0)), 0775, true);
    foreach ($items as $n => $it) {
        if (BookShop::digital($it)) {
            file_put_contents(Orders::pdfPath($id, $n), '%PDF-1.4 livre essai'); // déjà composé : pas de composition du livre dans le test
        }
    }
    return $o;
};
$dig = $mk('SR' . date('ymd') . '-D1617000-00000001', [$book], 'num@example.org');
$tshirtItem = Orders::line($tee[0])['item'];
$mix = $mk('SR' . date('ymd') . '-D1617000-00000002', [$book, $tshirtItem], 'mix@example.org');
$mails = [];
Orders::markPaid($dig['id'], 'pi_dig', 900);
Orders::markPaid($mix['id'], 'pi_mix', $mix['total']);
$st = Accounts::statement(date('Y-m'));
$eq('3. livre numérique seul : pas de coût d’expédition, livré ; mixte : coût d’expédition', [Orders::get($dig['id'])['ship_cost'], Orders::get($dig['id'])['status'], Orders::get($mix['id'])['ship_cost']], [0, 'delivered', 400]);
$eq('3. relevé imprimeur : commande numérique absente, pas d’« Expédition » ni de livre numérique pour elle', [in_array($dig['id'], array_column($st['rows'], 'order'), true), count(array_filter($st['rows'], fn ($r) => $r['order'] === $mix['id'])), in_array(BookShop::NAME, array_column(array_filter($st['rows'], fn ($r) => $r['order'] === $mix['id']), 'name'), true)], [false, 2, false]);
$eq('3. sommes : expédition comptée 0 pour une commande numérique (même ancienne, sans ship_cost noté)', [Accounts::sums([Orders::get($dig['id'])])['ship_cost'], Accounts::sums([array_diff_key(Orders::get($dig['id']), ['ship_cost' => 1])])['ship_cost']], [0, 0]);
$eq('3. imprimeur : ne voit pas la commande numérique, voit la mixte', [PrinterSpace::visible($dig['id']), PrinterSpace::visible($mix['id'])['id'] ?? ''], [null, $mix['id']]);
$eq('3. pas d’e-mail à l’imprimeur pour la commande numérique', count(array_filter($mails, fn ($x) => $x[0] === 'imprimeur@example.org' && str_contains($x[1], $dig['id']))), 0);

// ---------------------------------------------------------------- 4. liens des e-mails : adresse réglée seulement
$all = implode("\n", array_column($mails, 2));
$eq('4. adresse réglée : liens des e-mails sur https://musee.example.org, jamais l’en-tête Host', [str_contains($all, 'https://musee.example.org/imprimeur/commande/' . $mix['id']), str_contains($all, 'https://musee.example.org/admin/boutique/commandes/'), str_contains($all, 'pirate.example')], [true, true, false]);
$setting(['general.base_url' => '']);
$mails = [];
$o4 = Orders::create($tee, $cust('nora'))['order'];
Orders::markPaid($o4['id'], 'pi_4', $o4['total']);
$all = implode("\n", array_column($mails, 2));
$eq('4. adresse non réglée : aucun lien (ni Host falsifié), message clair à la place', [str_contains($all, 'pirate.example'), str_contains($all, 'href="/'), str_contains($all, $o4['token']), str_contains($all, 'Lien non joint'), str_contains($all, 'Le lien n’a pas pu être joint')], [false, false, false, true, true]);
$r = PrinterSpace::handle(new Request('POST', '/imprimeur', [], ['_csrf' => \App\Core\Session::csrfToken(), 'email' => 'imprimeur@example.org'], [], ['REMOTE_ADDR' => '10.9.8.' . random_int(1, 250), 'HTTP_HOST' => 'pirate.example'], ''));
$eq('4. lien de connexion imprimeur : non envoyé sans adresse réglée, message clair, aucun jeton créé', [str_contains($r->body, 'adresse du site n’est pas réglée'), (array) JsonStore::read(STORAGE_PATH . '/shop/printer-links.json', [])], [true, []]);
$setting(['general.base_url' => 'https://musee.example.org']);

// ---------------------------------------------------------------- 13. lien imprimeur : GET affiche un bouton, POST consomme
$token = bin2hex(random_bytes(24));
JsonStore::write(STORAGE_PATH . '/shop/printer-links.json', [hash('sha256', $token) => ['email' => 'imprimeur@example.org', 'until' => time() + 600]]);
\App\Core\Session::forget('printer');
$r = PrinterSpace::handle(new Request('GET', '/imprimeur/lien/' . $token, [], [], [], ['REMOTE_ADDR' => '10.9.9.1'], ''));
JsonStore::forget();
$eq('13. GET du lien (antivirus) : page avec bouton, jeton intact, pas connecté', [$r->status, str_contains($r->body, 'method="post"'), isset(JsonStore::read(STORAGE_PATH . '/shop/printer-links.json', [])[hash('sha256', $token)]), PrinterSpace::logged()], [200, true, true, false]);
$r = PrinterSpace::handle(new Request('POST', '/imprimeur/lien/' . $token, [], ['_csrf' => 'faux'], [], ['REMOTE_ADDR' => '10.9.9.1'], ''));
JsonStore::forget();
$eq('13. POST sans jeton CSRF valable : refusé, jeton intact', [$r->status, isset(JsonStore::read(STORAGE_PATH . '/shop/printer-links.json', [])[hash('sha256', $token)])], [419, true]);
$r = PrinterSpace::handle(new Request('POST', '/imprimeur/lien/' . $token, [], ['_csrf' => \App\Core\Session::csrfToken()], [], ['REMOTE_ADDR' => '10.9.9.1'], ''));
JsonStore::forget();
$eq('13. POST avec CSRF : connecté, jeton consommé', [$r->status, PrinterSpace::logged(), isset(JsonStore::read(STORAGE_PATH . '/shop/printer-links.json', [])[hash('sha256', $token)])], [302, true, false]);
\App\Core\Session::forget('printer');

// ---------------------------------------------------------------- 6. codes promo réservés à la création
Promos::save(['code' => 'UNE', 'type' => 'amount', 'value' => 500, 'max' => 1, 'once' => false]);
$p1 = Orders::create($tee, $cust('alice'), 'UNE')['order'] ?? null;
$p2 = Orders::create($tee, $cust('bob'), 'UNE');
$eq('6. code à une utilisation : réservé par la 1re commande (non payée), refusé à la 2e', [$p1 !== null, $p2['error'] ?? '', Promos::find('UNE')['uses'][0]['state'] ?? ''], [true, 'Ce code promo a déjà été utilisé le nombre de fois prévu.', 'reserved']);
$eq('6. commande refusée : rien d’enregistré', count(array_filter(Orders::all(), fn ($x) => $x['customer']['email'] === 'bob@example.org')), 0);
Orders::setStatus($p1['id'], 'canceled', 'système', 'Paiement non abouti.', false);
$p3 = Orders::create($tee, $cust('bob'), 'UNE')['order'] ?? null;
$eq('6. 1re commande annulée sans paiement : code libéré, la 2e passe', [$p3 !== null, array_column(Promos::find('UNE')['uses'], 'order')], [true, [$p3['id'] ?? '']]);
Orders::markPaid($p3['id'], 'pi_p3', $p3['total']);
$eq('6. payée : utilisation confirmée', Promos::find('UNE')['uses'][0]['state'] ?? '', 'used');
Promos::save(['code' => 'CLIENT', 'type' => 'percent', 'value' => 10, 'once' => true]);
$c1 = Orders::create($tee, $cust('carla'), 'CLIENT')['order'];
$c2 = Orders::create($tee, $cust('carla'), 'CLIENT')['order'] ?? null;
$eq('6. une fois par client : le client qui recommence sa commande garde son code (l’ancienne, non payée, est annulée)', [$c2 !== null, Orders::get($c1['id'])['status'], count(Promos::find('CLIENT')['uses'])], [true, 'canceled', 1]);
Orders::markPaid($c2['id'], 'pi_c2', $c2['total']);
$eq('6. une fois par client : après paiement, refusé', Orders::create($tee, $cust('carla'), 'CLIENT')['error'] ?? '', 'Vous avez déjà utilisé ce code promo.');

// ---------------------------------------------------------------- 7. transitions sous verrou
$eq('7. commande payée : « annulée » refusée (setStatus), statut gardé', [Orders::setStatus($o1['id'], 'canceled', 'système', '', false), Orders::get($o1['id'])['status']], [null, 'paid']);
$x7 = Orders::create($tee, $cust('hugo'))['order'];
Orders::checkout($x7, 'https://musee.example.org');
$sid7 = Orders::get($x7['id'])['ext']['stripe_session'];
Orders::markPaid($x7['id'], 'pi_7', $x7['total']);
Orders::stripeSession(['id' => $sid7, 'status' => 'expired', 'payment_status' => 'unpaid', 'metadata' => ['commande' => $x7['id']]]);
$eq('7. session Stripe « expirée » reçue après le paiement : la commande reste payée', Orders::get($x7['id'])['status'], 'paid');
$eq('7. « en fabrication » impossible pour une commande en attente ou annulée', [Orders::setStatus($p1['id'], 'production', 'imprimeur'), Orders::get($p1['id'])['status']], [null, 'canceled']);

// ---------------------------------------------------------------- 5. expiration : session relue chez Stripe avant d'annuler
$old = date('c', time() - 3 * 86400);
$e1 = Orders::create($tee, $cust('eva'))['order'];
Orders::checkout($e1, 'https://musee.example.org');
Promos::save(['code' => 'DEUX', 'type' => 'amount', 'value' => 100, 'max' => 5]);
$e2 = Orders::create($tee, $cust('eric'), 'DEUX')['order'];
Orders::checkout($e2, 'https://musee.example.org');
$e3 = Orders::create($tee, $cust('emma'))['order'];
Orders::checkout($e3, 'https://musee.example.org');
foreach ([$e1, $e2, $e3] as $x) {
    Orders::update($x['id'], fn ($y) => ['created' => $old] + $y);
}
$s1 = Orders::get($e1['id'])['ext']['stripe_session'];
$s2 = Orders::get($e2['id'])['ext']['stripe_session'];
$GLOBALS['sessions'][$s1] = ['id' => $s1, 'status' => 'complete', 'payment_status' => 'paid', 'payment_intent' => 'pi_e1', 'amount_total' => $e1['total'], 'metadata' => ['commande' => $e1['id']]];
$GLOBALS['sessions'][$s2] = ['id' => $s2, 'status' => 'expired', 'payment_status' => 'unpaid', 'metadata' => ['commande' => $e2['id']]];
// $e3 : Stripe injoignable pour sa session
$n = Orders::expire();
$eq('5. expiration : payée chez Stripe → payée (pas annulée) ; expirée → annulée et code libéré ; Stripe injoignable → attend', [Orders::get($e1['id'])['status'], Orders::get($e2['id'])['status'], in_array($e2['id'], array_column(Promos::find('DEUX')['uses'], 'order'), true), Orders::get($e3['id'])['status'], $n], ['paid', 'canceled', false, 'pending', 1]);

// Webhook Stripe : l'événement n'est noté « traité » qu'après un traitement réussi.
$secret = 'whsec_' . bin2hex(random_bytes(8));
$setting(['donations.stripe_webhook_secret' => $secret]);
$hook = function (array $ev) use ($secret) {
    $payload = json_encode($ev);
    $t = time();
    return Donations::stripeWebhook(new Request('POST', '/api/dons/stripe/webhook', [], [], [], ['HTTP_STRIPE_SIGNATURE' => 't=' . $t . ',v1=' . hash_hmac('sha256', $t . '.' . $payload, $secret), 'CONTENT_TYPE' => 'application/json'], $payload));
};
$w = Orders::create($tee, $cust('wanda'))['order'];
Orders::checkout($w, 'https://musee.example.org');
$ws = Orders::get($w['id'])['ext']['stripe_session'];
$ev = ['id' => 'evt_lot3_' . bin2hex(random_bytes(4)), 'type' => 'checkout.session.completed', 'data' => ['object' => ['id' => $ws, 'payment_status' => 'paid', 'status' => 'complete', 'payment_intent' => 'pi_w', 'amount_total' => $w['total'], 'metadata' => ['commande' => $w['id']]]]];
$mailThrow = true;
$r1 = $hook($ev);
JsonStore::forget();
$seen1 = isset(((array) JsonStore::read(STORAGE_PATH . '/dons/events.json', []))['stripe:' . $ev['id']]);
$r2 = $hook($ev);
JsonStore::forget();
$seen2 = isset(((array) JsonStore::read(STORAGE_PATH . '/dons/events.json', []))['stripe:' . $ev['id']]);
$r3 = $hook($ev);
$eq('5. webhook : échec du traitement → 500 et non noté (Stripe le renverra) ; renvoi → traité et noté ; 3e → doublon', [$r1->status, $seen1, $r2->status, $seen2, json_decode($r3->body, true)['duplicate'] ?? false, Orders::get($w['id'])['status']], [500, false, 200, true, true, 'paid']);

// ---------------------------------------------------------------- 8. remboursement : webhook arrivé pendant l'appel à Stripe
$rf = Orders::create($tee, $cust('rita'))['order'];
Orders::markPaid($rf['id'], 'pi_rf', $rf['total']);
$GLOBALS['onRefund'] = function ($params) {
    // charge.refunded reçu avant que le back-office n'écrive : cumul Stripe = montant demandé.
    Orders::refundedAtStripe('pi_rf', (int) $params['amount']);
};
$res = Orders::refund($rf['id'], 1000, 'admin');
$GLOBALS['onRefund'] = null;
$x = Orders::get($rf['id']);
$eq('8. remboursement de 10 € avec webhook pendant l’appel : compté une fois, une seule ligne d’historique', [$res['cents'] ?? 0, $x['refunded'], count(array_filter($x['history'], fn ($h) => str_starts_with((string) $h['note'], 'Remboursement'))), isset($x['ext']['refund_pending'])], [1000, 1000, 1, false]);
Orders::refundedAtStripe('pi_rf', 1000);
$eq('8. webhook rejoué avec le même cumul : rien de plus', Orders::get($rf['id'])['refunded'], 1000);
$mails = [];
Orders::refundedAtStripe('pi_rf', $rf['total']);
Orders::refundedAtStripe('pi_rf', $rf['total']);
$eq('8. remboursement du reste chez Stripe (deux fois) : « Remboursée », un seul e-mail au client', [Orders::get($rf['id'])['refunded'], Orders::get($rf['id'])['status'], count(array_filter($mails, fn ($m) => $m[0] === 'rita@example.org'))], [$rf['total'], 'refunded', 1]);

// ---------------------------------------------------------------- 9 et 11. back-office : montant et annulation
$ad = Orders::create($tee, $cust('adam'))['order'];
Orders::markPaid($ad['id'], 'pi_ad', $ad['total']);
$act = fn (array $post) => \App\Admin\Shop::orderAction(new Request('POST', '/admin/boutique/commandes/' . $ad['id'], [], $post, [], ['REMOTE_ADDR' => '127.0.0.1'], ''), $ad['id']);
$calls = [];
foreach (['-5', 'abc', '0', '0,00'] as $bad) {
    $act(['action' => 'refund', 'amount' => $bad]);
}
$eq('9. montant négatif, illisible ou nul : refusé (aucun remboursement, aucun appel à Stripe)', [Orders::get($ad['id'])['refunded'], count(array_filter($calls, fn ($c) => $c[1] === 'refunds'))], [0, 0]);
$act(['action' => 'refund', 'amount' => '2,50']);
$eq('9. montant valable (2,50 €) : remboursé', Orders::get($ad['id'])['refunded'], 250);
$act(['action' => 'status', 'status' => 'canceled']);
$eq('11. commande payée passée « Annulée » depuis le back-office : refusé (il faut rembourser)', Orders::get($ad['id'])['status'], 'paid');
$html = \App\Core\View::partial('shop/commande', ['o' => Orders::get($ad['id']), 'previews' => [], 'base' => '/admin/boutique/commandes/' . $ad['id'], 'who' => 'association']);
$eq('11. fiche de commande payée : pas d’option « Annulée », renvoi vers le remboursement', [str_contains($html, 'value="canceled"'), str_contains($html, 'remboursez-la'), str_contains($html, 'id="rembourser"')], [false, true, true]);
$act(['action' => 'refund', 'amount' => '']);
$eq('9. champ vide : tout le reste remboursé', [Orders::get($ad['id'])['refunded'], Orders::get($ad['id'])['status']], [$ad['total'], 'refunded']);

// ---------------------------------------------------------------- 10. fichiers d'impression et téléchargements
$pp = Orders::create($tee, $cust('pia'))['order'];
Orders::markPaid($pp['id'], 'pi_pp', $pp['total']);
$path = Orders::pdfPath($pp['id'], 0);
@unlink($path);
$lk = fopen(STORAGE_PATH . '/shop/pdf/' . $pp['id'] . '.lock', 'c');
flock($lk, LOCK_EX); // une composition est en cours (autre clic, autre requête)
$busy = Orders::pdf(Orders::get($pp['id']), 0);
flock($lk, LOCK_UN);
fclose($lk);
$eq('10. composition déjà en cours : pas de seconde composition, réponse « en cours » (null)', [$busy, is_file($path)], [null, false]);
$eq('10. verrou libre : fichier composé (écrit d’un coup, aucun .tmp qui traîne)', [str_starts_with((string) Orders::pdf(Orders::get($pp['id']), 0), '%PDF-'), glob(STORAGE_PATH . '/shop/pdf/*.tmp') ?: []], [true, []]);
$codes = [];
for ($i = 0; $i < BookShop::MAX_DOWNLOADS + 1; $i++) {
    $codes[] = \App\Shop\ShopPages::bookDownload(new Request('GET', '/', [], [], [], ['REMOTE_ADDR' => '127.0.0.1'], ''), Orders::get($dig['id'])['token'], '1')->status;
}
$eq('10. livre numérique : ' . BookShop::MAX_DOWNLOADS . ' téléchargements comptés sous verrou, le suivant renvoyé vers la page', [array_count_values($codes)[200] ?? 0, end($codes), Orders::get($dig['id'])['ext']['downloads'][0] ?? 0], [BookShop::MAX_DOWNLOADS, 302, BookShop::MAX_DOWNLOADS]);

// ---------------------------------------------------------------- 14. écritures sous verrou
Orders::saveConfig(['delay' => 'Sous 3 jours.']);
$cfg = Orders::config();
$eq('14. réglages : enregistrement partiel sous verrou, le reste gardé', [$cfg['delay'], $cfg['printer_email'], $cfg['ship_cost'], is_file(STORAGE_PATH . '/shop/config.json.lock')], ['Sous 3 jours.', 'imprimeur@example.org', 400, true]);
$mails = [];
$n1 = Accounts::mailAlerts();
$n2 = Accounts::mailAlerts();
$eq('14. alertes par e-mail : notées sous verrou, jamais envoyées deux fois', [$n1 > 0, $n2, count($mails), is_file(STORAGE_PATH . '/shop/alerts-sent.json.lock')], [true, 0, 1, true]);

// ---------------------------------------------------------------- 5 (dons), 12, 14 (dons) et 15
$don = fn (array $x) => array_replace_recursive(['id' => 'D' . date('ymd') . '-' . bin2hex(random_bytes(3)), 'created' => date('c'), 'updated' => date('c'), 'status' => 'pending', 'provider' => 'stripe', 'mode' => 'test', 'frequency' => 'once', 'amount' => 2000, 'receipt' => true,
    'donor' => ['first' => 'Dona', 'last' => 'Essai', 'email' => 'dona@example.org', 'address' => '', 'zip' => '', 'city' => '', 'country' => 'FR'], 'wall' => false, 'wall_name' => '', 'manage' => bin2hex(random_bytes(16)), 'payments' => [], 'ext' => []], $x);
JsonStore::write(Donations::FILE, []); // dons réels écartés (sauvegardés, remis à la fin) : aucun appel à un vrai prestataire
JsonStore::write(Membership::FILE, []);
$d1 = $don(['created' => date('c', time() - 3 * 86400), 'ext' => ['stripe_session' => 'cs_don_1']]);
$d2 = $don(['created' => date('c', time() - 3 * 86400), 'ext' => ['stripe_session' => 'cs_don_2']]);
Donations::put($d1);
Donations::put($d2);
Donations::$stripe = function ($method, $path) use ($d1, $d2) {
    if ($path === 'checkout/sessions/cs_don_1') {
        return ['id' => 'cs_don_1', 'mode' => 'payment', 'status' => 'complete', 'payment_status' => 'paid', 'payment_intent' => 'pi_don_1', 'amount_total' => 2000, 'metadata' => ['don' => $d1['id']]];
    }
    if ($path === 'checkout/sessions/cs_don_2') {
        return ['id' => 'cs_don_2', 'mode' => 'payment', 'status' => 'open', 'payment_status' => 'unpaid', 'metadata' => ['don' => $d2['id']]];
    }
    throw new RuntimeException('inattendu : ' . $path);
};
Donations::sync();
$eq('5. dons ponctuels Stripe en attente depuis 3 jours : relus chez Stripe (payé → payé, pas abandonné ; non payé → abandonné)', [Donations::get($d1['id'])['status'], Donations::get($d1['id'])['payments'][0]['ref'] ?? '', Donations::get($d2['id'])['status']], ['paid', 'pi_don_1', 'abandoned']);
$eq('14. statistiques des dons recalculées sous verrou', [is_file(STORAGE_PATH . '/dons/stats.json.lock'), Donations::rebuildStats()['raised'] >= 2000], [true, true]);

$setting(['donations.tax_receipts' => true]);
$y = (int) date('Y');
$d3 = $don(['status' => 'active', 'frequency' => 'month', 'payments' => [
    ['ref' => 'in_a', 'amount' => 1000, 'at' => ($y - 1) . '-11-05T10:00:00+01:00', 'status' => 'paid'],
    ['ref' => 'in_b', 'amount' => 1000, 'at' => ($y - 1) . '-12-05T10:00:00+01:00', 'status' => 'paid'],
    ['ref' => 'in_c', 'amount' => 1000, 'at' => $y . '-01-05T10:00:00+01:00', 'status' => 'paid'],
]]);
Donations::put($d3);
$r1 = Donations::issueReceipt($d3['id'], ['in_a', 'in_b', 'in_c']);
$r2 = Donations::issueReceipt($d3['id'], ['in_a', 'in_b']);
$pays = array_column(Donations::get($d3['id'])['payments'], 'receipt', 'ref');
$eq('12. reçu : une seule année par reçu ; redemandé pour les mêmes paiements → aucun second reçu', [substr((string) $r1, 0, 7), $pays['in_a'] ?? null, $pays['in_b'] ?? null, $pays['in_c'] ?? null, $r2], ['RF-' . ($y - 1), $r1, $r1, null, null]);
$r3 = Donations::issueReceipt($d3['id'], ['in_a', 'in_b', 'in_c']);
$eq('12. paiement de l’autre année : son propre reçu', [substr((string) $r3, 0, 7), Donations::get($d3['id'])['payments'][2]['receipt'] ?? null, is_file(Donations::RECEIPTS . '/' . $y . '/' . $r3 . '.pdf')], ['RF-' . $y, $r3, true]);

// 15. Adhésions : remboursement PayPal, expiration conditionnelle.
$ad1 = ['id' => Membership::newId(), 'created' => date('c'), 'updated' => date('c'), 'year' => $y, 'tariff' => 'individuel', 'label' => 'Adhésion individuelle', 'amount' => 1500,
    'status' => 'paid', 'provider' => 'paypal', 'mode' => 'test', 'member' => ['first' => 'Ad', 'last' => 'Hérent', 'email' => 'ad@example.org', 'phone' => '', 'address' => '', 'zip' => '', 'city' => '', 'family' => ''],
    'newsletter' => false, 'source' => '', 'ext' => ['paypal_order' => 'ORDER-LOT3'], 'payments' => [['ref' => 'CAPTURE-LOT3', 'amount' => 1500, 'at' => date('c')]], 'ip' => '', 'origin' => 'site'];
Membership::put($ad1);
$h = new ReflectionMethod(Donations::class, 'handlePaypalEvent');
$h->invoke(null, ['id' => 'WH-LOT3', 'event_type' => 'PAYMENT.CAPTURE.REFUNDED', 'resource' => ['id' => 'REF-1', 'links' => [['rel' => 'up', 'href' => 'https://api.paypal.com/v2/payments/captures/CAPTURE-LOT3']]]]);
$eq('15. remboursement PayPal d’une adhésion : « Remboursée » (comme pour Stripe)', Membership::get($ad1['id'])['status'], 'refunded');
$ad2 = array_replace($ad1, ['id' => Membership::newId(), 'status' => 'pending', 'provider' => 'stripe', 'created' => date('c', time() - 4 * 86400), 'ext' => [], 'payments' => []]);
$ad3 = array_replace($ad1, ['id' => Membership::newId(), 'status' => 'paid', 'provider' => 'stripe', 'created' => date('c', time() - 4 * 86400), 'ext' => []]);
Membership::put($ad2);
Membership::put($ad3);
$ne = Membership::expire();
$eq('15. adhésions : en attente depuis 4 jours → abandonnée ; payée → inchangée', [Membership::get($ad2['id'])['status'], Membership::get($ad3['id'])['status'], $ne], ['abandoned', 'paid', 1]);

// ---------------------------------------------------------------- remise en place (aussi faite à l'arrêt)
Catalog::deleteModel($m['id']);
Catalog::deleteModel($an['id']);
echo $fail ? "\n$fail échec(s)\n" : "\nTout est bon.\n";
exit($fail ? 1 : 0);
