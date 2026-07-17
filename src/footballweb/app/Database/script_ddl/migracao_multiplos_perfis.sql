-- =========================================================================
-- SCRIPT DE MIGRAÇÃO: Evolução do modelo de dados de perfis de usuário
-- De: 1 usuário -> 1 perfil
-- Para: 1 usuário -> N perfis (relacionamento muitos-para-muitos)
-- =========================================================================

USE `lista_revisao2`;

-- 1. Criar a nova tabela associativa usuario_perfil
CREATE TABLE IF NOT EXISTS `usuario_perfil` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `id_usuario` tinyint unsigned NOT NULL,
  `id_perfil` int NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `usuario_perfil_unique` (`id_usuario`, `id_perfil`),
  KEY `fk_usuario_perfil_usuario_idx` (`id_usuario`),
  KEY `fk_usuario_perfil_perfil_idx` (`id_perfil`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- 2. Migrar os dados existentes da tabela usuario para usuario_perfil
-- Isso preservará os perfis atuais de cada usuário
INSERT INTO `usuario_perfil` (`id_usuario`, `id_perfil`)
SELECT `id`, `id_perfil`
FROM `usuario`
WHERE `id_perfil` IS NOT NULL;

-- 3. Adicionar as constraints de chave estrangeira à tabela usuario_perfil
-- (Fazemos isso depois da inserção de dados para evitar problemas)
ALTER TABLE `usuario_perfil`
ADD CONSTRAINT `fk_usuario_perfil_usuario` 
    FOREIGN KEY (`id_usuario`) 
    REFERENCES `usuario` (`id`) 
    ON DELETE CASCADE 
    ON UPDATE CASCADE;

ALTER TABLE `usuario_perfil`
ADD CONSTRAINT `fk_usuario_perfil_perfil` 
    FOREIGN KEY (`id_perfil`) 
    REFERENCES `perfil` (`id`) 
    ON DELETE CASCADE 
    ON UPDATE CASCADE;

-- 4. Remover a constraint antiga da tabela usuario
ALTER TABLE `usuario` DROP FOREIGN KEY `fk_usuario_perfil`;

-- 5. Remover a coluna id_perfil da tabela usuario
ALTER TABLE `usuario` DROP COLUMN `id_perfil`;

-- 6. Verificação: Mostrar quantos registros foram migrados
SELECT 
    CONCAT('Total de usuários: ', COUNT(DISTINCT id_usuario)) as resultado
FROM `usuario_perfil`
UNION ALL
SELECT 
    CONCAT('Total de associações usuário-perfil: ', COUNT(*)) as resultado
FROM `usuario_perfil`;

-- =========================================================================
-- NOTAS IMPORTANTES:
-- - Este script deve ser executado ANTES de aplicar o novo código da webapp
-- - Faça um BACKUP do banco de dados antes de executar este script
-- - Todos os perfis existentes serão preservados
-- - O relacionamento passa de 1:1 para N:N (muitos-para-muitos)
-- =========================================================================
