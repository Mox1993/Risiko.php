<?php

declare(strict_types=1);

namespace Risiko\Application;

use Risiko\Domain\Dice;
use Risiko\Domain\Game;
use Risiko\Domain\RuleViolation;
use Risiko\Persistence\Db;
use Risiko\Persistence\GameRepository;

/**
 * Der Rahmen um jeden Spielzug.
 *
 * Jeder Zug laeuft nach demselben Muster: Transaktion oeffnen, Partie gesperrt
 * laden, pruefen ob dieser Benutzer ueberhaupt am Zug ist, Regel aufrufen,
 * speichern, committen. Wer davon abweicht, baut Rennen ein.
 *
 * Was genau geschieht, steht im uebergebenen Aufruf - Angriff, Verstaerkung
 * und Verschieben unterscheiden sich nur dort und brauchen deshalb keine
 * eigenen Klassen.
 */
final class GameAction
{
    public function __construct(
        private Db $db,
        private GameRepository $games,
        private Dice $dice,
    ) {
    }

    /**
     * @template T
     * @param callable(Game,int):T $work bekommt Partie und eigene Spieler-ID
     * @return T
     */
    public function withGame(int $gameId, ?int $userId, callable $work): mixed
    {
        return $this->db->transaction(function () use ($gameId, $userId, $work) {
            $game = $this->games->findForUpdate($gameId, $this->dice);

            $playerId = $game->actingPlayerIdFor($userId);
            if ($playerId === null) {
                // Nie darauf verlassen, dass der Button ausgeblendet war.
                throw new RuleViolation('Du gehörst nicht zu dieser Partie.');
            }

            $result = $work($game, $playerId);
            $this->games->save($game);

            return $result;
        });
    }
}
