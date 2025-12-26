-- =========================================================================
-- SCRIPT DE MIGRAÇÃO: Evolução do modelo de dados de funcionalidades
-- De: Sem tabela de funcionalidades
-- Para: Tabela funcionalidade + tabela associativa perfil_funcionalidade (N:N)
-- =========================================================================

USE `lista_revisao2`;

-- 1. Criar a tabela de funcionalidades (se não existir)
CREATE TABLE IF NOT EXISTS `funcionalidade` (
  `id` int NOT NULL AUTO_INCREMENT,
  `descricao` varchar(100) NOT NULL UNIQUE,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- 2. Criar a tabela associativa perfil_funcionalidade (se não existir)
CREATE TABLE IF NOT EXISTS `perfil_funcionalidade` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `id_perfil` int NOT NULL,
  `id_funcionalidade` int NOT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `perfil_funcionalidade_unique` (`id_perfil`, `id_funcionalidade`),
  KEY `fk_perfil_funcionalidade_perfil_idx` (`id_perfil`),
  KEY `fk_perfil_funcionalidade_funcionalidade_idx` (`id_funcionalidade`),
  CONSTRAINT `fk_perfil_funcionalidade_perfil` FOREIGN KEY (`id_perfil`) REFERENCES `perfil` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_perfil_funcionalidade_funcionalidade` FOREIGN KEY (`id_funcionalidade`) REFERENCES `funcionalidade` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- 3. Inserir funcionalidades padrão (se não existirem)
INSERT IGNORE INTO `funcionalidade` (`descricao`) VALUES
('Gerenciar Usuários'),
('Gerenciar Perfis'),
('Gerenciar Funcionalidades'),
('Visualizar Buckets'),
('Criar Buckets'),
('Editar Buckets'),
('Deletar Buckets'),
('Visualizar Pastas'),
('Criar Pastas'),
('Editar Pastas'),
('Deletar Pastas'),
('Gerenciar Configurações'),
('Acessar Relatórios'),
('Operar Fluxos de Dados'),
('Exportar Dados'),
('Importar Dados');

-- 4. Verificação: Mostrar a estrutura criada
SELECT 
    CONCAT('Total de funcionalidades: ', COUNT(*)) as resultado
FROM `funcionalidade`
UNION ALL
SELECT 
    CONCAT('Total de perfis: ', COUNT(*)) as resultado
FROM `perfil`
UNION ALL
SELECT 
    CONCAT('Total de associações perfil-funcionalidade: ', COUNT(*)) as resultado
FROM `perfil_funcionalidade`;

-- =========================================================================
-- NOTAS IMPORTANTES:
-- - Este script deve ser executado ANTES de usar o novo código da webapp
-- - Faça um BACKUP do banco de dados antes de executar este script
-- - As funcionalidades padrão podem ser customizadas conforme necessário
-- - Os perfis existentes terão suas funcionalidades gerenciadas via webapp
-- - Você pode associar funcionalidades aos perfis através da interface web
-- =========================================================================
