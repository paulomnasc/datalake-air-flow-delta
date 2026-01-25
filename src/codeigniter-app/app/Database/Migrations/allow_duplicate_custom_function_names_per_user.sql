-- ══════════════════════════════════════════════════════════════════
-- Migration: Permitir nomes de funções custom por usuário
-- Data: 2026-01-23
-- Descrição: Escopar unicidade de nome para owner_user_id, evitando conflito
--            entre funções core e custom de usuários diferentes.
-- ══════════════════════════════════════════════════════════════════

-- 1) Ajustar constraint de unicidade (nome deixa de ser global)
ALTER TABLE `funcion_configuration`
  DROP INDEX `nome`;

ALTER TABLE `funcion_configuration`
  ADD UNIQUE KEY `uk_owner_nome` (`owner_user_id`, `nome`);

-- 2) Garantir unicidade de nome para funções CORE (owner_user_id NULL)
DELIMITER $$
DROP TRIGGER IF EXISTS `trg_funcion_core_nome_before_insert`$$
CREATE TRIGGER `trg_funcion_core_nome_before_insert`
BEFORE INSERT ON `funcion_configuration`
FOR EACH ROW
BEGIN
  IF NEW.owner_user_id IS NULL THEN
    IF EXISTS (
      SELECT 1 FROM funcion_configuration
      WHERE nome = NEW.nome
        AND owner_user_id IS NULL
        AND id != IFNULL(NEW.id, 0)
    ) THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Nome já existe nas funções CORE';
    END IF;
  END IF;
END$$

DROP TRIGGER IF EXISTS `trg_funcion_core_nome_before_update`$$
CREATE TRIGGER `trg_funcion_core_nome_before_update`
BEFORE UPDATE ON `funcion_configuration`
FOR EACH ROW
BEGIN
  IF NEW.owner_user_id IS NULL THEN
    IF EXISTS (
      SELECT 1 FROM funcion_configuration
      WHERE nome = NEW.nome
        AND owner_user_id IS NULL
        AND id != NEW.id
    ) THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Nome já existe nas funções CORE';
    END IF;
  END IF;
END$$
DELIMITER ;
