<?php

declare(strict_types=1);

namespace Risiko\Persistence;

use Risiko\Domain\Card;
use Risiko\Domain\CardState;
use Risiko\Domain\CardSymbol;
use Risiko\Domain\Dice;
use Risiko\Domain\Game;
use Risiko\Domain\GameStatus;
use Risiko\Domain\Phase;
use Risiko\Domain\Player;
use Risiko\Domain\RuleViolation;
use Risiko\Domain\TerritoryState;

/**
 * Das Aggregat "Partie": Spiel, Spieler, Gebietsbesitz und Karten.
 *
 * Bewusst kein PlayerRepository und kein TerritoryRepository - ein Spieler
 * ohne Partie existiert nicht, ein Gebietsbesitz ohne Partie auch nicht. Sie
 * werden immer zusammen geladen und zusammen gespeichert.
 *
 * Geladen wird mit vier Abfragen, nie mit einer Schleife voller Einzelabfragen.
 * Gespeichert wird nur, was in changed*() steht: ein Angriff schreibt zwei
 * Zeilen, nicht 42.
 */
final class GameRepository
{
    /** Spielerfarben in der Reihenfolge der Sitzplaetze. */
    public const COLORS = ['#c0392b', '#2980b9', '#27ae60', '#8e44ad', '#f39c12', '#16a085'];

    public function __construct(
        private Db $db,
        private MapRepository $maps,
    ) {
    }

    // ------------------------------------------------------------------
    // Laden
    // ------------------------------------------------------------------

    public function find(int $id, Dice $dice): ?Game
    {
        return $this->load($id, $dice, false);
    }

    /**
     * Wie find(), sperrt aber die Zeile bis zum Ende der Transaktion.
     *
     * Ohne FOR UPDATE fuehrt ein hastiger Doppelklick auf "Angriff" zwei
     * Angriffe aus, die beide vom selben Ausgangszustand ausgehen. Bei
     * rundenbasierten Spielen ist das der haeufigste Fehler ueberhaupt.
     */
    public function findForUpdate(int $id, Dice $dice): Game
    {
        return $this->load($id, $dice, true)
            ?? throw new RuleViolation('Diese Partie gibt es nicht.');
    }

    private function load(int $id, Dice $dice, bool $forUpdate): ?Game
    {
        $sql = 'SELECT id, name, status, phase, current_player_id, round_no,
                       reinforce_pool, conquered_this_turn, card_sets_traded,
                       pending_from, pending_to, pending_min, hotseat, winner_player_id
                  FROM games
                 WHERE id = ?' . ($forUpdate ? ' FOR UPDATE' : '');

        $row = $this->db->selectOne($sql, 'i', [$id]);
        if ($row === null) {
            return null;
        }

        $players     = $this->loadPlayers($id);
        $territories = $this->loadTerritories($id);
        $cards       = $this->loadCards($id);

        return Game::fromStorage(
            id: (int) $row['id'],
            name: (string) $row['name'],
            map: $this->maps->load(),
            players: $players,
            territories: $territories,
            cards: $cards,
            status: GameStatus::from((string) $row['status']),
            phase: Phase::from((string) $row['phase']),
            currentPlayerId: self::nullableInt($row['current_player_id']),
            round: (int) $row['round_no'],
            reinforcePool: (int) $row['reinforce_pool'],
            conqueredThisTurn: (bool) $row['conquered_this_turn'],
            cardSetsTraded: (int) $row['card_sets_traded'],
            pendingFrom: self::nullableInt($row['pending_from']),
            pendingTo: self::nullableInt($row['pending_to']),
            pendingMin: self::nullableInt($row['pending_min']),
            winnerPlayerId: self::nullableInt($row['winner_player_id']),
            hotseat: (bool) $row['hotseat'],
            dice: $dice,
        );
    }

