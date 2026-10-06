<?php
/**
 * Rétro-Direct commenté façon radio (App\Services\RetroRadio) : consigne envoyée à l'IA (sans
 * les restes de tweets), lecture de sa réponse (répliques calées sur le déroulé), fabrication
 * étape par étape (texte, puis une voix par réplique), poste radio, MP3, liste de lecture de la
 * page, commentaire « à refaire » si les buts ou les minutes changent, échecs, suppression.
 * Usage : php tests/radio.php (code de sortie 1 en cas d'échec). Fausse IA et fausse voix : aucun
 * coût ; n'écrit que dans un dossier temporaire.
 */
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Services\RetroDirect;
use App\Services\RetroRadio as R;

$tmp = sys_get_temp_dir() . '/radio-test-' . bin2hex(random_bytes(4));
R::$dir = "$tmp/radio";
R::$media = "$tmp/media";
$fail = 0;
$eq = function (string $label, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    if (!$ok) {
        $fail++;
    }
    echo ($ok ? 'OK   ' : 'FAIL ') . $label . ($ok ? '' : ' => ' . json_encode($got, JSON_UNESCAPED_UNICODE) . ' attendu ' . json_encode($exp, JSON_UNESCAPED_UNICODE)) . "\n";
};

$id = (int) (RetroDirect::classics(1)[0]['id'] ?? 0);
$tl = R::timeline($id, 'fr');
$goals = array_keys(array_filter($tl['events'], fn ($e) => $e['type'] === 'goal'));
$eq('un grand match jouable, avec des buts', [$id > 0, count($goals) > 0], [true, true]);

// La consigne.
[$sys, $user] = R::prompt($id, 'fr', $tl);
$eq('consigne : reporter radio, JSON, pas d’invention', [str_contains($sys, 'reporter radio'), str_contains($sys, 'JSON'), str_contains($sys, 'n\'invente aucun fait')], [true, true, true]);
$eq('événements numérotés, sans liens ni @ ni #', [str_contains($user, '#0 COUP D’ENVOI'), (bool) preg_match('~https?://|pic\.twitter|@\w|(?<![\n\d])#[A-Za-z]~u', $user)], [true, false]);
[$sysEn] = R::prompt($id, 'en', R::timeline($id, 'en'));
$eq('consigne en anglais', str_contains($sysEn, 'English only'), true);

// Lecture de la réponse de l'IA.
$g = $goals[0];
$raw = "```json\n" . json_encode(['segments' => [
    ['e' => 0, 'text' => 'Bonsoir à tous, ici le stade, les équipes entrent sur la pelouse sous les vivats.'],
    ['e' => $g, 'text' => 'But ! But ! [cris] Quel but magnifique, la foule exulte !'],
    ['e' => $g, 'text' => 'Doublon de la même réplique, à ignorer.'],
    ['m' => 30, 'text' => 'La tension monte dans les tribunes, on sent que quelque chose se prépare.'],
    ['e' => 999, 'text' => 'Événement qui n’existe pas : ignoré.'],
    ['e' => 1, 'text' => 'ok'],
    ['m' => 70, 'text' => 'Soixante-dixième minute, le public pousse derrière ses joueurs.'],
]]) . "\n```";
$p = R::parse($raw, $tl);
$eq('répliques valides gardées, dans l’ordre', array_column($p, 'kind'), array_column(array_values(array_filter([
    ['t' => 0, 'kind' => 'kickoff'], ['t' => $tl['events'][$g]['t'], 'kind' => 'goal'], ['t' => 1770, 'kind' => 'ambiance'],
    ['t' => (int) round($tl['marks']['kickoff2'] + 24.5 * 60), 'kind' => 'ambiance'],
])), 'kind') === [] ? [] : (function () use ($p) { $k = array_column($p, 'kind'); return $k; })());
$eq('4 répliques (doublon, inconnu, trop court écartés)', count($p), 4);
$eq('didascalies retirées', $p[array_search('goal', array_column($p, 'kind'), true)]['text'], 'But ! But ! Quel but magnifique, la foule exulte !');
$eq('ambiance de la 30e : 29 min 30 s', $p[array_search(1770, array_column($p, 't'), true)]['kind'] ?? null, 'ambiance');
$eq('ordre chronologique', array_column($p, 't') === array_values((function ($a) { sort($a); return $a; })(array_column($p, 't'))), true);
$eq('réponse illisible : rien', R::parse('pas du json', $tl), []);

