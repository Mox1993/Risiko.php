<?php

declare(strict_types=1);

namespace Risiko\Domain;

use InvalidArgumentException;

/**
 * Die Aggregatwurzel: hier steckt die gesamte Spiellogik.
 *
 * Game kennt weder Datenbank noch HTTP. Wenn in dieser Datei jemals das Wort
 * SELECT auftaucht, ist etwas schiefgelaufen. Geaenderte Teile merkt sich das
 * Objekt selbst (changed*), damit das Repository nur schreiben muss, was sich
 * wirklich bewegt hat.
 */
final class Game
{
    /** Starteinheiten nach Spielerzahl. */
    private const START_ARMIES = [2 => 40, 3 => 35, 4 => 30, 5 => 25, 6 => 20];

    /** Klassische Kartenprogression, danach jeweils +5. */
    private const CARD_PROGRESSION = [4, 6, 8, 10, 12, 15];
    private const CARD_STEP        = 5;

    /** Ab dieser Handkartenzahl ist der Tausch Pflicht. */
    public const HAND_LIMIT = 5;

    public const CARDS_PER_SET = 3;

    /** Zusatzeinheiten, wenn eine getauschte Karte auf eigenem Gebiet liegt. */
    private const TERRITORY_CARD_BONUS = 2;

    /** @var array<int,true> Gebiets-IDs, die in diesem Request geaendert wurden */
    private array $dirtyTerritories = [];

    /** @var array<int,true> */
    private array $dirtyPlayers = [];

    /** @var array<int,true> */
    private array $dirtyCards = [];

    /** @var list<array{action:string,player_id:?int,payload:?array}> */
    private array $log = [];

    /**
     * @param array<int,Player>         $players     Spieler-ID => Spieler
     * @param array<int,TerritoryState> $territories Gebiets-ID => Zustand
     * @param array<int,Card>           $cards       Kartennummer => Karte
     */
    private function __construct(
        private ?int $id,
        private string $name,
        private WorldMap $map,
        private array $players,
        private array $territories,
        private array $cards,
        private GameStatus $status,
        private Phase $phase,
        private ?int $currentPlayerId,
        private int $round,
        private int $reinforcePool,
        private bool $conqueredThisTurn,
        private int $cardSetsTraded,
        private ?int $pendingFrom,
        private ?int $pendingTo,
        private ?int $pendingMin,
        private ?int $winnerPlayerId,
        private bool $hotseat,
        private Dice $dice,
    ) {
    }

    // ------------------------------------------------------------------
    // Erzeugung
    // ------------------------------------------------------------------

    /**
     * Startaufstellung einer neuen Partie.
     *
     * Prueft die Aufstellungsregeln und verteilt Gebiete und Starteinheiten
     * zufaellig. Die Spieler muessen bereits IDs haben - die vergibt die
     * Datenbank beim Anlegen der Lobby.
     *
     * @param list<Player>       $players
     * @param array<int,int>|null $ownerByTerritory Gebiets-ID => Spieler-ID, nur fuer Tests
     * @param list<int>|null      $deckOrder        Kartennummern in Ziehreihenfolge, nur fuer Tests
     */
    public static function start(
        int $id,
        string $name,
        WorldMap $map,
        array $players,
        Dice $dice,
        bool $hotseat = false,
        ?array $ownerByTerritory = null,
        ?array $deckOrder = null,
    ): self {
        self::validateLineup($players);

        $byId = [];
        foreach ($players as $p) {
            $byId[$p->id] = $p;
        }
        uasort($byId, static fn (Player $a, Player $b) => $a->turnOrder <=> $b->turnOrder);

        // Vorgegebene Aufstellung heisst: der Test will ein reproduzierbares
        // Ergebnis - dann wird auch die Truppenverteilung nicht gewuerfelt.
        $randomly          = $ownerByTerritory === null;
        $ownerByTerritory ??= self::dealTerritories($map->territoryIds(), array_keys($byId));

        $territories = self::placeStartArmies($ownerByTerritory, count($byId), $randomly);
        $cards       = self::buildDeck($map, $deckOrder);

        $first = array_key_first($byId);

        $game = new self(
            id: $id,
            name: $name,
            map: $map,
            players: $byId,
            territories: $territories,
            cards: $cards,
            status: GameStatus::Running,
            phase: Phase::Reinforce,
            currentPlayerId: $first,
            round: 1,
            reinforcePool: 0,
            conqueredThisTurn: false,
            cardSetsTraded: 0,
            pendingFrom: null,
            pendingTo: null,
            pendingMin: null,
            winnerPlayerId: null,
            hotseat: $hotseat,
            dice: $dice,
        );

        $game->reinforcePool = $game->reinforcementsFor($first);

        // Beim Start ist alles neu, also alles schmutzig.
        foreach ($territories as $tid => $_) {
            $game->dirtyTerritories[$tid] = true;
        }
        foreach ($cards as $no => $_) {
            $game->dirtyCards[$no] = true;
        }
        foreach ($byId as $pid => $_) {
            $game->dirtyPlayers[$pid] = true;
        }

        $game->log('start', null, [
            'players'    => count($byId),
            'first'      => $first,
            'reinforce'  => $game->reinforcePool,
        ]);

        return $game;
    }

