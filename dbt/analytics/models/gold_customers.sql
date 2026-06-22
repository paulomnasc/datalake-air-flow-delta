{{ config(materialized='ephemeral') }}

-- Camada Gold: Dados agregados consolidados e prontos para consumo analítico
-- Localização no MinIO: gold/pipe-northwind/customers/*.parquet
select * from {{ ref('silver_customers') }}
