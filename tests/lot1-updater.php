<?php
/**
 * Mises à jour (App\Services\Updater), cas d'échec : fichier abîmé dans l'archive refusé avant
 * toute écriture (taille et CRC vérifiés) ; échec en pleine copie : sauvegarde remise aussitôt,
 * échec noté dans l'historique, caches refaits ; téléchargement raté : pas d'archive partielle
 * laissée dans storage/update/tmp ; drapeau de pause lu par public/index.php avant le démarrage.
 * Usage : php tests/lot1-updater.php (code de sortie 1 en cas d'échec). N'écrit que dans un
 * dossier temporaire.
 */
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Services\Updater as U;

$tmp = sys_get_temp_dir() . '/maj-lot1-' . bin2hex(random_bytes(4));
$root = "$tmp/site";
U::$root = $root;
U::$dir = "$root/storage/update";
U::$cacheDir = "$root/storage/cache";
U::$log = false;
$fail = 0;
$eq = function (string $label, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    if (!$ok) {
        $fail++;
    }
    echo ($ok ? 'OK   ' : 'FAIL ') . $label . ($ok ? '' : ' => ' . json_encode($got, JSON_UNESCAPED_UNICODE) . ' attendu ' . json_encode($exp, JSON_UNESCAPED_UNICODE)) . "\n";
};
$put = function (string $rel, string $content) use ($root) {
    @mkdir(dirname("$root/$rel"), 0775, true);
    file_put_contents("$root/$rel", $content);
};
$read = fn (string $rel) => is_file("$root/$rel") ? file_get_contents("$root/$rel") : null;
$zip = function (string $sha, array $files, bool $store = false) use ($tmp): string {
    $path = "$tmp/$sha.zip";
    $z = new ZipArchive();
    $z->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    foreach ($files as $rel => $content) {
        $z->addFromString("poubelle-$sha/$rel", $content);
        if ($store) {
            $z->setCompressionName("poubelle-$sha/$rel", ZipArchive::CM_STORE);
        }
    }
    $z->setArchiveComment($sha);
    $z->close();
    return $path;
};
$check = fn (string $sha) => ['latest' => ['sha' => $sha, 'date' => '', 'message' => 'essai'], 'commits' => []];
$stages = fn () => glob(U::$dir . '/tmp/prepa-*') ?: [];
$v1 = str_repeat('1', 40);
$v2 = str_repeat('2', 40);
$v3 = str_repeat('3', 40);

$put('app/bootstrap.php', "<?php // amorce\n");
$put('public/index.php', "<?php // entrée\n");
$put('app/A.php', "<?php // A ancien\n");
$base = ['app/bootstrap.php' => "<?php // amorce\n", 'public/index.php' => "<?php // entrée\n", 'app/A.php' => "<?php // A v1\n"];
U::install($zip($v1, $base), $v1, $check($v1), ['name' => 'Essai']);
$eq('version 1 installée', [U::installed()['sha'] ?? null, $read('app/A.php')], [$v1, "<?php // A v1\n"]);

// 1. Archive abîmée (octets d'un fichier modifiés, CRC inchangé) : refusée avant toute écriture.
$files = $base;
$files['app/A.php'] = "<?php // A v2 " . str_repeat('x', 200) . "\n";
$files['app/C.php'] = "<?php // C v2\n";
$path = $zip($v2, $files, true);
$raw = file_get_contents($path);
$pos = strpos($raw, str_repeat('x', 200));
$raw[$pos + 10] = 'y';
file_put_contents($path, $raw);
$msg = '';
try {
    U::install($path, $v2, $check($v2), ['name' => 'Essai']);
} catch (\Throwable $e) {
    $msg = $e->getMessage();
}
$eq('archive abîmée : refusée', str_contains($msg, 'incomplet ou abîmé'), true);
$eq('archive abîmée : site intact (A v1, pas de C)', [$read('app/A.php'), is_file("$root/app/C.php")], ["<?php // A v1\n", false]);
$eq('archive abîmée : version installée inchangée, pas de pause, pas de dossier de travail', [U::installed()['sha'] ?? null, is_file(U::$dir . '/maintenance'), $stages()], [$v1, false, []]);
$eq('archive abîmée : aucune sauvegarde de plus', count(U::backups()), 1);