    /**
     * Bereits gueltiger Zustand aus der Datenbank - ungeprueft uebernommen.
     *
     * Ohne diese Trennung von start() muesste das Repository die
     * Aufstellungsregeln beim Laden kuenstlich umgehen.
     *
     * @param array<int,Player>         $players
     * @param array<int,TerritoryState> $territories
     * @param array<int,Card>           $cards
     */
    public static function fromStorage(
        int $id,
        string $name,
        WorldMap $map,
        array $players,
        array $territories,
        array $cards,
        GameStatus $status,
        Phase $phase,
        ?int $currentPlayerId,
        int $round,
        int $reinforcePool,
        bool $conqueredThisTurn,
        int $cardSetsTraded,
        ?int $pendingFrom,
        ?int $pendingTo,
        ?int $pendingMin,
        ?int $winnerPlayerId,
        bool $hotseat,
        Dice $dice,
    ): self {
        return new self(
            $id, $name, $map, $players, $territories, $cards, $status, $phase,
            $currentPlayerId, $round, $reinforcePool, $conqueredThisTurn,
            $cardSetsTraded, $pendingFrom, $pendingTo, $pendingMin,
            $winnerPlayerId, $hotseat, $dice,
        );
    }

    /** @param list<Player> $players */
    private static function validateLineup(array $players): void
    {
        $count = count($players);
        if ($count < 2 || $count > 6) {
            throw new RuleViolation('Eine Partie braucht 2 bis 6 Spieler.');
        }

        $colors = [];
        $orders = [];
        $ids    = [];
        foreach ($players as $p) {
            if (isset($colors[$p->color])) {
                throw new RuleViolation("Die Farbe {$p->color} ist doppelt vergeben.");
            }
            if (isset($orders[$p->turnOrder])) {
                throw new RuleViolation('Zwei Spieler haben dieselbe Zugreihenfolge.');
            }
            if (isset($ids[$p->id])) {
                throw new RuleViolation('Zwei Spieler haben dieselbe ID.');
            }
            $colors[$p->color]    = true;
            $orders[$p->turnOrder] = true;
            $ids[$p->id]          = true;
        }
    }

    /**
     * Gebiete gleichmaessig und zufaellig verteilen.
     *
     * @param list<int> $territoryIds
     * @param list<int> $playerIds
     * @return array<int,int> Gebiets-ID => Spieler-ID
     */
    private static function dealTerritories(array $territoryIds, array $playerIds): array
    {
        $shuffled = self::shuffleList($territoryIds);
        $owner    = [];
        foreach ($shuffled as $i => $tid) {
            $owner[$tid] = $playerIds[$i % count($playerIds)];
        }
        ksort($owner);

        return $owner;
    }

    /**
     * Jedes Gebiet bekommt eine Einheit, der Rest wird auf die eigenen
     * Gebiete verteilt.
     *
     * @param array<int,int> $ownerByTerritory
     * @return array<int,TerritoryState>
     */
    private static function placeStartArmies(
        array $ownerByTerritory,
        int $playerCount,
        bool $randomly,
    ): array {
        $states = [];
        $byPlayer = [];
        foreach ($ownerByTerritory as $tid => $pid) {
            $states[$tid]     = new TerritoryState($tid, $pid, 1);
            $byPlayer[$pid][] = $tid;
        }
        ksort($states);

        $budget = self::START_ARMIES[$playerCount] ?? 20;
        foreach ($byPlayer as $pid => $owned) {
            $left = $budget - count($owned);
            if ($left <= 0) {
                continue;
            }
            $order = $randomly ? self::shuffleList($owned) : $owned;
            for ($i = 0; $i < $left; $i++) {
                $states[$order[$i % count($order)]]->addArmies(1);
            }
        }

        return $states;
    }

