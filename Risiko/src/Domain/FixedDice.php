<?php

declare(strict_types=1);

namespace Risiko\Domain;

use RuntimeException;

/**
 * Wuerfel mit vorgegebener Augenfolge - fuer Tests.
 *
 * Die Augen werden der Reihe nach entnommen; jeder Wurf wird wie bei echten
 * Wuerfeln absteigend sortiert zurueckgegeben.
 */
final class FixedDice implements Dice
{
    /** @var list<int> */
    private array $queue;

    /** @param list<int> $values */
    public function __construct(array $values)
    {
        $this->queue = array_values($values);
    }

    public function roll(int $count): array
    {
        if (count($this->queue) < $count) {
            throw new RuntimeException(
                'FixedDice ist leer - es werden mehr Wuerfel gebraucht als vorgegeben.'
            );
        }

        $values = array_splice($this->queue, 0, $count);
        rsort($values);

        return $values;
    }

    public function remaining(): int
    {
        return count($this->queue);
    }
}
