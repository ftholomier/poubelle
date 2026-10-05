<?php
/**
 * Déconnexion automatique du back-office (App\Core\Auth, App\Admin\Account) : session fermée après
 * 30 minutes sans activité (plus la marge du serveur), appels automatiques qui ne la prolongent pas,
 * déconnexion demandée par le navigateur refusée si une action récente a été vue, avis sur la page
 * de connexion. Usage : php tests/session.php (code de sortie 1 en cas d'échec). Crée puis supprime
 * un compte d'essai.
 */
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Admin\Account;
use App\Core\Auth;
use App\Core\Request;
use App\Core\Session;

$fail = 0;
$eq = function (string $label, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    if (!$ok) {
        $fail++;
    }
    echo ($ok ? 'OK   ' : 'FAIL ') . $label . ($ok ? '' : ' => ' . json_encode($got, JSON_UNESCAPED_UNICODE) . ' attendu ' . json_encode($exp, JSON_UNESCAPED_UNICODE)) . "\n";
};
// Nouvelle requête : l'utilisateur courant est relu dans la session.
$fresh = function () {
    (new ReflectionProperty(Auth::class, 'loaded'))->setValue(null, false);
    (new ReflectionProperty(Auth::class, 'current'))->setValue(null, null);
    Auth::$expired = null;
};
$req = fn (array $query = []) => new Request('GET', '/admin/connexion', $query, [], [], [], '');

ini_set('session.use_cookies', '0');
Session::start();
$email = 'essai.inactivite@example.org';
if ($old = Auth::findByEmail($email)) {
    Auth::delete($old['id']);
}
$u = Auth::createUser($email, 'Essai inactivité', 'user', bin2hex(random_bytes(10)));
Auth::login(Auth::find($u['id']));
$limit = Auth::idleLimit();

$fresh();
$eq('délai : 30 minutes, marge du serveur 5 minutes', [$limit, Auth::idleGrace(), Account::idleMinutes()], [1800, 300, 30]);
$eq('connecté juste après la connexion', Auth::user()['id'] ?? null, $u['id']);

$_SESSION['seen'] = time() - 29 * 60;
$fresh();
$eq('29 minutes sans activité, puis une action : toujours connecté, activité notée', [Auth::user()['id'] ?? null, $_SESSION['seen'] >= time() - 2], [$u['id'], true]);

$_SESSION['seen'] = $tenAgo = time() - 10 * 60;
$_SERVER['HTTP_X_BO_BACKGROUND'] = '1';
$fresh();
$eq('appel automatique (verrou, rafraîchissement) : connecté, mais pas une activité', [Auth::user()['id'] ?? null, $_SESSION['seen']], [$u['id'], $tenAgo]);
unset($_SERVER['HTTP_X_BO_BACKGROUND']);
$_POST['_bg'] = '1';
$fresh();
Auth::user();
$eq('signal envoyé à la fermeture d’une page (_bg) : pas une activité non plus', $_SESSION['seen'], $tenAgo);
unset($_POST['_bg']);

$_SESSION['seen'] = time() - ($limit + 60);
$_SERVER['HTTP_X_BO_BACKGROUND'] = '1';
$fresh();
$eq('31 minutes : encore connecté côté serveur (marge : c’est le navigateur qui déconnecte à 30)', Auth::user()['id'] ?? null, $u['id']);
unset($_SERVER['HTTP_X_BO_BACKGROUND']);

// Déconnexion demandée par le navigateur : refusée si le serveur a vu une action récente (autre onglet).
$_SESSION['seen'] = time() - 5 * 60;
$_SERVER['HTTP_X_BO_BACKGROUND'] = '1';
$fresh();
Auth::user();
$r = json_decode(Account::idleLogout($req())->body, true);
$eq('déconnexion demandée alors qu’une action date de 5 minutes : refusée', [$r['ok'] ?? null, $r['active'] ?? null, Auth::user()['id'] ?? null], [false, true, $u['id']]);
unset($_SERVER['HTTP_X_BO_BACKGROUND']);

$_SESSION['seen'] = time() - ($limit + Auth::idleGrace() + 5);
$fresh();
$eq('au-delà du délai et de la marge : déconnecté, compte noté pour le journal, avis pour la connexion', [Auth::user(), Auth::$expired['id'] ?? null, Session::get('idle_out'), Session::get('uid')], [null, $u['id'], 1, null]);

$fresh();
$page = Account::login($req())->body;
$eq('page de connexion : « déconnecté après 30 minutes sans activité », une seule fois', [str_contains($page, 'Vous avez été déconnecté après 30 minutes sans activité'), str_contains(Account::login($req())->body, 'sans activité')], [true, false]);
$eq('déconnecté par le navigateur (?inactif=1) : même avis', str_contains(Account::login($req(['inactif' => '1']))->body, 'Vous avez été déconnecté après 30 minutes sans activité'), true);

Auth::login(Auth::find($u['id']));
unset($_SESSION['seen']);
$fresh();
$eq('session ouverte avant cette règle (sans date d’activité) : gardée, l’activité commence maintenant', [Auth::user()['id'] ?? null, ($_SESSION['seen'] ?? 0) >= time() - 2], [$u['id'], true]);

Auth::logout();
Auth::delete($u['id']);
session_destroy();
echo $fail ? "\n$fail échec(s)\n" : "\nTout est bon.\n";
exit($fail ? 1 : 0);