    /**
     * 42 Gebietskarten plus 2 Joker, gemischt.
     *
     * @param list<int>|null $deckOrder
     * @return array<int,Card>
     */
    private static function buildDeck(WorldMap $map, ?array $deckOrder): array
    {
        $cards = [];
        foreach ($map->territories() as $t) {
            $cards[$t->id] = [$t->id, $t->cardSymbol];
        }
        $jokerBase = max(array_keys($cards));
        $cards[$jokerBase + 1] = [null, CardSymbol::Joker];
        $cards[$jokerBase + 2] = [null, CardSymbol::Joker];

        $order = $deckOrder ?? self::shuffleList(array_keys($cards));

        $out = [];
        foreach ($order as $pos => $cardNo) {
            [$territoryId, $symbol] = $cards[$cardNo];
            $out[$cardNo] = new Card(
                $cardNo,
                $territoryId,
                $symbol,
                null,
                CardState::Deck,
                $pos + 1,
            );
        }
        ksort($out);

        return $out;
    }

    /**
     * Fisher-Yates mit random_int - rand() waere hier vorhersagbar.
     *
     * @template T
     * @param list<T> $items
     * @return list<T>
     */
    private static function shuffleList(array $items): array
    {
        $items = array_values($items);
        for ($i = count($items) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$items[$i], $items[$j]] = [$items[$j], $items[$i]];
        }

