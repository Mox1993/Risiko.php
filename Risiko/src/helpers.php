<?php

declare(strict_types=1);

/**
 * Ausgabe-Escaping.
 *
 * Kurz genug, dass man es in Templates ueberall hinschreibt - genau das ist
 * der Zweck. Jede Ausgabe laeuft hier durch, ohne Ausnahme.
 */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/** JSON fuer ein data-Attribut oder einen <script>-Block. */
function json_attr(mixed $value): string
{
    return e(json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

/**
 * Eine Zeile aus game_log in verstaendliches Deutsch uebersetzen.
 *
 * @param array<string,mixed> $entry Zeile aus GameRepository::recentLog()
 */
function bericht_zeile(array $entry, Risiko\Domain\WorldMap $map): string
{
    $wer  = (string) ($entry['display_name'] ?? '');
    $data = [];
    if (is_string($entry['payload'] ?? null) && $entry['payload'] !== '') {
        $decoded = json_decode((string) $entry['payload'], true);
        $data    = is_array($decoded) ? $decoded : [];
    }

    $gebiet = static function (mixed $id) use ($map): string {
        $id = (int) $id;

        return $map->has($id) ? $map->territory($id)->name : "Gebiet $id";
    };

    return match ((string) $entry['action']) {
        'start' => sprintf(
            'Die Partie beginnt mit %d Spielern.',
            (int) ($data['players'] ?? 0),
        ),
        'reinforce' => sprintf(
            '%s stellt %d Einheiten in %s auf.',
            $wer,
            (int) ($data['amount'] ?? 0),
            $gebiet($data['territory'] ?? 0),
        ),
        'trade' => sprintf(
            '%s tauscht einen Kartensatz gegen %d Einheiten.',
            $wer,
            (int) ($data['armies'] ?? 0),
        ),
        'attack' => sprintf(
            '%s greift %s von %s aus an (%s gegen %s)%s%s',
            $wer,
            $gebiet($data['to'] ?? 0),
            $gebiet($data['from'] ?? 0),
            implode('/', array_map('intval', (array) ($data['attacker_dice'] ?? []))),
            implode('/', array_map('intval', (array) ($data['defender_dice'] ?? []))),
            sprintf(
                ' — Verluste %d zu %d.',
                (int) ($data['attacker_losses'] ?? 0),
                (int) ($data['defender_losses'] ?? 0),
            ),
            ($data['conquered'] ?? false) ? ' Erobert!' : '',
        ),
        'occupy' => sprintf(
            '%s besetzt %s mit %d Einheiten.',
            $wer,
            $gebiet($data['to'] ?? 0),
            (int) ($data['moved'] ?? 0),
        ),
        'fortify' => sprintf(
            '%s verschiebt %d Einheiten von %s nach %s.',
            $wer,
            (int) ($data['amount'] ?? 0),
            $gebiet($data['from'] ?? 0),
            $gebiet($data['to'] ?? 0),
        ),
        'end_phase'  => sprintf('%s beendet einen Abschnitt.', $wer),
        'end_turn'   => sprintf('%s beendet den Zug.', $wer),
        'draw_card'  => sprintf('%s zieht eine Ereigniskarte.', $wer),
        'eliminate'  => sprintf('%s schaltet einen Gegner aus.', $wer),
        'reshuffle'  => 'Der Ablagestapel wird neu gemischt.',
        'victory'    => sprintf('%s hat die Welt geeint.', $wer),
        default      => sprintf('%s: %s', $wer, (string) $entry['action']),
    };
}

/** Zeitangabe in einem Format, das man beim Ueberfliegen versteht. */
function moment(?string $sqlDateTime): string
{
    if ($sqlDateTime === null || $sqlDateTime === '') {
        return '';
    }
    $ts = strtotime($sqlDateTime);

    return $ts === false ? '' : date('d.m.Y H:i', $ts);
}
