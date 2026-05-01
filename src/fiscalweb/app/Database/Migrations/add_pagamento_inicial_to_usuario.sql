-- Migration: Adiciona campo pagamento_inicial para controle de pagamento inicial de novos usuários
-- Data: 2026-02-10

ALTER TABLE `usuario`
ADD COLUMN `pagamento_inicial` TINYINT(1) DEFAULT 0 COMMENT 'Pagamento inicial de $2,00 USD realizado';

-- Rollback
-- ALTER TABLE `usuario`
-- DROP COLUMN `pagamento_inicial`;