    /** @return array<int,Player> */
    private function loadPlayers(int $gameId): array
    {
        $out = [];
        foreach ($this->db->select(
            'SELECT id, user_id, display_name, color, turn_order, is_eliminated, is_ai
               FROM players
              WHERE game_id = ?
              ORDER BY turn_order',
            'i',
            [$gameId],
        ) as $row) {
            $id = (int) $row['id'];
            $out[$id] = new Player(
                $id,
                self::nullableInt($row['user_id']),
                (string) $row['display_name'],
                (string) $row['color'],
                (int) $row['turn_order'],
                (bool) $row['is_eliminated'],
                (bool) $row['is_ai'],
            );
        }

        return $out;
    }

    /** @return array<int,TerritoryState> */
    private function loadTerritories(int $gameId): array
    {
        $out = [];
        foreach ($this->db->select(
            'SELECT territory_id, owner_player_id, armies
               FROM game_territories
              WHERE game_id = ?
              ORDER BY territory_id',
            'i',
            [$gameId],
        ) as $row) {
            $tid       = (int) $row['territory_id'];
            $out[$tid] = new TerritoryState(
                $tid,
                self::nullableInt($row['owner_player_id']),
                (int) $row['armies'],
            );
        }

        return $out;
    }

    /** @return array<int,Card> */
    private function loadCards(int $gameId): array
    {
        $out = [];
        foreach ($this->db->select(
            'SELECT c.card_no, c.territory_id, c.owner_player_id, c.state, c.deck_pos,
                    t.card_symbol
               FROM game_cards c
               LEFT JOIN territories t ON t.id = c.territory_id
              WHERE c.game_id = ?
              ORDER BY c.card_no',
            'i',
            [$gameId],
        ) as $row) {
            $no          = (int) $row['card_no'];
            $territoryId = self::nullableInt($row['territory_id']);
            $out[$no]    = new Card(
                $no,
                $territoryId,
                $territoryId === null
                    ? CardSymbol::Joker
                    : CardSymbol::from((string) $row['card_symbol']),
                self::nullableInt($row['owner_player_id']),
                CardState::from((string) $row['state']),
                (int) $row['deck_pos'],
            );
        }

        return $out;
    }

    // ------------------------------------------------------------------
    // Speichern
    // ------------------------------------------------------------------

    public function save(Game $game): void
    {
        $id = $game->id();
        if ($id === null) {
            throw new RuleViolation('Diese Partie wurde nie angelegt.');
        }

        $this->db->execute(
            'UPDATE games
                SET status = ?, phase = ?, current_player_id = ?, round_no = ?,
                    reinforce_pool = ?, conquered_this_turn = ?, card_sets_traded = ?,
                    pending_from = ?, pending_to = ?, pending_min = ?, winner_player_id = ?
              WHERE id = ?',
            'ssiiiiiiiiii',
            [
                $game->status()->value,
                $game->phase()->value,
                $game->currentPlayerId(),
                $game->round(),
                $game->reinforcePool(),
                $game->conqueredThisTurn() ? 1 : 0,
                $game->cardSetsTraded(),
                $game->pendingFrom(),
                $game->pendingTo(),
                $game->pendingMin(),
                $game->winnerPlayerId(),
                $id,
            ],
        );

        $this->saveTerritories($game, $id);
        $this->savePlayers($game);
        $this->saveCards($game, $id);
        $this->saveLog($game, $id);

        $game->clearChanges();
    }

    private function saveTerritories(Game $game, int $gameId): void
    {
        $changed = $game->changedTerritories();
        if ($changed === []) {
            return;
        }

        $values = [];
        $types  = '';
        $params = [];
        foreach ($changed as $tid) {
            $state    = $game->stateOf($tid);
            $values[] = '(?, ?, ?, ?)';
            $types   .= 'iiii';
            array_push($params, $gameId, $tid, $state->ownerPlayerId(), $state->armies());
        }

        $this->db->execute(
            'INSERT INTO game_territories (game_id, territory_id, owner_player_id, armies)
             VALUES ' . implode(', ', $values) . '
             ON DUPLICATE KEY UPDATE
                owner_player_id = VALUES(owner_player_id),
                armies          = VALUES(armies)',
            $types,
            $params,
        );
    }

