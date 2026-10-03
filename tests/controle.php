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
$entering = [['position' => 'A', 'name' => 'ENTRANT', 'sub_in' => '60']];
$eq('même anomalie (entrant noté titulaire) avec 10 ou 11 titulaires : même repère', array_column($check($match(['lineup' => ['rows' => $rows(11, $entering)]])), 'ref'), array_column($check($match(['lineup' => ['rows' => $rows(10, $entering)]])), 'ref'));
// Fichier retouché à la main : scores en texte ou décimaux lus comme des entiers, date impossible signalée.
$goals = fn ($v) => $call(Derived::class, 'goals', $v);
$eq('buts : « 2 », 2.0 et 2 valent 2 ; texte illisible ignoré', [$goals('2'), $goals(2.0), $goals(2), $goals('deux'), $goals(-1), $goals(1.5), $goals(null)], [2, 2, 2, null, null, null, null]);
$eq('date du match en nombre : signalée sans planter', $codes($check(array_replace($match([]), ['date' => 1991]))), ['match-date']);
$eq('date du match impossible : signalée', $codes($check($match(['date' => '1991-13-01']))), ['match-date']);

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

// ---------------------------------------------------------------- fichiers de fiches
$usable = fn ($d, int $id) => $call(Fiches::class, 'usable', $d, $id);
$eq('fichier de fiche complet : lu', $usable(['id' => 12, 'type' => 'article', 'title' => 'Essai'], 12), true);
$eq('numéro différent du nom du fichier : ignoré', $usable(['id' => 13, 'type' => 'article'], 12), false);
$eq('sans numéro ou sans type : ignoré', [$usable(['type' => 'article'], 12), $usable(['id' => 12, 'title' => 'Essai'], 12)], [false, false]);
$eq('type inconnu ou mal formé : ignoré', [$usable(['id' => 12, 'type' => 'truc'], 12), $usable(['id' => 12, 'type' => ['page']], 12)], [false, false]);
$eq('match ou personne sans ses données : ignoré', [$usable(['id' => 12, 'type' => 'match'], 12), $usable(['id' => 12, 'type' => 'personne', 'personne' => null], 12)], [false, false]);
$eq('JSON qui n’est pas un objet : ignoré', [$usable([], 12), $usable('texte', 12), $usable(null, 12)], [false, false, false]);
// Types inattendus (fichier retouché à la main) : lus comme s'ils étaient bien formés, et signalés.
$norm = fn (array $d) => $call(Fiches::class, 'normalize', $d);
$valid = Fiches::get($visible['id'] ?? (int) array_key_first(Index::all()));
$eq('fiche bien formée : inchangée à la lecture', $norm($valid), $valid);
$m0 = ['id' => 999970, 'type' => 'match', 'title' => 'Essai', 'seo' => ['title' => '', 'description' => ['liste']], 'match' => [
    'date' => 19910130, 'home' => 'Sochaux', 'score' => ['home' => '2', 'away' => 1.0, 'pens' => 'non'], 'spectators' => '12 000',
    'lineup' => ['rows' => [['name' => 'DUPONT Jean', 'goals' => "12'", 'yellow' => 61], 'ligne en texte']], 'highlights' => 'texte', 'breves' => [3, ['text' => 4]],
]];
$n0 = $norm($m0);
$eq('types inattendus : lus correctement', [$n0['match']['date'], $n0['match']['home'], $n0['match']['score']['home'], $n0['match']['score']['away'], $n0['match']['score']['pens'], $n0['match']['spectators'], $n0['seo']['description'],
    $n0['match']['lineup']['rows'], $n0['match']['highlights'], $n0['match']['breves']],
    ['19910130', ['name' => 'Sochaux', 'level' => null], 2, 1, null, null, '', [['name' => 'DUPONT Jean', 'goals' => ["12'"], 'yellow' => ['61']]], [], ['3', ['text' => '4']]]);
