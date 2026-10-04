<?php
/**
 * Favoris du back-office (App\Admin\Favorites) : adresses acceptées (back-office seulement),
 * choix proposés selon le rôle, favoris d'un compte filtrés. Usage : php tests/favoris.php
 * (code de sortie 1 en cas d'échec). N'écrit rien.
 */
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Admin\Favorites as F;
use App\Core\Auth;

$fail = 0;
$eq = function (string $label, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    if (!$ok) {
        $fail++;
    }
    echo ($ok ? 'OK   ' : 'FAIL ') . $label . ($ok ? '' : ' => ' . json_encode($got, JSON_UNESCAPED_UNICODE) . ' attendu ' . json_encode($exp, JSON_UNESCAPED_UNICODE)) . "\n";
};
$as = function (?array $u) {
    (new ReflectionProperty(Auth::class, 'current'))->setValue(null, $u);
    (new ReflectionProperty(Auth::class, 'loaded'))->setValue(null, true);
};

$ok = ['/admin', '/admin/audio', '/admin/reglages?groupe=audio', '/admin/collection/quiz#nouveau', '/admin/fiche/2431?onglet=texte'];
$eq('adresses du back-office acceptées', array_map(fn ($u) => F::clean($u), $ok), $ok);
$bad = ['https://exemple.com/admin', '//exemple.com/admin', '/admin//exemple.com', '/adminx', '/admin/ x', '/admin/"><script>', 'javascript:alert(1)', '/admin\\exemple', '/' , '', '/admin/' . str_repeat('a', 300)];
$eq('toute autre adresse refusée', array_map(fn ($u) => F::clean($u), $bad), array_fill(0, count($bad), null));

$admin = ['id' => 'essai-a', 'name' => 'Essai', 'email' => 'essai@example.org', 'role' => 'admin', 'status' => 'active'];
$user = ['role' => 'user', 'id' => 'essai-u'] + $admin;
$labels = fn () => array_merge(...array_map(fn ($g) => array_column($g, 'label'), array_values(F::choices())));
$as($admin);
$a = $labels();
$eq('administrateur : écrans techniques et réglages proposés', [in_array('Fiches audio', $a, true), in_array('Réglages › Fiches audio', $a, true), in_array('Inviter un utilisateur', $a, true)], [true, true, true]);
$as($user);
$u = $labels();
$eq('historien : ni écrans techniques ni réglages', [in_array('Matchs', $u, true), in_array('Fiche match', $u, true), in_array('Fiches audio', $u, true), in_array('Utilisateurs', $u, true), (bool) preg_grep('/^Réglages/', $u)], [true, true, false, false, false]);
$as($user + ['favoris' => [
    ['label' => 'Matchs', 'url' => '/admin/matchs'],
    ['label' => 'Audio', 'url' => '/admin/audio'],
    ['label' => 'Comptes', 'url' => '/admin/utilisateurs'],
    ['label' => 'Ailleurs', 'url' => 'https://exemple.com'],
    ['label' => '  <b>Une   fiche</b> ', 'url' => '/admin/fiche/2431'],
    ['label' => '', 'url' => '/admin/page-attente'],
]]);
$eq('favoris d’un historien : écrans permis seulement, noms nettoyés', F::mine(), [
    ['label' => 'Matchs', 'url' => '/admin/matchs'],
    ['label' => 'Une fiche', 'url' => '/admin/fiche/2431'],
    ['label' => 'Page attente', 'url' => '/admin/page-attente'],
]);
$as(null);
echo $fail ? "\n$fail échec(s)\n" : "\nTout est bon.\n";
exit($fail ? 1 : 0);
