<?php
declare(strict_types=1);

/** POST : email laissé dans la pop-up de sortie. */

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
$guard = Api::guard($input, 'lead');

$email = Api::email($input);
if ($email === '') {
    Api::fail(I18n::t('form.required'), 422);
}

$saved = Requests::add([
    'type' => 'lead',
    'spam' => $guard['quarantine'],
    'spamScore' => $guard['score'],
    'spamReasons' => $guard['reasons'],
    'email' => $email,
    'subject' => 'Rappel des disponibilités',
    'lang' => $lang,
    'source' => Api::str($input, 'source', 40) ?: 'exit-intent',
]);

// En quarantaine, aucun email ne part : l'adresse est peut-être celle d'un
// tiers que l'on ne veut surtout pas solliciter à sa place.
if ($guard['quarantine']) {
    Api::respond(['ok' => true, 'ref' => $saved['ref'], 'message' => I18n::t('exit.sent')]);
}

// Réponse immédiate au visiteur, dans sa langue : la liste réelle des bureaux libres.
$siteName = (string) (Content::settings()['site']['name'] ?? 'Le Signal');
$rows = '';
foreach (Offices::decorateAll(Offices::filter(Offices::published(), ['status' => 'available']), $lang) as $office) {
    $rows .= '<p>• <strong>' . Text::e($office['name']) . '</strong> — ' . Text::e($office['area'])
        . ' — ' . Text::e($office['priceLabel']) . ' ' . Text::e(I18n::t('office.perMonthShort')) . ' — '
        . '<a href="' . Text::e(Router::absolute('office', $lang, ['id' => (string) $office['id']])) . '">' . Text::e(I18n::t('mail.view')) . '</a></p>';
}
Mailer::send(
    $email,
    View::fill(I18n::t('mail.leadSubject'), ['site' => $siteName]),
    '<p>' . Text::e(I18n::t('mail.leadIntro')) . '</p>'
    . ($rows !== '' ? $rows : '<p>' . Text::e(I18n::t('mail.leadNone')) . '</p>')
    . '<p><a class="btn" href="' . Text::e(Router::absolute('contact', $lang)) . '">' . Text::e(I18n::t('mail.leadCta')) . '</a></p>'
    . '<p class="muted">' . Text::e(I18n::t('mail.leadFooter')) . '</p>'
);

Mailer::send(Mailer::inbox(), 'Nouvelle demande de dispos', '<p>' . Text::e($email) . ' a demandé les disponibilités (réf. ' . Text::e($saved['ref']) . ').</p>');

Api::respond(['ok' => true, 'ref' => $saved['ref'], 'message' => I18n::t('exit.sent')]);