// 2. Échec en pleine copie (un dossier occupe la place d'un nouveau fichier) : retour arrière.
$put('app/Z.php/occupe', 'dossier');
@unlink("$root/storage/cache/apres-mise-a-jour");
$files = $base;
$files['app/A.php'] = "<?php // A v3\n";
$files['app/B.php'] = "<?php // B v3\n";
$files['app/Z.php'] = "<?php // Z v3\n";
$msg = '';
try {
    U::install($zip($v3, $files), $v3, $check($v3), ['name' => 'Essai']);
} catch (\Throwable $e) {
    $msg = $e->getMessage();
}
$eq('copie interrompue : erreur signalée avec le retour arrière', str_contains($msg, 'Mise à jour interrompue') && str_contains($msg, 'version précédente remise en place'), true);
$eq('copie interrompue : A remis en v1, B (ajouté) retiré', [$read('app/A.php'), is_file("$root/app/B.php")], ["<?php // A v1\n", false]);
$h = U::history()[0] ?? [];
$eq('copie interrompue : échec noté dans l’historique', [$h['to'] ?? null, $h['error'] ?? null, str_starts_with((string) ($h['message'] ?? ''), 'Échec de la mise à jour')], [$v3, true, true]);
$eq('copie interrompue : version installée inchangée', U::installed()['sha'] ?? null, $v1);
$eq('copie interrompue : pause levée, caches à refaire, dossier de travail supprimé', [is_file(U::$dir . '/maintenance'), is_file("$root/storage/cache/apres-mise-a-jour"), $stages()], [false, true, []]);
$eq('copie interrompue : pas de fichier temporaire laissé', glob("$root/app/*.maj-*") ?: [], []);

// 3. Téléchargement raté : l'archive partielle ne reste pas dans storage/update/tmp.
foreach ([fn ($f) => [500, ''], fn ($f) => throw new \RuntimeException('GitHub injoignable : coupure')] as $i => $end) {
    U::$http = function (string $url, ?string $toFile = null) use ($end) {
        if ($toFile === null) {
            return [500, ''];
        }
        file_put_contents($toFile, 'PK début d’archive');
        return $end($toFile);
    };
    $msg = '';
    try {
        U::apply(['name' => 'Essai'], $v2);
    } catch (\Throwable $e) {
        $msg = $e->getMessage();
    }
    $eq("téléchargement raté ($i) : erreur signalée", $msg !== '', true);
    $eq("téléchargement raté ($i) : archive partielle supprimée", glob(U::$dir . '/tmp/*.zip') ?: [], []);
}
U::$http = null;

// 4. Pause : public/index.php répond 503 sans charger l'application.
$flag = APP_ROOT . '/storage/update/maintenance';
$had = is_file($flag);
if (!$had) {
    file_put_contents($flag, (string) time());
}
$out = shell_exec(escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg('$_SERVER["REQUEST_URI"]="/"; $_SERVER["HTTP_HOST"]="localhost"; require "' . APP_ROOT . '/public/index.php"; echo "|", defined("APP_ROOT") ? "charge" : "non-charge";'));
if (!$had) {
    @unlink($flag);
}
$eq('pause : page « Mise à jour en cours » avant le démarrage', [str_contains((string) $out, 'Mise à jour en cours'), str_contains((string) $out, '|')], [true, false]);

exec('rm -rf ' . escapeshellarg($tmp));
echo $fail ? "\n$fail échec(s)\n" : "\nTout est bon.\n";
exit($fail ? 1 : 0);
