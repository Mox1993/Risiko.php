<?php
/**
 * @var string $base
 * @var Risiko\Http\Csrf $csrf
 */

use Risiko\Persistence\UserRepository;
?>
<section class="karte-blatt schmal-blatt">
    <h1>Konto anlegen</h1>

    <form method="post" action="<?= e($base) ?>/registrieren" class="formular">
        <?= $csrf->field() ?>

        <label for="benutzer">Benutzername</label>
        <input id="benutzer" name="benutzer" type="text" required autofocus
               autocomplete="username" minlength="3" maxlength="32">

        <label for="passwort">Passwort</label>
        <input id="passwort" name="passwort" type="password" required
               autocomplete="new-password" minlength="<?= UserRepository::MIN_PASSWORD_LENGTH ?>">

        <label for="passwort2">Passwort wiederholen</label>
        <input id="passwort2" name="passwort2" type="password" required
               autocomplete="new-password" minlength="<?= UserRepository::MIN_PASSWORD_LENGTH ?>">

        <button class="knopf haupt" type="submit">Konto anlegen</button>
    </form>

    <p class="hinweis">
        Schon registriert? <a href="<?= e($base) ?>/anmelden">Zur Anmeldung.</a>
    </p>
</section>
