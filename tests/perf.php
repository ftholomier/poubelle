<?php
/**
 * Optimisations des temps d'accès : elles ne changent aucun résultat. Cache de calculs (App\Core\Memo),
 * repliement ASCII rapide (Names::ascii, identique à ICU), index des apparitions (Derived), ordre
 * des rubriques et compteurs des menus, réseau du Fil jaune, plans de l'écran Fiches audio.
 * Usage : php tests/perf.php (code de sortie 1 en cas d'échec). N'écrit que des fichiers de cache.
 */
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Core\Memo;
use App\Data\Categories;
use App\Data\Derived;
use App\Data\Index;
use App\Data\Names;

$fail = 0;
$eq = function (string $label, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    if (!$ok) {
        $fail++;
    }
    echo ($ok ? 'OK   ' : 'FAIL ') . $label . ($ok ? '' : ' => ' . json_encode($got, JSON_UNESCAPED_UNICODE) . ' attendu ' . json_encode($exp, JSON_UNESCAPED_UNICODE)) . "\n";
};

// Cache de calculs : gardé tant que les fichiers sources ne changent pas.
$name = 'essai-' . bin2hex(random_bytes(4));
$src = sys_get_temp_dir() . "/$name.txt";
file_put_contents($src, 'a');
$calls = 0;
$f = function () use (&$calls) {
    $calls++;
    return ['n' => $calls];
};
Memo::get($name, [$src], '', $f);
Memo::forget();
$eq('valeur reprise du cache (pas de nouveau calcul)', [Memo::get($name, [$src], '', $f), $calls], [['n' => 1], 1]);
file_put_contents($src, 'abc');
clearstatcache();
Memo::forget();
$eq('source modifiée : recalcul', [Memo::get($name, [$src], '', $f), $calls], [['n' => 2], 2]);
Memo::forget();
$eq('autre empreinte (données recalculées) : recalcul', [Memo::get($name, [$src], 'v2', $f), $calls], [['n' => 3], 3]);
@unlink($src);
array_map('unlink', glob(Memo::DIR . "/$name-*.php") ?: []);

// Repliement ASCII : table tirée d'ICU, même résultat qu'ICU (y compris accents « combinants »,
// émojis, autres écritures, entités HTML).
$tr = Transliterator::create('Any-Latin; Latin-ASCII; Lower()');
$samples = ['Bernard Genghini', 'Éric Hély', 'Mecha Bazdarević', 'Ðorđe', 'Œuvre « Sochaux » — 1–0 …', 'Stade Auguste-Bonal 💛💙', 'São Paulo', 'Ålesund', 'Straße',
    "e\u{0301}tienne", '1️⃣ but', '❤️ Sochaux', 'Δελτίο ; Ж', '𝗦𝗼𝗰𝗵𝗮𝘂𝘅', 'Saint-&Eacute;tienne &amp; Lyon', 'ᵉ siècle', "Ĳsselmeer", '№ 10 → 2'];
$diff = array_filter($samples, fn ($s) => Names::ascii($s) !== $tr->transliterate(html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
$eq('repliement ASCII identique à ICU', array_values($diff), []);
mt_srand(3);
$bad = 0;
for ($i = 0; $i < 20000; $i++) {
    $s = '';
    for ($j = mt_rand(1, 8); $j > 0; $j--) {
        $r = [[0x20, 0x7E], [0xC0, 0x24F], [0x300, 0x36F], [0x1E00, 0x1EFF], [0x2000, 0x206F], [0x1F300, 0x1F64F], [0x391, 0x3C9], [0x1D400, 0x1D7FF], [0xFE0F, 0xFE0F]][mt_rand(0, 8)];
        $s .= mb_chr(mt_rand($r[0], $r[1]), 'UTF-8') ?: '';
    }
    $bad += Names::ascii($s) !== $tr->transliterate($s) ? 1 : 0;
}
$eq('20 000 chaînes au hasard : identique à ICU', $bad, 0);

// Index des apparitions : mêmes lignes que le parcours complet.
$d = Derived::get();
$mid = (int) array_key_first(array_filter($d['matches'], fn ($m) => !empty($m['v'])));
$scan = array_values(array_filter($d['apps'], fn ($a) => $a[1] === $mid));
$eq('composition d’un match : mêmes lignes', Derived::lineupLinks($mid), $scan);
$pid = (int) $d['apps'][0][0];
$eq('matchs d’un joueur : autant que de lignes visibles', count(Derived::personMatches($pid)), count(array_filter($d['apps'], fn ($a) => $a[0] === $pid && $d['matches'][$a[1]]['v'])));

// Page de fiche : seules ses parties sont lues (le fichier du groupe de ses compositions, pas les
// 26 000 lignes), pour le même résultat que tout le calcul en mémoire.
$avant = [Derived::lineupLinks($mid), Derived::personMatches($pid)];
(new ReflectionMethod(Derived::class, 'forget'))->invoke(null);
$eq('fiche lue partie par partie : mêmes compositions et mêmes matchs', [Derived::lineupLinks($mid), Derived::personMatches($pid)], $avant);
$lues = array_keys((new ReflectionProperty(Derived::class, 'parts'))->getValue());
sort($lues);
$attendu = ['apps_m.' . ($mid % 16), 'apps_p.' . ($pid % 16), 'matches'];
sort($attendu);
$eq('fiche : seules les parties utiles sont lues', $lues, $attendu);
$eq('date du calcul sans tout relire', Derived::built(), $d['built']);

// Médiathèque : une image est cherchée dans son seul groupe, même description qu'en entier.
$tout = \App\Data\Media::all();
$rel = (string) array_rand($tout);
(new ReflectionProperty(\App\Data\Media::class, 'items'))->setValue(null, null);
(new ReflectionProperty(\App\Data\Media::class, 'parts'))->setValue(null, []);
$eq('média lu dans son groupe : même description', \App\Data\Media::get($rel), $tout[$rel]);
$eq('un seul groupe de la médiathèque lu', count((new ReflectionProperty(\App\Data\Media::class, 'parts'))->getValue()), 1);

// Rubriques : ordre en cache identique au calcul direct.
$slug = 'joueurs';
$direct = Index::ordered(array_filter(Index::published(), fn ($s) => (bool) array_intersect(Categories::descendants($slug, true), $s['categories'])), $slug);
$eq('rubrique « joueurs » : même ordre', array_column(Index::inCategory($slug), 'id'), array_column($direct, 'id'));
$byName = array_values(array_filter($direct, fn ($s) => $s['type'] === 'personne'));
usort($byName, fn ($a, $b) => strcoll(Index::sortName($a), Index::sortName($b)));
$eq('personnes par ordre alphabétique : même ordre', array_column(Index::personsByName($slug), 'id'), array_column($byName, 'id'));

// Plans de l'écran Fiches audio : identiques au calcul complet.
[$plan, $text] = \App\Services\FicheAudio::overview();
$eq('écran Fiches audio : mêmes plans que le calcul complet', [$plan, $text], [\App\Services\FicheAudio::plan(['fr', 'en']), \App\Services\FicheAudio::plan(['fr', 'en'], false, null, true)]);

echo $fail ? "\n$fail échec(s)\n" : "\nTout est bon.\n";
exit($fail ? 1 : 0);
