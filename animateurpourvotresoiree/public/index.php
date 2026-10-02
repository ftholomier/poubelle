<?php
declare(strict_types=1);

// Point d'entrée unique : tout le code applicatif est hors du dossier public.
require dirname(__DIR__) . '/app/bootstrap.php';

App\Core\App::run();
