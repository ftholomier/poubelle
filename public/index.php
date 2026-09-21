<?php
/**
 * Point d'entrée unique. La racine web est ce dossier : tout le reste
 * (app/, data/, config/, templates/) vit au-dessus et n'est pas servable.
 */
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Kernel;
use App\Core\Request;

(new Kernel())->handle(Request::capture())->send();
