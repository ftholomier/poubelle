<?php

declare(strict_types=1);

/**
 * Autoloader natif (sans Composer) pour le namespace Poubelle\WhatsApp.
 * Mappe Poubelle\WhatsApp\Foo  ->  src/Foo.php
 */
spl_autoload_register(static function (string $class): void {
    $prefix = 'Poubelle\\WhatsApp\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $file = __DIR__ . '/src/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require $file;
    }
});
