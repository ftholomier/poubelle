<?php
declare(strict_types=1);

// Espace client (vendeur, acquéreur, notaire) : accès par lien personnel, sans mot de passe.
//   /espace/?t=<jeton>              page
//   /espace/?t=<jeton>&pdf=<doc>    document PDF
//   POST /espace/?t=<jeton>&a=<action>   code de signature, signature, dépôt de pièce…

require __DIR__ . '/../../app/bootstrap.php';
set_time_limit(180);

$token = (string) ($_GET['t'] ?? '');
if ($_SERVER['REQUEST_METHOD'] === 'POST') espace_action($token, (string) ($_GET['a'] ?? ''));
if (isset($_GET['pdf'])) espace_pdf($token, (string) $_GET['pdf']);
if (isset($_GET['piece'])) {
    // Pièces du dossier : notaire et vendeur uniquement
    $lien = lien_lire($token);
    $trouve = $lien && in_array($lien['role'], ['notaire', 'vendeur'], true) ? chercher_dossier($lien['dossier']) : null;
    if (!$trouve) { http_response_code(403); exit('Accès refusé.'); }
    acces_lien($lien, $trouve[1], 'Pièce du dossier', (string) $_GET['piece'], true);
    try {
        servir_piece(visit_dir($trouve[0], $lien['dossier']), (string) $_GET['piece']);
    } catch (HttpError $e) {
        http_response_code(404);
        exit('Pièce introuvable.');
    }
}
espace_page($token);
