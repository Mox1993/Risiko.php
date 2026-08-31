<?php
/**
 * @var string $base
 * @var Risiko\Http\Csrf $csrf
 * @var Risiko\Domain\Game $game
 * @var array $geo
 * @var int $myPlayerId
 * @var bool $isMyTurn
 * @var list<Risiko\Domain\Card> $hand
 * @var array<int,list<int>> $targets
 * @var list<array<string,mixed>> $log
 * @var string $token
 */

use Risiko\Domain\Phase;

$map     = $game->map();
$gid     = (int) $game->id();
$aktion  = fn (string $pfad): string => e($base . "/partie/$gid" . $pfad);
$farbe   = static function (?int $playerId) use ($game): string {
    $p = $playerId === null ? null : $game->player($playerId);

    return $p?->color ?? '#8a8a8a';
};

$eigene = $game->territoryIdsOf($myPlayerId);

// Obergrenze fuers Zahlenfeld beim Verschieben; das JavaScript zieht sie
// beim Umschalten des Ausgangsgebiets nach, der Server prueft ohnehin erneut.
$maxVerschieben = 1;
foreach ($eigene as $tid) {
    $maxVerschieben = max($maxVerschieben, $game->stateOf($tid)->armies() - 1);
}

// Alles, was das JavaScript wissen muss - berechnet wird serverseitig.
$daten = [
    'phase'     => $game->phase()->value,
    'meinZug'   => $isMyTurn,
    'ziele'     => $targets,
    'eigene'    => $eigene,
    'einheiten' => array_map(
        static fn ($s) => $s->armies(),
        $game->territories(),
    ),
    'namen'     => array_map(static fn ($t) => $t->name, $map->territories()),
    'token'     => $token,
    'statusUrl' => $base . "/partie/$gid/status",
    'seiteUrl'  => $base . "/partie/$gid",
];
?>
<div class="spiel">

    <div class="kartenbereich">
        <svg class="weltkarte" viewBox="0 0 <?= (int) $geo['width'] ?> <?= (int) $geo['height'] ?>"
             role="img" aria-label="Weltkarte">
            <rect class="ozean" width="<?= (int) $geo['width'] ?>" height="<?= (int) $geo['height'] ?>"/>

            <g class="seewege">
                <?php foreach ($geo['sea'] as [$x1, $y1, $x2, $y2]): ?>
                    <line x1="<?= $x1 ?>" y1="<?= $y1 ?>" x2="<?= $x2 ?>" y2="<?= $y2 ?>"/>
                <?php endforeach; ?>
            </g>

            <g class="gebiete">
                <?php foreach ($geo['territories'] as $tid => $t): ?>
                    <?php
                    $state = $game->stateOf((int) $tid);
                    $owner = $state->ownerPlayerId();
                    $mein  = $owner === $myPlayerId;
                    ?>
                    <path class="gebiet<?= $mein ? ' mein' : '' ?>"
                          data-terr="<?= (int) $tid ?>"
                          style="--besitz: <?= e($farbe($owner)) ?>"
                          d="<?= e($t['d']) ?>">
                        <title><?= e($map->territory((int) $tid)->name) ?> — <?= e($game->player($owner)?->displayName ?? 'niemand') ?>,
                            <?= $state->armies() ?> Einheiten</title>
                    </path>
                <?php endforeach; ?>
            </g>

            <g class="beschriftung">
                <?php foreach ($geo['continents'] as $c): ?>
                    <text class="kontinent" x="<?= $c['x'] ?>" y="<?= $c['y'] ?>">
                        <?= e(mb_strtoupper($c['name'])) ?></text>
                <?php endforeach; ?>

                <?php foreach ($geo['territories'] as $tid => $t): ?>
                    <text class="armeen" data-armeen="<?= (int) $tid ?>"
                          x="<?= $t['cx'] ?>" y="<?= $t['cy'] ?>"><?= $game->stateOf((int) $tid)->armies() ?></text>
                <?php endforeach; ?>
            </g>
        </svg>
    </div>

    <aside class="seitenleiste">

        <section class="block zustand">
            <h1><?= e($game->name()) ?></h1>
            <p class="rundenzeile">
                Runde <?= $game->round() ?> ·
                <strong><?= e($game->phase()->label()) ?></strong>
            </p>

            <?php if ($game->isFinished()): ?>
                <p class="sieg">
                    <?= e($game->player($game->winnerPlayerId())?->displayName ?? 'Niemand') ?>
                    hat die Welt geeint.
                </p>
            <?php elseif ($isMyTurn): ?>
                <p class="dranzeile">Du bist am Zug.</p>
            <?php else: ?>
                <p class="wartezeile">
                    <?= e($game->currentPlayer()?->displayName ?? '—') ?> ist am Zug.
                </p>
            <?php endif; ?>
        </section>

        <section class="block">
            <h2>Spieler</h2>
            <ul class="spielerliste">
                <?php foreach ($game->playersInOrder() as $p): ?>
                    <li class="<?= $p->id === $game->currentPlayerId() ? 'aktiv' : '' ?>
                               <?= $p->isEliminated() ? 'raus' : '' ?>">
                        <span class="farbfleck" style="--farbe: <?= e($p->color) ?>"></span>
                        <span class="spielername"><?= e($p->displayName) ?></span>
                        <span class="zahlen">
                            <?= $game->territoryCountOf($p->id) ?> Gebiete ·
                            <?= $game->armyCountOf($p->id) ?> Einheiten
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>

        <?php if ($game->isFinished()): ?>

            <section class="block">
                <a class="knopf haupt" href="<?= e($base) ?>/">Zur Übersicht</a>
            </section>

        <?php elseif (!$isMyTurn): ?>

            <section class="block">
                <p class="hinweis">
                    Sobald <?= e($game->currentPlayer()?->displayName ?? 'der Gegner') ?>
                    fertig ist, geht es hier weiter. Die Seite lädt sich selbst neu.
                </p>
            </section>

        <?php elseif ($game->hasPendingConquest()): ?>

            <?php
            $from = (int) $game->pendingFrom();
            $to   = (int) $game->pendingTo();
            $frei = $game->stateOf($from)->armies() - 1;
            ?>
            <section class="block aktion">
                <h2>Erobert</h2>
                <p><?= e($map->territory($to)->name) ?> gehört dir.
                    <?= (int) $game->pendingMin() ?> Einheiten sind bereits nachgerückt.</p>

                <form method="post" action="<?= $aktion('/nachruecken') ?>" class="formular">
                    <?= $csrf->field() ?>
                    <label for="nachschub">Zusätzlich nachrücken (höchstens <?= $frei ?>)</label>
                    <input id="nachschub" name="anzahl" type="number" value="0"
                           min="0" max="<?= $frei ?>">
                    <button class="knopf haupt" type="submit">Übernehmen</button>
                </form>
            </section>

        <?php elseif ($game->phase() === Phase::Reinforce): ?>

            <?php if (count($hand) >= 3): ?>
                <section class="block aktion">
                    <h2>Karten tauschen</h2>
                    <?php if ($game->mustTradeCards($myPlayerId)): ?>
                        <p class="warnung">Du hast <?= count($hand) ?> Karten — ein Tausch ist Pflicht.</p>
                    <?php endif; ?>
                    <p class="hinweis">Nächster Satz: <?= $game->nextTradeValue() ?> Einheiten.</p>

                    <form method="post" action="<?= $aktion('/tauschen') ?>" class="formular">
                        <?= $csrf->field() ?>
                        <ul class="kartenhand">
                            <?php foreach ($hand as $card): ?>
                                <li>
                                    <label>
                                        <input type="checkbox" name="karte[]" value="<?= $card->cardNo ?>">
                                        <span class="symbol"><?= e($card->symbol->glyph()) ?></span>
                                        <span class="kartentext">
                                            <?= e($card->territoryId === null
                                                ? 'Joker'
                                                : $map->territory($card->territoryId)->name) ?>
                                            <em><?= e($card->label()) ?></em>
                                        </span>
                                    </label>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <button class="knopf" type="submit">Drei Karten tauschen</button>
                    </form>
                </section>
            <?php endif; ?>

            <section class="block aktion">
                <h2>Verstärkung setzen</h2>
                <p class="vorrat">Noch <strong><?= $game->reinforcePool() ?></strong> Einheiten im Vorrat.</p>

                <form method="post" action="<?= $aktion('/verstaerken') ?>" class="formular">
                    <?= $csrf->field() ?>

                    <label for="gebiet">Gebiet</label>
                    <select id="gebiet" name="gebiet" data-rolle="von">
                        <?php foreach ($eigene as $tid): ?>
                            <option value="<?= $tid ?>"><?= e($map->territory($tid)->name) ?>
                                (<?= $game->stateOf($tid)->armies() ?>)</option>
                        <?php endforeach; ?>
                    </select>

                    <label for="anzahl">Einheiten</label>
                    <input id="anzahl" name="anzahl" type="number" value="1"
                           min="1" max="<?= max(1, $game->reinforcePool()) ?>">

                    <button class="knopf haupt" type="submit"
                        <?= $game->reinforcePool() === 0 ? 'disabled' : '' ?>>Setzen</button>
                </form>

                <form method="post" action="<?= $aktion('/phase') ?>">
                    <?= $csrf->field() ?>
                    <button class="knopf" type="submit"
                        <?= $game->reinforcePool() > 0 ? 'disabled' : '' ?>>Weiter zum Angriff</button>
                </form>
            </section>

        <?php elseif ($game->phase() === Phase::Attack): ?>

            <section class="block aktion">
                <h2>Angriff</h2>
                <p class="hinweis">Erst eigenes Gebiet anklicken, dann das Ziel.</p>

                <form method="post" action="<?= $aktion('/angriff') ?>" class="formular">
                    <?= $csrf->field() ?>

                    <label for="von">Von</label>
                    <select id="von" name="von" data-rolle="von">
                        <?php foreach (array_keys($targets) as $tid): ?>
                            <option value="<?= $tid ?>"><?= e($map->territory($tid)->name) ?>
                                (<?= $game->stateOf($tid)->armies() ?>)</option>
                        <?php endforeach; ?>
                    </select>

                    <label for="nach">Nach</label>
                    <select id="nach" name="nach" data-rolle="nach">
                        <?php foreach ($targets as $von => $ziele): ?>
                            <?php foreach ($ziele as $ziel): ?>
                                <option value="<?= $ziel ?>" data-von="<?= $von ?>">
                                    <?= e($map->territory($ziel)->name) ?>
                                    (<?= $game->stateOf($ziel)->armies() ?>)</option>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </select>

                    <label for="wuerfel">Würfel</label>
                    <select id="wuerfel" name="wuerfel" data-rolle="wuerfel">
                        <option value="1">1 Würfel</option>
                        <option value="2">2 Würfel</option>
                        <option value="3" selected>3 Würfel</option>
                    </select>

                    <button class="knopf haupt" type="submit"
                        <?= $targets === [] ? 'disabled' : '' ?>>Angreifen</button>
                </form>

                <form method="post" action="<?= $aktion('/phase') ?>">
                    <?= $csrf->field() ?>
                    <button class="knopf" type="submit">Weiter zum Verschieben</button>
                </form>
            </section>

        <?php else: ?>

            <section class="block aktion">
                <h2>Verschieben</h2>
                <p class="hinweis">Einmal pro Zug, über zusammenhängende eigene Gebiete.</p>

                <form method="post" action="<?= $aktion('/verschieben') ?>" class="formular">
                    <?= $csrf->field() ?>

                    <label for="von">Von</label>
                    <select id="von" name="von" data-rolle="von">
                        <?php foreach (array_keys($targets) as $tid): ?>
                            <option value="<?= $tid ?>"><?= e($map->territory($tid)->name) ?>
                                (<?= $game->stateOf($tid)->armies() ?>)</option>
                        <?php endforeach; ?>
                    </select>

                    <label for="nach">Nach</label>
                    <select id="nach" name="nach" data-rolle="nach">
                        <?php foreach ($targets as $von => $ziele): ?>
                            <?php foreach ($ziele as $ziel): ?>
                                <option value="<?= $ziel ?>" data-von="<?= $von ?>">
                                    <?= e($map->territory($ziel)->name) ?></option>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </select>

                    <label for="anzahl">Einheiten</label>
                    <input id="anzahl" name="anzahl" type="number" value="1" min="1"
                           max="<?= $maxVerschieben ?>">

                    <button class="knopf haupt" type="submit"
                        <?= $targets === [] ? 'disabled' : '' ?>>Verschieben</button>
                </form>

                <form method="post" action="<?= $aktion('/phase') ?>">
                    <?= $csrf->field() ?>
                    <button class="knopf" type="submit">Zug beenden</button>
                </form>
            </section>

        <?php endif; ?>

        <section class="block">
            <h2>Deine Karten<span class="zaehler"><?= count($hand) ?></span></h2>
            <?php if ($hand === []): ?>
                <p class="hinweis">Noch keine. Wer in einem Zug mindestens ein Gebiet erobert,
                    zieht am Zugende eine Karte.</p>
            <?php else: ?>
                <ul class="kartenhand still">
                    <?php foreach ($hand as $card): ?>
                        <li>
                            <span class="symbol"><?= e($card->symbol->glyph()) ?></span>
                            <span class="kartentext">
                                <?= e($card->territoryId === null
                                    ? 'Joker'
                                    : $map->territory($card->territoryId)->name) ?>
                                <em><?= e($card->label()) ?></em>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <section class="block">
            <h2>Kriegsbericht</h2>
            <ol class="bericht">
                <?php foreach ($log as $entry): ?>
                    <li>
                        <span class="farbfleck" style="--farbe: <?= e($entry['color'] ?? '#8a8a8a') ?>"></span>
                        <?= e(bericht_zeile($entry, $map)) ?>
                    </li>
                <?php endforeach; ?>
            </ol>
        </section>

    </aside>
</div>

<script id="spieldaten" type="application/json"><?= json_encode($daten, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<script src="<?= e($base) ?>/assets/js/karte.js" defer></script>
