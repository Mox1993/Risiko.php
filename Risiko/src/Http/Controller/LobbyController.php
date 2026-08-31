<?php

declare(strict_types=1);

namespace Risiko\Http\Controller;

use Risiko\Domain\GameStatus;
use Risiko\Http\App;

/** Uebersicht, neue Partien, Beitritt, Start. */
final class LobbyController
{
    public function __construct(private App $app)
    {
    }

    public function index(): void
    {
        $userId = $this->app->requireLogin();

        $this->app->send($this->app->view->page('lobby', 'Partien', [
            'mine'     => $this->app->games->findGamesForUser($userId),
            'open'     => $this->app->games->findOpenGames(),
            'username' => $this->app->session->username() ?? 'Spieler',
        ]));
    }

    public function create(): never
    {
        $userId  = $this->app->requireLogin();
        $hotseat = $this->app->request->post('modus') === 'hotseat';

        $gameId = $this->app->guardPost('/', fn (): int => $this->app->lobby->create(
            $userId,
            $this->app->session->username() ?? 'Spieler',
            $this->app->request->post('name'),
            $this->app->request->postInt('plaetze', 3),
            $hotseat,
            $this->app->request->postStringList('sitz'),
        ));

        // Hotseat startet sofort, eine offene Partie wartet erst auf Mitspieler.
        $this->app->redirect($hotseat ? "/partie/$gameId" : "/partie/$gameId/lobby");
    }

    public function join(int $gameId): never
    {
        $userId = $this->app->requireLogin();

        $this->app->guardPost('/', fn () => $this->app->lobby->join(
            $gameId,
            $userId,
            $this->app->session->username() ?? 'Spieler',
        ));

        $this->app->redirect("/partie/$gameId/lobby");
    }

    public function waitingRoom(int $gameId): void
    {
        $userId = $this->app->requireLogin();
        $game   = $this->app->games->find($gameId, $this->app->dice);

        if ($game === null) {
            $this->app->notFound('Diese Partie gibt es nicht.');
        }
        if ($game->status() !== GameStatus::Lobby) {
            $this->app->redirect("/partie/$gameId");
        }

        $host = $game->playersInOrder()[0] ?? null;

        $this->app->send($this->app->view->page('warteraum', $game->name(), [
            'game'     => $game,
            'isHost'   => $host !== null && $host->userId === $userId,
            'isMember' => $game->actingPlayerIdFor($userId) !== null,
        ]));
    }

    public function start(int $gameId): never
    {
        $userId = $this->app->requireLogin();

        $this->app->guardPost(
            "/partie/$gameId/lobby",
            fn () => $this->app->lobby->start($gameId, $userId),
        );

        $this->app->session->flash('info', 'Die Partie hat begonnen.');
        $this->app->redirect("/partie/$gameId");
    }
}
