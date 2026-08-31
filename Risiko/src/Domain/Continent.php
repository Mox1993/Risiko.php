<?php

declare(strict_types=1);

namespace Risiko\Domain;

/** Ein Kontinent samt Bonus bei Vollbesitz. */
final class Continent
{
    /** @param list<int> $territoryIds */
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly int $bonus,
        public readonly array $territoryIds,
    ) {
    }
}
