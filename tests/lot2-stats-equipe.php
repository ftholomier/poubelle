<?php
/**
 * Lot 2 — statistiques de l'équipe (StatsReport::team) : regroupement par compte (uid) sous le nom
 * actuel (membre renommé = une personne, homonymes = deux), « actions » sans connexions, déconnexions
 * ni passages d'un musée à l'autre, 10 dernières actions relues mois par mois. Comptes simulés en
 * mémoire ; deux mois de journal d'essai (1990) écrits puis effacés. Usage : php tests/lot2-stats-equipe.php
 */
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Core\JsonStore;
use App\Services\StatsReport;

$fail = 0;
$eq = function (string $label, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    if (!$ok) {
        $fail++;
    }
    echo ($ok ? 'OK   ' : 'FAIL ') . $label . ($ok ? '' : ' => ' . json_encode($got, JSON_UNESCAPED_UNICODE) . ' attendu ' . json_encode($exp, JSON_UNESCAPED_UNICODE)) . "\n";
};
$users = [
    'zq1' => ['id' => 'zq1', 'name' => 'Zqnouveau Nom', 'email' => 'a@exemple.fr', 'role' => 'user', 'status' => 'active'],
    'zq2' => ['id' => 'zq2', 'name' => 'Zqhomonyme', 'email' => 'b@exemple.fr', 'role' => 'user', 'status' => 'active'],
    'zq3' => ['id' => 'zq3', 'name' => 'Zqhomonyme', 'email' => 'c@exemple.fr', 'role' => 'user', 'status' => 'active'],
];
$cache = new ReflectionProperty(JsonStore::class, 'cache');
$c = $cache->getValue();
$c[APP_ROOT . '/storage/users.json'] = $users;
$cache->setValue(null, $c);

$dir = STORAGE_PATH . '/activity';
$files = ["$dir/1990-01.jsonl", "$dir/1990-02.jsonl"];
$line = fn (string $at, ?string $uid, string $by, string $action, string $title = '') => json_encode(['at' => $at, 'by' => $by, 'uid' => $uid, 'action' => $action, 'title' => $title, 'id' => null, 'type' => null], JSON_UNESCAPED_UNICODE) . "\n";
try {
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $jan = $feb = '';
    // Janvier : zq1 sous son ancien nom (uid), 6 modifications ; une entrée ancienne sans uid, au nom actuel.
    for ($i = 1; $i <= 6; $i++) {
        $jan .= $line(sprintf('1990-01-%02dT10:00:00+01:00', $i), 'zq1', 'Zqancien Nom', 'a modifié', "Fiche J$i");
    }
    $jan .= $line('1990-01-20T10:00:00+01:00', null, 'Zqnouveau Nom', 'a créé', 'Fiche sans uid');
    $jan .= $line('1990-01-21T10:00:00+01:00', 'zq2', 'Zqhomonyme', 'a modifié', 'Fiche H2');
    // Février : connexions et bascules (pas des actions), 6 modifications, l'homonyme zq3.
    $feb .= $line('1990-02-01T09:00:00+01:00', 'zq1', 'Zqnouveau Nom', 's’est connecté');
    $feb .= $line('1990-02-01T09:01:00+01:00', 'zq1', 'Zqnouveau Nom', 'est passé au musée Paris');
    $feb .= $line('1990-02-01T09:02:00+01:00', 'zq1', 'Zqnouveau Nom', 'est arrivé depuis un autre musée');
    for ($i = 1; $i <= 6; $i++) {
        $feb .= $line(sprintf('1990-02-%02dT10:00:00+01:00', $i + 1), 'zq1', 'Zqnouveau Nom', 'a modifié', "Fiche F$i");
    }
    $feb .= $line('1990-02-20T18:00:00+01:00', 'zq1', 'Zqnouveau Nom', 's’est déconnecté');
    $feb .= $line('1990-02-21T10:00:00+01:00', 'zq3', 'Zqhomonyme', 'a publié', 'Fiche H3');
    file_put_contents($files[0], $jan);
    file_put_contents($files[1], $feb);

    $t = StatsReport::team('1990-01-01', '1990-02-28');
    $p = $t['people'];
    $eq('membre renommé : une seule personne, nom actuel', [isset($p['Zqnouveau Nom']), isset($p['Zqancien Nom'])], [true, false]);
    $eq('actions : sans connexions ni bascules', $p['Zqnouveau Nom']['edits'], 13);
    $eq('connexions comptées à part', $p['Zqnouveau Nom']['logins'], 1);
    $eq('homonymes : deux personnes', [$p['Zqhomonyme']['edits'] ?? null, $p['Zqhomonyme (2)']['edits'] ?? null, $p['Zqhomonyme']['uid'] !== $p['Zqhomonyme (2)']['uid']], [1, 1, true]);
    $eq('« Ce qui a été fait » sans les connexions', array_intersect(array_keys($t['actions']), ['s’est connecté', 's’est déconnecté', 'est passé au musée Paris']), []);
    $eq('total des actions', $t['edits'], 15);
    $recent = $p['Zqnouveau Nom']['recent'];
    $eq('10 dernières actions sur deux mois', count($recent), 10);
    $eq('la plus récente d’abord, sans la déconnexion', [$recent[0]['title'], $recent[9]['title']], ['Fiche F6', 'Fiche J4']);
    $eq('nom pour le lien du journal', $p['Zqhomonyme (2)']['name'], 'Zqhomonyme');
} finally {
    foreach ($files as $f) {
        @unlink($f);
    }
}
echo $fail ? "\n$fail échec(s).\n" : "\nTout est bon.\n";
exit($fail ? 1 : 0);
