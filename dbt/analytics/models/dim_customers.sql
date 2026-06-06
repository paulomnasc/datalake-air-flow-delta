{{ config(materialized='table') }}

with source_data as (
    select * from {{ ref('gold_customers') }}
)

select
    customer_id as id_usuario,
    company_name as nome,
    contact_name as contato
from source_data