    private function savePlayers(Game $game): void
    {
        foreach ($game->changedPlayers() as $pid) {
            $player = $game->player($pid);
            if ($player === null) {
                continue;
            }
            $this->db->execute(
                'UPDATE players SET is_eliminated = ? WHERE id = ?',
                'ii',
                [$player->isEliminated() ? 1 : 0, $pid],
            );
        }
    }

    private function saveCards(Game $game, int $gameId): void
    {
        $changed = $game->changedCards();
        if ($changed === []) {
            return;
        }

        $cards  = $game->cards();
        $values = [];
        $types  = '';
        $params = [];
        foreach ($changed as $no) {
            $card     = $cards[$no];
            $values[] = '(?, ?, ?, ?, ?, ?)';
            $types   .= 'iiiisi';
            array_push(
                $params,
                $gameId,
                $card->cardNo,
                $card->territoryId,
                $card->ownerPlayerId(),
                $card->state()->value,
                $card->deckPos(),
            );
        }

        $this->db->execute(
            'INSERT INTO game_cards
                (game_id, card_no, territory_id, owner_player_id, state, deck_pos)
             VALUES ' . implode(', ', $values) . '
             ON DUPLICATE KEY UPDATE
                owner_player_id = VALUES(owner_player_id),
                state           = VALUES(state),
                deck_pos        = VALUES(deck_pos)',
            $types,
            $params,
        );
    }

    private function saveLog(Game $game, int $gameId): void
    {
        $entries = $game->logEntries();
        if ($entries === []) {
            return;
        }

        $values = [];
        $types  = '';
        $params = [];
        foreach ($entries as $entry) {
            $values[] = '(?, ?, ?, ?, ?)';
            $types   .= 'iiiss';
            array_push(
                $params,
                $gameId,
                $game->round(),
                $entry['player_id'],
                $entry['action'],
                $entry['payload'] === null
                    ? null
                    : json_encode($entry['payload'], JSON_UNESCAPED_UNICODE),
            );
        }

        $this->db->execute(
            'INSERT INTO game_log (game_id, round_no, player_id, action, payload)
             VALUES ' . implode(', ', $values),
            $types,
            $params,
        );
    }

    // ------------------------------------------------------------------
    // Lobby
    // ------------------------------------------------------------------

    public function createLobby(string $name, int $maxPlayers, bool $hotseat): int
    {
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 60) {
            throw new RuleViolation('Der Name der Partie braucht 1 bis 60 Zeichen.');
        }
        if ($maxPlayers < 2 || $maxPlayers > 6) {
            throw new RuleViolation('Eine Partie hat 2 bis 6 Plätze.');
        }

        $this->db->execute(
            'INSERT INTO games (name, status, max_players, hotseat) VALUES (?, ?, ?, ?)',
            'ssii',
            [$name, GameStatus::Lobby->value, $maxPlayers, $hotseat ? 1 : 0],
        );

