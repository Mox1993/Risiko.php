<?php

declare(strict_types=1);

namespace Risiko\Http\Controller;

use Risiko\Domain\CombatResult;
use Risiko\Domain\Game;
use Risiko\Domain\GameStatus;
use Risiko\Domain\Phase;
use Risiko\Http\App;

/**
 * Alles rund um eine laufende Partie.
 *
 * Die Controller-Methoden tun bewusst wenig: Eingaben in int wandeln, den Zug
 * anstossen, umleiten. Die Regeln stecken in Game, die Transaktion in
 * GameAction, der Rahmen aus Tokenpruefung und Fehlermeldung in
 * App::guardPost().
 */
final class GameController
{
    public function __construct(private App $app)
    {
    }

    public function show(int $gameId): void
    {
        $userId = $this->app->requireLogin();
        $game   = $this->app->games->find($gameId, $this->app->dice);

        if ($game === null) {
            $this->app->notFound('Diese Partie gibt es nicht.');
        }
        if ($game->status() === GameStatus::Lobby) {
            $this->app->redirect("/partie/$gameId/lobby");
        }

        $myPlayerId = $game->actingPlayerIdFor($userId);
        if ($myPlayerId === null) {
            $this->app->notFound('Du gehörst nicht zu dieser Partie.');
        }

        $isMyTurn = $myPlayerId === $game->currentPlayerId() && !$game->isFinished();

        $this->app->send($this->app->view->page('spiel', $game->name(), [
            'game'       => $game,
            'geo'        => require dirname(__DIR__, 2) . '/View/map_data.php',
            'myPlayerId' => $myPlayerId,
            'isMyTurn'   => $isMyTurn,
            'hand'       => $game->handOf($myPlayerId),
            'targets'    => $this->targetsFor($game, $myPlayerId, $isMyTurn),
            'log'        => $this->app->games->recentLog($gameId, 25),
            'token'      => $this->app->games->lastChangeToken($gameId),
        ]));
    }

    /**
     * Gueltige Ziele je Ausgangsgebiet - einmal serverseitig berechnet, damit
     * das JavaScript nichts entscheiden muss.
     *
     * @return array<int,list<int>>
     */
    private function targetsFor(Game $game, int $playerId, bool $isMyTurn): array
    {
        if (!$isMyTurn) {
            return [];
        }

        $out = [];
        foreach ($game->territoryIdsOf($playerId) as $tid) {
            $targets = match ($game->phase()) {
                Phase::Attack  => $game->attackTargets($playerId, $tid),
                Phase::Fortify => $game->fortifyTargets($playerId, $tid),
                default        => [],
            };
            if ($targets !== []) {
                $out[$tid] = $targets;
            }
        }

        return $out;
    }

    public function reinforce(int $gameId): never
    {
        $gebiet = $this->app->request->postInt('gebiet');
        $anzahl = $this->app->request->postInt('anzahl', 1);

        $this->play($gameId, fn (Game $game, int $pid) => $game->reinforce($pid, $gebiet, $anzahl));
    }

    public function trade(int $gameId): never
    {
        $karten = $this->app->request->postIntList('karte');

        $this->play(
            $gameId,
            fn (Game $game, int $pid) => $game->tradeCards($pid, $karten),
            fn (int $armies) => $this->app->session->flash(
                'info',
                "Kartensatz getauscht: $armies zusätzliche Einheiten.",
            ),
        );
    }

    public function attack(int $gameId): never
    {
        $von     = $this->app->request->postInt('von');
        $nach    = $this->app->request->postInt('nach');
        $wuerfel = $this->app->request->postInt('wuerfel', 1);

        $this->play(
            $gameId,
            fn (Game $game, int $pid) => $game->attack($pid, $von, $nach, $wuerfel),
            fn (CombatResult $result) => $this->app->session->flash(
                'battle',
                $this->describe($result),
            ),
        );
    }

    public function occupy(int $gameId): never
    {
        $anzahl = $this->app->request->postInt('anzahl', 0);

        $this->play($gameId, fn (Game $game, int $pid) => $game->occupy($pid, $anzahl));
    }

    public function fortify(int $gameId): never
    {
        $von    = $this->app->request->postInt('von');
        $nach   = $this->app->request->postInt('nach');
        $anzahl = $this->app->request->postInt('anzahl', 1);

        $this->play($gameId, fn (Game $game, int $pid) => $game->fortify($pid, $von, $nach, $anzahl));
    }

    public function endPhase(int $gameId): never
    {
        $this->play($gameId, fn (Game $game, int $pid) => $game->endPhase($pid));
    }

    /** Kleiner Endpunkt fuers Polling: aendert sich der Token, lohnt ein Neuladen. */
    public function status(int $gameId): void
    {
        $this->app->requireLogin();

        $this->app->json(['token' => $this->app->games->lastChangeToken($gameId)]);
    }

    /**
     * Einen Spielzug ausfuehren und zurueck zur Partie.
     *
     * Egal ob der Zug gelingt oder gegen eine Regel verstoesst - es geht immer
     * per Redirect zurueck, sonst wiederholt F5 den letzten Angriff. Bei einem
     * Verstoss leitet guardPost() selbst um, $melde laeuft dann nicht mehr.
     *
     * @param callable(Game,int):mixed $work  der eigentliche Zug
     * @param (callable(mixed):void)|null $melde Meldung aus dem Ergebnis
     */
    private function play(int $gameId, callable $work, ?callable $melde = null): never
    {
        $userId = $this->app->requireLogin();
        $ziel   = "/partie/$gameId";

        $result = $this->app->guardPost(
            $ziel,
            fn () => $this->app->play->withGame($gameId, $userId, $work),
        );

        if ($melde !== null) {
            $melde($result);
        }

        $this->app->redirect($ziel);
    }

    private function describe(CombatResult $result): string
    {
        $map  = $this->app->maps->load();
        $from = $map->territory($result->from)->name;
        $to   = $map->territory($result->to)->name;

        $text = sprintf(
            '%s greift %s an — Würfel %s gegen %s.',
            $from,
            $to,
            implode(', ', $result->attackerDice),
            implode(', ', $result->defenderDice),
        );

        $losses = [];
        if ($result->attackerLosses > 0) {
            $losses[] = "Angreifer verliert {$result->attackerLosses}";
        }
        if ($result->defenderLosses > 0) {
            $losses[] = "Verteidiger verliert {$result->defenderLosses}";
        }
        if ($losses !== []) {
            $text .= ' ' . implode(', ', $losses) . '.';
        }
        if ($result->conquered) {
            $text .= " $to ist erobert.";
        }

        return $text;
    }
}
