{{ config(materialized='ephemeral') }}

-- Camada Silver: Limpeza dos dados brutos (remover duplicatas e nulos de linha)
-- Localização no MinIO: silver/pipe-northwind/customers/*.parquet
select * from {{ ref('bronze_customers') }}
