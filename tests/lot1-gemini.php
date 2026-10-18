<?php
/**
 * Gemini (App\Services\Gemini) : téléchargement des résultats d'un traitement groupé qui suit
 * une redirection vers un autre hôte sans y renvoyer la clé API ; reconnaissance des erreurs
 * « globales » (quota, clé, accès refusé, injoignable) qui arrêtent les traitements par lots.
 * Deux serveurs de développement PHP locaux simulent Google (aucun appel réseau réel).
 * Usage : php tests/lot1-gemini.php (code de sortie 1 en cas d'échec).
 */
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Services\Gemini;

$fail = 0;
$eq = function (string $label, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    if (!$ok) {
        $fail++;
    }
    echo ($ok ? 'OK   ' : 'FAIL ') . $label . ($ok ? '' : ' => ' . json_encode($got, JSON_UNESCAPED_UNICODE) . ' attendu ' . json_encode($exp, JSON_UNESCAPED_UNICODE)) . "\n";
};

// Erreurs globales.
$eq('quota (429)', Gemini::globalError('Gemini : quota atteint, réessayez plus tard.', 429), true);
$eq('clé invalide', Gemini::globalError('Gemini : clé API invalide.', 400), true);
$eq('accès refusé (403)', Gemini::globalError('Gemini : accès refusé (x)', 403), true);
$eq('401', Gemini::globalError('Gemini : unauthorized', 401), true);
$eq('injoignable (code 0)', Gemini::globalError('Gemini injoignable : Could not resolve host'), true);
$eq('erreur propre à une fiche : pas globale', Gemini::globalError('Gemini : réponse tronquée', 500), false);

// Téléchargement avec redirection vers un autre hôte.
$tmp = sys_get_temp_dir() . '/gemini-lot1-' . bin2hex(random_bytes(4));
mkdir($tmp);
$port = fn () => (function () {
    $s = stream_socket_server('tcp://127.0.0.1:0');
    $p = (int) substr(strrchr(stream_socket_get_name($s, false), ':'), 1);
    fclose($s);
    return $p;
})();
$p1 = $port();
$p2 = $port();
file_put_contents("$tmp/google.php", '<?php
$key = isset($_SERVER["HTTP_X_GOOG_API_KEY"]) ? "cle" : "sans-cle";
file_put_contents(' . var_export("$tmp/journal.txt", true) . ', $_SERVER["HTTP_HOST"] . " " . strtok($_SERVER["REQUEST_URI"], "?") . " " . $key . "\n", FILE_APPEND);
if (str_starts_with($_SERVER["REQUEST_URI"], "/download/")) {
    header("Location: http://localhost:' . $p2 . '/stockage/resultats", true, 302);
    exit;
}
echo "RESULTATS";
');
file_put_contents("$tmp/site.php", '<?php
require ' . var_export(APP_ROOT . '/app/bootstrap.php', true) . ';
try {
    $n = \App\Services\Gemini::download("files/lot-1", ' . var_export("$tmp/sortie.jsonl", true) . ');
    echo $n, "|", file_get_contents(' . var_export("$tmp/sortie.jsonl", true) . ');
} catch (\Throwable $e) {
    echo "ERREUR ", $e->getMessage();
}
');
$null = ['file', '/dev/null', 'w'];
$g = proc_open([PHP_BINARY, '-S', "127.0.0.1:$p2", "$tmp/google.php"], [$null, $null, $null], $x);
$a = proc_open([PHP_BINARY, '-S', "127.0.0.1:$p1", "$tmp/site.php"], [$null, $null, $null], $x, null, ['GEMINI_MOCK_URL' => "http://127.0.0.1:$p2/"] + getenv());
usleep(600000);
$out = (string) @file_get_contents("http://127.0.0.1:$p1/");
proc_terminate($a);
proc_terminate($g);
$log = (string) @file_get_contents("$tmp/journal.txt");
$eq('résultats téléchargés après la redirection', $out, '9|RESULTATS');
$eq('clé envoyée à l’hôte de départ, pas à l’hôte de la redirection', trim($log), "127.0.0.1:$p2 /download/v1beta/files/lot-1:download cle\nlocalhost:$p2 /stockage/resultats sans-cle");
exec('rm -rf ' . escapeshellarg($tmp));
echo $fail ? "\n$fail échec(s)\n" : "\nTout est bon.\n";
exit($fail ? 1 : 0);
