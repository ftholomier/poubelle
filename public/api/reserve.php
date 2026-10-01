<?php
declare(strict_types=1);

/**
 * POST : « Ce bureau m'intéresse » depuis une fiche. Pas de vente en ligne :
 * la demande est enregistrée, l'équipe est prévenue et rappelle le visiteur.
 */

require __DIR__ . '/../../app/bootstrap.php';

use App\Api;
use App\Content;
use App\I18n;
use App\Mailer;
use App\Offices;
use App\Requests;
use App\Router;
use App\Text;
use App\View;

Api::boot();
Api::requireMethod('POST');

$input = $_POST !== [] ? $_POST : Api::jsonBody();
$lang = Api::lang($input);
$guard = Api::guard($input, 'reserve');

$name = Api::str($input, 'name', 120);
$email = Api::email($input);
$phone = Api::str($input, 'phone', 40);
$startDate = Api::str($input, 'startDate', 60);
$officeId = Api::str($input, 'officeId', 80);

if ($name === '' || $email === '') {
    Api::fail(I18n::t('form.required'), 422);
}
Api::requireCallback($input, $phone);

$office = Offices::findPublished($officeId);
if ($office === null) {
    Api::fail(I18n::t('form.error'), 404);
}
$decorated = Offices::decorate($office, $lang);

$saved = Requests::add([
    'type' => 'reserve',
    'spam' => $guard['quarantine'],
    'spamScore' => $guard['score'],
    'spamReasons' => $guard['reasons'],
    'name' => $name,
    'email' => $email,
    'phone' => $phone,
    'subject' => $decorated['name'],
    'officeId' => $officeId,
    'startDate' => $startDate,
    'lang' => $lang,
    'source' => 'fiche-bureau',
    'consent' => true,
]);

$settings = Content::settings();
$siteName = (string) ($settings['site']['name'] ?? 'Le Signal');
$sitePhone = (string) ($settings['contact']['phone'] ?? '');
$isRented = ($office['status'] ?? '') === 'rented';

// Message à l'équipe : toujours en français, avec ce qu'il faut pour rappeler.
$html = '<p><strong>' . ($isRented ? 'Intérêt pour un bureau réservé' : 'Nouvelle demande pour un bureau') . '</strong> (réf. ' . Text::e($saved['ref']) . ')</p>'
    . '<p>Bureau : ' . Text::e(Offices::decorate($office, 'fr')['name']) . ' — ' . Text::e($decorated['priceLabel']) . ' HT/mois — ' . Text::e(Offices::statusLabel((string) ($office['status'] ?? ''))) . '</p>'
    . '<p>' . Text::e($name) . ' — <a href="tel:' . Text::e((string) preg_replace('/[^0-9+]/', '', $phone)) . '">' . Text::e($phone) . '</a> — ' . Text::e($email) . '</p>'
    . ($startDate !== '' ? '<p>Entrée souhaitée : ' . Text::e($startDate) . '</p>' : '')
    . '<p class="muted">Consentement au rappel téléphonique donné. Langue du visiteur : ' . Text::e($lang) . '.</p>'
    . '<p><a class="btn" href="' . Text::e(Router::absolute('office', 'fr', ['id' => $officeId])) . '">Voir la fiche</a></p>';
// En quarantaine, rien ne part : ni l'alerte à l'équipe, ni l'accusé au visiteur.
if (!$guard['quarantine']) {
    Mailer::send(Mailer::inbox(), ($isRented ? 'Intérêt (réservé) — ' : 'Demande bureau — ') . Offices::decorate($office, 'fr')['name'] . ' — ' . $name, $html, '', $email);
    $fill = ['name' => $name, 'office' => $decorated['name'], 'price' => $decorated['priceLabel'], 'site' => $siteName, 'ref' => $saved['ref'], 'phone' => $sitePhone];
    Mailer::send(
        $email,
        View::fill(I18n::t('mail.reserveSubject'), $fill),
        '<p>' . Text::e(View::fill(I18n::t('mail.hello'), $fill)) . '</p>'
        . '<p>' . Text::e(View::fill(I18n::t($isRented ? 'mail.notifyBody' : 'mail.reserveBody'), $fill)) . '</p>'
        . ($sitePhone !== '' ? '<p>' . Text::e(View::fill(I18n::t('mail.callUs'), $fill)) . '</p>' : '')
        . '<p class="muted">' . Text::e(View::fill(I18n::t('mail.ref'), $fill)) . '</p>'
    );
}

Api::respond(['ok' => true, 'ref' => $saved['ref'], 'message' => I18n::t('form.sentReserve')]);
