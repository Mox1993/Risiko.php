<?php
/**
 * @var string $base
 * @var Risiko\Http\Csrf $csrf
 * @var list<array<string,mixed>> $mine
 * @var list<array<string,mixed>> $open
 * @var string $username
 */

use Risiko\Domain\GameStatus;
use Risiko\Domain\Phase;
?>
<div class="lobby">

    <section class="karte-blatt">
        <h1>Neue Partie</h1>

        <form method="post" action="<?= e($base) ?>/partie/neu" class="formular" id="neue-partie">
            <?= $csrf->field() ?>

            <label for="name">Name der Partie</label>
            <input id="name" name="name" type="text" required maxlength="60"
                   value="Partie von <?= e($username) ?>">

            <fieldset class="modus">
                <legend>Wie wird gespielt?</legend>

                <label class="wahl">
                    <input type="radio" name="modus" value="offen" checked>
                    <span>
                        <strong>Offene Partie</strong>
                        Mitspieler treten über die Lobby bei und ziehen, wann sie wollen.
                    </span>
                </label>

                <label class="wahl">
                    <input type="radio" name="modus" value="hotseat">
                    <span>
                        <strong>Hotseat</strong>
                        Alle sitzen an diesem Rechner und geben reihum weiter.
                    </span>
                </label>
            </fieldset>

            <div class="nur-offen">
                <label for="plaetze">Plätze</label>
                <select id="plaetze" name="plaetze">
                    <?php for ($i = 2; $i <= 6; $i++): ?>
                        <option value="<?= $i ?>" <?= $i === 3 ? 'selected' : '' ?>><?= $i ?> Spieler</option>
                    <?php endfor; ?>
                </select>
            </div>

            <div class="nur-hotseat" hidden>
                <p class="hinweis">Namen der Mitspieler — leere Felder bleiben unbesetzt.</p>
                <?php for ($i = 0; $i < 6; $i++): ?>
                    <input name="sitz[]" type="text" maxlength="32"
                           placeholder="Spieler <?= $i + 1 ?>"
                           value="<?= $i === 0 ? e($username) : '' ?>">
                <?php endfor; ?>
            </div>

            <button class="knopf haupt" type="submit">Partie eröffnen</button>
        </form>
    </section>

    <section class="karte-blatt">
        <h1>Deine Partien</h1>

        <?php if ($mine === []): ?>
            <p class="hinweis">Noch keine Partie. Eröffne links eine oder tritt einer bei.</p>
        <?php else: ?>
            <ul class="partienliste">
                <?php foreach ($mine as $g): ?>
                    <?php
                    $status  = GameStatus::from((string) $g['status']);
                    $amZug   = $status === GameStatus::Running
                        && (int) $g['current_player_id'] === (int) $g['my_player_id'];
                    $ziel    = $status === GameStatus::Lobby
                        ? "/partie/{$g['id']}/lobby"
                        : "/partie/{$g['id']}";
                    ?>
                    <li class="<?= $amZug ? 'dran' : '' ?>">
                        <a href="<?= e($base . $ziel) ?>">
                            <span class="partie-name"><?= e($g['name']) ?></span>
                            <span class="partie-zeile">
                                <?= e($status->label()) ?>
                                <?php if ($status === GameStatus::Running): ?>
                                    · Runde <?= (int) $g['round_no'] ?>
                                    · <?= e(Phase::from((string) $g['phase'])->label()) ?>
                                <?php endif; ?>
                                · <?= (int) $g['player_count'] ?> Spieler
                                <?php if ((int) $g['hotseat'] === 1): ?> · Hotseat<?php endif; ?>
                            </span>
                        </a>
                        <?php if ($amZug): ?><span class="marke-dran">Du bist dran</span><?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <section class="karte-blatt">
        <h1>Offene Partien</h1>

        <?php if ($open === []): ?>
            <p class="hinweis">Gerade wartet niemand auf Mitspieler.</p>
        <?php else: ?>
            <ul class="partienliste">
                <?php foreach ($open as $g): ?>
                    <li>
                        <a href="<?= e($base) ?>/partie/<?= (int) $g['id'] ?>/lobby">
                            <span class="partie-name"><?= e($g['name']) ?></span>
                            <span class="partie-zeile">
                                <?= (int) $g['player_count'] ?> von <?= (int) $g['max_players'] ?> Plätzen belegt
                                · eröffnet <?= e(moment((string) $g['created_at'])) ?>
                            </span>
                        </a>
                        <form method="post" action="<?= e($base) ?>/partie/<?= (int) $g['id'] ?>/beitreten">
                            <?= $csrf->field() ?>
                            <button class="knopf schmal" type="submit">Beitreten</button>
                        </form>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

</div>

<script>
    // Reine Anzeigelogik: je nach Spielart die passenden Felder zeigen.
    (function () {
        var form = document.getElementById('neue-partie');
        if (!form) { return; }
        var offen   = form.querySelector('.nur-offen');
        var hotseat = form.querySelector('.nur-hotseat');

        function sync() {
            var istHotseat = form.querySelector('input[name="modus"]:checked').value === 'hotseat';
            offen.hidden   = istHotseat;
            hotseat.hidden = !istHotseat;
        }

        form.querySelectorAll('input[name="modus"]').forEach(function (el) {
            el.addEventListener('change', sync);
        });
        sync();
    })();
</script>
