-- ====================================================================
-- SCRIPT DE EXCLUSÃO DAS TABELAS DO MÓDULO ÁGIL (EXCETO DEMANDAS)
-- ====================================================================
-- Este script realiza o DROP das tabelas do módulo ágil, preservando 
-- a tabela `agile_demandas`.
-- ====================================================================
-- 4. Reativa a checagem de chaves estrangeiras
SET FOREIGN_KEY_CHECKS = 1;



-- ====================================================================
-- ALTERNATIVA: APENAS LIMPAR DADOS (TRUNCATE) SEM APAGAR AS TABELAS
-- ====================================================================
-- Use os comandos abaixo caso queira apenas limpar os registros, 
-- mantendo a estrutura das tabelas intacta.
-- ====================================================================

SET FOREIGN_KEY_CHECKS = 0;

TRUNCATE TABLE agile_backlog_itens;
TRUNCATE TABLE agile_sprints;
TRUNCATE TABLE agile_cerimonias;
TRUNCATE TABLE agile_pareceres_homologacao;
TRUNCATE TABLE agile_releases;
TRUNCATE TABLE agile_demandas;

-- TRUNCATE TABLE `agile_sistemas`;
TRUNCATE TABLE item_doc_rec_lista_ver;
TRUNCATE TABLE item_documento_recebimento;
delete from item_documento_recebimento;
delete from documento_recebimento;
delete from os_item_os;
delete from ordem_servico;