<?php
/**
 * Boutique, lot B : prix et options du client (couleur, taille et position du texte), commande,
 * paiement Stripe (faux Stripe), fichiers d'impression, étapes de l'imprimeur, messages,
 * e-mails, remboursement. Usage : php tests/boutique-commandes.php. Les données de la boutique
 * (storage/shop) et les modèles sont remis en place à la fin.
 */
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Data\Collections;
use App\Shop\Catalog;
use App\Shop\Orders;

$fail = 0;
$eq = function (string $label, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    if (!$ok) {
        $fail++;
    }
    echo ($ok ? 'OK   ' : 'FAIL ') . $label . ($ok ? '' : ' => ' . json_encode($got, JSON_UNESCAPED_UNICODE) . ' attendu ' . json_encode($exp, JSON_UNESCAPED_UNICODE)) . "\n";
};
$mfile = Collections::DIR . '/' . Catalog::MODELS_FILE . '.json';
$mbackup = is_file($mfile) ? (string) file_get_contents($mfile) : null;
$shop = STORAGE_PATH . '/shop';
$sbackup = STORAGE_PATH . '/shop-test-backup-' . getmypid();
if (is_dir($shop)) {
    rename($shop, $sbackup);
}

$mails = [];
Orders::$mail = function ($to, $subject, $html) use (&$mails) { $mails[] = [$to, $subject, $html]; };
$calls = [];
Orders::$stripe = function ($method, $path, $params) use (&$calls) {
    $calls[] = [$method, $path, $params];
    if ($path === 'checkout/sessions') {
        return ['id' => 'cs_test_1', 'url' => 'https://checkout.stripe.com/c/pay/cs_test_1'];
    }
    if (str_starts_with($path, 'checkout/sessions/')) {
        return ['id' => 'cs_test_1', 'payment_status' => 'paid', 'payment_intent' => 'pi_test_1', 'amount_total' => 5890, 'metadata' => ['commande' => $GLOBALS['oid']]];
    }
    return ['id' => 're_1'];
};

Orders::saveConfig(['open' => true, 'printer_name' => 'Imprimerie essai', 'printer_email' => 'imprimeur@example.org', 'shipping' => 590, 'free_from' => 6000, 'alert_email' => 'asso@example.org']);

// 1. Un modèle en vente : prix, supplément XXL, options.
$m = Catalog::saveModel(['name' => 'T-shirt essai', 'support' => 'tshirt', 'color' => '#0E1F4D', 'active' => true,
    'sale' => ['price' => 2500, 'extra' => ['XXL' => 300], 'colors' => ['#0E1F4D', '#1A1A1A'], 'text_colors' => ['#F6C400', '#FFFFFF', '#ABCDEF'], 'text_sizes' => true, 'positions' => true],
    'faces' => ['avant' => ['layers' => [
        ['id' => 'logo', 'type' => 'logo', 'x' => 100, 'y' => 20, 'w' => 80],
        ['id' => 'p', 'type' => 'text', 'x' => 20, 'y' => 150, 'w' => 240, 'h' => 40, 'text' => 'Né pour rugir.', 'size' => 60, 'min' => 20, 'fit' => true, 'upper' => true, 'color' => '#F6C400', 'mode' => 'client', 'field' => 'phrase', 'label' => 'Votre slogan', 'list' => 'slogans'],
    ]]]]);
$m = Catalog::find($m['id']);
$eq('vente : en vente, prix, XXL +3 €, couleurs de texte limitées à la charte', [Catalog::sellable($m), Catalog::price($m, 'M'), Catalog::price($m, 'XXL'), $m['sale']['text_colors']], [true, 2500, 2800, ['#F6C400', '#FFFFFF']]);
[$mm, $opt] = Catalog::applyOptions($m, ['color' => '#1A1A1A', 'tcolor' => '#FFFFFF', 'tsize' => 'l', 'pos' => 'haut']);
$t = $mm['faces']['avant']['layers'][1];
$eq('options : couleur du produit, texte blanc, grand (×1,15), en haut ; choix interdit ignoré', [$mm['color'], $t['color'], $t['size'], $t['y'] < 150, Catalog::applyOptions($m, ['color' => '#FF0000'])[1]['color']], ['#1A1A1A', '#FFFFFF', 69.0, true, '#0E1F4D']);
$pos = Catalog::positions($m);
$eq('positions proposées : seulement celles qui ne recouvrent pas le logo', [isset($pos['haut']), isset($pos['bas'])], [false, true]);

