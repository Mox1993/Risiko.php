<?php

declare(strict_types=1);

namespace Risiko\Domain;

/**
 * Wuerfelquelle.
 *
 * Existiert nur, damit Combat testbar bleibt: im Spiel steckt RandomDice
 * darin, im Test FixedDice mit vorgegebener Augenfolge.
 */
interface Dice
{
    /**
     * @param int $count Anzahl Wuerfel (>= 1)
     * @return list<int> Augenzahlen, absteigend sortiert
     */
    public function roll(int $count): array;
}
