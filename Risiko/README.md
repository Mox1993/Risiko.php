# Weltenteiler

Ein rundenbasiertes Eroberungsspiel im Browser — 42 Gebiete, 6 Kontinente,
83 Grenzen. Umgesetzt nach `konzept.md`: PHP 8.2+, MySQL/MariaDB über mysqli,
JavaScript ohne Framework, keine externen Pakete, kein Composer.

> Der Titel ist bewusst nicht „Risiko": das ist eine Marke von Hasbro.
> Für den Hausgebrauch egal, für eine öffentliche Instanz nicht.

---

## Was drin ist

Die Meilensteine 1 bis 4 aus dem Konzept sind umgesetzt, dazu die
Ereigniskarten aus Stufe 6:

| Stufe | Inhalt | Zustand |
|---|---|---|
| 1 | Schema, `Db`, `MapRepository`, Karte rendern | fertig |
| 2 | `Game`, `Combat`, Tests ohne DB | fertig, 69 Tests |
| 3 | `GameRepository`, Hotseat am selben Rechner | fertig |
| 4 | Login, asynchrones Mehrspieler, Lobby | fertig |
| 5 | Polling, Kriegsbericht | Kriegsbericht fertig, Polling nur als schlichter Neulade-Wecker |
| 6 | Ereigniskarten, Missionsziele, KI | Karten fertig, Rest offen |

Die offenen Punkte aus Abschnitt 11 des Konzepts sind so entschieden:

* **Kartentausch:** klassische Progression 4, 6, 8, 10, 12, 15, danach +5.
  Ab fünf Handkarten ist der Tausch Pflicht. Liegt eine getauschte Karte auf
  eigenem Gebiet, kommen dort zwei Einheiten dazu — einmal pro Tausch.
* **Startaufstellung:** Gebiete werden gleichmäßig zufällig verteilt, jedes
  bekommt eine Einheit, der Rest der Starteinheiten wird zufällig auf die
  eigenen Gebiete gestreut (40/35/30/25/20 je nach Spielerzahl).
* **Zeitlimit pro Zug:** keins.
* **Zuschauermodus:** offen.

---

## Einrichten

### 1. Datenbank

```
mysql -u root -p -e "CREATE DATABASE risiko CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p risiko < sql/schema.sql
```

`sql/schema.sql` legt die Tabellen an **und** löscht sie vorher — der Aufruf
setzt eine bestehende Installation also zurück.

### 2. Konfiguration

```
cp config/config.example.php config/config.php
```

Darin Host, Benutzer, Passwort und Datenbankname eintragen. Beim Uniform
Server steht der MySQL-Zugang im Bedienfeld unter *MySQL → root password*.

### 3. Webserver

`public/` ist das einzige Verzeichnis, das in den DocumentRoot gehört. Beim
Uniform Server trägst du das in der vHost-Konfiguration ein:

