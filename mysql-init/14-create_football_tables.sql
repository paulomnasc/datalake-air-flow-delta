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
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`fixture_id`),
    INDEX idx_fixture_date (`fixture_date`),
    FOREIGN KEY (`referee_name`) REFERENCES `referee_stats` (`name`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
