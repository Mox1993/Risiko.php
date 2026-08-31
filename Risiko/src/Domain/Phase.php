<?php

declare(strict_types=1);

namespace Risiko\Domain;

/**
 * Die drei Abschnitte eines Spielzugs.
 *
 * Die Werte entsprechen exakt dem ENUM in games.phase.
 */
enum Phase: string
{
    case Reinforce = 'reinforce';
    case Attack    = 'attack';
    case Fortify   = 'fortify';

    public function label(): string
    {
        return match ($this) {
            self::Reinforce => 'Verstärkung',
            self::Attack    => 'Angriff',
            self::Fortify   => 'Verschieben',
        };
    }
}
