<?php

declare(strict_types=1);

namespace Risiko\Domain;

/**
 * Eine Ereigniskarte innerhalb einer Partie.
 *
 * 42 Gebietskarten (cardNo === territoryId) und 2 Joker (43, 44).
 */
final class Card
{
    public function __construct(
        public readonly int $cardNo,
        public readonly ?int $territoryId,
        public readonly CardSymbol $symbol,
        private ?int $ownerPlayerId,
        private CardState $state,
        private int $deckPos,
    ) {
    }

    public function ownerPlayerId(): ?int
    {
        return $this->ownerPlayerId;
    }

    public function state(): CardState
    {
        return $this->state;
    }

    public function deckPos(): int
    {
        return $this->deckPos;
    }

    public function isJoker(): bool
    {
        return $this->symbol === CardSymbol::Joker;
    }

    public function giveTo(int $playerId): void
    {
        $this->ownerPlayerId = $playerId;
        $this->state         = CardState::Hand;
    }

    public function discard(): void
    {
        $this->ownerPlayerId = null;
        $this->state         = CardState::Discard;
    }

    public function returnToDeck(int $deckPos): void
    {
        $this->ownerPlayerId = null;
        $this->state         = CardState::Deck;
        $this->deckPos       = $deckPos;
    }

    public function label(): string
    {
        return $this->isJoker() ? 'Joker' : $this->symbol->label();
    }
}
