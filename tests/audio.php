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

P::$dir = "$tmp/etat/pages";
$v = Explore::opponentData('nancy')['vars'];
$a = P::opponent('nancy', Fiche::clubName('nancy'), $v, false);
$has('face-à-face : accroche, bilan, premier et dernier match, buteurs', $a['text'] ?? '', ['Entre Sochaux et Nancy, c’est une longue histoire : ' . $v['t']['count'] . ' rencontres', $v['t']['V'] . ' victoires sochaliennes', 'Tout a commencé le 29 août 1970, en Division 1 : une défaite 2 à 1 à l’extérieur.', 'Le dernier épisode s’est joué le', 'Côté buteurs,']);
$eq('face-à-face : voix du navigateur, en français, dans la durée', [$a['url'], $a['lang'], A::words($a['text']) <= A::maxWords(), $a['secs'] > 0], [null, 'fr-FR', true, true]);
$en = P::opponent('nancy', 'Nancy', $v, true);
$has('face-à-face en anglais', $en['text'] ?? '', ['Between Sochaux and Nancy', 'It all began on 29 August 1970, in Division 1: a 2–1 defeat away.']);
$s = Explore::seasonData('1987-1988')['vars'];
$a = P::season($s, 'Division 2', false);
$has('saison : bilan, banc, buteurs, coupe jusqu’en finale (tirs au but)', $a['text'] ?? '', ['Retour sur la saison 1987‑1988, vécue en Division 2.', 'Sur le banc : Sylvester Takac.', 'Le meilleur buteur de la saison est Stéphane Paille', 'En Coupe de France, l’aventure s’arrête en finale : un match nul 1 à 1 après prolongation, puis une séance de tirs au but perdue 5 à 4']);
$b = Explore::bilanPage('coupe-de-france')['vars'];
$has('bilan d’une coupe : la finale, au 8e tour', P::competition('coupe-de-france', 'Coupe de France', $b, false)['text'] ?? '', ['La Coupe de France, l’épreuve de tous les exploits', 'Sochaux a atteint la finale le 11 juin 1988, contre Metz', 'au 8e tour de la Coupe de France']);
$b = Explore::bilanPage('stade-auguste-bonal')['vars'];
$has('bilan à Bonal', P::stadium('auguste-bonal', 'Stade Auguste Bonal', $b, false)['text'] ?? '', ['Le stade Auguste-Bonal, c’est la maison des Lionceaux', 'Le premier match fiché ici date du']);
$rows = Explore::recordRows('series');
$has('records : en tête du classement, dates dites', P::records('series', null, null, 'Plus longues séries d’invincibilité', 'matchs', 'Toutes époques · toutes compétitions officielles', $rows, false)['text'] ?? '', ['En tête du classement : Du 3 octobre 1987 au 17 mai 1988, 32 matchs', 'Suivent du ', ', toutes compétitions officielles.']);
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
$eq('audio désactivé : pas de bouton', P::opponent('nancy', 'Nancy', $v, false), null);
$values->setValue(null, $before);

// Récits rédigés par l'IA : même empreinte en français, en anglais et au traitement groupé.
use App\Services\I18n;

