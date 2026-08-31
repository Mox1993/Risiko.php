<?php

declare(strict_types=1);

namespace Risiko\Persistence;

use Risiko\Domain\RuleViolation;

/**
 * Accounts. Passwoerter gehen nie im Klartext durch dieses Repository -
 * gehasht wird hier, geprueft wird hier, sonst nirgends.
 */
final class UserRepository
{
    public const MIN_PASSWORD_LENGTH = 8;

    public function __construct(private Db $db)
    {
    }

    /** @return array{id:int,username:string}|null */
    public function findByName(string $username): ?array
    {
        $row = $this->db->selectOne(
            'SELECT id, username FROM users WHERE username = ?',
            's',
            [$username],
        );

        return $row === null
            ? null
            : ['id' => (int) $row['id'], 'username' => (string) $row['username']];
    }

    public function create(string $username, string $password): int
    {
        $username = trim($username);

        if (mb_strlen($username) < 3 || mb_strlen($username) > 32) {
            throw new RuleViolation('Der Benutzername braucht 3 bis 32 Zeichen.');
        }
        if (!preg_match('/^[\p{L}\p{N}_. -]+$/u', $username)) {
            throw new RuleViolation('Erlaubt sind Buchstaben, Ziffern, Punkt, Bindestrich und Unterstrich.');
        }
        if (mb_strlen($password) < self::MIN_PASSWORD_LENGTH) {
            throw new RuleViolation(
                'Das Passwort braucht mindestens ' . self::MIN_PASSWORD_LENGTH . ' Zeichen.'
            );
        }
        if ($this->findByName($username) !== null) {
            throw new RuleViolation('Diesen Benutzernamen gibt es schon.');
        }

        $this->db->execute(
            'INSERT INTO users (username, password_hash) VALUES (?, ?)',
            'ss',
            [$username, password_hash($password, PASSWORD_DEFAULT)],
        );

        return $this->db->lastInsertId();
    }

    /** @return array{id:int,username:string}|null null bei falschen Zugangsdaten */
    public function authenticate(string $username, string $password): ?array
    {
        $row = $this->db->selectOne(
            'SELECT id, username, password_hash FROM users WHERE username = ?',
            's',
            [trim($username)],
        );

        // Auch ohne Treffer einmal hashen, damit die Antwortzeit nicht verraet,
        // ob es den Benutzer gibt.
        $hash = (string) ($row['password_hash'] ?? '$2y$12$invalidinvalidinvalidinvalidinvalidinvalidinvalidinvalidinv');

        if (!password_verify($password, $hash) || $row === null) {
            return null;
        }

        return ['id' => (int) $row['id'], 'username' => (string) $row['username']];
    }
}
