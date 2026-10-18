<?php
/**
 * Traitements IA (lot 1) : budget IA du mois respecté par Trouvailles, Compos et la reprise
 * FCSM Story ; échéance de Trouvailles vérifiée pendant la fouille (match remis en tête de
 * file) ; verrou propre à chaque traitement long (traductions, index de l'assistant, fiches
 * audio, correcteur) ; traductions et index arrêtés dès une panne générale de Gemini (clé
 * refusée), essais comptés dans le plafond, heure limite respectée. Gemini et Gallica simulés
 * (serveurs de développement PHP locaux), aucun appel réseau réel. Les fichiers d'état réels
 * touchés un instant (file des compositions, reprise FCSM Story, dernière erreur de traduction)
 * sont remis tels quels. Usage : php tests/lot1-ia.php (code de sortie 1 en cas d'échec).
 */
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Core\Settings;
use App\Data\Fiches;
use App\Data\Index;
use App\Services\AiCosts;
use App\Services\Compos;
use App\Services\FcsmImport;
use App\Services\FcsmStory;
use App\Services\FicheAudio;
use App\Services\Proofreader;
use App\Services\Rag;
use App\Services\Translator;
use App\Services\Trouvailles as T;

// Sous-processus servi par « php -S » (Gemini simulé) : traductions et index face à une clé refusée.
if (PHP_SAPI === 'cli-server') {
    (new ReflectionProperty(Settings::class, 'values'))->setValue(null, Settings::all() + ['ai.embedding_model' => 'text-embedding-004', 'ai.enabled' => true]);
    $out = [];
    $r = Translator::run(8);
    $out['trad'] = ['tried' => count($r['tried'] ?? []), 'error' => $r['error'] ?? null];
    $r = Translator::run(8, false, microtime(true) - 1);
    $out['trad_late'] = count($r['tried'] ?? []);
    $r = Rag::reindex(null, 300);
    $out['rag'] = ['error' => $r['error'] ?? null, 'embedded' => $r['embedded'] ?? null];
    echo json_encode($out, JSON_UNESCAPED_UNICODE);
    return;
}

$tmp = sys_get_temp_dir() . '/ia-lot1-' . bin2hex(random_bytes(4));
mkdir($tmp);
$fail = 0;
$eq = function (string $label, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    if (!$ok) {
        $fail++;
    }
    echo ($ok ? 'OK   ' : 'FAIL ') . $label . ($ok ? '' : ' => ' . json_encode($got, JSON_UNESCAPED_UNICODE) . ' attendu ' . json_encode($exp, JSON_UNESCAPED_UNICODE)) . "\n";
};
$settings = new ReflectionProperty(Settings::class, 'values');
$real = Settings::all();
$set = fn (array $v) => $settings->setValue(null, $v + $real);
// Copie et remise en place d'un fichier d'état réel.
$saved = [];
$keep = function (string $file) use (&$saved) {
    $saved[$file] = is_file($file) ? file_get_contents($file) : null;
};
$restore = function () use (&$saved) {
    foreach ($saved as $file => $content) {
        $content === null ? @unlink($file) : file_put_contents($file, $content);
    }
};
$aiDir = STORAGE_PATH . '/ai';
$aiExisted = is_dir($aiDir);