$sigOf = fn (string $slug) => P::sig(P::factsFor($slug));
P::saveText('club-nancy', 'fr', "Le récit de l’IA.\n\nDevant 19 994 spectateurs.", $sigOf('club-nancy'), 'essai');
P::saveText('club-nancy', 'en', 'The AI story.', $sigOf('club-nancy'), 'essai');
$fr = P::opponent('nancy', 'Nancy', $v, false);
I18n::set('en');
$vEn = Explore::opponentData('nancy')['vars'];
$enA = P::opponent('nancy', 'Nancy', $vEn, true);
I18n::set('fr');
$eq('IA : récit lu en français et en anglais (page en anglais : même empreinte)', [$fr['src'], $fr['text'], $enA['src'], $enA['text']], ['ai', "Le récit de l’IA.\n\nDevant 19994 spectateurs.", 'ai', 'The AI story.']);
$pages = [
    'saison-1987-1988' => fn (bool $e) => P::season(Explore::seasonData('1987-1988')['vars'], 'Division 2', $e),
    'bilan-coupe-de-france' => fn (bool $e) => P::competition('coupe-de-france', 'Coupe de France', Explore::bilanPage('coupe-de-france')['vars'], $e),
    'bilan-stade-auguste-bonal' => fn (bool $e) => P::stadium('auguste-bonal', 'Stade Auguste Bonal', Explore::bilanPage('stade-auguste-bonal')['vars'], $e),
    'records-buteurs-1980' => fn (bool $e) => P::records('buteurs', 1980, null, 'x', 'buts', 'x', Explore::recordRows('buteurs', 1980), $e),
    'chiffres' => fn (bool $e) => P::chiffres(\App\Services\Chiffres::all()['chapters'], 100, $e),
];
$src = [];
foreach ($pages as $slug => $page) {
    P::saveText($slug, 'fr', 'IA fr ' . $slug, $sigOf($slug));
    P::saveText($slug, 'en', 'IA en ' . $slug, $sigOf($slug));
    $src[$slug] = $page(false)['src'] ?? null;
    I18n::set('en');
    $src[$slug] .= '/' . ($page(true)['src'] ?? null);
    I18n::set('fr');
}
$eq('IA : saison, coupe, Bonal, records, chiffres (français/anglais)', $src, array_fill_keys(array_keys($pages), 'ai/ai'));
P::saveText('club-nancy', 'fr', 'Ancien récit.', 'empreinte-perimee');
$eq('chiffres changés : récit automatique en attendant le nouveau', P::opponent('nancy', 'Nancy', $v, false)['src'] ?? null, 'auto');
$plan = P::plan(false, ['club-nancy', 'club-metz', 'records-buteurs-1920-amical']);
$eq('à rédiger : récit dépassé et récits manquants (page vide écartée)', [$plan['pages'], $plan['keys']], [2, ['page:club-nancy:fr', 'page:club-metz:fr', 'page:club-metz:en']]);
$eq('tout refaire', count(P::plan(true, ['club-nancy'])['keys']), 2);
[$req, $sig] = P::request('page:club-metz:fr', 'gemini-2.5-flash-lite');
$body = json_encode($req, JSON_UNESCAPED_UNICODE);
$eq('demande à l’IA : consigne de conteur, faits de la page, empreinte', [str_contains($body, 'le face-à-face entre Sochaux et Metz'), str_contains($body, 'premier match'), str_contains($body, 'au plus 450 mots'), $sig === $sigOf('club-metz')], [true, true, true, true]);
$eq('clé inconnue ou page vide : rien d’envoyé', [P::request('page:club-inconnu:fr', 'm'), P::request('page:../x:fr', 'm'), P::parseKey('page:club-metz:de')], [null, null, null]);
// Traitement groupé : rangement des récits, coût compté, pas de voix ensuite.
$n = A::queueTexts(['page:club-metz:fr'], ['name' => 'Essai'], true);
$job = array_values(array_filter(A::jobs(), fn ($j) => !empty($j['pages'])))[0];
$eq('traitement groupé des récits : texte seulement', [$n, $job['kind'], $job['then_voice'], $job['keys']], [1, 'texte', false, ['page:club-metz:fr']]);
$job['state'] = 'recup';
JsonStore::write(A::$dir . "/jobs/{$job['id']}-textes.json", ['page:club-metz:fr' => $sigOf('club-metz')]);
file_put_contents(A::$dir . "/jobs/{$job['id']}-resultats.jsonl", json_encode(['key' => 'page:club-metz:fr', 'response' => ['candidates' => [['content' => ['parts' => [['text' => "**Metz et Sochaux**, une histoire.\n\nSuite."]]]]], 'usageMetadata' => ['promptTokenCount' => 2500, 'candidatesTokenCount' => 500]]]) . "\n");
$job = A::process($job, microtime(true) + 30);
$m = P::opponent('metz', 'Metz', Explore::opponentData('metz')['vars'], false);
$eq('récit rangé et lu sur la page', [$job['state'], $job['done'], $m['src'] ?? null, $m['text'] ?? null], ['termine', 1, 'ai', "Metz et Sochaux, une histoire.\n\nSuite."]);
$eq('aucune voix commandée pour une page', count(array_filter(A::jobs(), fn ($j) => $j['kind'] === 'voix' && in_array('page:club-metz:fr', $j['keys'], true))), 0);
$eq('coût compté pour la page', in_array('page:club-metz', array_column(AiCosts::lines(date('Y-m'), 5), 'r'), true), true);