// Le poste radio.
$rate = 24000;
$pcm = '';
for ($i = 0; $i < $rate; $i++) {
    $pcm .= pack('s', (int) (12000 * sin(2 * M_PI * 440 * $i / $rate)));
}
$out = R::radioize($pcm, $rate, true, 7);
$eq('poste radio : 0,4 s avant, 0,7 s après', intdiv(strlen($out), 2), $rate + (int) (0.4 * $rate) + (int) (0.7 * $rate));
$eq('même graine, même son', R::radioize($pcm, $rate, true, 7) === $out, true);
$s = unpack('s*', $out);
$eq('pas de saturation numérique', max(array_map('abs', $s)) <= 32767, true);

// Fabrication complète avec une fausse IA et une fausse voix.
$calls = ['write' => 0, 'speak' => 0];
R::$writer = function (string $system, string $user) use (&$calls, $raw) {
    $calls['write']++;
    return $raw;
};
R::$speaker = function (string $text) use (&$calls, $pcm, $rate) {
    $calls['speak']++;
    return ['pcm' => $pcm, 'rate' => $rate];
};
$eq('rien au départ', [R::status($id, 'fr')['state'], R::playlist($id, 'fr')], ['none', null]);
R::request($id, 'fr', 'Puck');
$eq('demandé : en attente, voix choisie', [R::status($id, 'fr')['state'], R::get($id, 'fr')['voice'], R::pending()], ['waiting', 'Puck', [[$id, 'fr']]]);
$st = R::step($id, 'fr');
$eq('étape 1 : le texte (une demande à l’IA)', [$st['state'], $st['total'], $st['done'], $calls['write']], ['script', 4, 0, 1]);
$st = R::step($id, 'fr');
$eq('étape 2 : une réplique lue', [$st['done'], $calls['speak']], [1, 1]);
$eq('pas jouée tant qu’elle n’est pas finie', R::playlist($id, 'fr'), null);
$n = R::work(30.0);
$st = R::status($id, 'fr');
$eq('tâche planifiée : le reste, puis prêt', [$n >= 3, $st['state'], $st['done'], $calls['speak'], R::pending()], [true, 'ready', 4, 4, []]);
$eq('durée totale (4 × 2,1 s)', $st['dur'], 8);
$pl = R::playlist($id, 'fr');
$eq('liste de lecture : 4 répliques, décalées d’une seconde, fichiers présents', [count($pl), $pl[0]['t'], is_file(R::$media . '/' . substr($pl[1]['url'], 7))], [4, R::LAG, true]);
$eq('MP3 (encodeur du site)', str_ends_with($pl[0]['url'], '.mp3'), true);
$eq('boucle d’ambiance du stade fabriquée (secours) et ambiance du site servie', [is_file(R::$media . '/radio/ambiance.mp3'), R::ambianceUrl(), is_file(PUBLIC_PATH . R::STADE)], [true, R::STADE, true]);
$eq('étape de plus : rien ne bouge', [R::step($id, 'fr')['state'], $calls['speak']], ['ready', 4]);

// La fiche change ses buts ou ses minutes : à refaire.
$f = R::$dir . "/$id-fr.json";
$d = json_decode((string) file_get_contents($f), true);
$d['sig'] = 'autre';
file_put_contents($f, json_encode($d));
$eq('déroulé changé : à refaire, plus joué', [R::status($id, 'fr')['state'], R::playlist($id, 'fr')], ['stale', null]);

// Échecs : trois de suite, puis arrêt.
R::$writer = fn () => throw new RuntimeException('Gemini : quota');
R::request($id, 'fr');
R::step($id, 'fr');
$eq('échec : message gardé, nouvel essai prévu', [R::status($id, 'fr')['state'], R::status($id, 'fr')['error'], R::pending() !== []], ['waiting', 'Gemini : quota', true]);
R::step($id, 'fr');
R::step($id, 'fr');
$eq('trois échecs : arrêt', [R::status($id, 'fr')['state'], R::pending()], ['error', []]);

// Verrou : une seule fabrication à la fois.
R::$writer = fn () => $raw;
R::request($id, 'en');
$lock = fopen(R::$dir . "/$id-en.json.work", 'c');
flock($lock, LOCK_EX);
$eq('déjà en cours ailleurs : on n’y touche pas', [R::step($id, 'en')['busy'] ?? false, R::status($id, 'en')['state']], [true, 'waiting']);
flock($lock, LOCK_UN);
fclose($lock);

// Suppression.
R::delete($id, 'fr');
R::delete($id, 'en');
$eq('supprimé : plus rien, fichiers effacés', [R::status($id, 'fr')['state'], count(glob(R::$media . "/radio/$id-*") ?: [])], ['none', 0]);

exec('rm -rf ' . escapeshellarg($tmp));
echo $fail ? "\n$fail échec(s)\n" : "\nTout est bon.\n";
exit($fail ? 1 : 0);
