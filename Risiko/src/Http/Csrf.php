<?php

declare(strict_types=1);

namespace Risiko\Http;

use RuntimeException;

/**
 * CSRF-Schutz: ein Token pro Sitzung, ein verstecktes Feld in jedem Formular,
 * Pruefung bei jedem POST.
 *
 * Ohne das kann eine fremde Seite im Namen des eingeloggten Spielers Angriffe
 * ausloesen, solange nur dessen Browser das Sitzungscookie mitschickt.
 */
final class Csrf
{
    private const KEY = 'csrf_token';

    public function __construct(private Session $session)
    {
    }

    public function token(): string
    {
        $token = $this->session->get(self::KEY);
        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            $this->session->set(self::KEY, $token);
        }

        return $token;
    }

    public function field(): string
    {
        return sprintf(
            '<input type="hidden" name="csrf" value="%s">',
            htmlspecialchars($this->token(), ENT_QUOTES, 'UTF-8'),
        );
    }

    public function check(?string $given): void
    {
        if (!is_string($given) || !hash_equals($this->token(), $given)) {
            throw new RuntimeException('Ungültiges Sicherheitstoken. Bitte lade die Seite neu.');
        }
    }
}
