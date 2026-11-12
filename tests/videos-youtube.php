<?php
/**
 * Vidéos YouTube dont la chaîne interdit la lecture hors YouTube (ticket : vidéos de la LFP, écran noir
 * sur la fiche Sochaux–Dijon 2014) : détection (oEmbed puis lecteur intégré), affichage en image avec
 * « Regarder sur YouTube », vidéo supprimée masquée, mention dans l'onglet Médias. Réponses de YouTube
 * simulées ; listes remises en état. Usage : php tests/videos-youtube.php
 */
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Services\VideoThumbs as V;

$fail = 0;
$eq = function (string $label, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    if (!$ok) {
        $fail++;
    }
    echo ($ok ? 'OK   ' : 'FAIL ') . $label . ($ok ? '' : ' => ' . json_encode($got, JSON_UNESCAPED_UNICODE) . ' attendu ' . json_encode($exp, JSON_UNESCAPED_UNICODE)) . "\n";
};
$files = [V::GONE, V::BLOCKED, STORAGE_PATH . '/cache/videos-verifiees.json'];
$saved = [];
foreach ($files as $f) {
    $saved[$f] = is_file($f) ? (string) file_get_contents($f) : null;
}
$doc = \App\Data\Fiches::fresh(4499);
$lfp = (string) ($doc['videos'][0]['id'] ?? '');
try {
    $eq('fiche Sochaux–Dijon 2014 : une vidéo YouTube', [$doc['videos'][0]['provider'] ?? null, $lfp !== ''], ['youtube', true]);
    $page = fn (string $status) => '<script>var ytInitialPlayerResponse = {"previewPlayabilityStatus":{"status":"' . $status . '","reason":"x"}};</script>';
    // Réponses simulées : la vidéo LFP refusée par le lecteur intégré, les autres lisibles.
    $get = function (string $url) use ($lfp, $page) {
        if (str_contains($url, '/oembed')) {
            return [200, '{}'];
        }
        return [200, $page(str_contains($url, $lfp) ? 'UNPLAYABLE' : 'OK')];
    };
    foreach ($files as $f) {
        @unlink($f);
    }
    $r = V::check(5000, $get, 600);
    $blocked = \App\Core\JsonStore::read(V::BLOCKED, []);
    $eq('détectée comme bloquée (et seulement elle)', array_keys($blocked), ['youtube-' . $lfp]);
    $eq('compte rendu', [$r['bloquees'], $r['indisponibles']], [1, 0]);

    // Site : image et lien au lieu d'un lecteur noir (nouveau processus : listes relues).
    $html = shell_exec('php -r ' . escapeshellarg('require "' . __DIR__ . '/../app/bootstrap.php"; echo \App\Core\View::partial("partials/videos", ["videos" => \App\Data\Fiches::fresh(4499)["videos"]]);'));
    $eq('site : image et « Regarder sur YouTube ↗ », pas de lecteur', [str_contains($html, 'video--out'), str_contains($html, 'Regarder sur YouTube'), str_contains($html, 'data-embed=')], [true, true, false]);
    $adm = shell_exec('php -r ' . escapeshellarg('require "' . __DIR__ . '/../app/bootstrap.php"; echo \App\Core\View::partial("admin/fiches/_medias", ["doc" => \App\Data\Fiches::fresh(4499)]);'));
    $eq('back-office : mention sous la vidéo', str_contains((string) $adm, 'interdit sa lecture hors de YouTube'), true);

    // Cas de YouTube : supprimée (oEmbed 400/404 ou lecteur « ERROR »), intégration interdite (401), panne.
    $state = (new ReflectionClass(V::class))->getMethod('youtubeState');
    $one = fn (int $code, string $status = 'OK') => $state->invoke(null, 'x', fn ($u) => str_contains($u, '/oembed') ? [$code, ''] : [200, $page($status)]);
    $eq('supprimée, interdite, lisible, panne', [$one(404), $one(401), $one(200, 'ERROR'), $one(200), $one(503)], [['gone', 'supprimée'], ['blocked', 'lecture réservée à YouTube'], ['gone', 'supprimée'], ['ok'], null]);
} finally {
    foreach ($saved as $f => $c) {
        $c === null ? @unlink($f) : file_put_contents($f, $c);
    }
}
echo $fail ? "\n$fail échec(s).\n" : "\nTout est bon.\n";
exit($fail ? 1 : 0);
