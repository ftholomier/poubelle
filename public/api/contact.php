<?php
declare(strict_types=1);

/** POST : demande de contact. */

require __DIR__ . '/../../app/bootstrap.php';

use App\Api;
use App\Content;
use App\I18n;
use App\Mailer;
use App\Requests;
use App\Text;
use App\View;

Api::boot();
Api::requireMethod('POST');

$input = $_POST !== [] ? $_POST : Api::jsonBody();
$lang = Api::lang($input);
$guard = Api::guard($input, 'contact');

$name = Api::str($input, 'name', 120);
$email = Api::email($input);
$phone = Api::str($input, 'phone', 40);
$need = Api::str($input, 'need', 80);
$message = Api::str($input, 'message', 4000);

if ($name === '' || $email === '' || $message === '') {
    Api::fail(I18n::t('form.required'), 422);
}
Api::requireCallback($input, $phone);

$saved = Requests::add([
    'type' => 'contact',
    'spam' => $guard['quarantine'],
    'spamScore' => $guard['score'],
    'spamReasons' => $guard['reasons'],
    'name' => $name,
    'email' => $email,
    'phone' => $phone,
    'subject' => $need !== '' ? $need : 'Demande de contact',
    'message' => $message,
    'lang' => $lang,
    'source' => 'contact',
    'consent' => true,
]);

$settings = Content::settings();
$siteName = (string) ($settings['site']['name'] ?? 'Le Signal');
$sitePhone = (string) ($settings['contact']['phone'] ?? '');

$html = '<p><strong>Nouvelle demande de contact</strong> (réf. ' . Text::e($saved['ref']) . ')</p>'
    . '<p>' . Text::e($name) . ' — <a href="tel:' . Text::e((string) preg_replace('/[^0-9+]/', '', $phone)) . '">' . Text::e($phone) . '</a> — ' . Text::e($email) . '</p>'
    . ($need !== '' ? '<p>Besoin : ' . Text::e($need) . '</p>' : '')
    . '<p>' . nl2br(Text::e($message)) . '</p>'
    . '<p class="muted">Consentement au rappel téléphonique donné. Langue du visiteur : ' . Text::e($lang) . '.</p>';
// Une demande en quarantaine n'est jamais transmise : elle attend votre avis
// au back-office. Le visiteur, lui, reçoit la même réponse que les autres.
if (!$guard['quarantine']) {
    Mailer::send(Mailer::inbox(), 'Contact — ' . $name, $html, '', $email);
}

if (!$guard['quarantine'] && !empty($settings['contact']['autoReply'])) {
    $fill = ['name' => $name, 'site' => $siteName, 'ref' => $saved['ref'], 'phone' => $sitePhone];
    Mailer::send(
        $email,
        View::fill(I18n::t('mail.contactSubject'), $fill),
        '<p>' . Text::e(View::fill(I18n::t('mail.hello'), $fill)) . '</p>'
        . '<p>' . Text::e(I18n::t('form.sentContact')) . '</p>'
        . ($sitePhone !== '' ? '<p>' . Text::e(View::fill(I18n::t('mail.callUs'), $fill)) . '</p>' : '')
        . '<p class="muted">' . Text::e(View::fill(I18n::t('mail.ref'), $fill)) . '</p>'
    );
}

Api::respond(['ok' => true, 'ref' => $saved['ref'], 'message' => I18n::t('form.sentContact')]);
