<?php
/**
 * Sous-rubrique calculée « Match officiel » (App\Data\OfficialPlayers) : liste livrée chargée,
 * résolution par numéro de fiche puis par le nom (même sans numéro), joueurs signalés « sans fiche »
 * bien retrouvés, ajustements du back-office (retrait, ajout), page publique (200, compteur, crédit,
 * entrée du méga-menu et pastille des sous-rubriques), écran du back-office, absence sur Paris.
 * Usage : php tests/match-officiel.php (code de sortie 1 en cas d'échec). Ne laisse rien dans data/.
 */
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Core\Auth;
use App\Core\JsonStore;
use App\Core\Request;
use App\Data\Collections;
use App\Data\Index;
use App\Data\Names;
use App\Data\OfficialPlayers as O;
use App\Kernel;

$fail = 0;
$eq = function (string $label, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    if (!$ok) {
        $fail++;
    }
    echo ($ok ? 'OK   ' : 'FAIL ') . $label . ($ok ? '' : ' => ' . json_encode($got, JSON_UNESCAPED_UNICODE) . ' attendu ' . json_encode($exp, JSON_UNESCAPED_UNICODE)) . "\n";
};

// 1. Liste livrée
$rows = O::rows();
$eq('liste chargée : 773 joueurs', count($rows), 773);
$eq('rubrique active pour Sochaux', O::enabled(), true);
$eq('numéros de fiche tous renseignés et distincts', count(array_unique(array_filter(array_map(fn ($r) => (int) $r[0], $rows)))), 773);

// 2. Résolution
$res = O::resolve();
$eq('toutes les lignes retrouvées au musée', [count($res['ids']), count($res['missing'])], [773, 0]);
$eq('compteur de la rubrique', O::count(), 773);
// Les cinq joueurs donnés « sans fiche » par le premier appariement ont bien leur fiche (autre graphie).
$five = ['René Colin' => 7327, 'Charles Gaffanesch' => 7983, 'Emile Harthong' => 8253, 'Mohamed Kader-Touré' => 5560, 'Natanaël Ntolla' => 9257];
$got = [];
foreach ($rows as $r) {
    $k = $r[2] . ' ' . $r[1];
    if (isset($five[$k])) {
        $got[$k] = O::has((int) $r[0]) ? (int) $r[0] : null;
    }
}
ksort($got);
ksort($five);
$eq('les 5 joueurs « à créer » sont des fiches existantes, dans la rubrique', $got, $five);

// Même sans numéro (fiche recréée en ligne sous un autre numéro) : retrouvée par le nom.
$persons = array_filter(Index::published('personne'), fn ($s) => isset($s['p']));
$byKey = [];
foreach ($persons as $s) {
    foreach (array_unique([Names::personKey((string) $s['title']), Names::personKey((string) ($s['p']['name'] ?? '')), Names::personKey(trim(($s['p']['first'] ?? '') . ' ' . ($s['p']['last'] ?? '')))]) as $k) {
        if ($k !== '') {
            $byKey[$k][$s['id']] = true;
        }
    }
}
$match = new ReflectionMethod(O::class, 'match');
$same = 0;
foreach ($rows as $r) {
    $id = (int) $r[0];
    $r[0] = 0;
    $same += $match->invoke(null, $r, $persons, $byKey) === $id ? 1 : 0;
}
$eq('sans numéro : les 773 lignes retrouvées par le nom, sur la bonne fiche', $same, 773);
$wrong = $rows[0];
$wrong[0] = 7327; // numéro d'un autre joueur (René Collin) : refusé, le nom l'emporte
$eq('numéro pointant vers un autre joueur : corrigé par le nom', $match->invoke(null, $wrong, $persons, $byKey), (int) $rows[0][0]);
$ghost = [0, 'Inconnu', 'Joueur', 'Joueur Inconnu', '1950-1951', 1, 0, 'A'];
$eq('joueur sans fiche : écarté', $match->invoke(null, $ghost, $persons, $byKey), null);

