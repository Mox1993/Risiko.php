-- ============================================================
-- Risiko-Browserspiel  |  Schema + Stammdaten
-- MySQL / MariaDB, InnoDB, utf8mb4
-- Erzeugt von tools/gen_schema.py - nicht von Hand nachpflegen.
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS game_log;
DROP TABLE IF EXISTS game_cards;
DROP TABLE IF EXISTS game_territories;
DROP TABLE IF EXISTS players;
DROP TABLE IF EXISTS games;
DROP TABLE IF EXISTS territory_neighbors;
DROP TABLE IF EXISTS territories;
DROP TABLE IF EXISTS continents;
DROP TABLE IF EXISTS users;

SET FOREIGN_KEY_CHECKS = 1;

-- ------------------------------------------------------------
-- 1. Stammdaten der Karte (statisch, spielunabhaengig)
-- ------------------------------------------------------------

CREATE TABLE continents (
    id          TINYINT UNSIGNED NOT NULL PRIMARY KEY,
    name        VARCHAR(40)      NOT NULL,
    bonus       TINYINT UNSIGNED NOT NULL COMMENT 'Zusatzeinheiten bei Vollbesitz'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE territories (
    id           TINYINT UNSIGNED NOT NULL PRIMARY KEY,
    name         VARCHAR(40)      NOT NULL,
    continent_id TINYINT UNSIGNED NOT NULL,
    card_symbol  ENUM('infanterie','kavallerie','artillerie') NOT NULL
                 COMMENT 'Symbol der zugehoerigen Ereigniskarte',
    KEY idx_territories_continent (continent_id),
    CONSTRAINT fk_territories_continent
        FOREIGN KEY (continent_id) REFERENCES continents (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE territory_neighbors (
    territory_id TINYINT UNSIGNED NOT NULL,
    neighbor_id  TINYINT UNSIGNED NOT NULL,
    PRIMARY KEY (territory_id, neighbor_id),
    CONSTRAINT fk_neigh_from FOREIGN KEY (territory_id) REFERENCES territories (id),
    CONSTRAINT fk_neigh_to   FOREIGN KEY (neighbor_id)  REFERENCES territories (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  COMMENT='Beide Richtungen sind eingetragen - Abfragen brauchen kein OR.';

-- ------------------------------------------------------------
-- 2. Accounts
-- ------------------------------------------------------------

CREATE TABLE users (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(32)  NOT NULL,
    password_hash VARCHAR(255) NOT NULL COMMENT 'password_hash(), PASSWORD_DEFAULT',
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_users_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- 3. Partien
-- ------------------------------------------------------------

CREATE TABLE games (
    id                INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name              VARCHAR(60)  NOT NULL,
    status            ENUM('lobby','running','finished') NOT NULL DEFAULT 'lobby',
    phase             ENUM('reinforce','attack','fortify') NOT NULL DEFAULT 'reinforce',
    current_player_id INT UNSIGNED NULL COMMENT 'players.id - NULL solange Lobby',
    round_no          SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    reinforce_pool    SMALLINT UNSIGNED NOT NULL DEFAULT 0
                      COMMENT 'noch zu setzende Einheiten in der Verstaerkungsphase',
    conquered_this_turn TINYINT(1) NOT NULL DEFAULT 0
                      COMMENT 'steuert die Kartenvergabe am Zugende',
    card_sets_traded  SMALLINT UNSIGNED NOT NULL DEFAULT 0
                      COMMENT 'Anzahl bereits getauschter Saetze - klassische Progression',
    pending_from      TINYINT UNSIGNED NULL
                      COMMENT 'Eroberung schwebt: Ausgangsgebiet',
    pending_to        TINYINT UNSIGNED NULL
                      COMMENT 'Eroberung schwebt: erobertes Gebiet',
    pending_min       TINYINT UNSIGNED NULL
                      COMMENT 'Eroberung schwebt: Mindestzahl nachrueckender Einheiten',
    max_players       TINYINT UNSIGNED NOT NULL DEFAULT 6,
    hotseat           TINYINT(1) NOT NULL DEFAULT 0
                      COMMENT '1 = alle Spieler an einem Rechner',
    winner_player_id  INT UNSIGNED NULL,
    created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                      ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_games_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE players (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    game_id      INT UNSIGNED NOT NULL,
    user_id      INT UNSIGNED NULL COMMENT 'NULL = Hotseat-Sitzplatz oder KI',
    display_name VARCHAR(32)  NOT NULL,
    color        CHAR(7)      NOT NULL COMMENT 'Hex, z.B. #c0392b - direkt als SVG-fill',
    turn_order   TINYINT UNSIGNED NOT NULL,
    is_eliminated TINYINT(1)  NOT NULL DEFAULT 0,
    is_ai        TINYINT(1)   NOT NULL DEFAULT 0,
    UNIQUE KEY uq_players_order (game_id, turn_order),
    KEY idx_players_game (game_id),
    KEY idx_players_user (user_id),
    CONSTRAINT fk_players_game FOREIGN KEY (game_id) REFERENCES games (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_players_user FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE game_territories (
    game_id         INT UNSIGNED     NOT NULL,
    territory_id    TINYINT UNSIGNED NOT NULL,
    owner_player_id INT UNSIGNED     NULL,
    armies          SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    PRIMARY KEY (game_id, territory_id),
    KEY idx_gt_owner (game_id, owner_player_id),
    CONSTRAINT fk_gt_game  FOREIGN KEY (game_id)      REFERENCES games (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_gt_terr  FOREIGN KEY (territory_id) REFERENCES territories (id),
    CONSTRAINT fk_gt_owner FOREIGN KEY (owner_player_id) REFERENCES players (id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  COMMENT='Der eigentliche Spielzustand. 42 Zeilen pro Partie.';

CREATE TABLE game_cards (
    game_id         INT UNSIGNED     NOT NULL,
    card_no         TINYINT UNSIGNED NOT NULL COMMENT '1..44, stabil innerhalb der Partie',
    territory_id    TINYINT UNSIGNED NULL COMMENT 'NULL = Joker',
    owner_player_id INT UNSIGNED     NULL,
    state           ENUM('deck','hand','discard') NOT NULL DEFAULT 'deck',
    deck_pos        TINYINT UNSIGNED NOT NULL COMMENT 'Ziehreihenfolge, klein zuerst',
    PRIMARY KEY (game_id, card_no),
    KEY idx_cards_hand (game_id, owner_player_id),
    KEY idx_cards_deck (game_id, state, deck_pos),
    CONSTRAINT fk_cards_game  FOREIGN KEY (game_id)      REFERENCES games (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_cards_terr  FOREIGN KEY (territory_id) REFERENCES territories (id),
    CONSTRAINT fk_cards_owner FOREIGN KEY (owner_player_id) REFERENCES players (id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  COMMENT='42 Gebietskarten + 2 Joker je Partie. Symbol steht in territories.';

CREATE TABLE game_log (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    game_id    INT UNSIGNED    NOT NULL,
    round_no   SMALLINT UNSIGNED NOT NULL,
    player_id  INT UNSIGNED    NULL,
    action     VARCHAR(24)     NOT NULL COMMENT 'reinforce|attack|fortify|end_phase|...',
    payload    JSON            NULL COMMENT 'Wuerfel, Gebiete, Verluste',
    created_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_log_game (game_id, id),
    CONSTRAINT fk_log_game FOREIGN KEY (game_id) REFERENCES games (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- 4. Stammdaten einspielen
-- ------------------------------------------------------------

INSERT INTO continents (id, name, bonus) VALUES
    (1, 'Nordamerika', 5),
    (2, 'Südamerika', 2),
    (3, 'Europa', 5),
    (4, 'Afrika', 3),
    (5, 'Asien', 7),
    (6, 'Australien', 2);

INSERT INTO territories (id, name, continent_id, card_symbol) VALUES
    -- Nordamerika
    ( 1, 'Alaska', 1, 'infanterie'),
    ( 2, 'Nordwest-Territorium', 1, 'kavallerie'),
    ( 3, 'Grönland', 1, 'artillerie'),
    ( 4, 'Alberta', 1, 'infanterie'),
    ( 5, 'Ontario', 1, 'kavallerie'),
    ( 6, 'Quebec', 1, 'artillerie'),
    ( 7, 'Weststaaten', 1, 'infanterie'),
    ( 8, 'Oststaaten', 1, 'kavallerie'),
    ( 9, 'Mittelamerika', 1, 'artillerie'),
    -- Südamerika
    (10, 'Venezuela', 2, 'infanterie'),
    (11, 'Peru', 2, 'kavallerie'),
    (12, 'Brasilien', 2, 'artillerie'),
    (13, 'Argentinien', 2, 'infanterie'),
    -- Europa
    (14, 'Island', 3, 'kavallerie'),
    (15, 'Skandinavien', 3, 'artillerie'),
    (16, 'Großbritannien', 3, 'infanterie'),
    (17, 'Nordeuropa', 3, 'kavallerie'),
    (18, 'Ukraine', 3, 'artillerie'),
    (19, 'Westeuropa', 3, 'infanterie'),
    (20, 'Südeuropa', 3, 'kavallerie'),
    -- Afrika
    (21, 'Nordafrika', 4, 'artillerie'),
    (22, 'Ägypten', 4, 'infanterie'),
    (23, 'Ostafrika', 4, 'kavallerie'),
    (24, 'Kongo', 4, 'artillerie'),
    (25, 'Südafrika', 4, 'infanterie'),
    (26, 'Madagaskar', 4, 'kavallerie'),
    -- Asien
    (27, 'Ural', 5, 'artillerie'),
    (28, 'Sibirien', 5, 'infanterie'),
    (29, 'Jakutien', 5, 'kavallerie'),
    (30, 'Kamtschatka', 5, 'artillerie'),
    (31, 'Irkutsk', 5, 'infanterie'),
    (32, 'Mongolei', 5, 'kavallerie'),
    (33, 'Japan', 5, 'artillerie'),
    (34, 'Afghanistan', 5, 'infanterie'),
    (35, 'China', 5, 'kavallerie'),
    (36, 'Mittlerer Osten', 5, 'artillerie'),
    (37, 'Indien', 5, 'infanterie'),
    (38, 'Siam', 5, 'kavallerie'),
    -- Australien
    (39, 'Indonesien', 6, 'artillerie'),
    (40, 'Neuguinea', 6, 'infanterie'),
    (41, 'Westaustralien', 6, 'kavallerie'),
    (42, 'Ostaustralien', 6, 'artillerie');

-- 166 Eintraege = 83 Grenzen, jeweils beidseitig
INSERT INTO territory_neighbors (territory_id, neighbor_id) VALUES
    (1,2), (1,4), (1,30),  -- Alaska
    (2,1), (2,3), (2,4), (2,5),  -- Nordwest-Territorium
    (3,2), (3,5), (3,6), (3,14),  -- Grönland
    (4,1), (4,2), (4,5), (4,7),  -- Alberta
    (5,2), (5,3), (5,4), (5,6), (5,7), (5,8),  -- Ontario
    (6,3), (6,5), (6,8),  -- Quebec
    (7,4), (7,5), (7,8), (7,9),  -- Weststaaten
    (8,5), (8,6), (8,7), (8,9),  -- Oststaaten
    (9,7), (9,8), (9,10),  -- Mittelamerika
    (10,9), (10,11), (10,12),  -- Venezuela
    (11,10), (11,12), (11,13),  -- Peru
    (12,10), (12,11), (12,13), (12,21),  -- Brasilien
    (13,11), (13,12),  -- Argentinien
    (14,3), (14,15), (14,16),  -- Island
    (15,14), (15,16), (15,17), (15,18),  -- Skandinavien
    (16,14), (16,15), (16,17), (16,19),  -- Großbritannien
    (17,15), (17,16), (17,18), (17,19), (17,20),  -- Nordeuropa
    (18,15), (18,17), (18,20), (18,27), (18,34), (18,36),  -- Ukraine
    (19,16), (19,17), (19,20), (19,21),  -- Westeuropa
    (20,17), (20,18), (20,19), (20,21), (20,22), (20,36),  -- Südeuropa
    (21,12), (21,19), (21,20), (21,22), (21,23), (21,24),  -- Nordafrika
    (22,20), (22,21), (22,23), (22,36),  -- Ägypten
    (23,21), (23,22), (23,24), (23,25), (23,26), (23,36),  -- Ostafrika
    (24,21), (24,23), (24,25),  -- Kongo
    (25,23), (25,24), (25,26),  -- Südafrika
    (26,23), (26,25),  -- Madagaskar
    (27,18), (27,28), (27,34), (27,35),  -- Ural
    (28,27), (28,29), (28,31), (28,32), (28,35),  -- Sibirien
    (29,28), (29,30), (29,31),  -- Jakutien
    (30,1), (30,29), (30,31), (30,32), (30,33),  -- Kamtschatka
    (31,28), (31,29), (31,30), (31,32),  -- Irkutsk
    (32,28), (32,30), (32,31), (32,33), (32,35),  -- Mongolei
    (33,30), (33,32),  -- Japan
    (34,18), (34,27), (34,35), (34,36), (34,37),  -- Afghanistan
    (35,27), (35,28), (35,32), (35,34), (35,37), (35,38),  -- China
    (36,18), (36,20), (36,22), (36,23), (36,34), (36,37),  -- Mittlerer Osten
    (37,34), (37,35), (37,36), (37,38),  -- Indien
    (38,35), (38,37), (38,39),  -- Siam
    (39,38), (39,40), (39,41),  -- Indonesien
    (40,39), (40,41), (40,42),  -- Neuguinea
    (41,39), (41,40), (41,42),  -- Westaustralien
    (42,40), (42,41);  -- Ostaustralien

-- ------------------------------------------------------------
-- 5. Nuetzliche Abfragen
-- ------------------------------------------------------------
--
-- Verstaerkung berechnen (Gebiete/3, min. 3) - Kontinentboni separat:
--   SELECT GREATEST(3, FLOOR(COUNT(*)/3)) FROM game_territories
--    WHERE game_id = ? AND owner_player_id = ?;
--
-- Komplett besessene Kontinente eines Spielers:
--   SELECT c.id, c.bonus FROM continents c
--     JOIN territories t ON t.continent_id = c.id
--     JOIN game_territories gt ON gt.territory_id = t.id AND gt.game_id = ?
--    GROUP BY c.id, c.bonus
--   HAVING SUM(gt.owner_player_id = ?) = COUNT(*);
--
-- Grenzt A an B?
--   SELECT 1 FROM territory_neighbors
--    WHERE territory_id = ? AND neighbor_id = ?;
--
-- Naechste Karte vom Stapel:
--   SELECT card_no FROM game_cards
--    WHERE game_id = ? AND state = 'deck'
--    ORDER BY deck_pos LIMIT 1;
