<?php
/**
 * Fiches audio (App\Services\FicheAudio) : résumés automatiques, texte retenu (main, IA,
 * automatique), voix enregistrée, rangement des résultats d'un traitement groupé, coût à
 * moitié prix ; pages de synthèse racontées (App\Services\PageAudio). Usage : php tests/audio.php (code de sortie 1 en cas d'échec). N'écrit que
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
$eq('match : dans la durée maximale', A::words($t) <= A::maxWords(), true);
$eq('durée maximale par défaut : 3 min, 450 mots', [A::maxMinutes(), A::maxWords()], [3.0, 450]);
$t = A::template(Fiches::get(22054), 'fr');
$has('penalty dit en toutes lettres', $t, ['Durbant 74e sur penalty']);
// Tirs au but : le vainqueur de la séance et son score (Sochaux à domicile, puis à l'extérieur, puis battu).
$pens = function (bool $home, int $h, int $a) use ($match) {
    $m = $match;
    $m['match']['sochaux_home'] = $home;
    [$m['match']['home']['name'], $m['match']['away']['name']] = $home ? ['Sochaux', 'Auxerre'] : ['Auxerre', 'Sochaux'];
    $m['match']['score'] = ['home' => 1, 'away' => 1, 'extra' => 'a.p', 'aet' => true, 'pens' => ['home' => $h, 'away' => $a]];
    return A::template($m, 'fr') . ' | ' . A::template($m, 'en');
};
$eq('tirs au but racontés (et jamais « Array »)', [str_contains($pens(true, 9, 8), 'et Sochaux l’emporte 9 à 8 aux tirs au but'), str_contains($pens(false, 4, 5), 'et Sochaux l’emporte 5 à 4 aux tirs au but'),
    str_contains($pens(true, 3, 4), 'et Auxerre l’emporte 4 à 3 aux tirs au but'), str_contains($pens(true, 3, 4), 'and Auxerre won 4–3 on penalties'), str_contains($pens(true, 9, 8), 'Array')], [true, true, true, true, false]);
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
$eq('paragraphes de l’IA gardés', A::cleanAi("Un soir de mai.\n\n  Bonal   chavire.\nLa fin."), "Un soir de mai.\n\nBonal chavire.\n\nLa fin.");
$eq('texte trop long coupé en fin de phrase, paragraphes gardés', A::fitText("Un deux trois.\n\nQuatre cinq. Six sept huit neuf.", 5), "Un deux trois.\n\nQuatre cinq.");
// Textes seulement (sans voix IA) : à rédiger par l'IA, jamais un texte écrit à la main.
$eq('textes seulement : texte automatique à rédiger', in_array($id . '-fr', A::plan(['fr'], false, [$id . '-fr'], true)['text'], true), true);
A::saveText($id, 'fr', 'Texte écrit par un historien.', 'manual');
$eq('textes seulement : texte écrit à la main épargné', A::plan(['fr'], true, [$id . '-fr'], true)['text'], []);
A::resetText($id, 'fr');
$eq('estimation sans voix : moins chère', A::estimate(10, 0, 270, true, false)['usd'] < A::estimate(10, 0, 270, true)['usd'], true);

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

// Pages de synthèse racontées (App\Services\PageAudio) : récit calculé, gratuit, dans la durée.
use App\Front\Explore;
use App\Front\Fiche;
use App\Services\PageAudio as P;

$v = Explore::opponentData('nancy')['vars'];
$a = P::opponent(Fiche::clubName('nancy'), $v, false);
$has('face-à-face : accroche, bilan, premier et dernier match, buteurs', $a['text'] ?? '', ['Entre Sochaux et Nancy, c’est une longue histoire : ' . $v['t']['count'] . ' rencontres', $v['t']['V'] . ' victoires sochaliennes', 'Tout a commencé le 29 août 1970, en Division 1 : une défaite 2 à 1 à l’extérieur.', 'Le dernier épisode s’est joué le', 'Côté buteurs,']);
$eq('face-à-face : voix du navigateur, en français, dans la durée', [$a['url'], $a['lang'], A::words($a['text']) <= A::maxWords(), $a['secs'] > 0], [null, 'fr-FR', true, true]);
$en = P::opponent('Nancy', $v, true);
$has('face-à-face en anglais', $en['text'] ?? '', ['Between Sochaux and Nancy', 'It all began on 29 August 1970, in Division 1: a 2–1 defeat away.']);
$s = Explore::seasonData('1987-1988')['vars'];
$a = P::season($s, 'Division 2', false);
$has('saison : bilan, banc, buteurs, coupe jusqu’en finale (tirs au but)', $a['text'] ?? '', ['Retour sur la saison 1987‑1988, vécue en Division 2.', 'Sur le banc : Sylvester Takac.', 'Le meilleur buteur de la saison est Stéphane Paille', 'En Coupe de France, l’aventure s’arrête en finale : un match nul 1 à 1 après prolongation, puis une séance de tirs au but perdue 5 à 4']);
$b = Explore::bilanPage('coupe-de-france')['vars'];
$has('bilan d’une coupe : la finale, au 8e tour', P::competition('coupe-de-france', 'Coupe de France', $b, false)['text'] ?? '', ['La Coupe de France, l’épreuve de tous les exploits', 'Sochaux a atteint la finale le 11 juin 1988, contre Metz', 'au 8e tour de la Coupe de France']);
$b = Explore::bilanPage('stade-auguste-bonal')['vars'];
$has('bilan à Bonal', P::stadium('auguste-bonal', 'Stade Auguste Bonal', $b, false)['text'] ?? '', ['Le stade Auguste-Bonal, c’est la maison des Lionceaux', 'Le premier match fiché ici date du']);
$rows = Explore::recordRows('series');
$has('records : en tête du classement, dates dites', P::records('series', 'Plus longues séries d’invincibilité', 'matchs', 'Toutes époques · toutes compétitions officielles', $rows, false)['text'] ?? '', ['En tête du classement : Du 3 octobre 1987 au 17 mai 1988, 32 matchs', 'Suivent du ', ', toutes compétitions officielles.']);
$all = \App\Services\Chiffres::all();
$c = P::chiffres($all['chapters'], (int) $all['count'], false);
$has('chiffres : un chiffre au moins par chapitre', $c['text'] ?? '', array_map(fn ($ch) => rtrim($ch['title'], '.') . '.', $all['chapters']));
$eq('chiffres : dans la durée', A::words($c['text']) <= A::maxWords(), true);
$eq('texte pour la voix : milliers, scores, dates, tirets, saisons', [
    P::speakable('19 994 spectateurs, Sochaux – Toulon 7-2, le 12/09/1989, carrière 1933–1952, saison 1987-1988', false),
    P::speakable('Sochaux – Toulon 7-2', true),
], ['19994 spectateurs, Sochaux contre Toulon 7 à 2, le 12 septembre 1989, carrière de 1933 à 1952, saison 1987‑1988', 'Sochaux against Toulon 7–2']);
$values = new ReflectionProperty(\App\Core\Settings::class, 'values');
$before = \App\Core\Settings::all();
$values->setValue(null, ['audio.enabled' => false] + $before);
$eq('audio désactivé : pas de bouton', P::opponent('Nancy', $v, false), null);
$values->setValue(null, $before);

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
