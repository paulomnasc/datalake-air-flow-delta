-- DML Script: Seed Admin user with full permissions (idempotent)
-- Environment: lista_revisao2_test

USE `lista_revisao2_test`;

-- 1) Ensure Admin profile exists
INSERT INTO perfil (descricao)
SELECT 'Admin'
WHERE NOT EXISTS (SELECT 1 FROM perfil WHERE descricao = 'Admin');

-- 2) Ensure Admin user exists
INSERT INTO usuario (nome, email, senha, email_confirmado, criado_em)
SELECT 'Admin', 'admin@gmail.com', '123', 1, CURRENT_TIMESTAMP
WHERE NOT EXISTS (SELECT 1 FROM usuario WHERE email = 'admin@gmail.com');

-- 3) Ensure association usuario_perfil (Admin user -> Admin profile)
INSERT INTO usuario_perfil (id_usuario, id_perfil)
SELECT u.id, p.id
FROM usuario u
JOIN perfil p ON p.descricao = 'Admin'
WHERE u.email = 'admin@gmail.com'
  AND NOT EXISTS (
    SELECT 1 FROM usuario_perfil up
    WHERE up.id_usuario = u.id AND up.id_perfil = p.id
  );

-- 4) Ensure Admin profile has ALL funcionalidades
INSERT INTO perfil_funcionalidade (id_perfil, id_funcionalidade)
SELECT p.id, f.id
FROM perfil p
JOIN funcionalidade f
WHERE p.descricao = 'Admin'
  AND NOT EXISTS (
    SELECT 1 FROM perfil_funcionalidade pf
    WHERE pf.id_perfil = p.id AND pf.id_funcionalidade = f.id
  );

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

-- 5) Seed source_types (idempotente)
INSERT IGNORE INTO `source_types` (`description`) VALUES
('CSV'),
('JSON'),
('MySQL'),
('PostgreSQL');