<?php

declare(strict_types=1);

namespace Risiko\Http\Controller;

use Risiko\Domain\RuleViolation;
use Risiko\Http\App;

/** Registrieren, anmelden, abmelden. */
final class AuthController
{
    public function __construct(private App $app)
    {
    }

    public function loginForm(): void
    {
        if ($this->app->session->isLoggedIn()) {
            $this->app->redirect('/');
        }

        $this->app->send($this->app->view->page('anmelden', 'Anmelden'));
    }

    public function login(): never
    {
        $user = $this->app->guardPost('/anmelden', fn (): ?array => $this->app->users->authenticate(
            $this->app->request->post('benutzer'),
            (string) ($_POST['passwort'] ?? ''),
        ));

        if ($user === null) {
            $this->app->session->flash('error', 'Benutzername oder Passwort stimmt nicht.');
            $this->app->redirect('/anmelden');
        }

        $this->app->session->login($user['id'], $user['username']);
        $this->app->redirect('/');
    }

    public function registerForm(): void
    {
        if ($this->app->session->isLoggedIn()) {
            $this->app->redirect('/');
        }

        $this->app->send($this->app->view->page('registrieren', 'Konto anlegen'));
    }

    public function register(): never
    {
        $username = $this->app->request->post('benutzer');

        $userId = $this->app->guardPost('/registrieren', function () use ($username): int {
            $password = (string) ($_POST['passwort'] ?? '');
            if ($password !== (string) ($_POST['passwort2'] ?? '')) {
                throw new RuleViolation('Die beiden Passwörter stimmen nicht überein.');
            }

            return $this->app->users->create($username, $password);
        });

        $this->app->session->login($userId, $username);
        $this->app->session->flash('info', 'Willkommen, ' . $username . '.');
        $this->app->redirect('/');
    }

    public function logout(): never
    {
        $this->app->guardPost('/anmelden', fn () => $this->app->session->logout());
        $this->app->redirect('/anmelden');
    }
}
