<?php

declare(strict_types=1);

namespace Risiko\Application;

use Risiko\Domain\Dice;
use Risiko\Domain\RuleViolation;
use Risiko\Persistence\Db;
use Risiko\Persistence\GameRepository;

/**
 * Der Lebenslauf einer Partie vor dem ersten Zug: anlegen, beitreten, starten.
 *
 * Die drei Schritte gehoeren zusammen und laufen jeder in einer Transaktion -
 * ohne die traete beim gleichzeitigen Beitritt zweier Spieler derselbe
 * Sitzplatz doppelt auf.
 */
final class LobbyAction
{
    public function __construct(
        private Db $db,
        private GameRepository $games,
        private Dice $dice,
    ) {
    }

    /**
     * Legt eine Partie an und liefert ihre ID.
     *
     * Zwei Betriebsarten:
     *   - Hotseat: alle Sitzplaetze gehoeren demselben Benutzer, die Partie
     *     startet sofort.
     *   - Offen:   nur der Ersteller sitzt drin, weitere treten ueber die
     *     Lobby bei.
     *
     * @param list<string> $seatNames nur im Hotseat-Modus ausgewertet
     */
    public function create(
        int $userId,
        string $ownName,
        string $gameName,
        int $maxPlayers,
        bool $hotseat,
        array $seatNames = [],
    ): int {
        return $this->db->transaction(function () use (
            $userId, $ownName, $gameName, $maxPlayers, $hotseat, $seatNames
        ): int {
            if ($hotseat) {
                $seatNames = array_values(array_filter(
                    array_map('trim', $seatNames),
                    static fn (string $n): bool => $n !== '',
                ));
                if (count($seatNames) < 2 || count($seatNames) > 6) {
                    throw new RuleViolation('Für eine Hotseat-Partie brauchst du 2 bis 6 Namen.');
                }
                $maxPlayers = count($seatNames);
            }

            $gameId = $this->games->createLobby($gameName, $maxPlayers, $hotseat);

            if ($hotseat) {
                foreach ($seatNames as $name) {
                    // Alle Plaetze gehoeren dem Ersteller: er sitzt vor dem
                    // Rechner und steuert reihum jeden Spieler.
                    $this->games->addSeat($gameId, $userId, $name);
                }
                $this->games->startGame($gameId, $this->dice);
            } else {
                $this->games->addSeat($gameId, $userId, $ownName);
            }

            return $gameId;
        });
    }

    /** Einer offenen Partie beitreten. */
    public function join(int $gameId, int $userId, string $displayName): void
    {
        $this->db->transaction(function () use ($gameId, $userId, $displayName): void {
            $this->games->join($gameId, $userId, $displayName);
        });
    }

    /**
     * Aus der Lobby wird eine laufende Partie.
     *
     * Starten darf nur, wer den ersten Sitzplatz haelt - sonst koennte jeder
     * Beitretende die Aufstellung erzwingen, bevor die Runde vollstaendig ist.
     */
    public function start(int $gameId, int $userId): void
    {
        $this->db->transaction(function () use ($gameId, $userId): void {
            $game = $this->games->findForUpdate($gameId, $this->dice);

            $host = $game->playersInOrder()[0] ?? null;
            if ($host === null || $host->userId !== $userId) {
                throw new RuleViolation('Nur wer die Partie eröffnet hat, kann sie starten.');
            }

            $this->games->startGame($gameId, $this->dice);
        });
    }
}
