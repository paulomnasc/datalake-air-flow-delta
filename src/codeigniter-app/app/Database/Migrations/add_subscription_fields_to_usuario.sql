-- Migration: Adicionar campos de controle de assinatura na tabela usuario
-- Data: 2026-01-05
-- Descrição: Adiciona campos para controlar período de trial, pagamentos e vencimento de assinatura

-- Adicionar coluna created_at se não existir (para rastrear data de criação do usuário)
ALTER TABLE `usuario` 
ADD COLUMN IF NOT EXISTS `criado_em` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data de criação do usuário',

-- Adicionar campos de assinatura
ALTER TABLE `usuario` 
ADD COLUMN `data_ultimo_pagamento` DATE NULL COMMENT 'Data do último pagamento realizado',
ADD COLUMN `data_vencimento_assinatura` DATE NULL COMMENT 'Data de vencimento da assinatura',
ADD COLUMN `status_assinatura` ENUM('trial', 'active', 'expired', 'cancelled') DEFAULT 'trial' COMMENT 'Status da assinatura: trial (30 dias grátis), active (paga), expired (vencida), cancelled (cancelada)',
ADD COLUMN `data_inicio_trial` DATE NULL COMMENT 'Data de início do período de trial (30 dias)',
ADD INDEX `idx_data_vencimento` (`data_vencimento_assinatura`),
ADD INDEX `idx_status_assinatura` (`status_assinatura`);

-- Atualizar usuários existentes: definir data de início do trial baseado na data de criação (criado_em)
-- e vencimento em 30 dias a partir da data de criação
UPDATE `usuario` 
SET 
    `data_inicio_trial` = COALESCE(DATE(`criado_em`), CURDATE()),
    `data_vencimento_assinatura` = DATE_ADD(COALESCE(DATE(`criado_em`), CURDATE()), INTERVAL 30 DAY),
    `status_assinatura` = 'trial'
WHERE 
    `data_vencimento_assinatura` IS NULL 
    AND `email_confirmado` = 1;

-- Rollback (caso necessário reverter a migration)
-- ALTER TABLE `usuario` 
-- DROP COLUMN `data_ultimo_pagamento`,
-- DROP COLUMN `data_vencimento_assinatura`, 
-- DROP COLUMN `status_assinatura`,
-- DROP COLUMN `data_inicio_trial`,
-- DROP INDEX `idx_data_vencimento`,
-- DROP INDEX `idx_status_assinatura`;