        return $this->db->lastInsertId();
    }

    public function addSeat(int $gameId, ?int $userId, string $displayName): int
    {
        $displayName = trim($displayName);
        if ($displayName === '' || mb_strlen($displayName) > 32) {
            throw new RuleViolation('Der Spielername braucht 1 bis 32 Zeichen.');
        }

        $game = $this->db->selectOne(
            'SELECT status, max_players FROM games WHERE id = ? FOR UPDATE',
            'i',
            [$gameId],
        ) ?? throw new RuleViolation('Diese Partie gibt es nicht.');

        if ($game['status'] !== GameStatus::Lobby->value) {
            throw new RuleViolation('Diese Partie hat bereits begonnen.');
        }

        $seats = (int) ($this->db->selectOne(
            'SELECT COUNT(*) AS n FROM players WHERE game_id = ?',
            'i',
            [$gameId],
        )['n'] ?? 0);

        if ($seats >= (int) $game['max_players']) {
            throw new RuleViolation('Diese Partie ist voll.');
        }

        $this->db->execute(
            'INSERT INTO players (game_id, user_id, display_name, color, turn_order)
             VALUES (?, ?, ?, ?, ?)',
            'iissi',
            [$gameId, $userId, $displayName, self::COLORS[$seats], $seats],
        );

        return $this->db->lastInsertId();
    }

    public function join(int $gameId, int $userId, string $displayName): void
    {
        $already = $this->db->selectOne(
            'SELECT id FROM players WHERE game_id = ? AND user_id = ?',
            'ii',
            [$gameId, $userId],
        );
        if ($already !== null) {
            throw new RuleViolation('Du sitzt schon in dieser Partie.');
        }

        $this->addSeat($gameId, $userId, $displayName);
    }

    /**
     * Aus der Lobby wird eine laufende Partie.
     *
     * Erst hier greifen die Aufstellungsregeln - deshalb baut Game::start()
     * den Zustand komplett neu auf, statt ihn wie fromStorage() zu uebernehmen.
     */
    public function startGame(int $gameId, Dice $dice): Game
    {
        $row = $this->db->selectOne(
            'SELECT id, name, status, hotseat FROM games WHERE id = ? FOR UPDATE',
            'i',
            [$gameId],
        ) ?? throw new RuleViolation('Diese Partie gibt es nicht.');

        if ($row['status'] !== GameStatus::Lobby->value) {
            throw new RuleViolation('Diese Partie läuft bereits.');
        }

        $players = array_values($this->loadPlayers($gameId));

        $game = Game::start(
            (int) $row['id'],
            (string) $row['name'],
            $this->maps->load(),
            $players,
            $dice,
            (bool) $row['hotseat'],
        );

        $this->save($game);

        return $game;
    }

    // ------------------------------------------------------------------
    // Uebersichten
    // ------------------------------------------------------------------

    /** @return list<array<string,mixed>> offene Partien, denen man beitreten kann */
    public function findOpenGames(): array
    {
        return $this->db->select(
            "SELECT g.id, g.name, g.max_players, g.hotseat, g.created_at,
                    COUNT(p.id) AS player_count
               FROM games g
               LEFT JOIN players p ON p.game_id = g.id
              WHERE g.status = 'lobby' AND g.hotseat = 0
              GROUP BY g.id, g.name, g.max_players, g.hotseat, g.created_at
              ORDER BY g.created_at DESC
              LIMIT 50"
        );
    }

    /** @return list<array<string,mixed>> Partien, in denen dieser Benutzer sitzt */
    public function findGamesForUser(int $userId): array
    {
        return $this->db->select(
            'SELECT g.id, g.name, g.status, g.phase, g.round_no, g.hotseat,
                    g.current_player_id, g.winner_player_id, g.updated_at,
                    MIN(me.id) AS my_player_id,
                    (SELECT COUNT(*) FROM players p2 WHERE p2.game_id = g.id) AS player_count
               FROM games g
               JOIN players me ON me.game_id = g.id AND me.user_id = ?
              GROUP BY g.id, g.name, g.status, g.phase, g.round_no, g.hotseat,
                       g.current_player_id, g.winner_player_id, g.updated_at
              ORDER BY g.updated_at DESC
              LIMIT 50',
            'i',
            [$userId],
        );
    }

    /** @return list<array<string,mixed>> juengste Ereignisse zuerst */
    public function recentLog(int $gameId, int $limit = 30): array
    {
        $limit = max(1, min(200, $limit));

        return $this->db->select(
            'SELECT l.id, l.round_no, l.action, l.payload, l.created_at,
                    p.display_name, p.color
               FROM game_log l
               LEFT JOIN players p ON p.id = l.player_id
              WHERE l.game_id = ?
              ORDER BY l.id DESC
              LIMIT ' . $limit,
            'i',
            [$gameId],
        );
    }

    /** Zeitstempel der letzten Änderung - Grundlage fuers Polling. */
    public function lastChangeToken(int $gameId): string
    {
        $row = $this->db->selectOne(
            'SELECT g.updated_at,
                    (SELECT MAX(id) FROM game_log WHERE game_id = g.id) AS last_log
               FROM games g WHERE g.id = ?',
            'i',
            [$gameId],
        );

        return ($row['updated_at'] ?? '') . '#' . ($row['last_log'] ?? '0');
    }

    private static function nullableInt(mixed $value): ?int
    {
        return $value === null ? null : (int) $value;
    }
}
