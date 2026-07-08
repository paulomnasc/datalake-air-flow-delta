-- Tabela para cadastro dos grupos e e-mails de grupo
CREATE TABLE IF NOT EXISTS grupo (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Tabela relacional de Usuários e Grupos (Muitos para Muitos)
CREATE TABLE IF NOT EXISTS grupo_usuario (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT NOT NULL,
    id_grupo INT UNSIGNED NOT NULL,
    FOREIGN KEY (id_usuario) REFERENCES usuario(id) ON DELETE CASCADE,
    FOREIGN KEY (id_grupo) REFERENCES grupo(id) ON DELETE CASCADE,
    UNIQUE KEY uq_usuario_grupo (id_usuario, id_grupo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
