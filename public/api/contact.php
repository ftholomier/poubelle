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

if ($name === '' || $email === '') {
    Api::fail(I18n::t('form.required'), 422);
}

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
]);

$html = '<p><strong>Nouvelle demande de contact</strong> (réf. ' . Text::e($saved['ref']) . ')</p>'
    . '<p>' . Text::e($name) . ' — ' . Text::e($email) . ($phone !== '' ? ' — ' . Text::e($phone) : '') . '</p>'
    . ($need !== '' ? '<p>Besoin : ' . Text::e($need) . '</p>' : '')
    . ($message !== '' ? '<p>' . nl2br(Text::e($message)) . '</p>' : '')
    . '<p class="muted">Reçu depuis le site, langue « ' . Text::e($lang) . ' ».</p>';
// Une demande en quarantaine n'est jamais transmise : elle attend votre avis
// au back-office. Le visiteur, lui, reçoit la même réponse que les autres.
if (!$guard['quarantine']) {
    Mailer::send(Mailer::inbox(), 'Contact — ' . $name, $html, '', $email);
}

$settings = Content::settings();
if (!$guard['quarantine'] && !empty($settings['contact']['autoReply'])) {
    Mailer::send($email, 'Nous avons bien reçu votre message — Le iOiO', '<p>Bonjour ' . Text::e($name) . ',</p><p>' . Text::e(I18n::t('form.sentContact')) . '</p>');
}

Api::respond(['ok' => true, 'ref' => $saved['ref'], 'message' => I18n::t('form.sentContact')]);
