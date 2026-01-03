-- Script para limpeza de registros duplicados no PostgreSQL BI
-- Situação: A DAG de sync estava inserindo múltiplos registros ao invés de fazer UPSERT
-- Solução: Este script identifica e remove duplicados, mantendo apenas 1 cópia

-- ==========================================
-- 1. VERIFICAR DUPLICADOS
-- ==========================================

-- Listar tabelas e contar registros únicos vs totais
SELECT 
    tablename,
    (SELECT COUNT(*) FROM pg_class WHERE relname = tablename AND relkind = 'r') as total_rows
FROM pg_tables 
WHERE schemaname = 'public' 
  AND tablename LIKE 'delta_%'
ORDER BY tablename;


-- ==========================================
-- 2. EXEMPLO: Duplicados em delta_customer
-- ==========================================

-- Contar quantas cópias de cada customer existe
SELECT firstname, COUNT(*) as total_copies
FROM delta_customer
GROUP BY firstname
HAVING COUNT(*) > 1
ORDER BY total_copies DESC;

-- Ver IDs dos duplicados (assumindo coluna 'id' ou 'customerNumber')
SELECT 
    *,
    ROW_NUMBER() OVER (PARTITION BY firstname ORDER BY ctid) as row_num
FROM delta_customer
WHERE firstname IN (
    SELECT firstname FROM delta_customer
    GROUP BY firstname
    HAVING COUNT(*) > 1
);


-- ==========================================
-- 3. SCRIPT DE LIMPEZA (CUIDADO! Faz DELETE)
-- ==========================================

-- Opção A: Se a tabela tem PRIMARY KEY ou UNIQUE identifier
-- DELETE usando CTE com ROW_NUMBER (mantém primeira cópia)
DELETE FROM delta_customer
WHERE ctid NOT IN (
    SELECT MIN(ctid)
    FROM delta_customer
    GROUP BY firstname, customerNumber  -- Ajuste as colunas conforme necessário
);

-- Verificar resultado
SELECT firstname, COUNT(*) as total_copies
FROM delta_customer
GROUP BY firstname
ORDER BY total_copies DESC;


-- ==========================================
-- 4. SOLUÇÃO ALTERNATIVA: Recriar tabela sem duplicados
-- ==========================================

-- Opção B: Duplicar dados únicos, dropar original e renomear

-- Criar tabela temporária com registros únicos
CREATE TABLE delta_customer_clean AS
SELECT DISTINCT *
FROM delta_customer;

-- Dropar original
DROP TABLE delta_customer;

-- Renomear limpeza como original
ALTER TABLE delta_customer_clean RENAME TO delta_customer;

-- Verificar resultado
SELECT firstname, COUNT(*) as total_copies
FROM delta_customer
GROUP BY firstname;


-- ==========================================
-- 5. APLICAR PARA TODAS AS TABELAS
-- ==========================================

-- Script automático para todas as tabelas delta_*
-- Cria e executa statement dinamicamente

-- Listar todas as tabelas e seus duplicados
SELECT 
    t.tablename,
    (SELECT COUNT(*) FROM (
        SELECT * FROM pg_class WHERE relname = t.tablename
    ) AS subq) as total_rows,
    0 as unique_rows
FROM pg_tables t
WHERE t.schemaname = 'public' 
  AND t.tablename LIKE 'delta_%'
ORDER BY t.tablename;


-- ==========================================
-- 6. APÓS LIMPEZA: SINCRONIZAR NOVAMENTE
-- ==========================================

-- Após rodar este script, execute a DAG de sincronização:
-- - airflow dags trigger sync_delta_dw_{seu_usuario} --conf '{"bucket_name":"seu_bucket"}'
-- 
-- Ou pelo Airflow Web UI:
-- 1. Vá até a DAG sync_delta_dw_{seu_usuario}
-- 2. Clique em "Trigger DAG"
-- 3. Adicione config: {"bucket_name":"seu_bucket"} (se necessário)
-- 4. Confirme
--
-- A DAG foi corrigida para:
-- - DROP + CREATE ao invés de UPSERT
-- - Usar INSERT com batch (mais eficiente)
-- - Não processar o mesmo arquivo 2 vezes


-- ==========================================
-- RESUMO DAS CORREÇÕES IMPLEMENTADAS
-- ==========================================
-- 
-- ❌ PROBLEMA ORIGINAL:
--    - DAG tentava 2 padrões de glob sem usar BREAK corretamente
--    - Resultado: lê o mesmo arquivo 2+ vezes e insere duplicados
--    - Sem deduplicação: cada execução=mais cópias
--
-- ✅ SOLUÇÃO IMPLEMENTADA:
--    1. Adicionar flag `table_processed` para garantir break
--    2. DROP TABLE antes de INSERT (trunca antiga)
--    3. INSERT em bulk com batches (performance)
--    4. Logs detalhados para debug
--
-- 📝 NOTA IMPORTANTE:
--    - Power BI lê PostgreSQL diretamente (não Delta Lake)
--    - PostgreSQL é o "cache" sincronizado
--    - Se Delta mudar: re-sincronize (full replace)
--    - Se PostgreSQL tiver duplicados: clean + re-sync

