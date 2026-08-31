<?php

declare(strict_types=1);

namespace Risiko\Domain;

/**
 * Symbol einer Ereigniskarte.
 *
 * Die drei Gebietssymbole stehen als Stammdatum in territories.card_symbol;
 * Joker haben kein Gebiet und tragen dieses Symbol nur im Speicher.
 */
enum CardSymbol: string
{
    case Infanterie = 'infanterie';
    case Kavallerie = 'kavallerie';
    case Artillerie = 'artillerie';
    case Joker      = 'joker';

    public function label(): string
    {
        return match ($this) {
            self::Infanterie => 'Infanterie',
            self::Kavallerie => 'Kavallerie',
            self::Artillerie => 'Artillerie',
            self::Joker      => 'Joker',
        };
    }

    public function glyph(): string
    {
        return match ($this) {
            self::Infanterie => '♟',
            self::Kavallerie => '♞',
            self::Artillerie => '♜',
            self::Joker      => '★',
        };
    }
}
