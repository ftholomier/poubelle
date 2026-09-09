<?php
declare(strict_types=1);

/** POST : question à l'assistant → réponse + sources. */

require __DIR__ . '/../../app/bootstrap.php';

use App\Ai\Gemini;
use App\Api;
use App\Content;
use App\Csrf;
use App\I18n;
use App\RateLimit;

Api::boot();
Api::requireMethod('POST');

$input = $_POST !== [] ? $_POST : Api::jsonBody();
$lang = Api::lang($input);

$settings = Content::settings();
if (empty($settings['ai']['enabled'])) {
    Api::fail('L\'assistant est désactivé.', 403);
}
if (!Csrf::check((string) ($input['csrf'] ?? ''), 'chat')) {
    Api::fail(I18n::t('form.csrf'), 419);
}
// 20 questions par heure et par IP, question de 500 caractères maximum.
if (!RateLimit::allow('chat', 20, 3600)) {
    Api::respond(['ok' => true, 'answer' => I18n::t('bot.throttled'), 'sources' => [], 'actions' => []]);
}

$question = trim(mb_substr((string) ($input['q'] ?? ''), 0, 500));
if ($question === '') {
    Api::fail('Question vide.', 422);
}

$history = [];
foreach ((array) ($input['history'] ?? []) as $turn) {
    if (!\is_array($turn)) {
        continue;
    }
    $history[] = [
        'role' => ($turn['role'] ?? '') === 'user' ? 'user' : 'model',
        'text' => mb_substr((string) ($turn['text'] ?? ''), 0, 800),
    ];
}

$result = Gemini::ask($question, $lang, $history);

Api::respond([
    'ok' => true,
    'answer' => $result['answer'],
    'sources' => $result['sources'],
    'actions' => $result['actions'] ?? [],
    'engine' => $result['engine'],
]);
