-- Criação da Tabela de Sistemas
CREATE TABLE IF NOT EXISTS agile_sistemas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    sigla VARCHAR(20) NOT NULL,
    descricao TEXT,
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Criação da Tabela de Demandas (com FK de Sistema)
CREATE TABLE IF NOT EXISTS agile_demandas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_sistema INT NULL, -- FK de relacionamento 1:N
    titulo VARCHAR(255) NOT NULL,
    descricao TEXT,
    sistema_critico TINYINT(1) DEFAULT 0,
    status VARCHAR(50) DEFAULT 'Triagem', -- 'Triagem', 'Preparar Demanda SERPRO', 'Alocar Time Fábricas', 'Refinamento Backlog', 'Sprint Planning', 'Em Execução', 'Homologação', 'Sprint Review', 'Submissão Release', 'CCM', 'SERPRO', 'Atualizado Produção'
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_sistema) REFERENCES agile_sistemas(id) ON DELETE SET NULL
);

-- Tabela de Itens de Backlog (Histórias de Usuário / Requisitos)
CREATE TABLE IF NOT EXISTS agile_backlog_itens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_demanda INT NOT NULL,
    titulo VARCHAR(255) NOT NULL,
    criterios_aceite TEXT,
    pontuacao INT DEFAULT 0,
    ordem INT DEFAULT 0,
    status_kanban VARCHAR(50) DEFAULT 'A Fazer', -- 'A Fazer', 'Em Desenvolvimento', 'Teste/QA', 'Impedimento', 'Pronto'
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_demanda) REFERENCES agile_demandas(id) ON DELETE CASCADE
);

-- Tabela de Sprints
CREATE TABLE IF NOT EXISTS agile_sprints (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_demanda INT NOT NULL,
    meta TEXT NOT NULL,
    data_inicio DATE NOT NULL,
    data_fim DATE NOT NULL,
    status VARCHAR(20) DEFAULT 'Planejamento', -- 'Planejamento', 'Ativa', 'Concluída'
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_demanda) REFERENCES agile_demandas(id) ON DELETE CASCADE
);

-- Tabela de Cerimônias e Ritos
CREATE TABLE IF NOT EXISTS agile_cerimonias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_demanda INT NOT NULL,
    tipo_cerimonia VARCHAR(50) NOT NULL, -- 'Kick-Off', 'Refinamento', 'Sprint Planning', 'Daily', 'Sprint Review', 'Retrospectiva', 'Homologação', 'Reunião Alinhamento CCM'
    data_hora_agendada DATETIME NOT NULL,
    data_hora_realizada DATETIME NULL,
    participantes_presentes JSON NULL, -- Lista de IDs de usuários presentes
    ata_descritiva TEXT NULL,
    link_gravacao VARCHAR(512) NULL,
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_demanda) REFERENCES agile_demandas(id) ON DELETE CASCADE
);

-- Tabela de Pareceres de Homologação
CREATE TABLE IF NOT EXISTS agile_pareceres_homologacao (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_demanda INT NOT NULL,
    id_usuario_po INT NOT NULL,
    parecer VARCHAR(20) NOT NULL, -- 'Favorável', 'Rejeitado'
    observacoes TEXT NULL,
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_demanda) REFERENCES agile_demandas(id) ON DELETE CASCADE
);

-- Tabela de Submissões de Release
CREATE TABLE IF NOT EXISTS agile_releases (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_demanda INT NOT NULL,
    ticket_rdm VARCHAR(100) NOT NULL,
    metadados JSON NULL, -- Guarda metadados básicos inseridos manualmente
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_demanda) REFERENCES agile_demandas(id) ON DELETE CASCADE
);
