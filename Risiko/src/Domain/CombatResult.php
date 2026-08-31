<?php

declare(strict_types=1);

namespace Risiko\Domain;

/**
 * Ergebnis eines einzelnen Wuerfelaustauschs.
 *
 * Combat fuellt Wuerfel und Verluste, Game ergaenzt anschliessend den
 * Zusammenhang (welche Gebiete, wurde erobert). Beide Male entsteht ein neues
 * Objekt - so kann das Ergebnis nach der Rueckgabe niemand mehr verbiegen.
 */
final class CombatResult
{
    /**
     * @param list<int> $attackerDice
     * @param list<int> $defenderDice
     */
    public function __construct(
        public readonly array $attackerDice,
        public readonly array $defenderDice,
        public readonly int $attackerLosses,
        public readonly int $defenderLosses,
        public readonly int $from = 0,
        public readonly int $to = 0,
        public readonly bool $conquered = false,
    ) {
    }

    public function inBattle(int $from, int $to, bool $conquered): self
    {
        return new self(
            $this->attackerDice,
            $this->defenderDice,
            $this->attackerLosses,
            $this->defenderLosses,
            $from,
            $to,
            $conquered,
        );
    }

    /** @return array<string,mixed> fuer game_log.payload */
    public function toArray(): array
    {
        return [
            'from'            => $this->from,
            'to'              => $this->to,
            'attacker_dice'   => $this->attackerDice,
            'defender_dice'   => $this->defenderDice,
            'attacker_losses' => $this->attackerLosses,
            'defender_losses' => $this->defenderLosses,
            'conquered'       => $this->conquered,
        ];
    }
}
