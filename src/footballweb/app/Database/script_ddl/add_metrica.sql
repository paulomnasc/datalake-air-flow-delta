-- Criar tabela metrica_contrato
CREATE TABLE IF NOT EXISTS metrica_contrato (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    sigla VARCHAR(10) NOT NULL UNIQUE,
    descricao TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Inserir dados padrão de métricas
INSERT IGNORE INTO metrica_contrato (id, nome, sigla, descricao) VALUES
(1, 'Horas', 'H', 'Pagamento baseado em horas trabalhadas/base do perfil de serviço.'),
(2, 'Pontos de Função', 'PF', 'Pagamento baseado na quantidade de pontos de função (PF) entregues.'),
(3, 'Profissional Alocado', 'PROF', 'Pagamento mensal fixo por profissional alocado (Squad/Alocação).');

-- Adicionar coluna id_metrica na tabela item_contrato
ALTER TABLE item_contrato ADD COLUMN id_metrica INT DEFAULT 1;

-- Atualizar itens existentes para apontar para a métrica de Horas (H)
UPDATE item_contrato SET id_metrica = 1 WHERE id_metrica IS NULL OR id_metrica = 0;

-- Adicionar constraint de chave estrangeira
ALTER TABLE item_contrato ADD CONSTRAINT fk_item_contrato_metrica 
FOREIGN KEY (id_metrica) REFERENCES metrica_contrato(id);
