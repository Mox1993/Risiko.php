<?php

declare(strict_types=1);

namespace Risiko\Http;

use Risiko\Application\GameAction;
use Risiko\Application\LobbyAction;
use Risiko\Domain\Dice;
use Risiko\Domain\RandomDice;
use Risiko\Persistence\Db;
use Risiko\Persistence\GameRepository;
use Risiko\Persistence\MapRepository;
use Risiko\Persistence\UserRepository;
use RuntimeException;

/**
 * Verdrahtung. Ein einziger Ort, an dem entschieden wird, was wovon abhaengt.
 *
 * Bewusst kein DI-Container: bei einer Handvoll Objekten ist eine
 * Konstruktorkette kuerzer und besser lesbar als jede Konfiguration.
 */
final class App
{
    public readonly Db $db;
    public readonly Session $session;
    public readonly Csrf $csrf;
    public readonly Request $request;
    public readonly View $view;
    public readonly MapRepository $maps;
    public readonly GameRepository $games;
    public readonly UserRepository $users;
    public readonly Dice $dice;
    public readonly GameAction $play;
    public readonly LobbyAction $lobby;
    public readonly string $basePath;

    /** @param array<string,mixed> $config */
    public function __construct(array $config)
    {
        /** @var array{host:string,user:string,pass:string,name:string,port?:int} $dbConfig */
        $dbConfig = $config['db'];

        $this->basePath = rtrim(
            str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? ''))),
            '/',
        );

        $this->db      = Db::connect($dbConfig);
        $this->session = new Session();
        $this->session->start();
        $this->csrf    = new Csrf($this->session);
        $this->request = new Request();
        $this->view    = new View(
            dirname(__DIR__) . '/View',
            $this->basePath,
            $this->session,
            $this->csrf,
        );
        $this->maps    = new MapRepository($this->db);
        $this->games   = new GameRepository($this->db, $this->maps);
        $this->users   = new UserRepository($this->db);
        $this->dice    = new RandomDice();
        $this->play    = new GameAction($this->db, $this->games, $this->dice);
        $this->lobby   = new LobbyAction($this->db, $this->games, $this->dice);
    }

    /** Post/Redirect/Get - ohne Redirect wiederholt F5 den letzten Angriff. */
    public function redirect(string $path): never
    {
        header('Location: ' . $this->basePath . $path, true, 303);
        exit;
    }

    public function requireLogin(): int
    {
        $userId = $this->session->userId();
        if ($userId === null) {
            $this->session->flash('warn', 'Bitte melde dich zuerst an.');
            $this->redirect('/anmelden');
        }

        return $userId;
    }

    /**
     * Der Rahmen um jeden POST: Sicherheitstoken pruefen, Aufruf ausfuehren,
     * Regelverstoesse als Meldung in die Sitzung legen und zurueckleiten.
     *
     * Bei einem Fehler kehrt der Aufruf nie zurueck - er leitet nach $onError
     * um. Wohin es nach dem Gelingen geht, entscheidet der Aufrufer, denn das
     * haengt oft am Ergebnis (etwa an der ID der neuen Partie).
     *
     * RuleViolation erbt von RuntimeException, deshalb faengt ein catch beide:
     * Regelverstoesse und ein ungueltiges Token.
     *
     * @template T
     * @param callable():T $work
     * @return T
     */
    public function guardPost(string $onError, callable $work): mixed
    {
        try {
            $this->csrf->check($this->request->post('csrf'));

            return $work();
        } catch (RuntimeException $e) {
            $this->session->flash('error', $e->getMessage());
            $this->redirect($onError);
        }
    }

    public function send(string $html): void
    {
        header('Content-Type: text/html; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: same-origin');
        echo $html;
    }

    /** @param array<string,mixed> $data */
    public function json(array $data): never
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function notFound(string $message = 'Diese Seite gibt es nicht.'): never
    {
        http_response_code(404);
        $this->send($this->view->page('fehler', 'Nicht gefunden', [
            'headline' => 'Nicht gefunden',
            'message'  => $message,
        ]));
        exit;
    }
}