// 2. Panier et commande.
$bad = Orders::line(['model' => $m['id'], 'size' => 'M', 'values' => ['phrase' => 'Texte inventé']]);
$eq('ligne refusée : phrase hors liste', isset($bad['error']), true);
$r = Orders::create([['model' => $m['id'], 'size' => 'XXL', 'qty' => 1, 'values' => ['phrase' => 'Né pour rugir.'], 'opts' => ['tcolor' => '#FFFFFF']], ['model' => $m['id'], 'size' => 'M', 'qty' => 1, 'values' => ['phrase' => 'Mon cœur bat à Bonal.']]], ['name' => 'Jeanne Essai', 'email' => 'jeanne.essai@example.org', 'line1' => '1 rue du Stade', 'zip' => '25600', 'city' => 'Sochaux', 'country' => 'FR']);
$o = $r['order'];
$GLOBALS['oid'] = $o['id'];
$eq('commande : sous-total 53 €, port offert dès 60 € ? non → 5,90 €, total 58,90 €', [$o['subtotal'], $o['shipping'], $o['total'], $o['status']], [5300, 590, 5890, 'pending']);
$co = Orders::checkout($o, 'https://www.example.org');
$eq('paiement : session Stripe avec 3 lignes (2 articles + livraison) et la référence de la commande', [$co['url'] ?? '', count($calls[0][2]['line_items']), $calls[0][2]['metadata']['commande']], ['https://checkout.stripe.com/c/pay/cs_test_1', 3, $o['id']]);
Orders::syncReturn(Orders::get($o['id']), 'cs_test_1');
$o = Orders::get($o['id']);
$eq('retour de paiement : payée, référence Stripe, PDF d’impression de chaque article', [$o['status'], $o['ext']['stripe_pi'], is_file(Orders::pdfPath($o['id'], 0)), str_starts_with((string) Orders::pdf($o, 1), '%PDF-')], ['paid', 'pi_test_1', true, true]);
$to = array_column($mails, 0);
$eq('e-mails : client, imprimeur, association', [in_array('jeanne.essai@example.org', $to, true), in_array('imprimeur@example.org', $to, true), in_array('asso@example.org', $to, true)], [true, true, true]);
Orders::stripeSession(['id' => 'cs_test_1', 'payment_status' => 'paid', 'payment_intent' => 'pi_test_1', 'metadata' => ['commande' => $o['id']]]);
$eq('webhook reçu après le retour : pas de second traitement', count(array_filter(Orders::get($o['id'])['history'], fn ($h) => $h['status'] === 'paid')), 1);

// 3. Imprimeur : fabrication, expédition, messages.
$mails = [];
Orders::setStatus($o['id'], 'production', 'imprimeur');
Orders::setStatus($o['id'], 'shipped', 'imprimeur', '', true, ['carrier' => 'La Poste', 'number' => '6A123', 'url' => 'https://www.laposte.fr/outils/suivre-vos-envois?code=6A123']);
$o = Orders::get($o['id']);
$eq('étapes : expédiée avec numéro de suivi ; client prévenu à chaque étape', [$o['status'], $o['tracking']['number'], count($mails), str_contains($mails[1][2], '6A123')], ['shipped', '6A123', 2, true]);
$mails = [];
Orders::message($o['id'], 'client', 'Bonjour, quand arrive mon colis ?');
Orders::message($o['id'], 'imprimeur', 'Demain matin !');
$eq('messages : question du client à l’imprimeur, réponse au client', [$mails[0][0], $mails[1][0], count(Orders::get($o['id'])['messages'])], ['imprimeur@example.org', 'jeanne.essai@example.org', 2]);
$eq('suivi client : retrouvée par son lien secret, pas par un faux', [Orders::byToken($o['token'])['id'] ?? '', Orders::byToken(str_repeat('a', 32))], [$o['id'], null]);

// 4. Remboursement.
$calls = [];
$rf = Orders::refund($o['id'], 1000, 'admin');
$o = Orders::get($o['id']);
$eq('remboursement partiel de 10 € par Stripe', [$rf['cents'] ?? 0, $calls[0][1], $calls[0][2]['amount'], $o['refunded'], $o['status']], [1000, 'refunds', 1000, 1000, 'shipped']);
Orders::refund($o['id'], 0, 'admin');
$eq('remboursement du reste : commande remboursée', [Orders::get($o['id'])['refunded'], Orders::get($o['id'])['status']], [5890, 'refunded']);

// Remise en place.
Orders::$stripe = Orders::$mail = null;
Catalog::deleteModel($m['id']);
if ($mbackup !== null) {
    file_put_contents($mfile, $mbackup);
}
$rm = function (string $d) use (&$rm) {
    foreach (glob($d . '/{,.}*', GLOB_BRACE) ?: [] as $f) {
        if (basename($f) === '.' || basename($f) === '..') {
            continue;
        }
        is_dir($f) ? $rm($f) : unlink($f);
    }
    rmdir($d);
};
$rm($shop);
if (is_dir($sbackup)) {
    rename($sbackup, $shop);
}
echo $fail ? "\n$fail échec(s)\n" : "\nTout est bon.\n";
exit($fail ? 1 : 0);
