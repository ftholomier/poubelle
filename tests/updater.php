<?php
/**
 * Mises à jour en un clic (App\Services\Updater) : seuls les fichiers du code qui ont changé
 * sont remplacés ; data/, storage/ et public/media/ jamais touchés ; .htaccess réglé à la main
 * gardé ; libellés anglais fusionnés ; fichiers retirés du dépôt supprimés ; sauvegarde et
 * retour arrière ; archive piégée refusée ; vérification GitHub (API, flux Atom en secours) ;
 * pause du site pendant la copie. Usage : php tests/updater.php (code de sortie 1 en cas
 * d'échec). N'écrit que dans un dossier temporaire (et, un instant, le drapeau de pause).
 */
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Core\Request;
use App\Kernel;
use App\Services\Updater as U;

$tmp = sys_get_temp_dir() . '/maj-test-' . bin2hex(random_bytes(4));
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
/** Archive au format GitHub : un dossier racine « poubelle-<version>/ », la version en commentaire. */
$zip = function (string $sha, array $files) use ($tmp): string {
    $path = "$tmp/$sha.zip";
    $z = new ZipArchive();
    $z->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $z->addEmptyDir("poubelle-$sha");
    foreach ($files as $rel => $content) {
        $z->addFromString("poubelle-$sha/$rel", $content);
    }
    $z->setArchiveComment($sha);
    $z->close();
    return $path;
};
$v1 = str_repeat('1', 40);
$v2 = str_repeat('2', 40);
$check = fn (string $sha, string $msg) => ['latest' => ['sha' => $sha, 'date' => '2026-10-04T10:00:00Z', 'message' => $msg], 'commits' => []];

