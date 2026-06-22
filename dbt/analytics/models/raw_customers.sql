{{ config(materialized='ephemeral') }}

-- Camada Raw: Representa o arquivo CSV bruto de origem dentro do pipeline multi-table.
-- No MySQL (tabela lista_revisao2.dag_configurations para a DAG 'pipe-northwind'), 
-- a coluna source_filename contém um array JSON com todos os arquivos do upload.
-- O arquivo específico para esta tabela é extraído do array buscando o elemento com sufixo '_customers.csv', ex:
-- "raw/pipe-northwind/20260405203705_6ad42056_customers.csv"
select * from {{ source('raw_lakehouse', 'customers') }}