// Voix IA des récits des pages : demandée sur le récit rangé, jouée seulement si elle le lit.
$pcm = str_repeat("\0\0", 24000);
[$sreq, $said] = P::speechRequest('page:club-metz:fr', 'Charon');
$eq('demande de voix : le récit rangé, préparé pour la voix', [$said, str_contains(json_encode($sreq, JSON_UNESCAPED_UNICODE), 'Metz et Sochaux, une histoire.'), P::speechRequest('page:club-inconnu:fr', 'Charon')], ["Metz et Sochaux, une histoire.\n\nSuite.", true, null]);
$voiceJob = ['id' => 'vpage', 'kind' => 'voix', 'keys' => ['page:club-metz:fr'], 'state' => 'recup', 'model' => 'gemini-3.8-flash-tts', 'voice' => 'Charon', 'then_voice' => false, 'pages' => true, 'cursor' => 0, 'done' => 0, 'errors' => 0, 'by' => 'Essai'];
JsonStore::write(A::$dir . '/jobs/vpage-textes.json', ['page:club-metz:fr' => $said]);
file_put_contents(A::$dir . '/jobs/vpage-resultats.jsonl', json_encode(['key' => 'page:club-metz:fr', 'response' => ['candidates' => [['content' => ['parts' => [['inlineData' => ['mimeType' => 'audio/L16;codec=pcm;rate=24000', 'data' => base64_encode($pcm)]]]]]], 'usageMetadata' => ['promptTokenCount' => 40, 'candidatesTokenCount' => 25]]]) . "\n");
$voiceJob = A::process($voiceJob, microtime(true) + 30);
$m = P::opponent('metz', 'Metz', Explore::opponentData('metz')['vars'], false);
$eq('voix rangée et jouée sur la page', [$voiceJob['state'], $voiceJob['message'], (bool) preg_match('#^/media/audio/pages/club-metz-fr-[0-9a-f]{10}\.wav$#', (string) ($m['url'] ?? '')), $m['dur'] ?? null, $m['secs'] ?? null], ['termine', '1 voix de pages enregistrée(s).', true, 1.0, 1]);
$file = A::$media . '/' . P::stored('club-metz')['fr']['audio']['file'];
$eq('fichier de la voix', [is_file($file), P::stats()['voice_fr']], [true, 1]);
$planV = P::plan(false, ['club-metz']);
$eq('voix à faire : pas pour un récit à jour qui l’a déjà ; récit manquant d’abord rédigé', [$planV['keys'], $planV['voices']], [['page:club-metz:en'], []]);
P::saveText('club-metz', 'fr', 'Un nouveau récit.', $sigOf('club-metz'));
$m = P::opponent('metz', 'Metz', Explore::opponentData('metz')['vars'], false);
$eq('récit refait : l’ancienne voix ne joue plus, nouvelle voix à faire', [$m['url'] ?? null, $m['text'] ?? null, in_array('page:club-metz:fr', P::plan(false, ['club-metz'])['voices'], true)], [null, 'Un nouveau récit.', true]);
// Récits puis voix : un traitement de récits commande ensuite les voix de ses pages.
$tj = ['id' => 'tpage', 'kind' => 'texte', 'keys' => ['page:club-metz:en'], 'state' => 'recup', 'model' => 'gemini-2.5-flash-lite', 'voice' => 'Charon', 'then_voice' => true, 'pages' => true, 'cursor' => 0, 'done' => 0, 'errors' => 0, 'by' => 'Essai'];
JsonStore::write(A::$dir . '/jobs/tpage-textes.json', ['page:club-metz:en' => $sigOf('club-metz')]);
file_put_contents(A::$dir . '/jobs/tpage-resultats.jsonl', json_encode(['key' => 'page:club-metz:en', 'response' => ['candidates' => [['content' => ['parts' => [['text' => 'Metz and Sochaux.']]]]], 'usageMetadata' => ['promptTokenCount' => 2500, 'candidatesTokenCount' => 50]]]) . "\n");
A::process($tj, microtime(true) + 30);
$vj = array_values(array_filter(A::jobs(), fn ($j) => $j['kind'] === 'voix' && !empty($j['pages']) && in_array('page:club-metz:en', $j['keys'], true)));
$eq('récit rédigé : sa voix IA commandée ensuite', [count($vj), $vj[0]['state'] ?? null], [1, 'attente']);
$eq('voix des pages désactivées : récits seuls', P::launch(false, null, ['club-nancy'], false)['voice'], 0);