$eq('types inattendus : fiche signalée avec les champs réparés', count(Fiches::repaired()[999970] ?? []) >= 8, true);
$norm(['id' => 999970, 'type' => 'article', 'title' => 'Essai réparé']);
$eq('fiche réparée (bien formée) : plus signalée', isset(Fiches::repaired()[999970]), false);

// ---------------------------------------------------------------- redirections (adresse suivie comme par un visiteur)
$draft = null;
foreach (Index::all() as $s) {
    if ($s['status'] === 'brouillon' && $s['path']) {
        $draft = $s;
        break;
    }
}
$probe = fn (string $p, array $q = []) => array_slice(\App\Kernel::probe($p, $q), 0, 2);
$visible = array_values(array_filter(Index::all(), fn ($s) => $s['type'] === 'match' && Index::visible($s)))[0];
$eq('page calculée (saisons) : affichée', $probe('/saisons/'), [200, null]);
$eq('page calculée en anglais : affichée', $probe('/en/records/'), [200, null]);
$eq('fiche publiée : affichée', $probe($visible['path']), [200, null]);
$eq('fiche publiée en anglais : affichée', $probe('/en' . $visible['path']), [200, null]);
$eq('adresse sans « / » final : complétée', $probe('/saisons'), [301, '/saisons/']);
$eq('adresse inconnue : introuvable', [$probe('/cette-page-n-existe-pas/'), $call(Controle::class, 'why404', '/cette-page-n-existe-pas/')], [[404, null], 'page inexistante']);
$eq('fiche en brouillon : introuvable, et pourquoi', $draft ? [$probe($draft['path'])[0], $call(Controle::class, 'why404', $draft['path'])] : [404, 'fiche non publiée (brouillon)'], [404, 'fiche non publiée (brouillon)']);
$season = (string) array_key_first(Derived::get()['seasons']);
$eq('saison existante : affichée', $probe('/matchs/' . $season . '/'), [200, null]);
$eq('saison sans match ni rubrique : introuvable', $probe('/matchs/1850-1851/'), [404, null]);
$club = (string) array_key_first(Derived::get()['clubs']);
$eq('face-à-face existant : affiché', $probe('/face-a-face/' . $club . '/'), [200, null]);
$eq('face-à-face inconnu : introuvable (la page fait la même vérification)', $probe('/face-a-face/club-qui-n-existe-pas/'), [404, null]);
$eq('bilan inconnu : introuvable', $probe('/bilans/n-importe-quoi/'), [404, null]);
$eq('Rétro-Direct inconnu : introuvable', $probe('/interactif/retro-direct/match-qui-n-existe-pas/'), [404, null]);
$rel = (string) array_key_first(array_filter($lib, fn ($m, $r) => is_file(Media::ORIGINALS . '/' . $r), ARRAY_FILTER_USE_BOTH));
$eq('image de la médiathèque (originale, vignette) : trouvée', [$probe('/media/full/' . $rel)[0], $probe('/media/800/' . $rel . '.webp')[0]], [200, 200]);
$eq('image absente : introuvable', $probe('/media/full/2099/01/absente.jpg'), [404, null]);
$eq('chemin qui sort du dossier : jamais lu', [$probe('/media/full/../../storage/users.json')[0], $probe('/../storage/users.json')[0], $probe('/assets/../../storage/users.json')[0]], [404, 404, 404]);
$eq('fichier de public/ : servi tel quel', $probe('/assets/img/placeholder.svg'), [200, null]);
$eq('ancienne image WordPress : redirigée vers la médiathèque', $probe('/wp-content/uploads/' . $rel)[0], 301);