try {
    // 1. Budget IA du mois atteint : les tâches s'arrêtent avant tout appel.
    AiCosts::$dir = "$tmp/ia";
    $set(['couts.free_tier' => false]);
    AiCosts::record('traduction', 'gemini-2.5-flash', ['in' => 2_000_000]);
    $set(['couts.free_tier' => false, 'couts.monthly_budget' => AiCosts::eur(AiCosts::month(date('Y-m'))['usd']) / 2, 'couts.pause_tasks' => true]);
    $eq('budget dépassé', AiCosts::paused('trouvailles'), true);

    T::$dir = "$tmp/trouvailles";
    $calls = 0;
    T::$get = function () use (&$calls) {
        $calls++;
        usleep(1_100_000); // Gallica lent
        return '<srw:searchRetrieveResponse><srw:numberOfRecords>0</srw:numberOfRecords></srw:searchRetrieveResponse>';
    };
    // Fiche de match d'essai de 1931 (supprimée à la fin ; clubs et stades ajoutés par son
    // enregistrement remis tels quels).
    $collections = [DATA_PATH . '/collections/clubs.json' => null, DATA_PATH . '/collections/stades.json' => null];
    foreach ($collections as $f => $_) {
        $collections[$f] = is_file($f) ? file_get_contents($f) : null;
    }
    $doc = Fiches::blank('match');
    $doc['title'] = 'Zzessai lot1 – FC Chalon / Sochaux – 17/10/1931';
    $doc['path'] = '/matchs/1931-1932/zzessai-lot1-17-10-1931/';
    $doc['slug'] = 'zzessai-lot1-17-10-1931';
    $doc['status'] = 'publie';
    $doc['match']['date'] = '1931-10-17';
    $doc['match']['season'] = '1931-1932';
    $doc['match']['home'] = ['name' => 'FC Chalon', 'level' => null];
    $doc['match']['away'] = ['name' => 'Sochaux', 'level' => null];
    $doc['match']['sochaux_home'] = false;
    $doc['match']['score'] = ['home' => 0, 'away' => 8, 'extra' => null, 'aet' => false, 'pens' => null];
    $old = (int) Fiches::save($doc, ['name' => 'Essai'], 'essai')['id'];
    T::start([$old], ['gallica']);
    $eq('Trouvailles : en pause', [T::tick(), $calls], ['en pause : budget IA du mois atteint', 0]);

    $keep(STORAGE_PATH . '/compos/etat.json');
    @mkdir(STORAGE_PATH . '/compos', 0775, true);
    file_put_contents(STORAGE_PATH . '/compos/etat.json', json_encode(['on' => true, 'queue' => [$old]]));
    $eq('Compos : en pause', Compos::tick(1), 'en pause : budget IA du mois atteint');
    $eq('Compos : file intacte', Compos::state()['queue'], [$old]);

    $keep(FcsmStory::$dir . '/state.json');
    $fcsmDir = is_dir(FcsmStory::$dir) ? null : FcsmStory::$dir;
    @mkdir(FcsmStory::$dir, 0775, true);
    file_put_contents(FcsmStory::$dir . '/state.json', json_encode(['running' => true, 'items' => []]));
    $eq('Reprise FCSM Story : en pause', FcsmImport::tick(), 'en pause : budget IA du mois atteint');
    $restore();
    $saved = [];
    if ($fcsmDir) {
        exec('rm -rf ' . escapeshellarg($fcsmDir));
    }

    // 2. Échéance de Trouvailles vérifiée pendant la fouille : le match revient en tête de file.
    $set([]);
    $calls = 0;
    $t0 = microtime(true);
    $r = T::work(1, 4);
    $dt = microtime(true) - $t0;
    $eq('Trouvailles : fouille arrêtée à l’échéance, sans erreur', [$r['done'], $r['errors']], [0, 0]);
    $eq('Trouvailles : arrêt à l’intérieur de la fouille (une seule page de Gallica attendue)', [$calls, $dt < 3], [1, true]);
    $eq('Trouvailles : match remis en tête de file', T::state()['queue'], [$old]);
    $msg = '';
    try {
        T::search($old, ['gallica'], microtime(true) - 1);
    } catch (\RuntimeException $e) {
        $msg = $e->getMessage();
    }
    $eq('Trouvailles::search : échéance dépassée, rien enregistré', [$msg, isset(T::state()['searched'][(string) $old])], [T::LATE, false]);
    T::clearQueue();

    // 3. Verrous propres à chaque traitement (deuxième passage refusé, sans attendre).
    $hold = function (string $file) {
        @mkdir(dirname($file), 0775, true);
        $fp = fopen($file, 'c');
        flock($fp, LOCK_EX);
        return $fp;
    };
    $set(['ai.gemini_api_key' => 'cle-essai']);
    Proofreader::$dir = "$tmp/correcteur";
    $l = $hold("$tmp/correcteur/verification.lock");
    $eq('correcteur : deuxième passage refusé', Proofreader::run(5)['message'] ?? null, 'vérification déjà en cours (autre passage)');
    fclose($l);
    FicheAudio::$dir = "$tmp/audio";
    $l = $hold("$tmp/audio/traitements.lock");
    $eq('fiches audio : deuxième passage refusé', FicheAudio::run(5), 'traitements déjà en cours (autre passage)');
    fclose($l);
    $l = $hold(STORAGE_PATH . '/traductions.lock');
    $eq('traductions : deuxième passage refusé', Translator::run(8)['busy'] ?? false, true);
    fclose($l);
    $set(['ai.gemini_api_key' => 'cle-essai', 'ai.embedding_model' => 'text-embedding-004']);
    $l = $hold("$aiDir/indexation.lock");
    $eq('index de l’assistant : deuxième passage refusé', Rag::reindex(null, 10)['busy'] ?? false, true);
    fclose($l);
    $set([]);

    // 4. Gemini refuse la clé (403) : un seul essai, puis arrêt (traductions et index).
    $keep(STORAGE_PATH . '/i18n-erreur.json');
    $port = function (): int {
        $s = stream_socket_server('tcp://127.0.0.1:0');
        $p = (int) substr(strrchr(stream_socket_get_name($s, false), ':'), 1);
        fclose($s);
        return $p;
    };
    [$p1, $p2] = [$port(), $port()];
    file_put_contents("$tmp/google.php", '<?php
file_put_contents(' . var_export("$tmp/appels.txt", true) . ', strtok($_SERVER["REQUEST_URI"], "?") . "\n", FILE_APPEND);
http_response_code(403);
header("Content-Type: application/json");
echo json_encode(["error" => ["code" => 403, "message" => "Permission denied: API key revoked", "status" => "PERMISSION_DENIED"]]);
');
    $null = ['file', '/dev/null', 'w'];
    $g = proc_open([PHP_BINARY, '-S', "127.0.0.1:$p2", "$tmp/google.php"], [$null, $null, $null], $x);
    $a = proc_open([PHP_BINARY, '-d', 'max_execution_time=120', '-S', "127.0.0.1:$p1", __FILE__], [$null, $null, $null], $x, null, ['GEMINI_MOCK_URL' => "http://127.0.0.1:$p2/"] + getenv());
    usleep(700000);
    $res = json_decode((string) @file_get_contents("http://127.0.0.1:$p1/", false, stream_context_create(['http' => ['timeout' => 110]])), true);
    proc_terminate($a);
    proc_terminate($g);
    $log = array_filter(explode("\n", (string) @file_get_contents("$tmp/appels.txt")));
    $gen = count(array_filter($log, fn ($l) => str_contains($l, 'generateContent')));
    $emb = count(array_filter($log, fn ($l) => str_contains($l, 'embed')));
    $eq('traductions : une seule fiche essayée, panne signalée', [$res['trad']['tried'] ?? null, str_contains((string) ($res['trad']['error'] ?? ''), 'accès refusé')], [1, true]);
    $eq('traductions : un seul appel à Gemini', $gen, 1);
    $eq('traductions : heure limite passée, aucun essai', $res['trad_late'] ?? null, 0);
    $eq('index : arrêt dès la panne, un seul appel', [str_contains((string) ($res['rag']['error'] ?? ''), 'accès refusé'), $emb], [true, 1]);
} finally {
    if (!empty($old)) {
        Fiches::destroy($old, ['name' => 'Essai']);
        exec('rm -rf ' . escapeshellarg(STORAGE_PATH . "/versions/$old"));
    }
    foreach ($collections ?? [] as $f => $content) {
        if ($content !== null) {
            file_put_contents($f, $content);
        }
    }
    $restore();
    if (!$aiExisted) {
        exec('rm -rf ' . escapeshellarg($aiDir));
    }
    exec('rm -rf ' . escapeshellarg($tmp));
}
echo $fail ? "\n$fail échec(s)\n" : "\nTout est bon.\n";
exit($fail ? 1 : 0);
