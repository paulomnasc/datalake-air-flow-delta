CREATE DATABASE IF NOT EXISTS `footballweb` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

USE `footballweb`;

CREATE TABLE IF NOT EXISTS `referee_stats` (
    `name` VARCHAR(100) NOT NULL,
    `average_yellow_cards` DECIMAL(4,2) DEFAULT 0.00,
    `average_red_cards` DECIMAL(4,2) DEFAULT 0.00,
    `average_fouls` DECIMAL(5,2) DEFAULT 0.00,
    `total_games` INT DEFAULT 0,
    `rigor_level` VARCHAR(20) DEFAULT 'Moderado',
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `fixtures_trends` (
    `fixture_id` INT NOT NULL,
    `fixture_date` DATETIME NOT NULL,
    `league_id` INT NOT NULL,
    `league_name` VARCHAR(100) NOT NULL,
    `home_team` VARCHAR(100) NOT NULL,
    `away_team` VARCHAR(100) NOT NULL,
    `referee_name` VARCHAR(100) DEFAULT NULL,
    `prediction_text` TEXT DEFAULT NULL,
    `over_cards_probability` DECIMAL(5,2) DEFAULT 50.00,
    `status` VARCHAR(50) DEFAULT 'NS',
    `goals_home` INT DEFAULT NULL,
    `goals_away` INT DEFAULT NULL,
    `elapsed` INT DEFAULT NULL,
    `yellow_cards_home` INT DEFAULT NULL,
    `yellow_cards_away` INT DEFAULT NULL,
    `red_cards_home` INT DEFAULT NULL,
    `red_cards_away` INT DEFAULT NULL,
    `corners_home` INT DEFAULT 0,
    `corners_away` INT DEFAULT 0,
    `shots_home` INT DEFAULT 0,
    `shots_away` INT DEFAULT 0,
    `xg_home` DECIMAL(4,2) DEFAULT 0.00,
    `xg_away` DECIMAL(4,2) DEFAULT 0.00,
    `goal_scorers` TEXT DEFAULT NULL,
    `last_event` VARCHAR(255) DEFAULT NULL,
    `odd_home` DECIMAL(5,2) DEFAULT NULL,
    `casa_odd_home` VARCHAR(50) DEFAULT NULL,
    `odd_draw` DECIMAL(5,2) DEFAULT NULL,
    `casa_odd_draw` VARCHAR(50) DEFAULT NULL,
    `odd_away` DECIMAL(5,2) DEFAULT NULL,
    `casa_odd_away` VARCHAR(50) DEFAULT NULL,
    `is_surebet` TINYINT(1) DEFAULT 0,
    `surebet_profit_pct` DECIMAL(5,2) DEFAULT NULL,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`fixture_id`),
    INDEX idx_fixture_date (`fixture_date`),
    FOREIGN KEY (`referee_name`) REFERENCES `referee_stats` (`name`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