// Site mis en ligne par FTP : code ancien, réglages faits à la main, données des historiens.
$put('app/bootstrap.php', "<?php // amorce\n");
$put('public/index.php', "<?php // entrée\n");
$put('app/A.php', "<?php // A ancien\n");
$put('public/.htaccess', "# HTTPS activé à la main\nRewriteEngine On\n");
$put('data/i18n/en.json', json_encode(['Bonjour' => 'Hello (traduit sur le serveur)'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");
$put('data/fiches/1.json', '{"fiche":"modifiée par un historien"}');
$put('storage/settings.json', '{"reglage":"serveur"}');
$put('public/media/800/photo.jpg.webp', 'vignette');
$put('storage/cache/derived.php', '<?php return [];');
$put('storage/cache/derived/matches-0123456789ab.php', '<?php return [];');
$put('storage/cache/index-2.php', '<?php return [];');
$put('storage/cache/media/3.php', '<?php return [];');
$put('storage/cache/sitemap.xml', '<urlset/>');
$put('storage/cache/memo/menus-1a2b3c4d.php', '<?php return [];');
$put('storage/cache/correcteur/ab/abc.json', '{"items":[]}');

// 1. Première mise à jour : version installée inconnue.
$files1 = [
    'app/bootstrap.php' => "<?php // amorce\n",           // identique : pas réécrit
    'public/index.php' => "<?php // entrée v1\n",
    'app/A.php' => "<?php // A v1\n",
    'app/B.php' => "<?php // B v1\n",                      // nouveau
    'templates/x.php' => "x v1\n",
    'public/.htaccess' => "# version du dépôt\n",           // modifié sur le serveur : gardé
    'data/i18n/en.json' => json_encode(['Bonjour' => 'Hello', 'Nouveau' => 'New'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n",
    'data/fiches/1.json' => '{"fiche":"version du dépôt"}', // jamais touché
    'docs/LISEZMOI.md' => 'doc',                             // hors code : ignoré
    'public/media/800/photo.jpg.webp' => 'autre',            // jamais touché
];
$mtime = filemtime("$root/app/bootstrap.php");
$r = U::install($zip($v1, $files1), $v1, $check($v1, 'Première version'), ['name' => 'Essai']);
sort($r['updated']);
$eq('fichiers du code remplacés ou ajoutés (seulement ceux qui changent)', $r['updated'], ['app/A.php', 'app/B.php', 'public/index.php', 'templates/x.php']);
$eq('fichier identique non réécrit', filemtime("$root/app/bootstrap.php"), $mtime);
$eq('.htaccess réglé à la main : gardé', [$r['kept'], $read('public/.htaccess')], [['public/.htaccess'], "# HTTPS activé à la main\nRewriteEngine On\n"]);
$en = json_decode((string) $read('data/i18n/en.json'), true);
$eq('libellés anglais : nouveau ajouté, traduction du serveur gardée', [$r['merged'], $en], [1, ['Bonjour' => 'Hello (traduit sur le serveur)', 'Nouveau' => 'New']]);
$eq('données, réglages, médias jamais touchés', [$read('data/fiches/1.json'), $read('storage/settings.json'), $read('public/media/800/photo.jpg.webp'), is_file("$root/docs/LISEZMOI.md")],
    ['{"fiche":"modifiée par un historien"}', '{"reglage":"serveur"}', 'vignette', false]);
$eq('version installée connue', U::installed()['sha'] ?? null, $v1);
$eq('caches vidés, calculs Memo et relectures du correcteur gardés', [is_file("$root/storage/cache/sitemap.xml"), is_file("$root/storage/cache/memo/menus-1a2b3c4d.php"), is_file("$root/storage/cache/correcteur/ab/abc.json")], [false, true, true]);
$eq('gros caches de données gardés (refaits en arrière-plan)', [is_file("$root/storage/cache/derived.php"), is_file("$root/storage/cache/derived/matches-0123456789ab.php"), is_file("$root/storage/cache/index-2.php"), is_file("$root/storage/cache/media/3.php")], [true, true, true, true]);
$eq('caches à refaire signalés à la requête suivante', is_file("$root/storage/cache/apres-mise-a-jour"), true);
$eq('pause du site levée après la copie', is_file(U::$dir . '/maintenance'), false);
$eq('sauvegarde créée', count(U::backups()), 1);

// 2. Deuxième version : A modifié, B retiré du dépôt.
$files2 = $files1;
$files2['app/A.php'] = "<?php // A v2\n";
unset($files2['app/B.php']);
$r = U::install($zip($v2, $files2), $v2, $check($v2, 'Deuxième version'), ['name' => 'Essai']);
$eq('fichier modifié remplacé', [$r['updated'], $read('app/A.php')], [['app/A.php'], "<?php // A v2\n"]);
$eq('fichier retiré du dépôt supprimé', [$r['deleted'], is_file("$root/app/B.php")], [['app/B.php'], false]);
$eq('.htaccess toujours gardé', $r['kept'], ['public/.htaccess']);
$eq('historique', array_map(fn ($h) => $h['to'], U::history()), [$v2, $v1]);

// 3. Retour arrière : la version 1 revient.
$b = U::backups()[0]['name'];
$back = U::rollback($b, ['name' => 'Essai']);
$eq('retour arrière : A et B remis', [$read('app/A.php'), $read('app/B.php')], ["<?php // A v1\n", "<?php // B v1\n"]);
$eq('retour arrière : version installée v1', U::installed()['sha'] ?? null, $v1);
// …et la mise à jour suivante supprime de nouveau B (manifeste de la version 1 rétabli).
$r = U::install($zip($v2, $files2), $v2, $check($v2, 'Deuxième version'), ['name' => 'Essai']);
$eq('manifeste rétabli : B de nouveau supprimé', $r['deleted'], ['app/B.php']);

// 4. Archives refusées : chemin piégé, archive qui n'est pas le musée, mauvaise version.
$evil = "$tmp/evil.zip";
$z = new ZipArchive();
$z->open($evil, ZipArchive::CREATE | ZipArchive::OVERWRITE);
$z->addFromString('poubelle-x/app/bootstrap.php', '<?php');
$z->addFromString('poubelle-x/public/index.php', '<?php');
$z->addFromString('poubelle-x/../../pirate.php', '<?php // pirate');
$z->close();
$msg = '';
try {
    U::install($evil, $v2, $check($v2, ''), null);
} catch (RuntimeException $e) {
    $msg = $e->getMessage();
}
$eq('chemin piégé (« .. ») refusé, rien d’écrit', [str_contains($msg, 'chemin suspect'), is_file("$tmp/pirate.php"), is_file("$root/../pirate.php")], [true, false, false]);
$msg = '';
try {
    U::install($zip($v2, ['README.md' => 'autre projet']), $v2, $check($v2, ''), null);
} catch (RuntimeException $e) {
    $msg = $e->getMessage();
}
$eq('archive d’un autre projet refusée', str_contains($msg, 'pas le code du musée'), true);
$msg = '';
try {
    U::install($zip($v1, $files1), $v2, $check($v2, ''), null);
} catch (RuntimeException $e) {
    $msg = $e->getMessage();
}
$eq('archive d’une autre version refusée', str_contains($msg, 'ne correspond pas'), true);
$eq('chemins sûrs', [U::safe('app/A.php'), U::safe('../x'), U::safe('/etc/passwd'), U::safe('app/../x'), U::safe("a\\b"), U::safe('')], [true, false, false, false, false, false]);
$eq('périmètre', [U::selected('app/X.php'), U::selected('public/media/a.webp'), U::selected('data/fiches/1.json'), U::selected('data/i18n/en.json'), U::selected('storage/x'), U::selected('docs/a.md')], [true, false, false, true, false, false]);

// 5. Vérification : API GitHub, flux Atom si l'API refuse, changements limités au code.
$sha3 = str_repeat('3', 40);
U::$http = function (string $url) use ($sha3, $v2) {
    if (str_contains($url, '/commits/') && str_starts_with($url, 'https://api.github.com/')) {
        return [200, json_encode(['sha' => $sha3, 'commit' => ['message' => "Menu fixe\n\nDétails", 'committer' => ['date' => '2026-10-04T11:00:00Z']]])];
    }
    if (str_contains($url, '/compare/')) {
        return [200, json_encode(['commits' => [['sha' => $sha3, 'commit' => ['message' => 'Menu fixe', 'committer' => ['date' => '2026-10-04T11:00:00Z']]]],
            'files' => [['filename' => 'app/Kernel.php'], ['filename' => 'data/fiches/2.json'], ['filename' => 'docs/X.md']]])];
    }
    return [404, ''];
};
$c = U::check(true);
$eq('dernière version (API)', [$c['latest']['sha'], $c['latest']['message'], $c['error']], [$sha3, 'Menu fixe', null]);
$eq('changements : seuls les fichiers du code', [count($c['commits']), $c['files']], [1, ['app/Kernel.php']]);
$eq('mise à jour proposée', U::available()['latest']['sha'] ?? null, $sha3);
$sha4 = str_repeat('4', 40);
U::$http = function (string $url) use ($sha4, $sha3, $v2) {
    if (str_starts_with($url, 'https://api.github.com/')) {
        return [403, '{"message":"API rate limit exceeded"}'];
    }
    if (str_ends_with($url, '.atom')) {
        $e = fn ($s, $t) => "<entry><id>tag:github.com,2008:Grit::Commit/$s</id><updated>2026-10-04T12:00:00Z</updated><title>$t</title></entry>";
        return [200, '<?xml version="1.0"?><feed xmlns="http://www.w3.org/2005/Atom">' . $e($sha4, 'Pied de page') . $e($sha3, 'Menu fixe') . $e($v2, 'Deuxième version') . '</feed>'];
    }
    return [404, ''];
};
$c = U::check(true);
$eq('limite de l’API : flux Atom en secours', [$c['latest']['sha'] ?? null, array_column($c['commits'], 'message'), $c['error']], [$sha4, ['Pied de page', 'Menu fixe'], null]);
U::$http = fn () => [404, ''];
$c = U::check(true);
$eq('branche introuvable : message clair', str_contains((string) $c['error'], 'introuvable'), true);
$eq('rien de proposé sans vérification réussie', U::available(), null);
U::$http = null;

// 5 bis. Synchronisation : le code du serveur comparé fichier par fichier à GitHub (empreintes Git).
$eq('empreinte Git (comme « git hash-object »)', sha1("blob 6\0hello\n"), 'ce013625030ba8dba906f756967f9e9ca394464a');
$root2 = "$tmp/site2";
U::$root = $root2;
U::$dir = "$root2/storage/update";
U::$cacheDir = "$root2/storage/cache";
$put2 = function (string $rel, string $content) use ($root2) {
    @mkdir(dirname("$root2/$rel"), 0775, true);
    file_put_contents("$root2/$rel", $content);
};
$blob = fn (string $s) => sha1('blob ' . strlen($s) . "\0" . $s);
$png = "\x89PNG\r\n\x1a\n\0\0\0\rIHDR\r\n";
$gh = [ // la version sur GitHub
    'app/bootstrap.php' => "<?php // amorce\n",
    'public/index.php' => "<?php // entrée\n",
    'app/A.php' => "<?php // A GitHub\n",
    'public/assets/vendor.css' => "a {}\r\nb {}\r\n",
    'public/.htaccess' => "# version du dépôt\n",
    'public/img.png' => $png,
    'templates/t.php' => "t\n",
];
// Le serveur : A retouché, vendor.css passé en LF par le FTP, .htaccess réglé à la main, t.php
// oublié, image abîmée par un transfert en mode texte.
$put2('app/bootstrap.php', $gh['app/bootstrap.php']);
$put2('public/index.php', $gh['public/index.php']);
$put2('app/A.php', "<?php // A retouché\n");
$put2('public/assets/vendor.css', "a {}\nb {}\n");
$put2('public/.htaccess', "# réglé à la main\n");
$put2('public/img.png', str_replace("\r\n", "\n", $png));
$sha5 = str_repeat('5', 40);
$calls = [];
$ghHttp = function (string $url) use (&$calls, &$gh, $sha5, $blob) {
    $calls[] = $url;
    if (str_contains($url, '/commits/')) {
        return [200, json_encode(['sha' => $sha5, 'commit' => ['message' => 'Version 5', 'committer' => ['date' => '2026-10-04T13:00:00Z']]])];
    }
    if (!preg_match('#/git/trees/([0-9a-f]{40})(\?recursive=1)?$#', $url, $m)) {
        return [404, ''];
    }
    $dirs = [];
    foreach ($gh as $rel => $content) {
        [$top, $rest] = explode('/', $rel, 2);
        $dirs[$top][$rest] = $blob($content);
    }
    $treeSha = fn (array $files) => sha1((string) json_encode($files));
    if ($m[1] === $sha5) {
        $tree = [['path' => 'README.md', 'type' => 'blob', 'mode' => '100644', 'sha' => $blob('lisez-moi')], ['path' => 'data', 'type' => 'tree', 'mode' => '040000', 'sha' => str_repeat('d', 40)]];
        foreach ($dirs as $top => $files) {
            $tree[] = ['path' => $top, 'type' => 'tree', 'mode' => '040000', 'sha' => $treeSha($files)];
        }
        return [200, json_encode(['sha' => $sha5, 'tree' => $tree, 'truncated' => false])];
    }
    foreach ($dirs as $top => $files) {
        if ($treeSha($files) === $m[1]) {
            $list = $top === 'public' ? [['path' => 'assets', 'type' => 'tree', 'mode' => '040000', 'sha' => str_repeat('a', 40)]] : [];
            $list[] = ['path' => 'lien', 'type' => 'blob', 'mode' => '120000', 'sha' => $blob('cible')]; // lien symbolique : ignoré
            foreach ($files as $p => $b) {
                $list[] = ['path' => $p, 'type' => 'blob', 'mode' => '100644', 'sha' => $b];
            }
            return [200, json_encode(['sha' => $m[1], 'tree' => $list, 'truncated' => false])];
        }
    }
    return [404, ''];
};
U::$http = $ghHttp;
$c = U::check(true);
$s = $c['sync'];
ksort($s['differ']);
$eq('comparaison : fichiers à remplacer (retouché, oublié, image abîmée)', [$s['count'], $s['differ']], [3, ['app/A.php' => 'différent', 'public/img.png' => 'différent', 'templates/t.php' => 'absent']]);
$eq('comparaison : fins de ligne sans effet, .htaccess réglé à la main gardé, data/ et liens ignorés', [$s['total'], $s['eol'], $s['kept'], $s['error']], [7, 1, ['public/.htaccess'], null]);
$eq('version pas reconnue tant qu’un fichier diffère', U::installed(), null);
$m = U::available() ?? [];
$eq('synchronisation proposée (pas une nouvelle version)', [$m !== [], U::isNewVersion($m), U::summary($m)], [true, false, 'Le code du serveur diffère de GitHub (3 fichiers) : le synchroniser en un clic']);
$put2('app/A.php', $gh['app/A.php']);
$put2('templates/t.php', $gh['templates/t.php']);
$put2('public/img.png', $png);
$calls = [];
$c = U::check(true);
$eq('code identique : version reconnue sans rien réécrire', [U::installed()['sha'] ?? null, U::installed()['via'] ?? null, U::available()], [$sha5, 'reconnue', null]);
$eq('dossiers inchangés : pas redemandés à GitHub', count(array_filter($calls, fn ($u) => str_contains($u, '?recursive=1'))), 0);
$man = json_decode((string) file_get_contents(U::$dir . '/manifeste.json'), true);
$eq('manifeste : fichiers identiques, sans le .htaccess réglé à la main', [isset($man['app/A.php'], $man['public/assets/vendor.css']), isset($man['public/.htaccess'])], [true, false]);
$eq('historique : version reconnue', str_starts_with((string) (U::history()[0]['message'] ?? ''), 'Version reconnue'), true);
$put2('app/A.php', "<?php // retouche par FTP\n");
$c = U::check(true);
$eq('fichier retouché après coup : signalé', [U::differing($c), U::available() !== null, U::isNewVersion($c)], [1, true, false]);
$r = U::install($zip($sha5, $gh), $sha5, $c, ['name' => 'Essai']);
$eq('synchronisation : fichier remis, .htaccess gardé', [in_array('app/A.php', $r['updated'], true), $r['kept'], file_get_contents("$root2/app/A.php")], [true, ['public/.htaccess'], $gh['app/A.php']]);
$eq('après la copie : plus rien à remplacer', [U::differing(U::state()['check']), U::available()], [0, null]);
U::rollback(U::backups()[0]['name'], ['name' => 'Essai']);
$eq('retour arrière : comparaison à refaire', U::differing(U::state()['check']), null);
U::$http = fn (string $url) => str_contains($url, '/git/trees/') ? [403, '{"message":"API rate limit exceeded"}'] : $ghHttp($url);
$c = U::check(true);
$eq('limite de l’API : vérification faite, comparaison reportée', [$c['error'], str_contains((string) $c['sync']['error'], 'limite'), U::differing($c)], [null, true, null]);
U::$http = null;

// 6. Pause du site pendant la copie : le Kernel répond 503 « Mise à jour en cours ».
$flag = STORAGE_PATH . '/update/maintenance';
$hadDir = is_dir(dirname($flag));
@mkdir(dirname($flag), 0775, true);
$had = is_file($flag);
file_put_contents($flag, (string) time());
$res = Kernel::handle(new Request('GET', '/', [], [], [], ['HTTP_HOST' => 'localhost', 'REQUEST_URI' => '/', 'REMOTE_ADDR' => '127.0.0.1', 'HTTP_USER_AGENT' => 'test-maj'], ''));
if (!$had) {
    @unlink($flag);
}
if (!$hadDir) {
    @rmdir(dirname($flag));
}
$eq('pause : 503 « Mise à jour en cours »', [$res->status, str_contains($res->body, 'Mise à jour en cours')], [503, true]);

// Ménage
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmp, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
foreach ($it as $f) {
    $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
}
@rmdir($tmp);
echo $fail ? "\n$fail échec(s).\n" : "\nTout est bon.\n";
exit($fail ? 1 : 0);
