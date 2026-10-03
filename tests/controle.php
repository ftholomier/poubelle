<?php
/**
 * Contrôle complet (bouton « Contrôler maintenant » de l'écran Qualité) : vérifications des
 * matchs et des fiches, clés stables des alertes, nouvelles anomalies d'un contrôle à l'autre.
 * Usage : php tests/controle.php (code de sortie 1 en cas d'échec).
 * Ne modifie aucune fiche ; le fichier du dernier contrôle (storage/controle.json) est remis en place.
 */
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Admin\Quality;
use App\Data\Derived;
use App\Data\Fiches;
use App\Data\Index;
use App\Data\Media;
use App\Services\Controle;

$fail = 0;
$eq = function (string $label, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    if (!$ok) {
        $fail++;
    }
    echo ($ok ? 'OK   ' : 'FAIL ') . $label . ($ok ? '' : ' => ' . json_encode($got, JSON_UNESCAPED_UNICODE) . ' attendu ' . json_encode($exp, JSON_UNESCAPED_UNICODE)) . "\n";
};
$call = function (string $class, string $method, ...$args) {
    $m = new ReflectionMethod($class, $method);
    return $m->invoke(null, ...$args);
};
$codes = fn (array $alerts) => array_column($alerts, 'code');

// ---------------------------------------------------------------- matchs
$past = date('Y') - 2;
$match = fn (array $m) => array_replace_recursive([
    'date' => "$past-10-12", 'season' => $past . '-' . ($past + 1), 'competition' => 'Championnat', 'event' => null,
    'sochaux_home' => true, 'score' => ['home' => 2, 'away' => 1], 'result' => 'V', 'lineup' => ['rows' => []],
], $m);
$check = fn (array $m, bool $vis = true) => $call(Derived::class, 'matchChecks', $m, $m['score'] ? ($m['sochaux_home'] ? $m['score']['home'] : $m['score']['away']) : null, $m['score'] ? ($m['sochaux_home'] ? $m['score']['away'] : $m['score']['home']) : null, $vis);
$eq('match correct : aucune alerte', $check($match([])), []);
$eq('match publié sans date', $codes($check(array_replace($match([]), ['date' => null, 'season' => null]))), ['match-date']);
$eq('brouillon sans date : rien (en cours de saisie)', $check(array_replace($match([]), ['date' => null, 'season' => null]), false), []);
$eq('date impossible (30 février)', $codes($check($match(['date' => "$past-02-30"]))), ['match-date']);
$eq('date hors de la saison', $codes($check($match(['date' => ($past + 2) . '-01-15']))), ['saison']);
$eq('amical de fin juin rangé dans la saison suivante : accepté', $check($match(['date' => "$past-06-28", 'competition' => 'Amical'])), []);
$eq('saison vide', $codes($check($match(['season' => '']))), ['saison']);
$eq('résultat incohérent avec le score', $codes($check($match(['result' => 'D']))), ['resultat']);
$eq('à l’extérieur : score lu côté Sochaux', $check($match(['sochaux_home' => false, 'score' => ['home' => 1, 'away' => 3], 'result' => 'V'])), []);
$tab = $match(['score' => ['home' => 1, 'away' => 1, 'pens' => ['home' => 4, 'away' => 5]], 'result' => 'D']);
$eq('tirs au but perdus : défaite cohérente', $check($tab), []);
$eq('tirs au but saisis sur un score non nul', array_map(fn ($a) => str_starts_with($a['msg'], 'Tirs au but saisis alors que le score n’est pas nul (2-1'), $check($match(['score' => ['home' => 2, 'away' => 1, 'pens' => ['home' => 4, 'away' => 5]]]))), [true]);
$eq('tirs au but sans la séance : victoire sur un nul acceptée', $check($match(['score' => ['home' => 0, 'away' => 0, 'extra' => '0-0 (tab)'], 'result' => 'V'])), []);
$eq('score saisi pour un match à venir', $codes($check($match(['date' => (date('Y') + 1) . '-01-10', 'season' => date('Y') . '-' . (date('Y') + 1)]))), ['score']);
$noScore = $match([]);
$noScore['score'] = null;
$noScore['result'] = null;
$eq('match officiel joué sans score', $codes($check($noScore)), ['score']);
$eq('amical sans score : rien', $check(array_replace($noScore, ['competition' => 'Amical'])), []);
$eq('tournoi (événement) sans score : rien', $check(array_replace($noScore, ['event' => 'Tournoi en salle'])), []);
$rows = function (int $starters, array $extra = []) {
    $r = [['position' => 'G', 'name' => 'Gardien Un']];
    for ($i = 1; $i < $starters; $i++) {
        $r[] = ['position' => 'D', 'name' => "Joueur $i"];
    }
    return array_merge($r, $extra);
};
$eq('composition de 11 avec remplaçant : rien', $check($match(['lineup' => ['rows' => $rows(11, [['position' => 'R', 'name' => 'Remplaçant', 'sub_in' => '60']])]])), []);
$eq('remplaçant noté titulaire (onze titulaires par ailleurs)', array_map(fn ($a) => str_starts_with($a['msg'], 'Joueur entré en cours de jeu noté titulaire : Entrant (60’)'), $check($match(['lineup' => ['rows' => $rows(11, [['position' => 'A', 'name' => 'ENTRANT', 'sub_in' => '60']])]]))), [true]);
$eq('minute d’entrée sur un titulaire (dix titulaires)', array_map(fn ($a) => str_starts_with($a['msg'], 'Minute d’entrée en jeu notée pour un titulaire'), $check($match(['lineup' => ['rows' => $rows(10, [['position' => 'M', 'name' => 'Milieu', 'sub_in' => '75']])]]))), [true]);
$eq('12 titulaires', $codes($check($match(['lineup' => ['rows' => $rows(12)]]))), ['compo']);
$eq('deux gardiens titulaires', $codes($check($match(['lineup' => ['rows' => $rows(10, [['position' => 'G', 'name' => 'Gardien Deux']])]]))), ['compo']);
$eq('composition incomplète (7 titulaires)', array_column($check($match(['lineup' => ['rows' => $rows(7)]])), 'sev'), ['basse']);
$eq('amical : composition non contrôlée', $check($match(['competition' => 'Amical', 'lineup' => ['rows' => $rows(14)]])), []);

