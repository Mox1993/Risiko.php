<?php

declare(strict_types=1);

namespace Risiko\Domain;

/**
 * Ein Gebiet als Stammdatum: Name, Kontinent, Kartensymbol.
 *
 * Wem es gehoert und wie viele Einheiten darauf stehen, steht nicht hier,
 * sondern in TerritoryState - das aendert sich pro Partie, das hier nie.
 *
 * Wie das Gebiet auf der Karte aussieht, steht ebenfalls nicht hier, sondern
 * in src/View/map_data.php. Verknuepft wird ueber die id.
 */
final class Territory
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly int $continentId,
        public readonly CardSymbol $cardSymbol,
    ) {
    }
}
