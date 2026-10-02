<?php
/**
 * Point d'entrée unique du site (seul dossier exposé : /public).
 */

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

\App\Kernel::handle(\App\Core\Request::fromGlobals())->send();