// ---------------------------------------------------------------- fiches (adresse, rubriques, images)
$lib = Media::all();
$rel = (string) array_key_first(array_filter($lib, fn ($m, $r) => is_file(Media::ORIGINALS . '/' . $r), ARRAY_FILTER_USE_BOTH));
$doc = ['title' => 'Essai', 'path' => '/joueurs/essai/', 'categories' => [], 'featured_image' => $rel, 'gallery' => [], 'images' => []];
$fiche = fn (array $d, bool $vis = true, ?array $library = null) => $call(Derived::class, 'ficheChecks', array_replace($doc, $d), $vis, \App\Data\Categories::all(), $library ?? $lib);
$eq('fiche correcte : aucune alerte', $fiche([]), []);
$eq('titre vide', $codes($fiche(['title' => ' '])), ['titre']);
$eq('adresse mal formée', $codes($fiche(['path' => 'joueurs/essai'])), ['adresse']);
$eq('adresse avec espace', $codes($fiche(['path' => '/joueurs/es sai/'])), ['adresse']);
$eq('adresse vide d’un brouillon : rien', $fiche(['path' => ''], false), []);
$eq('rubrique supprimée', $codes($fiche(['categories' => ['rubrique-qui-n-existe-pas']])), ['rubrique']);
$eq('image absente de la médiathèque', $codes($fiche(['gallery' => [['image' => '2099/01/disparue.jpg']]])), ['image']);
$missing = $lib + ['2099/01/sans-fichier.jpg' => ['file' => '2099/01/sans-fichier.jpg']];
$eq('fichier d’image absent du serveur', $call(Derived::class, 'imageFiles', array_replace($doc, ['featured_image' => '2099/01/sans-fichier.jpg']), $missing, [$rel]), [[$rel, '2099/01/sans-fichier.jpg'], ['2099/01/sans-fichier.jpg']]);
$alerts = $call(Derived::class, 'photoAlerts', [12 => ['2099/01/a.jpg'], 15 => ['2099/01/b.jpg', '2099/01/c.jpg']], 3000);
$eq('quelques fichiers absents : une alerte par fiche', [array_column($alerts, 'code'), array_column($alerts, 'id')], [['image', 'image'], [12, 15]]);
$many = [];
for ($i = 0; $i < 900; $i++) {
    $many[$i + 1] = ["2099/01/photo-$i.jpg"];
}
$alerts = $call(Derived::class, 'photoAlerts', $many, 1000);
$eq('photos pas encore copiées (serveur neuf) : une seule alerte', [count($alerts), $alerts[0]['code'], $alerts[0]['sev']], [1, 'photos', 'haute']);

// ---------------------------------------------------------------- redirections
$draft = null;
foreach (Index::all() as $s) {
    if ($s['status'] === 'brouillon' && $s['path']) {
        $draft = $s;
        break;
    }
}
$eq('page calculée (saisons) : trouvée', $call(Controle::class, 'missing', '/saisons/'), null);
$eq('page calculée en anglais : trouvée', $call(Controle::class, 'shows', '/en/records/'), true);
$visible = array_values(array_filter(Index::all(), fn ($s) => $s['type'] === 'match' && Index::visible($s)))[0];
$eq('fiche publiée : trouvée', $call(Controle::class, 'missing', $visible['path']), null);
$eq('fiche en brouillon : non publiée', $draft ? $call(Controle::class, 'missing', $draft['path']) : 'fiche non publiée (brouillon)', 'fiche non publiée (brouillon)');
$eq('adresse inconnue : introuvable', $call(Controle::class, 'missing', '/cette-page-n-existe-pas/'), 'page introuvable');