```apache
<VirtualHost *:80>
    ServerName weltenteiler.local
    DocumentRoot "C:/UniServerZ/www/risiko/public"
    <Directory "C:/UniServerZ/www/risiko/public">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

Danach `weltenteiler.local` in die `hosts`-Datei eintragen. Falls der
DocumentRoot doch auf das Projektverzeichnis zeigt, fängt die `.htaccess` im
Wurzelverzeichnis das ab und leitet nach `public/` um — die saubere Lösung
bleibt der vHost.

Zum schnellen Ausprobieren ohne Apache reicht auch der eingebaute Server:

```
php -S 127.0.0.1:8080 -t public devserver.php
```

### 4. Loslegen

Konto anlegen, Partie eröffnen. **Hotseat** heißt: alle sitzen an diesem
Rechner und geben reihum weiter. **Offene Partie** heißt: Mitspieler treten
über die Lobby bei und ziehen, wann sie wollen.

---

## Tests

```
php tests/run.php            # 69 Regeltests, ohne Datenbank, ohne Browser
php tests/integration.php    # spielt eine ganze Partie durch den echten Stapel
```

`tests/run.php` prüft die Regeln isoliert — Würfelvergleich, Verstärkung,
Eroberung, Verschieben, Karten, Zugwechsel, Aufstellung. Läuft in
Millisekunden und ist der Testpfad, den man beim Entwickeln offen hat.

`tests/integration.php` legt eine eigene Partie an, spielt sie mit einem
schlichten Bot bis zum Sieg durch und prüft nach jeder Aktion, dass der
Zustand plausibel bleibt: 42 Gebiete, jedes mit Besitzer und mindestens einer
Einheit, ausgeschiedene Spieler ohne Gebiete. Das dauert ein bis zwei Minuten
und braucht eine eingerichtete Datenbank.

---

## Generatoren

Zwei Dinge werden erzeugt und nicht von Hand gepflegt:

```
python3 tools/gen_schema.py   # -> sql/schema.sql, tests/MapFixture.php
python3 tools/gen_map.py      # -> src/View/map_data.php
```

`gen_schema.py` ist die einzige Quelle für Gebiets-IDs, Namen, Kontinente und
Grenzen. `gen_map.py` importiert diese Stammdaten und steuert nur die
Geometrie bei — so steht kein Gebietsname an zwei Stellen.

`gen_schema.py` ist die erweiterte Fassung des Skripts aus dem Konzept: dazu
gekommen sind das Kartensymbol als Stammdatum, die Tabelle `game_cards`, der
Zähler für die Kartenprogression und der Zwischenzustand nach einer Eroberung.
Das Skript prüft die Karte, bevor es schreibt — Symmetrie der Grenzen, kein
Gebiet ohne Nachbarn, ein zusammenhängender Graph.

`gen_map.py` erzeugt die schematische Weltkarte: 42 Vielecke in geografisch
plausibler Lage, dazu gestrichelte Seewege für Grenzen, die optisch weit
auseinanderliegen. Geschrieben wird ausschließlich Geometrie — Umriss,
Mittelpunkt, Seewege. Wie ein Gebiet heißt und zu welchem Kontinent es gehört,
kommt zur Laufzeit aus der Datenbank.

Wer später eine echte Weltkarte einsetzen will, ersetzt die Vielecke in
`SHAPES` und behält die `svg_id` als Zuordnung. Sie ist reine Generatorsache
und steht weder in der Datenbank noch im PHP-Code; dort läuft alles über die
Gebiets-ID. Der Generator bricht ab, wenn eine Form kein Gebiet in
`gen_schema.py` hat oder umgekehrt.

---

## Aufbau

```
risiko/
├── public/              <- einziges per Browser erreichbares Verzeichnis
│   ├── index.php        <- Front Controller
│   └── assets/          css, js
├── src/
│   ├── Domain/          Regeln. Kennt weder DB noch HTTP.
│   ├── Persistence/     Db + drei Repositories
│   ├── Application/     GameAction (jeder Zug) + LobbyAction (vor dem Start)
│   ├── Http/            Router, Session, Csrf, Controller
│   └── View/            Templates + map_data.php
├── config/              config.php (nicht im Repository)
├── sql/schema.sql       erzeugt
├── tests/               Regeltests und Integrationstest
└── tools/               die beiden Generatoren
```

Die Abhängigkeiten zeigen immer nach innen. Steht in `src/Domain/Game.php`
jemals das Wort `SELECT`, ist etwas schiefgelaufen.

### Ablauf eines Zuges

```
Browser  POST /partie/17/angriff  { von: 5, nach: 6, wuerfel: 3, csrf: ... }
   |
   v
GameController   Eingaben in int wandeln, Sitzungsbenutzer holen
   |             App::guardPost()  prüft das CSRF-Token
   v
GameAction       Transaktion öffnen
   |             GameRepository::findForUpdate()   (SELECT ... FOR UPDATE)
   |             Game::attack()  ->  RuleViolation oder CombatResult
   |             GameRepository::save()  +  Log-Eintrag
   |             Commit
   v
GameController   Redirect (Post/Redirect/Get)
```

Post/Redirect/Get ist keine Stilfrage: ohne Redirect wiederholt F5 den
Angriff. `FOR UPDATE` ebenso wenig — ohne die Sperre führt ein hastiger
Doppelklick zwei Angriffe aus, die beide vom selben Ausgangszustand ausgehen.

### Der Zwischenzustand nach einer Eroberung

Bei einer Eroberung rücken sofort so viele Einheiten nach, wie gewürfelt
wurde — damit steht nie ein Gebiet mit null Einheiten in der Datenbank.
Anschließend merkt sich die Partie in `pending_from/to/min`, dass der Spieler
noch entscheiden darf, ob weitere Einheiten folgen. Bis das geschehen ist,
lehnt `Game` jede andere Handlung ab.

---

## Sicherheit

* **SQL:** ausschließlich Prepared Statements, nie String-Verkettung.
* **XSS:** jede Ausgabe durch `e()` (`htmlspecialchars`, `ENT_QUOTES`, UTF-8).
* **CSRF:** Token in der Sitzung, verstecktes Feld in jedem Formular, Prüfung
  bei jedem POST.
* **Autorisierung:** jede Aktion prüft serverseitig, ob der Sitzungsbenutzer
  der Spieler ist, der am Zug ist. Nie darauf verlassen, dass der Knopf
  ausgeblendet war.
* **Passwörter:** `password_hash()` / `password_verify()`.
* **Zufall:** `random_int()`, nicht `rand()` — für Würfel, Kartenstapel und
  Startaufstellung.
* **Würfeln passiert ausschließlich serverseitig.** Würfelte das JavaScript,
  gewänne jeder mit geöffneter Entwicklerkonsole.

---

## Was als Nächstes sinnvoll wäre

* Verschieben mehrfach erlauben oder ganz sperren — aktuell beendet ein
  Verschieben den Zug, das ist die klassische Regel, aber nicht die einzige.
* Missionsziele statt Welteroberung: kürzere Partien.
* Ein KI-Gegner. `players.is_ai` steht schon im Schema, der Bot aus
  `tests/integration.php` wäre ein brauchbarer Anfang.
* Zuschauermodus für beendete Partien aus `game_log` — die Daten liegen alle
  da, es fehlt nur die Ansicht.
