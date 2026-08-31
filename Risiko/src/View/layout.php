<?php
/**
 * @var string $title
 * @var string $content
 * @var string $base
 * @var Risiko\Http\Session $session
 * @var Risiko\Http\Csrf    $csrf
 * @var list<array{type:string,text:string}> $flashes
 *
 * Sitzung, Token und Meldungen steuert View::page() bei - sie sind auf jeder
 * Seite vorhanden und muessen hier nicht abgesichert werden.
 */
?>
<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> · Weltenteiler</title>
    <link rel="stylesheet" href="<?= e($base) ?>/assets/css/spiel.css">
</head>
<body>
<header class="kopf">
    <a class="marke" href="<?= e($base) ?>/">Weltenteiler</a>
    <?php if ($session->isLoggedIn()): ?>
        <div class="kopf-rechts">
            <span class="wer"><?= e($session->username()) ?></span>
            <form method="post" action="<?= e($base) ?>/abmelden">
                <?= $csrf->field() ?>
                <button class="knopf schmal" type="submit">Abmelden</button>
            </form>
        </div>
    <?php endif; ?>
</header>

<?php if ($flashes !== []): ?>
    <div class="meldungen">
        <?php foreach ($flashes as $flash): ?>
            <p class="meldung <?= e($flash['type']) ?>"><?= e($flash['text']) ?></p>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<main>
    <?= $content ?>
</main>

<footer class="fuss">
    <p>Weltenteiler — ein rundenbasiertes Strategiespiel. Gewürfelt wird serverseitig.</p>
</footer>
</body>
</html>
