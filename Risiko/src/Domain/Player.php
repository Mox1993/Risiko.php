<?php

declare(strict_types=1);

namespace Risiko\Domain;

/**
 * Ein Sitzplatz in einer Partie.
 *
 * Ein Spieler ohne Partie existiert nicht, deshalb gibt es kein eigenes
 * Repository - Player wird immer zusammen mit Game geladen und gespeichert.
 */
final class Player
{
    public function __construct(
        public readonly int $id,
        public readonly ?int $userId,
        public readonly string $displayName,
        public readonly string $color,
        public readonly int $turnOrder,
        private bool $eliminated = false,
        public readonly bool $isAi = false,
    ) {
    }

    public function isEliminated(): bool
    {
        return $this->eliminated;
    }

    /** Nur Game darf ausscheiden lassen - alles andere geht ueber die Aggregatwurzel. */
    public function eliminate(): void
    {
        $this->eliminated = true;
    }
}
