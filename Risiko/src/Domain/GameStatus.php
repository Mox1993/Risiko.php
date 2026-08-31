<?php

declare(strict_types=1);

namespace Risiko\Domain;

/** Lebenszyklus einer Partie. Werte entsprechen games.status. */
enum GameStatus: string
{
    case Lobby    = 'lobby';
    case Running  = 'running';
    case Finished = 'finished';

    public function label(): string
    {
        return match ($this) {
            self::Lobby    => 'wartet auf Mitspieler',
            self::Running  => 'läuft',
            self::Finished => 'beendet',
        };
    }
}
