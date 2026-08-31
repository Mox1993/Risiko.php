<?php

declare(strict_types=1);

/**
 * Vorlage fuer die lokale Konfiguration.
 *
 * Kopieren nach config/config.php und anpassen. config.php gehoert nicht ins
 * Repository - dort stehen Zugangsdaten.
 *
 * Uniform Server: der MySQL-Zugang steht im Uniform-Server-Panel unter
 * "MySQL -> root password". Frisch installiert ist der Benutzer 'root' mit
 * dem dort gesetzten Passwort.
 */
return [
    'db' => [
       'host' => 'sql103.epizy.com',
        'port' => 3306,
        'user' => 'if0_42759988',
        'pass' => 'bDz84RrveSi',
        'name' => 'if0_42759988_risiko',
    ],

    // true zeigt Ausnahmen im Browser. Auf einer oeffentlichen Instanz aus.
    'debug' => false,
];
