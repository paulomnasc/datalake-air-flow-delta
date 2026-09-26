#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Script de Criação e Carga da Tabela world_cup_standings_cache (MySQL)
Classificação Final Oficial da Copa do Mundo de 2026 (48 seleções).
"""

import pymysql

# Mapeamento oficial dos 48 colocados da Copa do Mundo de 2026 (CSV Wikipedia)
# (pos, team_name, group_letter, pts, j, v, e, d, gp, gc, sg, stage_reached, efficiency_tier, pedigree_bonus, is_tier1, team_id)
WORLD_CUP_2026_DATA = [
    # Final
    (1, "Espanha", "H", 22, 8, 7, 1, 0, 14, 1, 13, "FINAL_CAMPEA", "TIER_1_CHAMPION", 3.0, 1, 9),
    (2, "Argentina", "J", 21, 8, 7, 0, 1, 19, 8, 11, "FINAL_VICE", "TIER_1_FINALIST", 2.5, 1, 26),
    # 3º e 4º Lugares
    (3, "Inglaterra", "L", 19, 8, 6, 1, 1, 20, 12, 8, "TERCEIRO_LUGAR", "TIER_1_SEMIS", 2.0, 1, 10),
    (4, "França", "I", 18, 8, 6, 0, 2, 20, 10, 10, "QUARTO_LUGAR", "TIER_1_SEMIS", 2.0, 1, 2),
    # Quartas de Final
    (5, "Noruega", "I", 12, 6, 4, 0, 2, 13, 11, 2, "QUARTAS_DE_FINAL", "TIER_1_QUARTERS", 1.5, 1, 1090),
    (6, "Bélgica", "G", 11, 6, 3, 2, 1, 14, 7, 7, "QUARTAS_DE_FINAL", "TIER_1_QUARTERS", 1.5, 1, 1),
    (7, "Marrocos", "C", 11, 6, 3, 2, 1, 10, 6, 4, "QUARTAS_DE_FINAL", "TIER_1_QUARTERS", 1.5, 1, 1530),
    (8, "Suíça", "B", 11, 6, 3, 2, 1, 10, 6, 4, "QUARTAS_DE_FINAL", "TIER_1_QUARTERS", 1.5, 1, 15),
    # Oitavas de Final
    (9, "México", "A", 12, 5, 4, 0, 1, 10, 3, 7, "OITAVAS_DE_FINAL", "TIER_2_ROUND_16", 1.0, 1, 16),
    (10, "Colômbia", "K", 11, 5, 3, 2, 0, 5, 1, 4, "OITAVAS_DE_FINAL", "TIER_2_ROUND_16", 1.0, 1, 8),
    (11, "Brasil", "C", 10, 5, 3, 1, 1, 10, 4, 6, "OITAVAS_DE_FINAL", "TIER_2_ROUND_16", 1.0, 1, 6),
    (12, "Estados Unidos", "D", 9, 5, 3, 0, 2, 11, 8, 3, "OITAVAS_DE_FINAL", "TIER_2_ROUND_16", 1.0, 1, 2384),
    (13, "Portugal", "K", 8, 5, 2, 2, 1, 8, 3, 5, "OITAVAS_DE_FINAL", "TIER_2_ROUND_16", 1.0, 1, 27),
    (14, "Canadá", "B", 7, 5, 2, 1, 2, 9, 6, 3, "OITAVAS_DE_FINAL", "TIER_2_ROUND_16", 1.0, 1, 1533),
    (15, "Egito", "G", 6, 5, 1, 3, 1, 8, 7, 1, "OITAVAS_DE_FINAL", "TIER_2_ROUND_16", 1.0, 1, 32),
    (16, "Paraguai", "D", 5, 5, 1, 2, 2, 3, 6, -3, "OITAVAS_DE_FINAL", "TIER_2_ROUND_16", 1.0, 1, 1569),
    # Dezesseis-avos de Final (Round of 32)
    (17, "Países Baixos", "F", 8, 4, 2, 2, 0, 11, 5, 6, "ROUND_OF_32", "TIER_3_ROUND_32", 0.5, 1, 1118),
    (18, "Alemanha", "E", 7, 4, 2, 1, 1, 11, 5, 6, "ROUND_OF_32", "TIER_3_ROUND_32", 0.5, 1, 25),
    (19, "Costa do Marfim", "E", 6, 4, 2, 0, 2, 5, 4, 1, "ROUND_OF_32", "TIER_3_ROUND_32", 0.5, 0, 1503),
    (20, "Croácia", "L", 6, 4, 2, 0, 2, 6, 7, -1, "ROUND_OF_32", "TIER_3_ROUND_32", 0.5, 1, 3),
    (21, "Japão", "F", 5, 4, 1, 2, 1, 8, 5, 3, "ROUND_OF_32", "TIER_3_ROUND_32", 0.5, 1, 1534),
    (22, "Austrália", "D", 5, 4, 1, 2, 1, 3, 3, 0, "ROUND_OF_32", "TIER_3_ROUND_32", 0.5, 0, 1537),
    (23, "RD Congo", "K", 4, 4, 1, 1, 2, 5, 5, 0, "ROUND_OF_32", "TIER_3_ROUND_32", 0.5, 0, 1508),
    (24, "Gana", "L", 4, 4, 1, 1, 2, 2, 3, -1, "ROUND_OF_32", "TIER_3_ROUND_32", 0.5, 0, 1504),
    (25, "Equador", "E", 4, 4, 1, 1, 2, 2, 4, -2, "ROUND_OF_32", "TIER_3_ROUND_32", 0.5, 0, 2388),
    (26, "África do Sul", "A", 4, 4, 1, 1, 2, 2, 4, -2, "ROUND_OF_32", "TIER_3_ROUND_32", 0.5, 0, 1506),
    (27, "Suécia", "F", 4, 4, 1, 1, 2, 7, 10, -3, "ROUND_OF_32", "TIER_3_ROUND_32", 0.5, 1, 5),
    (28, "Áustria", "J", 4, 4, 1, 1, 2, 6, 9, -3, "ROUND_OF_32", "TIER_3_ROUND_32", 0.5, 0, 775),
    (29, "Bósnia e Herzegovina", "B", 4, 4, 1, 1, 2, 5, 8, -3, "ROUND_OF_32", "TIER_3_ROUND_32", 0.5, 0, 1113),
    (30, "Argélia", "J", 4, 4, 1, 1, 2, 5, 9, -4, "ROUND_OF_32", "TIER_3_ROUND_32", 0.5, 0, 30),
    (31, "Senegal", "I", 3, 4, 1, 0, 3, 10, 9, 1, "ROUND_OF_32", "TIER_3_ROUND_32", 0.5, 0, 13),
    (32, "Cabo Verde", "H", 3, 4, 0, 3, 1, 4, 5, -1, "ROUND_OF_32", "TIER_3_ROUND_32", 0.5, 0, 1501),
    # Fase de Grupos
    (33, "Irã", "G", 3, 3, 0, 3, 0, 3, 3, 0, "FASE_DE_GRUPOS", "TIER_4_GROUP_STAGE", 0.0, 0, 22),
    (34, "Coreia do Sul", "A", 3, 3, 1, 0, 2, 2, 3, -1, "FASE_DE_GRUPOS", "TIER_4_GROUP_STAGE", 0.0, 0, 17),
    (35, "Turquia", "D", 3, 3, 1, 0, 2, 3, 5, -2, "FASE_DE_GRUPOS", "TIER_4_GROUP_STAGE", 0.0, 0, 777),
    (36, "Escócia", "C", 3, 3, 1, 0, 2, 1, 4, -3, "FASE_DE_GRUPOS", "TIER_4_GROUP_STAGE", 0.0, 0, 1108),
    (37, "Uruguai", "H", 2, 3, 0, 2, 1, 3, 4, -1, "FASE_DE_GRUPOS", "TIER_4_GROUP_STAGE", 0.0, 1, 7),
    (38, "Arábia Saudita", "H", 2, 3, 0, 2, 1, 1, 5, -4, "FASE_DE_GRUPOS", "TIER_4_GROUP_STAGE", 0.0, 0, 23),
    (39, "Tchéquia", "A", 1, 3, 0, 1, 2, 2, 6, -4, "FASE_DE_GRUPOS", "TIER_4_GROUP_STAGE", 0.0, 0, 1103),
    (40, "Nova Zelândia", "G", 1, 3, 0, 1, 2, 4, 10, -6, "FASE_DE_GRUPOS", "TIER_4_GROUP_STAGE", 0.0, 0, 2400),
    (41, "Catar", "B", 1, 3, 0, 1, 2, 2, 10, -8, "FASE_DE_GRUPOS", "TIER_4_GROUP_STAGE", 0.0, 0, 1567),
    (42, "Curaçau", "E", 1, 3, 0, 1, 2, 1, 9, -8, "FASE_DE_GRUPOS", "TIER_4_GROUP_STAGE", 0.0, 0, 2390),
    (43, "Panamá", "L", 0, 3, 0, 0, 3, 0, 4, -4, "FASE_DE_GRUPOS", "TIER_4_GROUP_STAGE", 0.0, 0, 2386),
    (44, "Jordânia", "J", 0, 3, 0, 0, 3, 3, 8, -5, "FASE_DE_GRUPOS", "TIER_4_GROUP_STAGE", 0.0, 0, 1564),
    (45, "Haiti", "C", 0, 3, 0, 0, 3, 2, 8, -6, "FASE_DE_GRUPOS", "TIER_4_GROUP_STAGE", 0.0, 0, 2385),
    (46, "Uzbequistão", "K", 0, 3, 0, 0, 3, 2, 11, -9, "FASE_DE_GRUPOS", "TIER_4_GROUP_STAGE", 0.0, 0, 1568),
    (47, "Tunísia", "F", 0, 3, 0, 0, 3, 2, 12, -10, "FASE_DE_GRUPOS", "TIER_4_GROUP_STAGE", 0.0, 0, 28),
    (48, "Iraque", "I", 0, 3, 0, 0, 3, 1, 12, -11, "FASE_DE_GRUPOS", "TIER_4_GROUP_STAGE", 0.0, 0, 1565),
]


def setup_world_cup_table():
    from db_config import get_db_connection
    conn = get_db_connection()
    cursor = conn.cursor()

    # 1. Criação da Tabela
    print("Criando tabela world_cup_standings_cache...")
    cursor.execute("""
    CREATE TABLE IF NOT EXISTS `world_cup_standings_cache` (
      `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      `tournament` VARCHAR(50) NOT NULL DEFAULT 'Copa do Mundo',
      `season` INT NOT NULL DEFAULT 2026,
      `pos` INT NOT NULL,
      `team_name` VARCHAR(100) NOT NULL,
      `team_id` INT DEFAULT NULL,
      `group_letter` VARCHAR(5) DEFAULT NULL,
      `stage_reached` VARCHAR(50) NOT NULL,
      `points` INT NOT NULL DEFAULT 0,
      `matches_played` INT NOT NULL DEFAULT 0,
      `wins` INT NOT NULL DEFAULT 0,
      `draws` INT NOT NULL DEFAULT 0,
      `losses` INT NOT NULL DEFAULT 0,
      `goals_for` INT NOT NULL DEFAULT 0,
      `goals_against` INT NOT NULL DEFAULT 0,
      `goal_diff` INT NOT NULL DEFAULT 0,
      `efficiency_tier` VARCHAR(50) NOT NULL,
      `pedigree_bonus` DECIMAL(3,1) NOT NULL DEFAULT 0.0,
      `is_tier1` TINYINT(1) NOT NULL DEFAULT 0,
      `is_latest` TINYINT(1) NOT NULL DEFAULT 1,
      `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      UNIQUE KEY `uk_season_pos` (`season`, `pos`),
      INDEX `idx_team_name` (`team_name`),
      INDEX `idx_team_id` (`team_id`),
      INDEX `idx_tier1` (`is_tier1`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    """)

    # 2. Inserção / Upsert dos 48 registros
    print("Populando os 48 registros oficiais da Copa do Mundo 2026...")
    upsert_sql = """
    INSERT INTO world_cup_standings_cache 
    (tournament, season, pos, team_name, group_letter, stage_reached, points, matches_played, wins, draws, losses, goals_for, goals_against, goal_diff, efficiency_tier, pedigree_bonus, is_tier1, team_id, is_latest)
    VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
    ON DUPLICATE KEY UPDATE
      team_name = VALUES(team_name),
      group_letter = VALUES(group_letter),
      stage_reached = VALUES(stage_reached),
      points = VALUES(points),
      matches_played = VALUES(matches_played),
      wins = VALUES(wins),
      draws = VALUES(draws),
      losses = VALUES(losses),
      goals_for = VALUES(goals_for),
      goals_against = VALUES(goals_against),
      goal_diff = VALUES(goal_diff),
      efficiency_tier = VALUES(efficiency_tier),
      pedigree_bonus = VALUES(pedigree_bonus),
      is_tier1 = VALUES(is_tier1),
      team_id = VALUES(team_id),
      is_latest = VALUES(is_latest);
    """

    for item in WORLD_CUP_2026_DATA:
        pos, team, gr, pts, j, v, e, d, gp, gc, sg, stage, eff_tier, bonus, is_t1, tid = item
        cursor.execute(upsert_sql, (
            "Copa do Mundo", 2026, pos, team, gr, stage, pts, j, v, e, d, gp, gc, sg, eff_tier, bonus, is_t1, tid, 1
        ))

    cursor.execute("SELECT count(*) FROM world_cup_standings_cache WHERE season = 2026;")
    total = cursor.fetchone()[0]
    print(f"Sucesso! Total de seleções cadastradas em world_cup_standings_cache: {total}")

    conn.close()

if __name__ == '__main__':
    setup_world_cup_table()