        return $items;
    }

    // ------------------------------------------------------------------
    // Spielzuege
    // ------------------------------------------------------------------

    public function reinforce(int $playerId, int $territoryId, int $amount): void
    {
        $this->requireTurn($playerId, Phase::Reinforce);

        if ($this->mustTradeCards($playerId)) {
            throw new RuleViolation(
                'Du hast ' . self::HAND_LIMIT . ' oder mehr Karten und musst zuerst einen Satz tauschen.'
            );
        }
        if ($amount < 1) {
            throw new RuleViolation('Setze mindestens eine Einheit.');
        }
        if ($amount > $this->reinforcePool) {
            throw new RuleViolation(
                "Du hast nur noch {$this->reinforcePool} Einheiten zu setzen."
            );
        }

        $state = $this->stateOf($territoryId);
        if (!$state->isOwnedBy($playerId)) {
            throw new RuleViolation('Dieses Gebiet gehört dir nicht.');
        }

        $state->addArmies($amount);
        $this->reinforcePool -= $amount;
        $this->touchTerritory($territoryId);
        $this->log('reinforce', $playerId, [
            'territory' => $territoryId,
            'amount'    => $amount,
            'pool_left' => $this->reinforcePool,
        ]);
    }

    /**
     * Einen Satz aus drei Karten tauschen.
     *
     * @param list<int> $cardNos
     * @return int erhaltene Einheiten
     */
    public function tradeCards(int $playerId, array $cardNos): int
    {
        $this->requireTurn($playerId, Phase::Reinforce);

        $cardNos = array_values(array_unique(array_map('intval', $cardNos)));
        if (count($cardNos) !== self::CARDS_PER_SET) {
            throw new RuleViolation('Ein Satz besteht aus genau drei verschiedenen Karten.');
        }

        $set = [];
        foreach ($cardNos as $no) {
            $card = $this->cards[$no] ?? throw new RuleViolation('Unbekannte Karte.');
            if ($card->ownerPlayerId() !== $playerId || $card->state() !== CardState::Hand) {
                throw new RuleViolation('Diese Karte liegt nicht auf deiner Hand.');
            }
            $set[] = $card;
        }

        if (!self::isValidSet($set)) {
            throw new RuleViolation(
                'Ein Satz braucht drei gleiche oder drei verschiedene Symbole - Joker ersetzen jedes Symbol.'
            );
        }

        $armies = $this->tradeValueFor($this->cardSetsTraded);
        $this->cardSetsTraded++;
        $this->reinforcePool += $armies;

        // Klassische Zusatzregel: liegt eine getauschte Gebietskarte auf
        // eigenem Gebiet, kommen dort zwei Einheiten direkt dazu - einmal
        // pro Tausch.
        $bonusTerritory = null;
        foreach ($set as $card) {
            $tid = $card->territoryId;
            if ($tid !== null && $this->stateOf($tid)->isOwnedBy($playerId)) {
                $bonusTerritory = $tid;
                break;
            }
        }
        if ($bonusTerritory !== null) {
            $this->stateOf($bonusTerritory)->addArmies(self::TERRITORY_CARD_BONUS);
            $this->touchTerritory($bonusTerritory);
        }

        foreach ($set as $card) {
            $card->discard();
            $this->dirtyCards[$card->cardNo] = true;
        }

        $this->log('trade', $playerId, [
            'cards'  => $cardNos,
            'armies' => $armies,
            'bonus_territory' => $bonusTerritory,
            'set_no' => $this->cardSetsTraded,
        ]);

        return $armies;
    }

    public function attack(int $playerId, int $from, int $to, int $diceCount): CombatResult
    {
        $this->requireTurn($playerId, Phase::Attack);

        $src = $this->stateOf($from);
        $dst = $this->stateOf($to);

        if (!$src->isOwnedBy($playerId)) {
            throw new RuleViolation('Das Ausgangsgebiet gehört dir nicht.');
        }
        if ($dst->isOwnedBy($playerId)) {
            throw new RuleViolation('Du kannst kein eigenes Gebiet angreifen.');
        }
        if (!$this->map->areNeighbours($from, $to)) {
            throw new RuleViolation('Zu diesem Gebiet besteht keine Grenze.');
        }
        if ($diceCount < 1 || $diceCount > Combat::MAX_ATTACK_DICE) {
            throw new RuleViolation('Der Angreifer würfelt mit einem bis drei Würfeln.');
        }
        if ($src->armies() <= $diceCount) {
            throw new RuleViolation(sprintf(
                'Für %d Würfel brauchst du mindestens %d Einheiten im Ausgangsgebiet.',
                $diceCount,
                $diceCount + 1,
            ));
        }

        $defenderId = $dst->ownerPlayerId();
        $defDice    = min(Combat::MAX_DEFENCE_DICE, $dst->armies());

        $result = (new Combat($this->dice))->resolve($diceCount, $defDice);

        $src->removeArmies($result->attackerLosses);
        $dst->removeArmies($result->defenderLosses);
        $this->touchTerritory($from);
        $this->touchTerritory($to);

        $conquered = $dst->armies() === 0;
        if ($conquered) {
            // Bei einer Eroberung verliert der Angreifer in diesem Austausch
            // nie Einheiten, das min() ist reine Vorsicht.
            $moved = min($diceCount, $src->armies() - 1);
            $src->removeArmies($moved);
            $dst->transferTo($playerId, $moved);

            $this->pendingFrom       = $from;
            $this->pendingTo         = $to;
            $this->pendingMin        = $moved;
            $this->conqueredThisTurn = true;
        }

        $result = $result->inBattle($from, $to, $conquered);
        $this->log('attack', $playerId, $result->toArray());

        if ($conquered && $defenderId !== null && $this->territoryCountOf($defenderId) === 0) {
            $this->eliminate($defenderId, $playerId);
        }

        $this->checkVictory();

        return $result;
    }

    /**
     * Nach einer Eroberung zusaetzliche Einheiten nachruecken lassen.
     *
     * Die Wuerfelzahl ist bereits umgezogen (Regel: mindestens so viele
     * Einheiten wie gewuerfelt wurde), hier kommt freiwillig etwas dazu.
     */
    public function occupy(int $playerId, int $extra): void
    {
        $this->requireRunning();
        $this->requireCurrentPlayer($playerId);

        if ($this->pendingFrom === null || $this->pendingTo === null) {
            throw new RuleViolation('Es steht gerade keine Eroberung offen.');
        }

        $src = $this->stateOf($this->pendingFrom);
        $dst = $this->stateOf($this->pendingTo);

        if ($extra < 0) {
            throw new RuleViolation('Es können keine negativen Einheiten nachrücken.');
        }
        if ($extra > $src->armies() - 1) {
            throw new RuleViolation('Im Ausgangsgebiet muss mindestens eine Einheit bleiben.');
        }

        if ($extra > 0) {
            $src->removeArmies($extra);
            $dst->addArmies($extra);
            $this->touchTerritory($this->pendingFrom);
            $this->touchTerritory($this->pendingTo);
        }

        $this->log('occupy', $playerId, [
            'from'  => $this->pendingFrom,
            'to'    => $this->pendingTo,
            'moved' => ($this->pendingMin ?? 0) + $extra,
        ]);

        $this->clearPending();
    }

    public function fortify(int $playerId, int $from, int $to, int $amount): void
    {
        $this->requireTurn($playerId, Phase::Fortify);

        if ($from === $to) {
            throw new RuleViolation('Ausgangs- und Zielgebiet sind identisch.');
        }

        $src = $this->stateOf($from);
        $dst = $this->stateOf($to);

        if (!$src->isOwnedBy($playerId) || !$dst->isOwnedBy($playerId)) {
            throw new RuleViolation('Verschieben geht nur zwischen eigenen Gebieten.');
        }
        if ($amount < 1) {
            throw new RuleViolation('Verschiebe mindestens eine Einheit.');
        }
        if ($amount > $src->armies() - 1) {
            throw new RuleViolation('Im Ausgangsgebiet muss mindestens eine Einheit bleiben.');
        }

        $ownedByPlayer = fn (int $tid): bool => $this->stateOf($tid)->isOwnedBy($playerId);
        if (!$this->map->connected($from, $to, $ownedByPlayer)) {
            throw new RuleViolation('Das Zielgebiet ist über eigene Gebiete nicht erreichbar.');
        }

        $src->removeArmies($amount);
        $dst->addArmies($amount);
        $this->touchTerritory($from);
        $this->touchTerritory($to);

        $this->log('fortify', $playerId, [
            'from'   => $from,
            'to'     => $to,
            'amount' => $amount,
        ]);

        // Verschieben ist die letzte Handlung eines Zuges.
        $this->endTurn();
    }

    public function endPhase(int $playerId): void
    {
        $this->requireRunning();
        $this->requireCurrentPlayer($playerId);
        $this->requireNoPendingConquest();

        switch ($this->phase) {
            case Phase::Reinforce:
                if ($this->mustTradeCards($playerId)) {
                    throw new RuleViolation(
                        'Du musst zuerst einen Kartensatz tauschen.'
                    );
                }
                if ($this->reinforcePool > 0) {
                    throw new RuleViolation(
                        "Du musst noch {$this->reinforcePool} Einheiten setzen."
                    );
                }
                $this->phase = Phase::Attack;
                $this->log('end_phase', $playerId, ['to' => $this->phase->value]);
                break;

            case Phase::Attack:
                $this->phase = Phase::Fortify;
                $this->log('end_phase', $playerId, ['to' => $this->phase->value]);
                break;

            case Phase::Fortify:
                $this->endTurn();
                break;
        }
    }

    // ------------------------------------------------------------------
    // Zugwechsel, Karten, Ausscheiden, Sieg
    // ------------------------------------------------------------------

    private function endTurn(): void
    {
        $playerId = $this->currentPlayerId;

        if ($this->conqueredThisTurn && $playerId !== null) {
            $card = $this->drawCard($playerId);
            if ($card !== null) {
                $this->log('draw_card', $playerId, ['card' => $card->cardNo]);
            }
        }
        $this->conqueredThisTurn = false;
        $this->clearPending();

        if ($this->status === GameStatus::Finished) {
            return;
        }

        $next = $this->nextPlayerId();
        if ($next === null) {
            return;
        }

        $this->currentPlayerId = $next;
        $this->phase           = Phase::Reinforce;
        $this->reinforcePool   = $this->reinforcementsFor($next);

        $this->log('end_turn', $playerId, [
            'next'      => $next,
            'round'     => $this->round,
            'reinforce' => $this->reinforcePool,
        ]);
    }

    /** Naechster nicht ausgeschiedener Spieler; erhoeht bei Rundenwechsel round. */
    private function nextPlayerId(): ?int
    {
        $order = array_values($this->players);
        usort($order, static fn (Player $a, Player $b) => $a->turnOrder <=> $b->turnOrder);

        $count = count($order);
        $index = null;
        foreach ($order as $i => $p) {
            if ($p->id === $this->currentPlayerId) {
                $index = $i;
                break;
            }
        }
        if ($index === null) {
            return $order[0]->id ?? null;
        }

        for ($step = 1; $step <= $count; $step++) {
            $candidate = $order[($index + $step) % $count];
            if ($candidate->isEliminated()) {
                continue;
            }
            if ($index + $step >= $count) {
                $this->round++;
            }

            return $candidate->id;
        }

        return null;
    }

    private function drawCard(int $playerId): ?Card
    {
        $card = $this->topOfDeck();
        if ($card === null) {
            $this->reshuffleDiscard();
            $card = $this->topOfDeck();
        }
        if ($card === null) {
            return null;   // alle 44 Karten liegen auf Haenden
        }

        $card->giveTo($playerId);
        $this->dirtyCards[$card->cardNo] = true;

        return $card;
    }

    private function topOfDeck(): ?Card
    {
        $best = null;
        foreach ($this->cards as $card) {
            if ($card->state() !== CardState::Deck) {
                continue;
            }
            if ($best === null || $card->deckPos() < $best->deckPos()) {
                $best = $card;
            }
        }

        return $best;
    }

    private function reshuffleDiscard(): void
    {
        $discarded = [];
        foreach ($this->cards as $card) {
            if ($card->state() === CardState::Discard) {
                $discarded[] = $card->cardNo;
            }
        }
        if ($discarded === []) {
            return;
        }

        foreach (self::shuffleList($discarded) as $pos => $no) {
            $this->cards[$no]->returnToDeck($pos + 1);
            $this->dirtyCards[$no] = true;
        }
        $this->log('reshuffle', null, ['cards' => count($discarded)]);
    }

    private function eliminate(int $playerId, int $byPlayerId): void
    {
        $player = $this->players[$playerId] ?? null;
        if ($player === null || $player->isEliminated()) {
            return;
        }

        $player->eliminate();
        $this->dirtyPlayers[$playerId] = true;

        $taken = [];
        foreach ($this->cards as $card) {
            if ($card->ownerPlayerId() === $playerId && $card->state() === CardState::Hand) {
                $card->giveTo($byPlayerId);
                $this->dirtyCards[$card->cardNo] = true;
                $taken[] = $card->cardNo;
            }
        }

        $this->log('eliminate', $byPlayerId, [
            'player' => $playerId,
            'cards_taken' => $taken,
        ]);
    }

    private function checkVictory(): void
    {
        $alive = [];
        foreach ($this->players as $player) {
            if ($this->territoryCountOf($player->id) > 0) {
                $alive[] = $player->id;
            }
        }

        if (count($alive) > 1) {
            return;
        }

        $this->status          = GameStatus::Finished;
        $this->winnerPlayerId  = $alive[0] ?? null;
        $this->clearPending();
        $this->log('victory', $this->winnerPlayerId, ['round' => $this->round]);
    }

    // ------------------------------------------------------------------
    // Abfragen
    // ------------------------------------------------------------------

    public function reinforcementsFor(int $playerId): int
    {
        $base = max(3, intdiv($this->territoryCountOf($playerId), 3));

        return $base + $this->continentBonusFor($playerId);
    }

    public function continentBonusFor(int $playerId): int
    {
        $bonus = 0;
        foreach ($this->continentsOf($playerId) as $continent) {
            $bonus += $continent->bonus;
        }

        return $bonus;
    }

    /** @return list<Continent> vollstaendig besessene Kontinente */
    public function continentsOf(int $playerId): array
    {
        $out = [];
        foreach ($this->map->continents() as $continent) {
            $complete = true;
            foreach ($continent->territoryIds as $tid) {
                if (!$this->stateOf($tid)->isOwnedBy($playerId)) {
                    $complete = false;
                    break;
                }
            }
            if ($complete) {
                $out[] = $continent;
            }
        }

        return $out;
    }

    public function territoryCountOf(int $playerId): int
    {
        $n = 0;
        foreach ($this->territories as $state) {
            if ($state->isOwnedBy($playerId)) {
                $n++;
            }
        }

        return $n;
    }

    public function armyCountOf(int $playerId): int
    {
        $n = 0;
        foreach ($this->territories as $state) {
            if ($state->isOwnedBy($playerId)) {
                $n += $state->armies();
            }
        }

        return $n;
    }

    /** @return list<int> */
    public function territoryIdsOf(int $playerId): array
    {
        $out = [];
        foreach ($this->territories as $tid => $state) {
            if ($state->isOwnedBy($playerId)) {
                $out[] = $tid;
            }
        }

        return $out;
    }

    /** @return list<Card> Handkarten, nach Symbol und Nummer sortiert */
    public function handOf(int $playerId): array
    {
        $out = [];
        foreach ($this->cards as $card) {
            if ($card->ownerPlayerId() === $playerId && $card->state() === CardState::Hand) {
                $out[] = $card;
            }
        }
        usort($out, static fn (Card $a, Card $b) => [$a->symbol->value, $a->cardNo]
            <=> [$b->symbol->value, $b->cardNo]);

        return $out;
    }

    public function mustTradeCards(int $playerId): bool
    {
        return count($this->handOf($playerId)) >= self::HAND_LIMIT;
    }

    /** Wert des naechsten Tauschs. */
    public function nextTradeValue(): int
    {
        return $this->tradeValueFor($this->cardSetsTraded);
    }

    private function tradeValueFor(int $setsAlreadyTraded): int
    {
        $last = count(self::CARD_PROGRESSION) - 1;
        if ($setsAlreadyTraded <= $last) {
            return self::CARD_PROGRESSION[$setsAlreadyTraded];
        }

        return self::CARD_PROGRESSION[$last]
            + self::CARD_STEP * ($setsAlreadyTraded - $last);
    }

    /** @param list<Card> $set */
    public static function isValidSet(array $set): bool
    {
        if (count($set) !== self::CARDS_PER_SET) {
            return false;
        }

        $symbols = [];
        $jokers  = 0;
        foreach ($set as $card) {
            if ($card->isJoker()) {
                $jokers++;
            } else {
                $symbols[] = $card->symbol->value;
            }
        }

        if ($jokers > 0) {
            return true;                       // Joker ersetzt jedes Symbol
        }

        $distinct = count(array_unique($symbols));

        return $distinct === 1 || $distinct === self::CARDS_PER_SET;
    }

    /**
     * Angreifbare Nachbarn eines eigenen Gebiets.
     *
     * @return list<int>
     */
    public function attackTargets(int $playerId, int $from): array
    {
        if (!$this->stateOf($from)->isOwnedBy($playerId) || $this->stateOf($from)->armies() < 2) {
            return [];
        }

        $out = [];
        foreach ($this->map->neighbours($from) as $tid) {
            if (!$this->stateOf($tid)->isOwnedBy($playerId)) {
                $out[] = $tid;
            }
        }

        return $out;
    }

    /**
     * Ueber eigene Gebiete erreichbare Ziele fuers Verschieben.
     *
     * @return list<int>
     */
    public function fortifyTargets(int $playerId, int $from): array
    {
        if (!$this->stateOf($from)->isOwnedBy($playerId) || $this->stateOf($from)->armies() < 2) {
            return [];
        }

        return $this->map->reachable(
            $from,
            fn (int $tid): bool => $this->stateOf($tid)->isOwnedBy($playerId),
        );
    }

    /**
     * Als welcher Spieler darf dieser Benutzer handeln?
     *
     * Im Hotseat sitzen alle vor demselben Rechner - dann steuert der
     * Eigentuemer der Partie den jeweils aktiven Spieler. Sonst gilt strikt:
     * ein Benutzer, ein Sitzplatz.
     */
    public function actingPlayerIdFor(?int $userId): ?int
    {
        if ($userId === null) {
            return null;
        }

        if ($this->hotseat) {
            foreach ($this->players as $player) {
                if ($player->userId === $userId) {
                    return $this->currentPlayerId;
                }
            }

            return null;
        }

        foreach ($this->players as $player) {
            if ($player->userId === $userId) {
                return $player->id;
            }
        }

        return null;
    }

    public function isFinished(): bool
    {
        return $this->status === GameStatus::Finished;
    }

    public function hasPendingConquest(): bool
    {
        return $this->pendingFrom !== null && $this->pendingTo !== null;
    }

    // ------------------------------------------------------------------
    // Einfache Zugriffe
    // ------------------------------------------------------------------

    public function id(): ?int { return $this->id; }
    public function name(): string { return $this->name; }
    public function map(): WorldMap { return $this->map; }
    public function status(): GameStatus { return $this->status; }
    public function phase(): Phase { return $this->phase; }
    public function currentPlayerId(): ?int { return $this->currentPlayerId; }
    public function round(): int { return $this->round; }
    public function reinforcePool(): int { return $this->reinforcePool; }
    public function conqueredThisTurn(): bool { return $this->conqueredThisTurn; }
    public function cardSetsTraded(): int { return $this->cardSetsTraded; }
    public function pendingFrom(): ?int { return $this->pendingFrom; }
    public function pendingTo(): ?int { return $this->pendingTo; }
    public function pendingMin(): ?int { return $this->pendingMin; }
    public function winnerPlayerId(): ?int { return $this->winnerPlayerId; }
    public function isHotseat(): bool { return $this->hotseat; }

    /** @return array<int,Player> */
    public function players(): array
    {
        return $this->players;
    }

    /** @return list<Player> nach Zugreihenfolge */
    public function playersInOrder(): array
    {
        $order = array_values($this->players);
        usort($order, static fn (Player $a, Player $b) => $a->turnOrder <=> $b->turnOrder);

        return $order;
    }

    public function player(int $id): ?Player
    {
        return $this->players[$id] ?? null;
    }

    public function currentPlayer(): ?Player
    {
        return $this->currentPlayerId === null ? null : $this->player($this->currentPlayerId);
    }

    /** @return array<int,TerritoryState> */
    public function territories(): array
    {
        return $this->territories;
    }

    public function stateOf(int $territoryId): TerritoryState
    {
        return $this->territories[$territoryId]
            ?? throw new RuleViolation("Unbekanntes Gebiet: $territoryId");
    }

    /** @return array<int,Card> */
    public function cards(): array
    {
        return $this->cards;
    }

    public function deckCount(): int
    {
        return count(array_filter(
            $this->cards,
            static fn (Card $c) => $c->state() === CardState::Deck,
        ));
    }

    // ------------------------------------------------------------------
    // Was hat sich geaendert?
    // ------------------------------------------------------------------

    /** @return list<int> */
    public function changedTerritories(): array
    {
        return array_keys($this->dirtyTerritories);
    }

    /** @return list<int> */
    public function changedPlayers(): array
    {
        return array_keys($this->dirtyPlayers);
    }

    /** @return list<int> */
    public function changedCards(): array
    {
        return array_keys($this->dirtyCards);
    }

    /** @return list<array{action:string,player_id:?int,payload:?array}> */
    public function logEntries(): array
    {
        return $this->log;
    }

    public function clearChanges(): void
    {
        $this->dirtyTerritories = [];
        $this->dirtyPlayers     = [];
        $this->dirtyCards       = [];
        $this->log              = [];
    }

    // ------------------------------------------------------------------
    // Innere Helfer
    // ------------------------------------------------------------------

    private function touchTerritory(int $territoryId): void
    {
        $this->dirtyTerritories[$territoryId] = true;
    }

    private function log(string $action, ?int $playerId, ?array $payload = null): void
    {
        $this->log[] = [
            'action'    => $action,
            'player_id' => $playerId,
            'payload'   => $payload,
        ];
    }

    private function clearPending(): void
    {
        $this->pendingFrom = null;
        $this->pendingTo   = null;
        $this->pendingMin  = null;
    }

    private function requireTurn(int $playerId, Phase $phase): void
    {
        $this->requireRunning();
        $this->requireCurrentPlayer($playerId);
        $this->requireNoPendingConquest();
        $this->requirePhase($phase);
    }

    private function requireRunning(): void
    {
        if ($this->status !== GameStatus::Running) {
            throw new RuleViolation('Diese Partie läuft nicht (mehr).');
        }
    }

    private function requireCurrentPlayer(int $playerId): void
    {
        if ($playerId !== $this->currentPlayerId) {
            throw new RuleViolation('Du bist nicht am Zug.');
        }
    }

    private function requirePhase(Phase $phase): void
    {
        if ($this->phase !== $phase) {
            throw new RuleViolation(sprintf(
                'Das geht nur in der Phase "%s", gerade läuft "%s".',
                $phase->label(),
                $this->phase->label(),
            ));
        }
    }

    private function requireNoPendingConquest(): void
    {
        if ($this->hasPendingConquest()) {
            throw new RuleViolation(
                'Entscheide zuerst, wie viele Einheiten in das eroberte Gebiet nachrücken.'
            );
        }
    }
}
