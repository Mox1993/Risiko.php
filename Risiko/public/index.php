<?php

declare(strict_types=1);

/**
 * Front Controller - der einzige per Browser erreichbare PHP-Einstieg.
 *
 * Nur public/ liegt im DocumentRoot. Damit kann niemand config.php oder eine
 * Klassendatei direkt aufrufen.
 */

use Risiko\Http\App;
use Risiko\Http\Controller\AuthController;
use Risiko\Http\Controller\GameController;
use Risiko\Http\Controller\LobbyController;
use Risiko\Http\Router;

require dirname(__DIR__) . '/src/autoload.php';
require dirname(__DIR__) . '/src/helpers.php';

$configFile = dirname(__DIR__) . '/config/config.php';
if (!is_file($configFile)) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    exit(
        "config/config.php fehlt.\n\n"
        . "Kopiere config/config.example.php nach config/config.php und trage\n"
        . "die Zugangsdaten deiner Datenbank ein.\n"
    );
}

/** @var array<string,mixed> $config */
$config = require $configFile;

$debug = (bool) ($config['debug'] ?? false);
ini_set('display_errors', $debug ? '1' : '0');
error_reporting(E_ALL);
mb_internal_encoding('UTF-8');

try {
    $app = new App($config);

    $router = new Router();

    $auth  = new AuthController($app);
    $lobby = new LobbyController($app);
    $game  = new GameController($app);

    // --- Konto ---------------------------------------------------------
    $router->get('/anmelden', $auth->loginForm(...));
    $router->post('/anmelden', $auth->login(...));
    $router->get('/registrieren', $auth->registerForm(...));
    $router->post('/registrieren', $auth->register(...));
    $router->post('/abmelden', $auth->logout(...));

    // --- Lobby ---------------------------------------------------------
    $router->get('/', $lobby->index(...));
    $router->post('/partie/neu', $lobby->create(...));
    $router->get('/partie/{id}/lobby', $lobby->waitingRoom(...));
    $router->post('/partie/{id}/beitreten', $lobby->join(...));
    $router->post('/partie/{id}/starten', $lobby->start(...));

    // --- Partie --------------------------------------------------------
    $router->get('/partie/{id}', $game->show(...));
    $router->get('/partie/{id}/status', $game->status(...));
    $router->post('/partie/{id}/verstaerken', $game->reinforce(...));
    $router->post('/partie/{id}/tauschen', $game->trade(...));
    $router->post('/partie/{id}/angriff', $game->attack(...));
    $router->post('/partie/{id}/nachruecken', $game->occupy(...));
    $router->post('/partie/{id}/verschieben', $game->fortify(...));
    $router->post('/partie/{id}/phase', $game->endPhase(...));

    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if (!$router->dispatch($method, Router::currentPath())) {
        $app->notFound();
    }
} catch (Throwable $e) {
    error_log('Risiko: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());

    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');

    echo '<!doctype html><meta charset="utf-8"><title>Fehler</title>'
        . '<style>body{font:16px/1.6 system-ui,sans-serif;max-width:44rem;margin:4rem auto;'
        . 'padding:0 1rem;color:#2c2418}pre{background:#f2ece1;padding:1rem;overflow:auto}</style>'
        . '<h1>Da ist etwas schiefgegangen</h1>';

    if ($debug) {
        echo '<pre>' . htmlspecialchars(
            $e::class . ': ' . $e->getMessage() . "\n"
            . $e->getFile() . ':' . $e->getLine() . "\n\n"
            . $e->getTraceAsString(),
            ENT_QUOTES,
            'UTF-8',
        ) . '</pre>';
    } else {
        echo '<p>Die Einzelheiten stehen im Fehlerprotokoll des Servers.</p>';
    }
}