// Voix IA : le texte seul, jamais la consigne de ton (le modèle la lisait à voix haute).
$req = \App\Services\Gemini::speechRequest('Le texte.', 'Charon');
$eq('voix : seul le texte est envoyé', $req['contents'][0]['parts'][0]['text'], 'Le texte.');
[$sreq] = P::speechRequest('page:club-metz:fr', 'Charon');
$eq('voix d’une page : seul le récit est envoyé', $sreq['contents'][0]['parts'][0]['text'], 'Un nouveau récit.');
// Voix enregistrée avant la correction (sans version) : plus jouée, à refaire.
P::storeVoice('club-metz', 'fr', $pcm, 24000, 'Un nouveau récit.', 'essai', 'Charon');
$st = P::stored('club-metz');
$eq('nouvelle voix jouée', (bool) (P::opponent('metz', 'Metz', Explore::opponentData('metz')['vars'], false)['url'] ?? null), true);
unset($st['fr']['audio']['v']);
JsonStore::write(P::$dir . '/club-metz.json', $st);
$eq('ancienne voix (consigne lue) : plus jouée, à refaire', [P::opponent('metz', 'Metz', Explore::opponentData('metz')['vars'], false)['url'] ?? null, in_array('page:club-metz:fr', P::plan(false, ['club-metz'])['voices'], true)], [null, true]);

// Essai sur une page : adresse du site ↔ page ; rien la nuit avant le premier lancement complet.
$urls = ['/face-a-face/nancy/', 'https://musee.fcsochauxretro.com/en/matchs/1987-1988/', '/bilans/stade-auguste-bonal/', '/bilans/coupe-de-france', '/records/?cat=series&decennie=1980&comp=championnat', '/records/', '/en/chiffres/', '/fiche/inconnue/', '/records/?cat=pirate'];
$eq('adresse → page', array_map(fn ($u) => P::slugFromUrl($u), $urls), [['club-nancy', 'fr'], ['saison-1987-1988', 'en'], ['bilan-stade-auguste-bonal', 'fr'], ['bilan-coupe-de-france', 'fr'], ['records-series-1980-championnat', 'fr'], ['records-buteurs', 'fr'], ['chiffres', 'en'], null, null]);
$eq('page → adresse', [P::urlFor('club-nancy', 'fr'), P::urlFor('saison-1987-1988', 'en'), P::urlFor('records-series-1980-championnat', 'fr'), P::urlFor('records-buteurs', 'fr'), P::urlFor('bilan-stade-auguste-bonal', 'fr'), P::urlFor('chiffres', 'en')],
    ['/face-a-face/nancy/', '/en/matchs/1987-1988/', '/records/?cat=series&decennie=1980&comp=championnat', '/records/', '/bilans/stade-auguste-bonal/', '/en/chiffres/']);
$before = P::activated();
P::activate(['name' => 'Essai']);
$eq('rédaction de nuit : seulement après le premier lancement complet', [$before, P::activated()], [false, true]);

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
