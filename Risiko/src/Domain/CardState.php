<?php

declare(strict_types=1);

namespace Risiko\Domain;

/** Wo eine Karte gerade liegt. Werte entsprechen game_cards.state. */
enum CardState: string
{
    case Deck    = 'deck';
    case Hand    = 'hand';
    case Discard = 'discard';
}
