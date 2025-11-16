USE `lista_revisao2`;

-- 1. Insere o novo perfil. O MySQL gera um ID (ex: 1).
INSERT INTO perfil (descricao)
VALUES ('Admin');

-- 2. Insere o usuário, pegando o ID gerado no passo anterior
-- com a função LAST_INSERT_ID().
INSERT INTO usuario (
    nome,
    email,
    id_perfil,
    senha,
    email_confirmado
)
VALUES (
    'Admin',
    'admin@gmail.com',
    LAST_INSERT_ID(), -- Pega o ID que acabou de ser gerado pelo INSERT de 'perfil'
    '123',
    1
);