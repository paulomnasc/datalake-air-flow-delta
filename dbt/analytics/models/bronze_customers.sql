{{ config(materialized='ephemeral') }}

-- Camada Bronze: Conversão do CSV bruto em arquivo ParquetSnappy
-- Localização no MinIO: bronze/pipe-northwind/customers/*.parquet
select * from {{ ref('raw_customers') }}
