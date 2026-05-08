import re

ddl_v2 = """CREATE TABLE tipo_documento (
    id INT PRIMARY KEY AUTO_INCREMENT,
    descricao VARCHAR(255) NOT NULL
);

CREATE TABLE status (
    id INT PRIMARY KEY AUTO_INCREMENT,
    descricao VARCHAR(100) NOT NULL UNIQUE
);

CREATE TABLE status_recebimento (
    id INT PRIMARY KEY AUTO_INCREMENT,
    descricao VARCHAR(255) NOT NULL
);

CREATE TABLE item_contrato (
    id INT PRIMARY KEY AUTO_INCREMENT,
    gestor_substituto VARCHAR(255) NOT NULL,
    numero_contrato VARCHAR(100) NOT NULL,
    objeto VARCHAR(255) NOT NULL,
    total_horas_contratadas FLOAT NOT NULL,
    saldo_horas FLOAT NOT NULL,
    data_inicio DATETIME NOT NULL,
    data_fim DATETIME NOT NULL
);

CREATE TABLE catalogo_servicos (
    id INT PRIMARY KEY AUTO_INCREMENT,
    id_item_contrato INT,
    descricao VARCHAR(255) NOT NULL,
    FOREIGN KEY (id_item_contrato) REFERENCES item_contrato(id)
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
    quantidade_horas FLOAT NOT NULL,
    profissional_alocado VARCHAR(255) NOT NULL DEFAULT 'Nenhum',
    id_servico INT,
    FOREIGN KEY (id_servico) REFERENCES servico(id)
);

CREATE TABLE ordem_servico (
    id INT PRIMARY KEY AUTO_INCREMENT,
    horas_alocadas FLOAT NOT NULL,
    nup_sei VARCHAR(100) NOT NULL,
    data_emissao DATETIME NOT NULL,
    data_aceite DATETIME
);

CREATE TABLE documento_recebimento (
    id INT PRIMARY KEY AUTO_INCREMENT,
    id_os INT,
    data_assinatura DATETIME,
    nup_sei VARCHAR(100) NOT NULL,
    id_tipo_documento INT,
    id_usuario_fiscal_tecnico INT UNSIGNED,
    id_usuario_fiscal_requisitante INT UNSIGNED,
    id_usuario_gestor INT UNSIGNED,
    FOREIGN KEY (id_os) REFERENCES ordem_servico(id),
    FOREIGN KEY (id_tipo_documento) REFERENCES tipo_documento(id),
    FOREIGN KEY (id_usuario_fiscal_tecnico) REFERENCES usuario(id),
    FOREIGN KEY (id_usuario_fiscal_requisitante) REFERENCES usuario(id),
    FOREIGN KEY (id_usuario_gestor) REFERENCES usuario(id)
);

CREATE TABLE avaliacao_qualidade_sla (
    id INT PRIMARY KEY AUTO_INCREMENT,
    id_documento_recebimento INT,
    nota_ins1_pontualidade FLOAT NOT NULL,
    nota_ins2_qualidade FLOAT NOT NULL,
    percentual_glosa FLOAT NOT NULL DEFAULT 0,
    FOREIGN KEY (id_documento_recebimento) REFERENCES documento_recebimento(id)
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
    id_usuario INT UNSIGNED,
    PRIMARY KEY (id_os, id_usuario),
    FOREIGN KEY (id_os) REFERENCES ordem_servico(id),
    FOREIGN KEY (id_usuario) REFERENCES usuario(id)
);

CREATE TABLE usuario_recebimento (
    id_recebimento INT,
    id_usuario INT UNSIGNED,
    PRIMARY KEY (id_recebimento, id_usuario),
    FOREIGN KEY (id_recebimento) REFERENCES documento_recebimento(id),
    FOREIGN KEY (id_usuario) REFERENCES usuario(id)
);
"""

with open('/root/datalake-air-flow-delta/src/fiscalweb/app/Database/script_ddl/ddl.sql', 'r') as f:
    content = f.read()

# Preserve up to line 35
lines = content.split('\n')
base_ddl = '\n'.join(lines[:35])

new_ddl = base_ddl + '\n\n' + ddl_v2

with open('/root/datalake-air-flow-delta/src/fiscalweb/app/Database/script_ddl/ddl.sql', 'w') as f:
    f.write(new_ddl)

print("ddl.sql updated!")