// 3. Ajustements du back-office (fichier d'essai, supprimé ensuite)
$file = Collections::DIR . '/' . O::ADJUST . '.json';
$before = is_file($file) ? file_get_contents($file) : null;
$first = (int) $rows[0][0];
$extra = (int) array_key_first(array_diff_key($persons, $res['ids']));
JsonStore::write($file, ['add' => [$extra], 'remove' => [$first]]);
clearstatcache();
$eq('retrait et ajout appliqués', [O::has($first), O::has($extra), O::count()], [false, true, 773]);
if ($before === null) {
    @unlink($file);
} else {
    file_put_contents($file, $before);
}
JsonStore::forget($file);
clearstatcache();
$eq('réglage effacé : liste livrée', [O::has($first), O::has($extra)], [true, false]);

// 4. Page publique (site ouvert, en mémoire seulement)
(new ReflectionProperty(\App\Core\Settings::class, 'values'))->setValue(null, ['waiting.enabled' => false, 'general.front_password' => ''] + \App\Core\Settings::all());
$get = fn (string $path, array $q = []) => Kernel::handle(new Request('GET', $path, $q, [], [], ['HTTP_HOST' => 'musee.fcsochauxretro.com', 'REQUEST_URI' => $path, 'REMOTE_ADDR' => '127.0.0.1', 'HTTP_USER_AGENT' => 'test-match-officiel'], ''));
$r = $get(O::PATH);
$html = (string) $r->body;
$eq('page 200', $r->status, 200);
$eq('titre, compteur et crédit FCSM Story', [str_contains($html, '<h1 class="mhead__title">Match officiel</h1>'), str_contains($html, 'data-count>773<'), str_contains($html, O::SOURCE_URL), str_contains($html, 'Coupe Peugeot')], [true, true, true, true]);
$eq('vignettes de la première page', substr_count($html, 'data-item'), \App\Front\Mosaic::PER_PAGE);
$r = $get(O::PATH, ['poste' => 'G']);
$eq('filtre « Gardiens »', $r->status === 200 && preg_match('/data-mcount>(\d+) portraits/', (string) $r->body, $m) && (int) $m[1] > 0 && (int) $m[1] < 773, true);
$html = (string) $get('/nos-lions/joueurs/internationaux/')->body;
$eq('méga-menu et pastilles : lien vers la sous-rubrique', [str_contains($html, 'class="mega__sub" href="' . O::PATH . '"'), str_contains($html, 'class="mchip" href="' . O::PATH . '"')], [true, true]);
$eq('adresse connue des vérifications (probe)', Kernel::probe(O::PATH)[0], 200);

// 5. Écran du back-office (administrateur)
(new ReflectionProperty(Auth::class, 'current'))->setValue(null, ['id' => 'essai-a', 'name' => 'Essai', 'email' => 'essai@example.org', 'role' => 'admin', 'status' => 'active']);
(new ReflectionProperty(Auth::class, 'loaded'))->setValue(null, true);
$r = \App\Admin\Officials::index(new Request('GET', '/admin/match-officiel', [], [], [], ['REQUEST_URI' => '/admin/match-officiel'], ''));
$eq('écran Éditorial › Match officiel', [$r->status, str_contains((string) $r->body, 'joueurs affichés')], [200, true]);

// 6. Paris : pas de rubrique
$out = shell_exec('MUSEE_CLUB=paris ' . escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg('require ' . var_export(APP_ROOT . '/app/bootstrap.php', true) . '; echo json_encode([App\Data\OfficialPlayers::enabled(), App\Data\OfficialPlayers::count(), App\Kernel::probe(App\Data\OfficialPlayers::PATH)[0]]);'));
$eq('Paris : ni rubrique ni page', json_decode((string) $out, true), [false, 0, 404]);

echo $fail ? "\n$fail échec(s)\n" : "\nTout est bon.\n";
exit($fail ? 1 : 0);
