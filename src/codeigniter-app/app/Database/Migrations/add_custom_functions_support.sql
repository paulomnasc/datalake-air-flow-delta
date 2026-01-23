-- ══════════════════════════════════════════════════════════════════
-- Migration: Suporte para Custom Functions por Usuário
-- Data: 2026-01-23
-- Descrição: Permite usuários criarem suas próprias funções Python
--            sem perder acesso às funções core do sistema
-- ══════════════════════════════════════════════════════════════════

-- ─────────────────────────────────────────────────────────────────
-- 1. ADICIONAR COLUNAS NA funcion_configuration
-- ─────────────────────────────────────────────────────────────────

ALTER TABLE `funcion_configuration`
  -- Indicador se é função custom (0=core, 1=custom do usuário)
  ADD COLUMN `is_custom` TINYINT(1) NOT NULL DEFAULT 0 AFTER `ativo`,
  
  -- Dono da função custom (NULL para core, user_id para custom)
  ADD COLUMN `owner_user_id` TINYINT UNSIGNED NULL AFTER `is_custom`,
  
  -- FK para garantir integridade (owner existe na tabela usuario)
  ADD CONSTRAINT `fk_funcion_owner` 
    FOREIGN KEY (`owner_user_id`) 
    REFERENCES `usuario` (`id`) 
    ON DELETE CASCADE,
  
  -- Índices para performance
  ADD INDEX `idx_is_custom` (`is_custom`),
  ADD INDEX `idx_owner` (`owner_user_id`);


-- ─────────────────────────────────────────────────────────────────
-- 2. AJUSTAR CONSTRAINTS DE UNICIDADE
-- ─────────────────────────────────────────────────────────────────

-- Remover unique constraint atual (modulo_python global)
ALTER TABLE `funcion_configuration`
  DROP INDEX `uk_modulo_python`;

-- Criar unique composto: cada usuário pode ter módulo com mesmo nome
ALTER TABLE `funcion_configuration`
  ADD UNIQUE KEY `uk_owner_modulo` (`owner_user_id`, `modulo_python`);


-- ─────────────────────────────────────────────────────────────────
-- 3. TRIGGERS: Garantir unicidade de módulo_python para CORE
-- ─────────────────────────────────────────────────────────────────

DELIMITER $$

CREATE TRIGGER `trg_funcion_core_unique_before_insert`
BEFORE INSERT ON `funcion_configuration`
FOR EACH ROW
BEGIN
  -- Se é função CORE (owner_user_id NULL), verificar se módulo já existe
  IF NEW.owner_user_id IS NULL THEN
    IF EXISTS (
      SELECT 1 FROM funcion_configuration 
      WHERE modulo_python = NEW.modulo_python 
        AND owner_user_id IS NULL
        AND id != IFNULL(NEW.id, 0)
    ) THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Módulo Python já existe nas funções CORE';
    END IF;
  END IF;
END$$

CREATE TRIGGER `trg_funcion_core_unique_before_update`
BEFORE UPDATE ON `funcion_configuration`
FOR EACH ROW
BEGIN
  -- Se é função CORE (owner_user_id NULL), verificar se módulo já existe
  IF NEW.owner_user_id IS NULL THEN
    IF EXISTS (
      SELECT 1 FROM funcion_configuration 
      WHERE modulo_python = NEW.modulo_python 
        AND owner_user_id IS NULL
        AND id != NEW.id
    ) THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Módulo Python já existe nas funções CORE';
    END IF;
  END IF;
END$$

DELIMITER ;


-- ─────────────────────────────────────────────────────────────────
-- 4. ATUALIZAR DADOS EXISTENTES (marcar como core)
-- ─────────────────────────────────────────────────────────────────

UPDATE `funcion_configuration` 
SET 
  is_custom = 0,
  owner_user_id = NULL
WHERE id <= 7; -- As 7 funções originais são CORE


-- ─────────────────────────────────────────────────────────────────
-- 5. VALIDAÇÃO: Conferir estado final
-- ─────────────────────────────────────────────────────────────────

SELECT 
  COUNT(*) AS total_core,
  SUM(CASE WHEN ativo = 1 THEN 1 ELSE 0 END) AS core_ativas
FROM funcion_configuration 
WHERE is_custom = 0;

-- Resultado esperado: total_core=7, core_ativas=6
