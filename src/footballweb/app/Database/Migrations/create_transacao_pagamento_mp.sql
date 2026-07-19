-- Tabela para registros de cobranças e transações do Mercado Pago (Pix)
CREATE TABLE IF NOT EXISTS `pagamento_transacao` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `usuario_id` INT NOT NULL,
    `mp_payment_id` VARCHAR(100) NOT NULL,
    `status` VARCHAR(50) NOT NULL DEFAULT 'pending',
    `status_detail` VARCHAR(100) NULL,
    `valor` DECIMAL(10, 2) NOT NULL,
    `tipo` VARCHAR(50) DEFAULT 'subscription',
    `qr_code` TEXT NULL,
    `qr_code_base64` LONGTEXT NULL,
    `ticket_url` TEXT NULL,
    `criado_em` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `atualizado_em` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_usuario_id` (`usuario_id`),
    INDEX `idx_mp_payment_id` (`mp_payment_id`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
