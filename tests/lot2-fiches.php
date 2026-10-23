<?php
/**
 * Lot 2 — fiches : corbeille (double mise à la corbeille, sortie vers « brouillon », action groupée qui
 * ne compte que les fiches traitées), publication groupée (« déjà publiée » et date, comme à l'unité),
 * validation gardée après toute modification (historien, correcteur, tâche), effacée seulement par la suppression définitive.
 * La fiche d'essai est supprimée à la fin. Usage : php tests/lot2-fiches.php
 */
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Core\Request;
use App\Data\Fiches;
use App\Services\Validation;

$fail = 0;
$eq = function (string $label, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    if (!$ok) {
        $fail++;
    }
    echo ($ok ? 'OK   ' : 'FAIL ') . $label . ($ok ? '' : ' => ' . json_encode($got, JSON_UNESCAPED_UNICODE) . ' attendu ' . json_encode($exp, JSON_UNESCAPED_UNICODE)) . "\n";
};
$user = ['id' => 'lot2-essai', 'name' => 'Essai lot 2', 'email' => ''];
$ids = [];
try {
    $doc = Fiches::blank('article');
    $doc['title'] = 'Zzessai lot2 corbeille';
    $doc['status'] = 'relire';
    $id = $ids[] = (int) Fiches::save($doc, null, 'essai')['id'];

    // 1. Corbeille : deux fois de suite, le statut d'avant est gardé.
    $eq('mise à la corbeille', Fiches::trash($id, null), true);
    $eq('deuxième clic : rien à faire', Fiches::trash($id, null), false);
    $eq('statut d’avant gardé', Fiches::fresh($id)['status_before_trash'] ?? null, 'relire');
    $eq('sortie : statut d’avant', [Fiches::untrash($id, null), Fiches::fresh($id)['status']], [true, 'relire']);
    $eq('sortie d’une fiche hors corbeille : rien', Fiches::untrash($id, null), false);
    // Fiche déjà coincée (ancien défaut) : « corbeille » n'est jamais un statut de retour.
    $d = Fiches::fresh($id);
    $d['status'] = 'corbeille';
    $d['status_before_trash'] = 'corbeille';
    Fiches::save($d, null, 'essai');
    Fiches::untrash($id, null);
    $eq('fiche coincée : sortie en brouillon', Fiches::fresh($id)['status'], 'brouillon');

    // 2. Publication par un autre chemin que la fiche (action groupée) : « déjà publiée » et date.
    $d = Fiches::fresh($id);
    $d['date'] = '2001-01-01T00:00:00+01:00';
    Fiches::save($d, null, 'essai');
    $d = Fiches::fresh($id);
    $d['status'] = 'publie';
    $p = Fiches::save($d, null, 'Publié (action groupée)');
    $eq('publication : déjà publiée', $p['published_once'] ?? false, true);
    $eq('publication : date du jour', substr((string) $p['date'], 0, 10), date('Y-m-d'));
    $p['status'] = 'brouillon';
    $p['date'] = '2001-01-01T00:00:00+01:00';
    $b = Fiches::save($p, null, 'Brouillon');
    $eq('retour en brouillon : reste « déjà publiée »', $b['published_once'] ?? false, true);
    $b['status'] = 'publie';
    $eq('republiée : la date n’est plus touchée', substr((string) Fiches::save($b, null, 'Republiée')['date'], 0, 10), '2001-01-01');
    // Restauration d'une vieille version (sans la marque) : la marque reste.
    $eq('restauration de la v1 : reste « déjà publiée »', Fiches::restore($id, 1, null)['published_once'] ?? false, true);
    // Création directe en « publié » (import) : la date fournie est gardée.
    $n = Fiches::blank('article');
    $n['title'] = 'Zzessai lot2 import';
    $n['status'] = 'publie';
    $n['date'] = '1999-05-05T00:00:00+02:00';
    $c = Fiches::save($n, null, 'essai');
    $ids[] = (int) $c['id'];
    $eq('création publiée : date gardée, déjà publiée', [substr((string) $c['date'], 0, 10), $c['published_once'] ?? false], ['1999-05-05', true]);

    // Action groupée : ne compte que les fiches réellement mises à la corbeille.
    $_SESSION = [];
    $req = fn (string $action, array $list) => new Request('POST', '/admin/fiches/lot', [], ['ids' => array_map('strval', $list), 'action' => $action, 'back' => '/admin'], [], ['HTTP_HOST' => 'localhost', 'REQUEST_URI' => '/', 'REMOTE_ADDR' => '127.0.0.1'], '');
    Fiches::trash($ids[1], null);
    @\App\Admin\Fiches::bulk($req('corbeille', $ids));
    $msg = implode(' ', array_column($_SESSION['_flash'] ?? [], 'message'));
    $eq('action groupée : 1 fiche traitée (l’autre déjà à la corbeille)', str_starts_with($msg, '1 fiche traitée.'), true);
    $eq('action groupée : statut d’avant gardé', Fiches::fresh($ids[1])['status_before_trash'] ?? null, 'publie');
    $_SESSION = [];
    @\App\Admin\Fiches::bulk($req('publie', [$ids[0]]));
    $eq('publication groupée d’une fiche à la corbeille : 0 traitée', str_starts_with(implode(' ', array_column($_SESSION['_flash'] ?? [], 'message')), '0 fiche traitée.'), true);
    Fiches::untrash($ids[0], null);

    // 13. Validation : une fiche validée le reste, quelle que soit la modification (seul « Retirer la validation » l'enlève).
    Validation::set($id, $user);
    $d = Fiches::fresh($id);
    $d['intro'] = 'Modifiée par une tâche.';
    Fiches::save($d, null, 'tâche');
    $eq('modifiée par une tâche : toujours validée', Validation::get($id) !== null, true);
    $d = Fiches::fresh($id);
    Fiches::save($d, $user, 'rien');
    $eq('enregistrée sans changement : toujours validée', Validation::get($id) !== null, true);
    $d['intro'] = 'Modifiée par un historien.';
    Fiches::save($d, $user, 'essai');
    $eq('modifiée par un historien (correction d’orthographe…) : toujours validée', Validation::get($id) !== null, true);
    Validation::set($id, $user);
    Fiches::trash($id, null);
    Fiches::destroy($id, null);
    $eq('supprimée définitivement : validation retirée', Validation::get($id), null);
    \App\Core\JsonStore::forget();
    $eq('validation absente du fichier', isset(\App\Core\JsonStore::read(STORAGE_PATH . '/validations.json', [])[$id]), false);
} finally {
    foreach ($ids as $i) {
        Fiches::destroy($i, null);
        Validation::clear($i);
        exec('rm -rf ' . escapeshellarg(Fiches::VERSIONS . "/$i"));
    }
}
echo $fail ? "\n$fail échec(s).\n" : "\nTout est bon.\n";
exit($fail ? 1 : 0);
