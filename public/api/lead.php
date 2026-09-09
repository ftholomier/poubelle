<?php
declare(strict_types=1);

/** POST : email laissé dans la pop-up de sortie. */

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
Api::guard($input, 'lead', 3, 600);

$email = Api::email($input);
if ($email === '') {
    Api::fail(I18n::t('form.required'), 422);
}

$saved = Requests::add([
    'type' => 'lead',
    'email' => $email,
    'subject' => 'Rappel des disponibilités',
    'lang' => $lang,
    'source' => Api::str($input, 'source', 40) ?: 'exit-intent',
]);

// Réponse immédiate au visiteur : la liste réelle des bureaux libres.
$rows = '';
foreach (Offices::decorateAll(Offices::filter(Offices::published(), ['status' => 'available']), $lang) as $office) {
    $rows .= '<p>• <strong>' . Text::e($office['name']) . '</strong> — ' . Text::e($office['area'])
        . ' — ' . Text::e($office['priceLabel']) . ' HT/mois — '
        . '<a href="' . Text::e($office['url'] === '' ? '#' : Router::absolute('office', $lang, ['id' => (string) $office['id']])) . '">voir la fiche</a></p>';
}
Mailer::send(
    $email,
    'Les bureaux libres au iOiO',
    '<p>Bonjour,</p><p>Voici ce qui est réellement disponible aujourd\'hui :</p>'
    . ($rows !== '' ? $rows : '<p>Tout est loué pour le moment — nous vous prévenons dès qu\'une place se libère.</p>')
    . '<p><a class="btn" href="' . Text::e(Router::absolute('contact', $lang)) . '">Réserver une visite</a></p>'
    . '<p class="muted">Vous recevez cet email parce que vous avez demandé les disponibilités sur notre site. Aucune relance commerciale.</p>'
);

Mailer::send(Mailer::inbox(), 'Nouvelle demande de dispos', '<p>' . Text::e($email) . ' a demandé les disponibilités (réf. ' . Text::e($saved['ref']) . ').</p>');

Api::respond(['ok' => true, 'ref' => $saved['ref'], 'message' => I18n::t('exit.sent')]);