// ---------------------------------------------------------------- clés des alertes
$a = ['id' => 12, 'code' => 'buts', 'msg' => 'Total des buts (3) ≠ somme des buteurs de la composition (2)'];
$eq('clé : les chiffres du message ne comptent pas', Controle::key('stats', $a), Controle::key('stats', ['msg' => 'Total des buts (4) ≠ somme des buteurs de la composition (1)'] + $a));
$eq('clé : une autre fiche change la clé', Controle::key('stats', $a) !== Controle::key('stats', ['id' => 13] + $a), true);
$r1 = ['id' => 5, 'code' => 'rapproche', 'msg' => 'Nom « Jean Martin » relié par rapprochement (orthographe, 2 compositions) à la fiche : Jean Martin'];
$r2 = ['msg' => 'Nom « J. Martin » relié par rapprochement (nom incomplet, 1 composition) à la fiche : Jean Martin'] + $r1;
$eq('clé : deux rapprochements d’une même fiche se distinguent', Controle::key('liens', $r1) !== Controle::key('liens', $r2), true);
$eq('clé : sans fiche, le nom sert de repère', Controle::key('liens', ['id' => null, 'code' => 'nonrelie', 'title' => 'Jean Martin', 'msg' => 'x']) !== Controle::key('liens', ['id' => null, 'code' => 'nonrelie', 'title' => 'Paul Martin', 'msg' => 'x']), true);
$g1 = ['id' => null, 'code' => 'referentiel', 'title' => 'Adversaire : Metz', 'msg' => '« Metz » désigne à la fois « Monaco » et « Metz » : des matchs peuvent être rangés au mauvais endroit'];
$g2 = ['msg' => '« FC Metz » désigne à la fois « Monaco » et « Metz » : des matchs peuvent être rangés au mauvais endroit'] + $g1;
$eq('clé : deux alertes de référentiel sur un même adversaire se distinguent', Controle::key('site', $g1) !== Controle::key('site', $g2), true);

$rp = fn (string $title, int $n) => ['id' => 5, 'code' => 'rapproche', 'ref' => 'jean martin', 'msg' => "Nom « Jean Martin » relié par rapprochement (orthographe, $n composition" . ($n > 1 ? 's' : '') . ") à la fiche : $title"];
$eq('clé : nombre de compositions ou titre de la fiche changés, même alerte', Controle::key('liens', $rp('Jean Martin', 1)), Controle::key('liens', $rp('Jean Martin (1950)', 2)));
$img = fn (string $files) => ['id' => 7, 'code' => 'image', 'ref' => 'serveur', 'msg' => 'Fichier d’image absent du serveur : ' . $files];
$eq('clé : liste des fichiers absents qui change, même alerte', Controle::key('site', $img('2015/08/a.jpg')), Controle::key('site', $img('2015/08/a.jpg, 2016/09/b.jpg')));
$eq('clé : autre sorte d’écart (jour de la semaine → date), autre alerte', Controle::key('stats', ['id' => 9, 'code' => 'date', 'ref' => 'jour', 'msg' => 'x']) !== Controle::key('stats', ['id' => 9, 'code' => 'date', 'ref' => 'ecart', 'msg' => 'x']), true);

// Correcteur (tâche de fond) : une correction proposée sur une fiche dont les textes n'ont pas
// changé depuis le contrôle n'est pas « nouvelle » (une traduction ou un numéro d'album ne comptent pas).
$vid = (int) $visible['id'];
$item = ['id' => $vid];
$sig = substr((string) Index::get($vid)['tsig'], 0, 8);
$eq('orthographe : textes inchangés depuis le contrôle → pas nouvelle', $call(Controle::class, 'background', 'orthographe', $item, [$vid => $sig]), true);
$eq('orthographe : textes modifiés depuis le contrôle → nouvelle', $call(Controle::class, 'background', 'orthographe', $item, [$vid => 'autre000']), false);
$eq('orthographe : fiche créée depuis le contrôle → nouvelle', $call(Controle::class, 'background', 'orthographe', $item, []), false);
$eq('autres onglets : toujours comparés', $call(Controle::class, 'background', 'stats', $item, [$vid => $sig]), false);
$doc = Fiches::get($vid);
$eq('empreinte des textes : la traduction anglaise ou la date de modification n’y changent rien', \App\Services\Proofreader::textSig(['modified' => '2030-01-01', 'i18n' => ['en' => ['title' => 'X']]] + $doc), \App\Services\Proofreader::textSig($doc));
$eq('empreinte des textes : un mot changé la change', \App\Services\Proofreader::textSig(['title' => $doc['title'] . ' bis'] + $doc) !== \App\Services\Proofreader::textSig($doc), true);

