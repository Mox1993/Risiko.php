<?php

declare(strict_types=1);

namespace Risiko\Domain;

use InvalidArgumentException;

/**
 * Der Wuerfelvergleich - die fehleranfaelligste Stelle des Spiels,
 * deshalb bewusst isoliert und ohne jede Kenntnis von Gebieten oder Spielern.
 *
 * Gewuerfelt wird ausschliesslich hier, also serverseitig. Wuerfelt das
 * JavaScript, gewinnt jeder mit geoeffneter Entwicklerkonsole.
 */
final class Combat
{
    public const MAX_ATTACK_DICE  = 3;
    public const MAX_DEFENCE_DICE = 2;

    public function __construct(private Dice $dice)
    {
    }

    public function resolve(int $attackerDice, int $defenderDice): CombatResult
    {
        if ($attackerDice < 1 || $attackerDice > self::MAX_ATTACK_DICE) {
            throw new InvalidArgumentException('Der Angreifer wuerfelt mit 1 bis 3 Wuerfeln.');
        }
        if ($defenderDice < 1 || $defenderDice > self::MAX_DEFENCE_DICE) {
            throw new InvalidArgumentException('Der Verteidiger wuerfelt mit 1 oder 2 Wuerfeln.');
        }

        $a = $this->dice->roll($attackerDice);
        $d = $this->dice->roll($defenderDice);

        $lossAttacker = 0;
        $lossDefender = 0;

        $rounds = min(count($a), count($d));
        for ($i = 0; $i < $rounds; $i++) {
            // Gleichstand geht an den Verteidiger.
            if ($a[$i] > $d[$i]) {
                $lossDefender++;
            } else {
                $lossAttacker++;
            }
        }

        return new CombatResult($a, $d, $lossAttacker, $lossDefender);
    }
}
