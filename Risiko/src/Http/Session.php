<?php

declare(strict_types=1);

namespace Risiko\Http;

/**
 * Duenner Aufsatz auf $_SESSION.
 *
 * Der Rest des Programms fasst $_SESSION nicht an - so gibt es genau eine
 * Stelle, an der Sitzungsdaten benannt und geloescht werden.
 */
final class Session
{
    public function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        session_set_cookie_params([
            'httponly' => true,
            'samesite' => 'Lax',
            'secure'   => (($_SERVER['HTTPS'] ?? '') !== ''),
        ]);
        session_start();
    }

    public function userId(): ?int
    {
        $id = $_SESSION['user_id'] ?? null;

        return $id === null ? null : (int) $id;
    }

    public function username(): ?string
    {
        $name = $_SESSION['username'] ?? null;

        return $name === null ? null : (string) $name;
    }

    public function isLoggedIn(): bool
    {
        return $this->userId() !== null;
    }

    public function login(int $userId, string $username): void
    {
        session_regenerate_id(true);     // gegen Session Fixation
        $_SESSION['user_id']  = $userId;
        $_SESSION['username'] = $username;
    }

    public function logout(): void
    {
        $_SESSION = [];
        session_regenerate_id(true);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public function flash(string $type, string $message): void
    {
        $_SESSION['flash'][] = ['type' => $type, 'text' => $message];
    }

    /** @return list<array{type:string,text:string}> */
    public function takeFlashes(): array
    {
        $flashes           = $_SESSION['flash'] ?? [];
        $_SESSION['flash'] = [];

        return $flashes;
    }
}
