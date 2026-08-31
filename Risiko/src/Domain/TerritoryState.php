<?php

declare(strict_types=1);

namespace Risiko\Domain;

use InvalidArgumentException;

/**
 * Besitz und Truppenstaerke eines Gebiets innerhalb einer Partie.
 *
 * Die Methoden sind absichtlich schmal: Game ist die einzige Stelle, die sie
 * aufruft, und Game achtet auf die Regeln. Hier steht nur, was physikalisch
 * unmoeglich ist (negative Einheiten).
 */
final class TerritoryState
{
    public function __construct(
        public readonly int $territoryId,
        private ?int $ownerPlayerId,
        private int $armies,
    ) {
    }

    public function ownerPlayerId(): ?int
    {
        return $this->ownerPlayerId;
    }

    public function armies(): int
    {
        return $this->armies;
    }

    public function isOwnedBy(?int $playerId): bool
    {
        return $playerId !== null && $this->ownerPlayerId === $playerId;
    }

    public function addArmies(int $n): void
    {
        $this->setArmies($this->armies + $n);
    }

    public function removeArmies(int $n): void
    {
        $this->setArmies($this->armies - $n);
    }

    public function setArmies(int $n): void
    {
        if ($n < 0) {
            throw new InvalidArgumentException(
                "Negative Truppenstaerke fuer Gebiet {$this->territoryId}."
            );
        }
        $this->armies = $n;
    }

    public function transferTo(int $playerId, int $armies): void
    {
        $this->ownerPlayerId = $playerId;
        $this->setArmies($armies);
    }
}
