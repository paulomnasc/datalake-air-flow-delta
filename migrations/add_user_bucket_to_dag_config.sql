-- Migration: Adicionar suporte a bucket por usuário em DAGs
-- Data: 2025-12-27
-- Descrição: Adiciona coluna user_bucket na tabela dag_config para isolamento de dados

USE lista_revisao;

-- 1. Adicionar coluna user_bucket
ALTER TABLE dag_config 
ADD COLUMN user_bucket VARCHAR(100) DEFAULT NULL COMMENT 'Bucket do usuário no formato user-{id}',
ADD INDEX idx_user_bucket (user_bucket);

-- 2. Popular buckets para DAGs existentes baseado no usuário criador
-- Nota: Ajuste 'created_by_user_id' para o nome real da coluna no seu schema
UPDATE dag_config dc
SET dc.user_bucket = CONCAT('user-', dc.created_by_user_id)
WHERE dc.user_bucket IS NULL 
  AND dc.created_by_user_id IS NOT NULL;

-- 3. Para DAGs sem usuário criador, usar bucket padrão lab01
UPDATE dag_config
SET user_bucket = 'lab01'
WHERE user_bucket IS NULL;

-- 4. Verificar resultados
SELECT 
    dag_id,
    user_bucket,
    created_by_user_id,
    created_at
FROM dag_config
ORDER BY created_at DESC
LIMIT 10;

-- 5. (Opcional) Tornar coluna NOT NULL após popular dados
-- ALTER TABLE dag_config MODIFY COLUMN user_bucket VARCHAR(100) NOT NULL;
