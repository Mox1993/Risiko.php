<?php
/**
 * @var string $base
 * @var Risiko\Http\Csrf $csrf
 */
?>
<section class="karte-blatt schmal-blatt">
    <h1>Anmelden</h1>

    <form method="post" action="<?= e($base) ?>/anmelden" class="formular">
        <?= $csrf->field() ?>

        <label for="benutzer">Benutzername</label>
        <input id="benutzer" name="benutzer" type="text" required autofocus
               autocomplete="username" maxlength="32">

        <label for="passwort">Passwort</label>
        <input id="passwort" name="passwort" type="password" required
               autocomplete="current-password">

        <button class="knopf haupt" type="submit">Anmelden</button>
    </form>

    <p class="hinweis">
        Noch kein Konto? <a href="<?= e($base) ?>/registrieren">Hier anlegen.</a>
    </p>
</section>
