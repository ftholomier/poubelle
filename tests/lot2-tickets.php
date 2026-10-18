<?php
/**
 * Lot 2 — tickets : numéro jamais redonné après une suppression (compteur dans le fichier, ancien
 * format « liste » encore lu), « vu » écrit seulement si nécessaire, e-mail à la modification, aux
 * personnes qui ont commenté, HTML brut confié à l'envoi (pas de double mise en page), lien
 * « Écran concerné » seulement vers une adresse du site. Comptes simulés en mémoire, e-mails
 * simulés ; storage/tickets.json est remis dans son état d'origine. Usage : php tests/lot2-tickets.php
 */
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Core\JsonStore;
use App\Services\Tickets;

$fail = 0;
$eq = function (string $label, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    if (!$ok) {
        $fail++;
    }
    echo ($ok ? 'OK   ' : 'FAIL ') . $label . ($ok ? '' : ' => ' . json_encode($got, JSON_UNESCAPED_UNICODE) . ' attendu ' . json_encode($exp, JSON_UNESCAPED_UNICODE)) . "\n";
};
$users = [
    'ad' => ['id' => 'ad', 'name' => 'Admin', 'email' => 'admin@exemple.fr', 'role' => 'admin', 'status' => 'active'],
    'au' => ['id' => 'au', 'name' => 'Auteur', 'email' => 'auteur@exemple.fr', 'role' => 'user', 'status' => 'active'],
    'co' => ['id' => 'co', 'name' => 'Commente', 'email' => 'co@exemple.fr', 'role' => 'user', 'status' => 'active'],
    'au2' => ['id' => 'au2', 'name' => 'Autre', 'email' => 'autre@exemple.fr', 'role' => 'user', 'status' => 'active'],
];
$cache = new ReflectionProperty(JsonStore::class, 'cache');
$c = $cache->getValue();
$c[APP_ROOT . '/storage/users.json'] = $users;
$cache->setValue(null, $c);
$mails = [];
Tickets::$mailer = function (string $to, string $subject, string $html) use (&$mails) {
    $mails[] = [$to, $subject, $html];
    return true;
};
$to = function () use (&$mails) {
    return array_values(array_unique(array_column($mails, 0)));
};
$file = Tickets::FILE;
$backup = is_file($file) ? file_get_contents($file) : null;
try {
    // Ancien format (simple liste) : encore lu, numéros à la suite.
    JsonStore::write($file, [['id' => 4, 'title' => 'Ancien', 'type' => 'bug', 'priority' => 'normale', 'body' => 'x', 'prompt' => '', 'page' => '', 'status' => 'nouveau',
        'author' => ['id' => 'au', 'name' => 'Auteur'], 'created_at' => date('c'), 'updated_at' => date('c'), 'comments' => [], 'history' => [], 'seen' => []]]);
    $eq('ancien format lu', array_column(Tickets::all(), 'id'), [4]);
    $in = fn (string $t) => ['title' => $t, 'type' => 'bug', 'priority' => 'normale', 'body' => 'Corps', 'prompt' => '', 'page' => '/admin/fiches'];
    $a = Tickets::create($in('A'), $users['au']);
    $b = Tickets::create($in('B'), $users['au']);
    $eq('numéros à la suite', [$a['id'], $b['id']], [5, 6]);
    Tickets::delete($b['id']);
    $c3 = Tickets::create($in('C'), $users['au']);
    $eq('après suppression du dernier : numéro pas redonné', $c3['id'], 7);
    $eq('nouveau format : compteur + tickets', array_keys(JsonStore::read($file, [])), ['next', 'items']);

    // « Vu » : écrit à la première visite après un changement, pas aux suivantes.
    Tickets::markSeen($a['id'], $users['ad']);
    clearstatcache();
    $ino = fileinode($file);
    Tickets::markSeen($a['id'], $users['ad']);
    clearstatcache();
    $eq('déjà vu : fichier pas réécrit', fileinode($file), $ino);
    $eq('vu enregistré', Tickets::unread(Tickets::get($a['id']), $users['ad']), false);

    // Modification : e-mail (administrateurs, sauf la personne qui agit).
    $mails = [];
    Tickets::edit($a['id'], $in('A modifié'), $users['au']);
    $eq('modification : e-mail aux administrateurs', $to(), ['admin@exemple.fr']);
    $mails = [];
    Tickets::edit($a['id'], $in('A modifié'), $users['au']);
    $eq('modification sans changement : pas d’e-mail', $mails, []);

    // Ceux qui ont commenté sont prévenus des réponses suivantes.
    Tickets::comment($a['id'], 'Moi aussi', $users['co']);
    $mails = [];
    Tickets::comment($a['id'], 'Réponse de l’auteur', $users['au']);
    $got = $to();
    sort($got);
    $eq('réponse : administrateur et personne qui a commenté', $got, ['admin@exemple.fr', 'co@exemple.fr']);
    $eq('e-mail : HTML brut (mise en page faite par l’envoi)', [count($mails) > 0, str_contains((string) ($mails[0][2] ?? ''), '<!DOCTYPE')], [true, false]);
    $eq('personne étrangère au ticket : pas prévenue', in_array('autre@exemple.fr', $got, true), false);

    // Lien « Écran concerné » : seulement vers une adresse du site.
    $render = function (string $page) {
        $t = Tickets::get(5);
        $t['page'] = $page;
        $admin = false;
        $canEdit = false;
        $prompt = '';
        $unread = false;
        $edit = false;
        ob_start();
        include __DIR__ . '/../templates/admin/tickets/show.php';
        return (string) ob_get_clean();
    };
    $eq('adresse du site : lien', str_contains($render('/admin/fiches'), '<a href="/admin/fiches">'), true);
    $eq('« //autre-site » : pas de lien', str_contains($render('//evil.example/x'), 'href="//evil.example'), false);
    $eq('« /\\autre-site » : pas de lien', str_contains($render('/\\evil.example'), 'href="/\\evil.example'), false);
} finally {
    $backup === null ? JsonStore::delete($file) : file_put_contents($file, $backup);
    @unlink("$file.lock");
}
echo $fail ? "\n$fail échec(s).\n" : "\nTout est bon.\n";
exit($fail ? 1 : 0);
