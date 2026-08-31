<?php

declare(strict_types=1);

namespace Risiko\Domain;

use InvalidArgumentException;

/**
 * Echte Wuerfel.
 *
 * random_int() statt rand(): rand() ist vorhersagbar, und bei einem Spiel,
 * in dem Wuerfel ueber Sieg und Niederlage entscheiden, ist das ein Problem.
 */
final class RandomDice implements Dice
{
    public function roll(int $count): array
    {
        if ($count < 1) {
            throw new InvalidArgumentException('Es muss mindestens ein Wuerfel geworfen werden.');
        }

        $values = [];
        for ($i = 0; $i < $count; $i++) {
            $values[] = random_int(1, 6);
        }
        rsort($values);

        return $values;
    }
}
