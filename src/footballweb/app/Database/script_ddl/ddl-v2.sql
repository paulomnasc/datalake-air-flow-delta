
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

CREATE TABLE item_os (
    id INT PRIMARY KEY AUTO_INCREMENT,
    Quantidade_Horas FLOAT NOT NULL,
    Profissional_Alocado VARCHAR(255) NOT NULL DEFAULT 'Nenhum',
    id_servico INT,
    FOREIGN KEY (id_servico) REFERENCES servico(id)
);

CREATE TABLE avaliacao_qualidade_sla (
    id INT PRIMARY KEY AUTO_INCREMENT,
    id_documento_recebimento INT,
    Nota_INS1_Pontualidade FLOAT NOT NULL,
    Nota_INS2_Qualidade FLOAT NOT NULL,
    Percentual_Glosa FLOAT NOT NULL DEFAULT 0,
    FOREIGN KEY (id_documento_recebimento) REFERENCES documento_recebimento(id)
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
    saldo_horas FLOAT NOT NULL DEFAULT 0,
    id_atividade_macro INT,
    FOREIGN KEY (id_atividade_macro) REFERENCES atividade_macro(id)
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

CREATE TABLE tipo_documento (
    id INT PRIMARY KEY AUTO_INCREMENT,
    descricao VARCHAR(255) NOT NULL
);
DELIMITER //

CREATE TRIGGER after_item_doc_insert
AFTER INSERT ON item_documento_recebimento
FOR EACH ROW
BEGIN
    DECLARE v_id_servico INT;
    DECLARE v_remuneracao FLOAT;
    
    SELECT s.id, s.remuneracao INTO v_id_servico, v_remuneracao
    FROM item_os io
    JOIN servico s ON io.id_servico = s.id
    WHERE io.id = NEW.id_item_os;

    UPDATE servico
    SET saldo_horas = saldo_horas - (NEW.quantidade_entregue * v_remuneracao)
    WHERE id = v_id_servico;
END //

CREATE TRIGGER after_item_doc_update
AFTER UPDATE ON item_documento_recebimento
FOR EACH ROW
BEGIN
    DECLARE v_id_servico INT;
    DECLARE v_remuneracao FLOAT;
    
    SELECT s.id, s.remuneracao INTO v_id_servico, v_remuneracao
    FROM item_os io
    JOIN servico s ON io.id_servico = s.id
    WHERE io.id = NEW.id_item_os;

    UPDATE servico
    SET saldo_horas = saldo_horas + (OLD.quantidade_entregue * v_remuneracao) - (NEW.quantidade_entregue * v_remuneracao)
    WHERE id = v_id_servico;
END //

CREATE TRIGGER after_item_doc_delete
AFTER DELETE ON item_documento_recebimento
FOR EACH ROW
BEGIN
    DECLARE v_id_servico INT;
    DECLARE v_remuneracao FLOAT;
    
    SELECT s.id, s.remuneracao INTO v_id_servico, v_remuneracao
    FROM item_os io
    JOIN servico s ON io.id_servico = s.id
    WHERE io.id = OLD.id_item_os;

    UPDATE servico
    SET saldo_horas = saldo_horas + (OLD.quantidade_entregue * v_remuneracao)
    WHERE id = v_id_servico;
END //

DELIMITER ;
