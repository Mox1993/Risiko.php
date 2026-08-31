<?php

declare(strict_types=1);

/**
 * Minimaler PSR-4-Autoloader.
 *
 * Kein Composer: das Projekt hat keine externen Abhaengigkeiten, und auf einem
 * portablen Uniform Server ist ein fehlendes vendor/-Verzeichnis eine
 * Fehlerquelle mehr als noetig.
 */
spl_autoload_register(static function (string $class): void {
    static $prefixes = [
        'Risiko\\Tests\\' => __DIR__ . '/../tests/',
        'Risiko\\'        => __DIR__ . '/',
    ];

    foreach ($prefixes as $prefix => $baseDir) {
        if (!str_starts_with($class, $prefix)) {
            continue;
        }

        $relative = substr($class, strlen($prefix));
        $file     = $baseDir . str_replace('\\', '/', $relative) . '.php';

        if (is_file($file)) {
            require $file;
        }

        return;
    }
});
