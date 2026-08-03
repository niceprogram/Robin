-- =================================================================
-- Tournament Management System — Database Schema
-- MySQL 5.7+ / MariaDB 10.2+
--
-- This version targets shared hosting (e.g. cPanel / iFastNet) where the
-- database itself (kvannl_Zebra) is already created for you and every
-- table must be prefixed. Simply import this file into that existing
-- database via phpMyAdmin — do NOT run a CREATE DATABASE statement,
-- shared hosting accounts usually aren't allowed to create databases.
-- =================================================================

-- -----------------------------------------------------------------
-- tournament_players
-- -----------------------------------------------------------------
CREATE TABLE tournament_players (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(100) NOT NULL,
    active     TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_players_active (active)
) ENGINE=InnoDB;

-- -----------------------------------------------------------------
-- tournament_stations
-- -----------------------------------------------------------------
CREATE TABLE tournament_stations (
    id     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name   VARCHAR(100) NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    INDEX idx_stations_active (active)
) ENGINE=InnoDB;

-- -----------------------------------------------------------------
-- tournament_rounds
-- -----------------------------------------------------------------
CREATE TABLE tournament_rounds (
    id                       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    round_number             INT UNSIGNED NOT NULL,
    status                   ENUM('active','paused','completed') NOT NULL DEFAULT 'active',
    duration_seconds         INT UNSIGNED NOT NULL DEFAULT 420,
    started_at               DATETIME NOT NULL,
    ends_at                  DATETIME NOT NULL,
    paused_remaining_seconds INT UNSIGNED NULL,
    created_at               DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_round_number (round_number),
    INDEX idx_rounds_status (status)
) ENGINE=InnoDB;

-- -----------------------------------------------------------------
-- tournament_matches  (1v1, one judge, one station, per round)
-- -----------------------------------------------------------------
CREATE TABLE tournament_matches (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    round_id     INT UNSIGNED NOT NULL,
    station_id   INT UNSIGNED NOT NULL,
    player_a_id  INT UNSIGNED NOT NULL,
    player_b_id  INT UNSIGNED NOT NULL,
    judge_id     INT UNSIGNED NOT NULL,
    result_type  ENUM('pending','win_a','win_b','draw','fault') NOT NULL DEFAULT 'pending',
    points_a     TINYINT UNSIGNED NULL,
    points_b     TINYINT UNSIGNED NULL,
    points_judge TINYINT UNSIGNED NULL,
    decided_at   DATETIME NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_matches_round    FOREIGN KEY (round_id)    REFERENCES tournament_rounds(id)   ON DELETE CASCADE,
    CONSTRAINT fk_matches_station  FOREIGN KEY (station_id)  REFERENCES tournament_stations(id) ON DELETE RESTRICT,
    CONSTRAINT fk_matches_player_a FOREIGN KEY (player_a_id) REFERENCES tournament_players(id)  ON DELETE RESTRICT,
    CONSTRAINT fk_matches_player_b FOREIGN KEY (player_b_id) REFERENCES tournament_players(id)  ON DELETE RESTRICT,
    CONSTRAINT fk_matches_judge    FOREIGN KEY (judge_id)    REFERENCES tournament_players(id)  ON DELETE RESTRICT,

    INDEX idx_matches_round (round_id),
    INDEX idx_matches_player_a (player_a_id),
    INDEX idx_matches_player_b (player_b_id),
    INDEX idx_matches_judge (judge_id),
    INDEX idx_matches_station (station_id),
    INDEX idx_matches_result (result_type)
) ENGINE=InnoDB;

-- -----------------------------------------------------------------
-- tournament_round_idles  (players who sit out a given round, tracked
-- for fair rotation of rest turns)
-- -----------------------------------------------------------------
CREATE TABLE tournament_round_idles (
    id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    round_id  INT UNSIGNED NOT NULL,
    player_id INT UNSIGNED NOT NULL,

    CONSTRAINT fk_idles_round  FOREIGN KEY (round_id)  REFERENCES tournament_rounds(id)  ON DELETE CASCADE,
    CONSTRAINT fk_idles_player FOREIGN KEY (player_id) REFERENCES tournament_players(id) ON DELETE RESTRICT,

    UNIQUE KEY uniq_round_player (round_id, player_id),
    INDEX idx_idles_round (round_id),
    INDEX idx_idles_player (player_id)
) ENGINE=InnoDB;

-- -----------------------------------------------------------------
-- tournament_settings  (key/value store for tournament-wide state)
-- -----------------------------------------------------------------
CREATE TABLE tournament_settings (
    setting_key   VARCHAR(50) PRIMARY KEY,
    setting_value VARCHAR(255) NOT NULL
) ENGINE=InnoDB;

INSERT INTO tournament_settings (setting_key, setting_value) VALUES
    ('tournament_status', 'not_started'),
    ('current_round_id', ''),
    ('round_duration_seconds', '420');

-- Default stations, as specified.
INSERT INTO tournament_stations (name) VALUES
    ('Air Hockey'),
    ('Darts'),
    ('Uno'),
    ('Arm Wrestling'),
    ('Car Race'),
    ('Checkers'),
    ('Table Tennis'),
    ('Stand on One Leg'),
    ('Ping Pong Cups'),
    ('Mini Golf');