// ---------------------------------------------------------------- clés des alertes
$a = ['id' => 12, 'code' => 'buts', 'msg' => 'Total des buts (3) ≠ somme des buteurs de la composition (2)'];
$eq('clé : les chiffres du message ne comptent pas', Controle::key('stats', $a), Controle::key('stats', ['msg' => 'Total des buts (4) ≠ somme des buteurs de la composition (1)'] + $a));
$eq('clé : une autre fiche change la clé', Controle::key('stats', $a) !== Controle::key('stats', ['id' => 13] + $a), true);
$r1 = ['id' => 5, 'code' => 'rapproche', 'msg' => 'Nom « Jean Martin » relié par rapprochement (orthographe, 2 compositions) à la fiche : Jean Martin'];
$r2 = ['msg' => 'Nom « J. Martin » relié par rapprochement (nom incomplet, 1 composition) à la fiche : Jean Martin'] + $r1;
$eq('clé : deux rapprochements d’une même fiche se distinguent', Controle::key('liens', $r1) !== Controle::key('liens', $r2), true);
$eq('clé : sans fiche, le nom sert de repère', Controle::key('liens', ['id' => null, 'code' => 'nonrelie', 'title' => 'Jean Martin', 'msg' => 'x']) !== Controle::key('liens', ['id' => null, 'code' => 'nonrelie', 'title' => 'Paul Martin', 'msg' => 'x']), true);

// Correcteur (tâche de fond) : une correction proposée sur une fiche inchangée n'est pas « nouvelle ».
$mod = (string) $visible['modified'];
$item = ['id' => (int) $visible['id']];
$eq('orthographe : fiche inchangée depuis le contrôle → pas nouvelle', $call(Controle::class, 'background', 'orthographe', $item, date('c', strtotime($mod) + 86400)), true);
$eq('orthographe : fiche modifiée depuis le contrôle → nouvelle', $call(Controle::class, 'background', 'orthographe', $item, date('c', strtotime($mod) - 86400)), false);
$eq('autres onglets : toujours comparés', $call(Controle::class, 'background', 'stats', $item, date('c', strtotime($mod) + 86400)), false);

// ---------------------------------------------------------------- d'un contrôle à l'autre
$saved = is_file(Controle::FILE) ? file_get_contents(Controle::FILE) : null;
$reset = function () {
    (new ReflectionProperty(Controle::class, 'state'))->setValue(null, null);
};
try {
    $all = Quality::all();
    $pick = $all['stats'][0];
    $keys = array_map(fn ($l) => implode(' ', array_column($l, 'key')), $all);
    // Contrôle précédent sans l'alerte choisie, avec une alerte disparue depuis.
    $keys['stats'] = trim(str_replace($pick['key'], '', $keys['stats'])) . ' 0000000000';
    \App\Core\JsonStore::write(Controle::FILE, ['at' => date('c', time() - 3600), 'by' => 'Essai', 'ms' => 1, 'total' => 1, 'counts' => [], 'new' => '', 'fixed' => 0, 'since' => null, 'repaired' => 0, 'keys' => $keys, 'history' => []]);
    $reset();
    $flagged = [];
    foreach (Quality::all() as $tab => $list) {
        foreach ($list as $i) {
            if ($i['new']) {
                $flagged[] = $i['key'];
            }
        }
    }
    $eq('alerte apparue depuis le dernier contrôle : « Nouveau »', $flagged, [$pick['key']]);
    $run = Controle::run(null);
    $eq('contrôle : une nouvelle, une corrigée', [$run['new'], $run['fixed']], [[$pick['key']], 1]);
    $eq('contrôle : comparé au contrôle précédent', $run['since']['by'] ?? null, 'Essai');
    $reset();
    $eq('après le contrôle : l’alerte reste marquée', Controle::isNew('stats', $pick['key']), true);
    $eq('après le contrôle : les autres non', Controle::isNew('stats', $all['stats'][1]['key'] ?? ''), false);
    $run = Controle::run(null);
    $reset();
    $eq('contrôle suivant : plus rien de nouveau', [$run['new'], $run['fixed'], Controle::isNew('stats', $pick['key'])], [[], 0, false]);
    $eq('historique des contrôles', array_column(Controle::last()['history'], 'new'), [0, 1]);
    $eq('résumé', Controle::counts($run), number_format($run['total'], 0, ',', ' ') . ' anomalies, dont aucune nouvelle depuis ' . Controle::sinceLabel($run['since']));
    // Un contrôle à la fois.
    $lock = fopen(STORAGE_PATH . '/controle.lock', 'c');
    flock($lock, LOCK_EX);
    $eq('contrôle déjà en cours : refusé', Controle::run(null), ['busy' => true]);
    flock($lock, LOCK_UN);
    fclose($lock);
} finally {
    if ($saved === null) {
        @unlink(Controle::FILE);
    } else {
        file_put_contents(Controle::FILE, $saved);
    }
    @unlink(STORAGE_PATH . '/controle.lock');
    $reset();
}

echo $fail ? "\n$fail échec(s)\n" : "\nTout est bon.\n";
exit($fail ? 1 : 0);
