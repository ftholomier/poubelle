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
espace_page($token);
