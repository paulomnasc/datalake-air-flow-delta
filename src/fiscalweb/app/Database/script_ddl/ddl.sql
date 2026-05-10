CREATE DATABASE IF NOT EXISTS `fiscal` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci */ /*!80016 DEFAULT ENCRYPTION='N' */;

USE `fiscal`;

-- Criação da tabela perfil
CREATE TABLE IF NOT EXISTS `perfil` (
  `id` int NOT NULL AUTO_INCREMENT,
  `descricao` varchar(45) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Definição da tabela usuario
CREATE TABLE IF NOT EXISTS `usuario` (
  `id` tinyint unsigned NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `senha` varchar(100) NOT NULL,
  `email_confirmado` tinyint(1) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `usuario_unique` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=146 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Criação da tabela associativa usuario_perfil
CREATE TABLE IF NOT EXISTS `usuario_perfil` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `id_usuario` tinyint unsigned NOT NULL,
  `id_perfil` int NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `usuario_perfil_unique` (`id_usuario`, `id_perfil`),
  KEY `fk_usuario_perfil_usuario_idx` (`id_usuario`),
  KEY `fk_usuario_perfil_perfil_idx` (`id_perfil`),
  CONSTRAINT `fk_usuario_perfil_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_usuario_perfil_perfil` FOREIGN KEY (`id_perfil`) REFERENCES `perfil` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


CREATE TABLE item_contrato (
    id INT PRIMARY KEY AUTO_INCREMENT,
    gestor_substituto VARCHAR(255) NOT NULL,
    Numero_Contrato VARCHAR(100) NOT NULL,
    Objeto VARCHAR(255) NOT NULL,
    Total_Horas_Contratadas FLOAT NOT NULL,
    Saldo_Horas FLOAT NOT NULL,
    Data_Inicio DATETIME NOT NULL,
    Data_Fim DATETIME NOT NULL
);

CREATE TABLE Status (
    id INT PRIMARY KEY AUTO_INCREMENT,
    Status VARCHAR(100) NOT NULL UNIQUE
);

CREATE TABLE catalogo_servicos (
    id INT PRIMARY KEY AUTO_INCREMENT,
    id_item_contrato INT,
    descricao VARCHAR(255) NOT NULL,
    FOREIGN KEY (id_item_contrato) REFERENCES item_contrato(id)
);

CREATE TABLE ordem_servico (
    id INT PRIMARY KEY AUTO_INCREMENT,
    Horas_Alocadas FLOAT NOT NULL,
    nup_sei VARCHAR(100) NOT NULL,
    Data_Emissao DATETIME NOT NULL,
    Data_Aceite DATETIME
);

CREATE TABLE area_atuacao (
    id INT PRIMARY KEY AUTO_INCREMENT,
    id_catalogo_servicos INT,
    descricao VARCHAR(255) NOT NULL UNIQUE,
    FOREIGN KEY (id_catalogo_servicos) REFERENCES catalogo_servicos(id)
);

CREATE TABLE atividade_macro (
    id INT PRIMARY KEY AUTO_INCREMENT,
    id_area_atuacao INT,
    descricao VARCHAR(255) NOT NULL UNIQUE,
    FOREIGN KEY (id_area_atuacao) REFERENCES area_atuacao(id)
);

CREATE TABLE servico (
    id INT PRIMARY KEY AUTO_INCREMENT,
    remuneracao FLOAT NOT NULL,
    base_horas_mes FLOAT NOT NULL,
    base_horas_complexidade FLOAT NOT NULL,
    sla_dias INT NOT NULL,
    estim_max_ano FLOAT NOT NULL,
    id_atividade_macro INT,
    FOREIGN KEY (id_atividade_macro) REFERENCES atividade_macro(id)
);

CREATE TABLE item_os (
    id INT PRIMARY KEY AUTO_INCREMENT,
    Quantidade_Horas FLOAT NOT NULL,
    Profissional_Alocado VARCHAR(255) NOT NULL DEFAULT 'Nenhum',
    id_servico INT,
    FOREIGN KEY (id_servico) REFERENCES servico(id)
);

CREATE TABLE tipo_documento (
    id INT PRIMARY KEY AUTO_INCREMENT,
    descricao VARCHAR(255) NOT NULL
);

CREATE TABLE documento_recebimento (
    id INT PRIMARY KEY AUTO_INCREMENT,
    id_os INT,
    Data_Assinatura DATETIME,
    nup_sei VARCHAR(100) NOT NULL,
    id_tipo_documento INT,
    id_usuario_fiscal_tecnico INT,
    id_usuario_fiscal_requisitante INT,
    id_usuario_gestor INT,
    FOREIGN KEY (id_os) REFERENCES ordem_servico(id),
    FOREIGN KEY (id_tipo_documento) REFERENCES tipo_documento(id),
    FOREIGN KEY (id_usuario_fiscal_tecnico) REFERENCES usuario(id),
    FOREIGN KEY (id_usuario_fiscal_requisitante) REFERENCES usuario(id),
    FOREIGN KEY (id_usuario_gestor) REFERENCES usuario(id)
);

CREATE TABLE avaliacao_qualidade_sla (
    id INT PRIMARY KEY AUTO_INCREMENT,
    id_documento_recebimento INT,
    Nota_INS1_Pontualidade FLOAT NOT NULL,
    Nota_INS2_Qualidade FLOAT NOT NULL,
    Percentual_Glosa FLOAT NOT NULL DEFAULT 0,
    FOREIGN KEY (id_documento_recebimento) REFERENCES documento_recebimento(id)
);

CREATE TABLE status_recebimento (
    id INT PRIMARY KEY AUTO_INCREMENT,
    descricao VARCHAR(255) NOT NULL
);

CREATE TABLE os_item_os (
    id_os INT,
    id_item_os INT,
    PRIMARY KEY (id_os, id_item_os),
    FOREIGN KEY (id_os) REFERENCES ordem_servico(id),
    FOREIGN KEY (id_item_os) REFERENCES item_os(id)
);

CREATE TABLE os_status_recebimento (
    id_os INT,
    id_status_recebimento INT,
    PRIMARY KEY (id_os, id_status_recebimento),
    FOREIGN KEY (id_os) REFERENCES ordem_servico(id),
    FOREIGN KEY (id_status_recebimento) REFERENCES status_recebimento(id)
);

CREATE TABLE usuario_os (
    id_os INT,
    id_usuario INT,
    PRIMARY KEY (id_os, id_usuario),
    FOREIGN KEY (id_os) REFERENCES ordem_servico(id),
    FOREIGN KEY (id_usuario) REFERENCES usuario(id)
);

CREATE TABLE usuario_recebimento (
    id_recebimento INT,
    id_usuario INT,
    PRIMARY KEY (id_recebimento, id_usuario),
    FOREIGN KEY (id_recebimento) REFERENCES documento_recebimento(id),
    FOREIGN KEY (id_usuario) REFERENCES usuario(id)
);