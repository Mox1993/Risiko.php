<?php

declare(strict_types=1);

namespace Risiko\Http;

use RuntimeException;

/**
 * Rendert PHP-Templates in einen String.
 *
 * Kein Template-System: PHP kann das selbst. Wichtig ist nur, dass jede
 * Ausgabe durch e() laeuft - siehe src/helpers.php.
 */
final class View
{
    public function __construct(
        private string $dir,
        private string $basePath,
        private Session $session,
        private Csrf $csrf,
    ) {
    }

    /** @param array<string,mixed> $data */
    public function render(string $template, array $data = []): string
    {
        $file = $this->dir . '/' . $template . '.php';
        if (!is_file($file)) {
            throw new RuntimeException("Template nicht gefunden: $template");
        }

        $data['base'] = $this->basePath;

        extract($data, EXTR_SKIP);
        ob_start();
        require $file;

        return (string) ob_get_clean();
    }

    /**
     * Eine vollstaendige Seite im Rahmen von layout.php.
     *
     * Sitzung, Sicherheitstoken und Meldungen braucht jede Seite, also steuert
     * die Ansicht sie selbst bei - sonst wiederholt jede Controller-Methode
     * dieselben drei Zeilen. takeFlashes() leert den Zwischenspeicher, deshalb
     * darf es genau einmal pro Antwort laufen: hier.
     *
     * @param array<string,mixed> $data
     */
    public function page(string $template, string $title, array $data = []): string
    {
        $data['session'] = $this->session;
        $data['csrf']    = $this->csrf;
        $data['flashes'] = $this->session->takeFlashes();

        return $this->render('layout', [
            'title'   => $title,
            'content' => $this->render($template, $data),
        ] + $data);
    }
}
