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

    // Pièces jointes : vérifiées (type réel), rangées hors du site sous un nom au hasard, ajoutées,
    // retirées, en réponse, servies seulement par le back-office, effacées avec le ticket.
    $tmpDir = sys_get_temp_dir() . '/pj-' . bin2hex(random_bytes(3));
    @mkdir($tmpDir);
    $png = $tmpDir . '/capture.png';
    $im = imagecreatetruecolor(4, 3);
    imagepng($im, $png);
    file_put_contents("$tmpDir/note.txt", "Étapes pour reproduire\n");
    file_put_contents("$tmpDir/faux.png", '<?php echo "pirate";');
    file_put_contents("$tmpDir/dessin.svg", '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
    $up = fn (array $names) => ['name' => array_map('basename', $names), 'tmp_name' => $names, 'error' => array_fill(0, count($names), UPLOAD_ERR_OK), 'size' => array_map('filesize', $names)];
    \App\Services\TicketFiles::$mover = fn (string $src, string $dest) => copy($src, $dest);
    $mails = [];
    $p = Tickets::create($in('Avec captures'), $users['au'], $up([$png, "$tmpDir/note.txt"]));
    $pj = $p['files'];
    $eq('création : 2 pièces jointes', [count($pj), $pj[0]['name'], $pj[0]['mime'], $pj[1]['mime']], [2, 'capture.png', 'image/png', 'text/plain']);
    $path0 = \App\Services\TicketFiles::path($p['id'], $pj[0]);
    $eq('rangée hors du site, sous un nom au hasard', [is_string($path0) && str_starts_with($path0, STORAGE_PATH . '/tickets/' . $p['id'] . '/'), basename((string) $path0) !== 'capture.png'], [true, true]);
    $eq('e-mail : pièces jointes annoncées (pas envoyées)', str_contains((string) ($mails[0][2] ?? ''), '2 pièce(s) jointe(s)'), true);
    foreach (["$tmpDir/faux.png" => 'faux PNG (script)', "$tmpDir/dessin.svg" => 'SVG'] as $bad => $label) {
        $msg = '';
        try {
            Tickets::create($in('Refusé'), $users['au'], $up([$bad]));
        } catch (InvalidArgumentException $e) {
            $msg = $e->getMessage();
        }
        $eq("refusé : $label", str_contains($msg, 'n’est pas accepté'), true);
    }
    $eq('fichier refusé : aucun ticket créé', count(array_filter(Tickets::all(), fn ($t) => $t['title'] === 'Refusé')), 0);
    $q = Tickets::edit($p['id'], $in('Avec captures'), $users['au'], $up([$png]), [$pj[1]['id']]);
    $eq('modification : une ajoutée, une retirée (fichier effacé)', [count($q['files']), \App\Services\TicketFiles::path($p['id'], $pj[1])], [2, null]);
    $r = Tickets::comment($p['id'], '', $users['co'], $up([$png]));
    $eq('réponse avec seulement une capture', [count(end($r['comments'])['files'] ?? []), end($r['comments'])['text']], [1, '']);
    $eq('texte pour l’IA : pièces jointes listées', substr_count(Tickets::promptFor(Tickets::get($p['id'])), '/admin/tickets/' . $p['id'] . '/pj/'), 3);
    $res = \App\Admin\TicketsAdmin::file(new \App\Core\Request('GET', '/', [], [], [], [], ''), (string) $p['id'], $pj[0]['id']);
    $eq('servie : image, nosniff, bac à sable', [$res->status, $res->headers['Content-Type'] ?? null, $res->headers['X-Content-Type-Options'] ?? null, str_contains((string) ($res->headers['Content-Security-Policy'] ?? ''), 'sandbox')], [200, 'image/png', 'nosniff', true]);
    $res = \App\Admin\TicketsAdmin::file(new \App\Core\Request('GET', '/', [], [], [], [], ''), (string) $p['id'], 'ffffffffffffffff');
    $eq('pièce jointe inconnue : 404', $res->status, 404);
    $res = \App\Admin\TicketsAdmin::file(new \App\Core\Request('GET', '/', [], [], [], [], ''), (string) $a['id'], $pj[0]['id']);
    $eq('pièce jointe d’un autre ticket : 404', $res->status, 404);
    Tickets::delete($p['id']);
    $eq('ticket supprimé : dossier des pièces jointes effacé', is_dir(STORAGE_PATH . '/tickets/' . $p['id']), false);
    array_map('unlink', glob("$tmpDir/*") ?: []);
    @rmdir($tmpDir);
} finally {
    $backup === null ? JsonStore::delete($file) : file_put_contents($file, $backup);
    @unlink("$file.lock");
    @rmdir(STORAGE_PATH . '/tickets');
}
echo $fail ? "\n$fail échec(s).\n" : "\nTout est bon.\n";
exit($fail ? 1 : 0);
