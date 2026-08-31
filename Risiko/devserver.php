<?php

declare(strict_types=1);

/**
 * Router fuer den eingebauten PHP-Server - nur zum schnellen Ausprobieren.
 *
 *     php -S 127.0.0.1:8080 -t public devserver.php
 *
 * Vorhandene Dateien (CSS, JS, SVG) liefert der Server selbst aus, alles
 * andere geht an den Front Controller. Fuer den echten Betrieb nimmst du
 * Apache mit DocumentRoot auf public/.
 */

$path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
$file = __DIR__ . '/public' . $path;

if ($path !== '/' && is_file($file)) {
    return false;
}

require __DIR__ . '/public/index.php';
