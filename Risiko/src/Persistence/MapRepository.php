<?php

declare(strict_types=1);

namespace Risiko\Persistence;

use Risiko\Domain\CardSymbol;
use Risiko\Domain\Continent;
use Risiko\Domain\Territory;
use Risiko\Domain\WorldMap;

/**
 * Laedt die statische Karte - drei Abfragen, danach nie wieder.
 *
 * Die Karte aendert sich waehrend einer Partie nicht, deshalb wird sie pro
 * Request nur einmal gebaut und gemerkt.
 */
final class MapRepository
{
    private ?WorldMap $cached = null;

    public function __construct(private Db $db)
    {
    }

    public function load(): WorldMap
    {
        if ($this->cached !== null) {
            return $this->cached;
        }

        $territories = [];
        $members     = [];
        foreach ($this->db->select(
            'SELECT id, name, continent_id, card_symbol
               FROM territories
              ORDER BY id'
        ) as $row) {
            $id = (int) $row['id'];
            $territories[$id] = new Territory(
                $id,
                (string) $row['name'],
                (int) $row['continent_id'],
                CardSymbol::from((string) $row['card_symbol']),
            );
            $members[(int) $row['continent_id']][] = $id;
        }

        $continents = [];
        foreach ($this->db->select(
            'SELECT id, name, bonus FROM continents ORDER BY id'
        ) as $row) {
            $id = (int) $row['id'];
            $continents[$id] = new Continent(
                $id,
                (string) $row['name'],
                (int) $row['bonus'],
                $members[$id] ?? [],
            );
        }

        $adjacency = array_fill_keys(array_keys($territories), []);
        foreach ($this->db->select(
            'SELECT territory_id, neighbor_id
               FROM territory_neighbors
              ORDER BY territory_id, neighbor_id'
        ) as $row) {
            $adjacency[(int) $row['territory_id']][] = (int) $row['neighbor_id'];
        }

        return $this->cached = new WorldMap($territories, $continents, $adjacency);
    }
}
