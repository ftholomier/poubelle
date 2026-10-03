<?php
/**
 * Fiches audio (App\Services\FicheAudio) : résumés automatiques, texte retenu (main, IA,
 * automatique), voix enregistrée, rangement des résultats d'un traitement groupé, coût à
 * moitié prix. Usage : php tests/audio.php (code de sortie 1 en cas d'échec). N'écrit que
 * dans un dossier temporaire.
 */
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Core\JsonStore;
use App\Data\Fiches;
use App\Services\AiCosts;
use App\Services\FicheAudio as A;

$tmp = sys_get_temp_dir() . '/audio-test-' . bin2hex(random_bytes(4));
A::$dir = "$tmp/etat";
A::$media = "$tmp/media";
A::$mp3 = false;
AiCosts::$dir = "$tmp/ia";
$fail = 0;
$eq = function (string $label, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    if (!$ok) {
        $fail++;
    }
    echo ($ok ? 'OK   ' : 'FAIL ') . $label . ($ok ? '' : ' => ' . json_encode($got, JSON_UNESCAPED_UNICODE) . ' attendu ' . json_encode($exp, JSON_UNESCAPED_UNICODE)) . "\n";
};
$has = fn (string $label, string $text, array $parts) => $eq($label, array_values(array_filter($parts, fn ($p) => !str_contains($text, $p))), []);

// Résumés automatiques.
$match = Fiches::get(2431);
$t = A::template($match, 'fr');
$has('match : date, stade, score, buteurs', $t, ['Dimanche 28 février 1988, Stade Auguste Bonal.', '24e journée de D2 : Sochaux bat Le Puy 2 à 1, devant 6795 spectateurs.', 'Buts : Sauzée 37e et 61e pour Sochaux ; Faure 72e pour Le Puy.', 'évacuation de la neige']);
$eq('match : 75 mots au plus', A::words($t) <= A::WORDS, true);
$t = A::template(Fiches::get(22054), 'fr');
$has('penalty dit en toutes lettres', $t, ['Durbant 74e sur penalty']);
$t = A::template(Fiches::get(3000), 'fr');
$eq('personne : poste et années', str_starts_with($t, 'Joël Bats, gardien de but du FC Sochaux-Montbéliard de 1974 à 1980.'), true);
$eq('personne : naissance pas répétée', substr_count($t, 'Mont-de-Marsan'), 1);
$en = $match;
$en['i18n']['en'] = ['title' => 'Sochaux – Le Puy', 'match' => ['breves' => ['The stadium staff cleared the snow from 8 am.']]];
$t = A::template($en, 'en');
$has('version anglaise', $t, ['Sunday 28 February 1988', 'Sochaux beat Le Puy 2–1, in front of 6795 spectators.', 'Goals: Sauzée 37th and 61st for Sochaux', 'cleared the snow']);
$eq('langues d’une fiche traduite', A::langs($en), ['fr', 'en']);
$eq('découpage à la fin d’une phrase', A::fit(['Un deux trois.', 'Quatre cinq six. Sept huit neuf dix onze.'], 7), 'Un deux trois. Quatre cinq six.');

// Texte retenu : main > IA (si la fiche n'a pas changé) > automatique.
$id = 2431;
$eq('par défaut : automatique', A::current($match, 'fr')['src'], 'auto');
A::saveText($id, 'fr', 'Résumé de l’IA.', 'ai', A::sig($match, 'fr'));
$eq('texte de l’IA', A::current($match, 'fr'), ['text' => 'Résumé de l’IA.', 'src' => 'ai', 'outdated' => false]);
$changed = $match;
$changed['match']['score']['home'] = 3;
$eq('fiche modifiée : retour à l’automatique', [A::current($changed, 'fr')['src'], A::current($changed, 'fr')['outdated']], ['auto', true]);
$changed2 = $match;
$changed2['gallery'] = [];
$eq('changement sans rapport (photos) : texte de l’IA gardé', A::current($changed2, 'fr')['src'], 'ai');
A::saveText($id, 'fr', 'Texte écrit par un historien.', 'manual');
$eq('texte écrit à la main', A::current($changed, 'fr')['src'], 'manual');
A::resetText($id, 'fr');
$eq('retour à l’automatique', A::current($match, 'fr')['src'], 'auto');
$eq('réponse de l’IA nettoyée', A::cleanAi("**« Le match du siècle. »**"), 'Le match du siècle.');

// Voix enregistrée.
$pcm = str_repeat(pack('v', 1000), 24000);
$wav = A::wav($pcm, 24000);
$eq('en-tête WAV', [substr($wav, 0, 4), substr($wav, 8, 4), unpack('V', substr($wav, 40, 4))[1], strlen($wav)], ['RIFF', 'WAVE', 48000, 48044]);
$cur = A::current($match, 'fr');
$v = A::storeVoice($id, 'fr', $pcm, 24000, $cur['text'], 'gemini-3.8-flash-tts', 'Charon');
$eq('fichier audio', [is_file(A::$media . '/' . $v['file']), $v['dur'], str_ends_with($v['file'], '.wav')], [true, 1.0, true]);
$eq('voix valable pour le texte lu', A::audio($match, 'fr')['url'] ?? null, '/media/' . $v['file']);
$eq('page : voix IA proposée', A::forPage($match, 'fr')['url'] ?? null, '/media/' . $v['file']);
$eq('texte changé : voix plus valable', A::audio($changed, 'fr'), null);
$eq('page anglaise d’une fiche non traduite : en français', A::forPage($match, 'en')['lang'] ?? null, 'fr-FR');
$v2 = A::storeVoice($id, 'fr', $pcm . $pcm, 24000, $cur['text'], 'gemini-3.8-flash-tts', 'Charon');
$eq('ancienne voix supprimée', [is_file(A::$media . '/' . $v['file']), is_file(A::$media . '/' . $v2['file'])], [false, true]);
A::deleteVoice($id, 'fr');
$eq('voix supprimée', [A::audio($match, 'fr'), is_file(A::$media . '/' . $v2['file'])], [null, false]);

