<?php
declare(strict_types=1);

// Lien « Donnez votre avis » envoyé après l'acte : note le clic (pas de relance) puis redirige vers la fiche Google.
require __DIR__ . '/../app/bootstrap.php';

$lien = lien_lire((string) ($_GET['t'] ?? ''));
if ($lien && $lien['role'] === 'avis' && ($t = chercher_dossier($lien['dossier']))) {
    update_visit($t[0], $lien['dossier'], function (array $v) use ($lien) {
        if (empty($v['avis'][$lien['qui']]['clic'])) {
            $v['avis'][$lien['qui']]['clic'] = date('c');
            journal_ajout($v, 'avis', "Le {$lien['qui']} a ouvert le lien pour donner son avis Google.");
        }
        return $v;
    });
}
$cible = (string) ($CONFIG['lien_avis_google'] ?? '');
if (!preg_match('#^https://#', $cible)) {
    http_response_code(404);
    exit('Lien d\'avis non configuré.');
}
header('Location: ' . $cible, true, 302);
