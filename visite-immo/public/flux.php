<?php
declare(strict_types=1);

// Flux des annonces publiées, pour les portails ou un multidiffuseur : /flux.php?t=<jeton> (Paramètres → Diffusion)
require __DIR__ . '/../app/bootstrap.php';

if (!hash_equals(jeton_flux(), (string) ($_GET['t'] ?? ''))) {
    http_response_code(403);
    exit('Jeton invalide.');
}
header('Content-Type: application/xml; charset=utf-8');
echo flux_portails();
