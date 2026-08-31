<?php
/**
 * @var string $base
 * @var Risiko\Http\Csrf $csrf
 * @var Risiko\Domain\Game $game
 * @var bool $isHost
 * @var bool $isMember
 */

$players = $game->playersInOrder();
?>
<section class="karte-blatt schmal-blatt">
    <h1><?= e($game->name()) ?></h1>
    <p class="hinweis">Die Partie startet, sobald der Gastgeber sie eröffnet — ab zwei Spielern.</p>

    <ol class="spielerliste">
        <?php foreach ($players as $p): ?>
            <li>
                <span class="farbfleck" style="--farbe: <?= e($p->color) ?>"></span>
                <?= e($p->displayName) ?>
                <?php if ($p->turnOrder === 0): ?><em>Gastgeber</em><?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ol>

    <div class="knopfreihe">
        <?php if (!$isMember): ?>
            <form method="post" action="<?= e($base) ?>/partie/<?= (int) $game->id() ?>/beitreten">
                <?= $csrf->field() ?>
                <button class="knopf haupt" type="submit">Beitreten</button>
            </form>
        <?php endif; ?>

        <?php if ($isHost): ?>
            <form method="post" action="<?= e($base) ?>/partie/<?= (int) $game->id() ?>/starten">
                <?= $csrf->field() ?>
                <button class="knopf haupt" type="submit"
                    <?= count($players) < 2 ? 'disabled' : '' ?>>Partie starten</button>
            </form>
        <?php endif; ?>

        <a class="knopf" href="<?= e($base) ?>/">Zurück</a>
    </div>
</section>
