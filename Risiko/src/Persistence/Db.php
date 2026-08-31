<?php

declare(strict_types=1);

namespace Risiko\Persistence;

use mysqli;
use mysqli_sql_exception;
use RuntimeException;
use Throwable;

/**
 * Duenner mysqli-Wrapper.
 *
 * Drei Dinge sind hier bewusst gesetzt:
 *   - mysqli_report(...)      sonst schluckt mysqli Fehler stillschweigend
 *   - set_charset('utf8mb4')  ohne das kommen Umlaute kaputt zurueck
 *   - nur Prepared Statements nie String-Verkettung, auch nicht "kurz zum Testen"
 *
 * Der Preis gegenueber PDO: keine benannten Parameter, man zaehlt Fragezeichen
 * und pflegt den Typen-String ('iis'). Bei diesem Projektumfang gut auszuhalten.
 */
final class Db
{
    private int $transactionDepth = 0;

    /** @param array{host:string,user:string,pass:string,name:string,port?:int} $cfg */
    public static function connect(array $cfg): self
    {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

        $c = new mysqli(
            $cfg['host'],
            $cfg['user'],
            $cfg['pass'],
            $cfg['name'],
            $cfg['port'] ?? 3306,
        );
        $c->set_charset('utf8mb4');

        return new self($c);
    }

    private function __construct(private mysqli $c)
    {
    }

    /**
     * @param array<int,mixed> $params
     * @return list<array<string,mixed>>
     */
    public function select(string $sql, string $types = '', array $params = []): array
    {
        $stmt = $this->prepare($sql, $types, $params);
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return $rows;
    }

    /**
     * @param array<int,mixed> $params
     * @return array<string,mixed>|null
     */
    public function selectOne(string $sql, string $types = '', array $params = []): ?array
    {
        $rows = $this->select($sql, $types, $params);

        return $rows[0] ?? null;
    }

    /**
     * @param array<int,mixed> $params
     * @return int betroffene Zeilen
     */
    public function execute(string $sql, string $types = '', array $params = []): int
    {
        $stmt     = $this->prepare($sql, $types, $params);
        $affected = $stmt->affected_rows;
        $stmt->close();

        return $affected;
    }

    public function lastInsertId(): int
    {
        return (int) $this->c->insert_id;
    }

    /**
     * Fuehrt $fn in einer Transaktion aus. Verschachtelte Aufrufe laufen in
     * derselben Transaktion mit - so kann eine Action gefahrlos eine andere
     * benutzen, ohne dass zwischendurch committet wird.
     */
    public function transaction(callable $fn): mixed
    {
        if ($this->transactionDepth > 0) {
            $this->transactionDepth++;
            try {
                return $fn();
            } finally {
                $this->transactionDepth--;
            }
        }

        $this->c->begin_transaction();
        $this->transactionDepth = 1;

        try {
            $result = $fn();
            $this->c->commit();

            return $result;
        } catch (Throwable $e) {
            $this->c->rollback();

            throw $e;
        } finally {
            $this->transactionDepth = 0;
        }
    }

    /** @param array<int,mixed> $params */
    private function prepare(string $sql, string $types, array $params): \mysqli_stmt
    {
        try {
            $stmt = $this->c->prepare($sql);
        } catch (mysqli_sql_exception $e) {
            throw new RuntimeException(
                'SQL-Fehler beim Vorbereiten: ' . $e->getMessage() . "\n" . $sql,
                0,
                $e,
            );
        }

        if ($params !== []) {
            if (strlen($types) !== count($params)) {
                throw new RuntimeException(sprintf(
                    'Typen-String "%s" passt nicht zu %d Parametern.',
                    $types,
                    count($params),
                ));
            }
            $stmt->bind_param($types, ...$params);
        }

        $stmt->execute();

        return $stmt;
    }
}
