<?php

declare(strict_types=1);

namespace Risiko\Http;

/**
 * Kleiner Router.
 *
 * Muster duerfen Platzhalter in geschweiften Klammern enthalten:
 * "/partie/{id}/angriff". Der Platzhalter passt auf Ziffern und wird dem
 * Handler als Argument uebergeben.
 */
final class Router
{
    /** @var list<array{method:string,regex:string,keys:list<string>,handler:callable}> */
    private array $routes = [];

    public function get(string $pattern, callable $handler): void
    {
        $this->add('GET', $pattern, $handler);
    }

    public function post(string $pattern, callable $handler): void
    {
        $this->add('POST', $pattern, $handler);
    }

    private function add(string $method, string $pattern, callable $handler): void
    {
        $keys  = [];
        $regex = preg_replace_callback(
            '/\{(\w+)\}/',
            static function (array $m) use (&$keys): string {
                $keys[] = $m[1];

                return '(\d+)';
            },
            $pattern,
        );

        $this->routes[] = [
            'method'  => $method,
            'regex'   => '#^' . $regex . '$#',
            'keys'    => $keys,
            'handler' => $handler,
        ];
    }

    /** @return bool false, wenn keine Route passt */
    public function dispatch(string $method, string $path): bool
    {
        $path = '/' . trim($path, '/');

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }
            if (!preg_match($route['regex'], $path, $m)) {
                continue;
            }

            $args = array_map('intval', array_slice($m, 1));
            ($route['handler'])(...$args);

            return true;
        }

        return false;
    }

    /** Ermittelt den Pfad relativ zum Projektverzeichnis. */
    public static function currentPath(): string
    {
        $uri  = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $base = rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? ''))), '/');

        if ($base !== '' && str_starts_with($path, $base)) {
            $path = substr($path, strlen($base));
        }

        return '/' . trim(rawurldecode($path), '/');
    }
}