// Rangement des résultats d'un traitement groupé (fichier JSONL de Google).
@mkdir(A::$dir . '/jobs', 0775, true);
$job = ['id' => 'vtest', 'kind' => 'voix', 'keys' => ['2431-fr', '3000-fr'], 'state' => 'recup', 'model' => 'gemini-3.8-flash-tts', 'voice' => 'Kore', 'then_voice' => false, 'cursor' => 0, 'done' => 0, 'errors' => 0, 'by' => 'Essai'];
JsonStore::write(A::$dir . '/jobs/vtest-textes.json', ['2431-fr' => A::current($match, 'fr')['text'], '3000-fr' => 'Texte du joueur.']);
$audio = ['candidates' => [['content' => ['parts' => [['inlineData' => ['mimeType' => 'audio/L16;codec=pcm;rate=24000', 'data' => base64_encode($pcm)]]]]]], 'usageMetadata' => ['promptTokenCount' => 100, 'candidatesTokenCount' => 750]];
file_put_contents(A::$dir . '/jobs/vtest-resultats.jsonl', json_encode(['key' => '2431-fr', 'response' => $audio]) . "\n" . json_encode(['key' => '3000-fr', 'error' => ['message' => 'refusé']]) . "\n");
$job = A::process($job, microtime(true) + 30);
$eq('traitement groupé rangé', [$job['state'], $job['done'], $job['errors']], ['termine', 1, 1]);
$eq('voix de la fiche rangée', (A::audio($match, 'fr')['voice'] ?? null), 'Kore');
$eq('fichiers d’échange effacés', is_file(A::$dir . '/jobs/vtest-resultats.jsonl'), false);
$line = AiCosts::lines(date('Y-m'), 1)[0] ?? [];
$eq('coût à moitié prix', [$line['f'] ?? null, $line['b'] ?? null, round((float) ($line['usd'] ?? 0), 7)], ['audio', 1, round((100 * 0.5 + 750 * 9) / 1e6 / 2, 7)]);
// Résumés rédigés en groupe, puis voix commandées dans la foulée.
$job = ['id' => 'ttest', 'kind' => 'texte', 'keys' => ['2431-fr'], 'state' => 'recup', 'model' => 'gemini-3.1-flash-lite', 'voice' => 'Kore', 'then_voice' => true, 'cursor' => 0, 'done' => 0, 'errors' => 0, 'by' => 'Essai'];
JsonStore::write(A::$dir . '/jobs/ttest-textes.json', ['2431-fr' => A::sig($match, 'fr')]);
file_put_contents(A::$dir . '/jobs/ttest-resultats.jsonl', json_encode(['key' => '2431-fr', 'response' => ['candidates' => [['content' => ['parts' => [['text' => 'Un résumé groupé.']]]]], 'usageMetadata' => ['promptTokenCount' => 2000, 'candidatesTokenCount' => 30]]]) . "\n");
$job = A::process($job, microtime(true) + 30);
$eq('résumé groupé enregistré', [$job['state'], A::current($match, 'fr')['text']], ['termine', 'Un résumé groupé.']);
$next = array_values(array_filter(A::jobs(), fn ($j) => $j['kind'] === 'voix'));
$eq('voix commandée ensuite', [count($next), $next[0]['keys'] ?? null, $next[0]['state'] ?? null], [1, ['2431-fr'], 'attente']);
$eq('annulation d’un travail en attente', [A::cancel($next[0]['id']), array_values(array_filter(A::jobs(), fn ($j) => $j['id'] === $next[0]['id']))[0]['state']], [true, 'annule']);

// Barème des voix.
$eq('tarif des voix', [AiCosts::price('gemini-3.8-flash-tts')['out'], AiCosts::price('gemini-3.8-flash-preview-tts')['out'], AiCosts::price('gemini-3.8-flash-tts', '2027-01-01')['out']], [9.0, 9.0, 18.0]);
$eq('voix inconnue : jamais au tarif du texte', [AiCosts::price('gemini-9-flash-tts')['out'], AiCosts::price('gemini-9-flash-tts')['known']], [20.0, false]);
$e = A::estimate(0, 100, 60);
$eq('estimation', [round($e['seconds']), round($e['usd'], 4)], [24.0, round(100 * ((60 * 1.6 + 40) * 0.5 / 1e6 + 24 * 25 * 9 / 1e6) / 2, 4)]);

// Ménage.
$rm = function (string $d) use (&$rm) {
    foreach (glob("$d/*") ?: [] as $f) {
        is_dir($f) ? $rm($f) : unlink($f);
    }
    @rmdir($d);
};
$rm($tmp);
echo $fail ? "\n$fail échec(s)\n" : "\nTout est bon.\n";
exit($fail ? 1 : 0);