// ---------------------------------------------------------------- d'un contrôle à l'autre
$saved = is_file(Controle::FILE) ? file_get_contents(Controle::FILE) : null;
$hadLock = is_file(STORAGE_PATH . '/controle.lock');
$reset = function () {
    (new ReflectionProperty(Controle::class, 'state'))->setValue(null, null);
};
try {
    // Calculs à jour avant la photo des alertes (le contrôle les refait).
    $call(Controle::class, 'syncIndex');
    Derived::rebuild();
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
    $run = Controle::run(null, false);
    $eq('contrôle : une nouvelle, une corrigée', [$run['new'], $run['fixed']], [[$pick['key']], 1]);
    $eq('contrôle : comparé au contrôle précédent', $run['since']['by'] ?? null, 'Essai');
    $reset();
    $eq('après le contrôle : l’alerte reste marquée', Controle::isNew('stats', $pick['key']), true);
    $eq('après le contrôle : les autres non', Controle::isNew('stats', $all['stats'][1]['key'] ?? ''), false);
    $run = Controle::run(null, false);
    $reset();
    $eq('contrôle suivant : plus rien de nouveau', [$run['new'], $run['fixed'], Controle::isNew('stats', $pick['key'])], [[], 0, false]);
    $eq('historique des contrôles', array_column(Controle::last()['history'], 'new'), [0, 1]);
    $eq('résumé', Controle::counts($run), number_format($run['total'], 0, ',', ' ') . ' anomalies, dont aucune nouvelle depuis ' . Controle::sinceLabel($run['since']));
    // Index modifié hors du back-office (restauration, envoi par FTP) : remis à jour par le contrôle.
    $call(Controle::class, 'syncIndex');
    $id = (int) $visible['id'];
    \App\Core\PhpCache::update(Index::CACHE, fn ($items) => array_replace($items, [$id => ['title' => 'Titre périmé'] + $items[$id]]));
    Index::forget();
    $eq('index périmé (même date de modification) : détecté', [$call(Controle::class, 'syncIndex'), Index::get($id)['title']], [1, $visible['title']]);
    $eq('index à jour : rien à refaire', $call(Controle::class, 'syncIndex'), 0);
    $newest = max(array_map('filemtime', glob(Fiches::DIR . '/*.json')));
    touch(STORAGE_PATH . '/cache/search.php', $newest - 1);
    $eq('fichier plus récent que la recherche : recherche reconstruite', $call(Controle::class, 'syncIndex') > 0 && filemtime(STORAGE_PATH . '/cache/search.php') >= $newest, true);
    // Un contrôle à la fois.
    $lock = fopen(STORAGE_PATH . '/controle.lock', 'c');
    flock($lock, LOCK_EX);
    $eq('contrôle déjà en cours : refusé', Controle::run(null, false), ['busy' => true]);
    flock($lock, LOCK_UN);
    fclose($lock);
} finally {
    if ($saved === null) {
        @unlink(Controle::FILE);
    } else {
        file_put_contents(Controle::FILE, $saved);
    }
    // Fichier de verrou créé par l'essai : retiré seulement s'il n'est pas tenu par un vrai contrôle.
    if (!$hadLock && ($fp = @fopen(STORAGE_PATH . '/controle.lock', 'c')) && flock($fp, LOCK_EX | LOCK_NB)) {
        @unlink(STORAGE_PATH . '/controle.lock');
        flock($fp, LOCK_UN);
        fclose($fp);
    }
    $reset();
}

echo $fail ? "\n$fail échec(s)\n" : "\nTout est bon.\n";
exit($fail ? 1 : 0);
