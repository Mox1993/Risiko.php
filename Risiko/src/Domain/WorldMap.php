<?php

declare(strict_types=1);

namespace Risiko\Domain;

use InvalidArgumentException;

/**
 * Die unveraenderliche Karte.
 *
 * Wird einmal pro Request aus den Stammdaten gebaut und danach nur gelesen.
 * Kennt keine Partie, keinen Spieler und keine Datenbank.
 */
final class WorldMap
{
    /**
     * @param array<int,Territory>  $territories  Gebiets-ID => Gebiet
     * @param array<int,Continent>  $continents   Kontinent-ID => Kontinent
     * @param array<int,list<int>>  $adjacency    Gebiets-ID => Nachbar-IDs
     */
    public function __construct(
        private array $territories,
        private array $continents,
        private array $adjacency,
    ) {
    }

    public function has(int $id): bool
    {
        return isset($this->territories[$id]);
    }

    public function territory(int $id): Territory
    {
        return $this->territories[$id]
            ?? throw new InvalidArgumentException("Unbekanntes Gebiet: $id");
    }

    /** @return array<int,Territory> */
    public function territories(): array
    {
        return $this->territories;
    }

    /** @return list<int> */
    public function territoryIds(): array
    {
        return array_keys($this->territories);
    }

    /** @return array<int,Continent> */
    public function continents(): array
    {
        return $this->continents;
    }

    public function areNeighbours(int $a, int $b): bool
    {
        return in_array($b, $this->adjacency[$a] ?? [], true);
    }

    /** @return list<int> */
    public function neighbours(int $id): array
    {
        return $this->adjacency[$id] ?? [];
    }

    /**
     * Breitensuche: Gibt es einen Weg von $from nach $to, der ausschliesslich
     * ueber Gebiete fuehrt, fuer die $passable true liefert?
     *
     * Wird fuers Verschieben gebraucht - dort muessen alle Zwischenstationen
     * dem verschiebenden Spieler gehoeren.
     *
     * @param callable(int):bool $passable
     */
    public function connected(int $from, int $to, callable $passable): bool
    {
        if ($from === $to) {
            return true;
        }
        if (!$passable($from) || !$passable($to)) {
            return false;
        }

        $seen  = [$from => true];
        $queue = [$from];

        while ($queue !== []) {
            $current = array_shift($queue);
            foreach ($this->neighbours($current) as $next) {
                if (isset($seen[$next]) || !$passable($next)) {
                    continue;
                }
                if ($next === $to) {
                    return true;
                }
                $seen[$next]  = true;
                $queue[]      = $next;
            }
        }

        return false;
    }

    /**
     * Alle von $from aus erreichbaren Gebiete (ohne $from selbst).
     *
     * @param callable(int):bool $passable
     * @return list<int>
     */
    public function reachable(int $from, callable $passable): array
    {
        if (!$passable($from)) {
            return [];
        }

        $seen  = [$from => true];
        $queue = [$from];
        $out   = [];

        while ($queue !== []) {
            $current = array_shift($queue);
            foreach ($this->neighbours($current) as $next) {
                if (isset($seen[$next]) || !$passable($next)) {
                    continue;
                }
                $seen[$next] = true;
                $queue[]     = $next;
                $out[]       = $next;
            }
        }

        sort($out);

        return $out;
    }
}
