<?php

declare(strict_types=1);

namespace Risiko\Http;

/**
 * Zugriff auf Eingaben.
 *
 * Alles, was aus dem Browser kommt, ist ein String - hier wird daraus ein
 * int oder ein getrimmter String, und zwar an genau einer Stelle.
 */
final class Request
{
    public function post(string $key, string $default = ''): string
    {
        $value = $_POST[$key] ?? $default;

        return is_string($value) ? trim($value) : $default;
    }

    public function postInt(string $key, int $default = 0): int
    {
        $value = $_POST[$key] ?? null;

        return is_scalar($value) ? (int) $value : $default;
    }

    /** @return list<int> */
    public function postIntList(string $key): array
    {
        $value = $_POST[$key] ?? [];
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_map('intval', array_filter($value, 'is_scalar')));
    }

    /** @return list<string> */
    public function postStringList(string $key): array
    {
        $value = $_POST[$key] ?? [];
        if (!is_array($value)) {
            return [];
        }

        $out = [];
        foreach ($value as $item) {
            if (is_string($item)) {
                $out[] = trim($item);
            }
        }

        return $out;
    }
}
