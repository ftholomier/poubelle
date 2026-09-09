<?php
declare(strict_types=1);

/** POST : réservation d'un bureau depuis sa fiche. */

require __DIR__ . '/../../app/bootstrap.php';

use App\Api;
use App\I18n;
use App\Mailer;
use App\Offices;
use App\Requests;
use App\Router;
use App\Text;

Api::boot();
Api::requireMethod('POST');

$input = $_POST !== [] ? $_POST : Api::jsonBody();
$lang = Api::lang($input);
Api::guard($input, 'reserve');

$name = Api::str($input, 'name', 120);
$email = Api::email($input);
$phone = Api::str($input, 'phone', 40);
$startDate = Api::str($input, 'startDate', 60);
$officeId = Api::str($input, 'officeId', 80);

if ($name === '' || $email === '') {
    Api::fail(I18n::t('form.required'), 422);
}

$office = Offices::findPublished($officeId);
if ($office === null) {
    Api::fail('Ce bureau n\'est plus disponible à la réservation.', 404);
}
$decorated = Offices::decorate($office, $lang);

$saved = Requests::add([
    'type' => 'reserve',
    'name' => $name,
    'email' => $email,
    'phone' => $phone,
    'subject' => $decorated['name'],
    'officeId' => $officeId,
    'startDate' => $startDate,
    'lang' => $lang,
    'source' => 'fiche-bureau',
]);

$html = '<p><strong>Nouvelle réservation</strong> (réf. ' . Text::e($saved['ref']) . ')</p>'
    . '<p>Bureau : ' . Text::e($decorated['name']) . ' — ' . Text::e($decorated['priceLabel']) . ' HT/mois — ' . Text::e($decorated['statusLabel']) . '</p>'
    . '<p>' . Text::e($name) . ' — ' . Text::e($email) . ($phone !== '' ? ' — ' . Text::e($phone) : '') . '</p>'
    . ($startDate !== '' ? '<p>Entrée souhaitée : ' . Text::e($startDate) . '</p>' : '')
    . '<p><a class="btn" href="' . Text::e(Router::absolute('office', $lang, ['id' => $officeId])) . '">Voir la fiche</a></p>';
Mailer::send(Mailer::inbox(), 'Réservation — ' . $decorated['name'], $html, '', $email);

Mailer::send(
    $email,
    'Votre demande pour ' . $decorated['name'] . ' — Le iOiO',
    '<p>Bonjour ' . Text::e($name) . ',</p>'
    . '<p>Nous avons bien reçu votre demande pour <strong>' . Text::e($decorated['name']) . '</strong> ('
    . Text::e($decorated['priceLabel']) . ' HT/mois, tout compris). Nous revenons vers vous sous 24 h ouvrées avec deux créneaux de visite.</p>'
    . '<p class="muted">Référence : ' . Text::e($saved['ref']) . '</p>'
);

Api::respond(['ok' => true, 'ref' => $saved['ref'], 'message' => I18n::t('form.sentReserve')]);
