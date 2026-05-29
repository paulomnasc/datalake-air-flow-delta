{{ config(materialized='table') }}

with source_customers as (
    select * from {{ source('raw_lakehouse', 'customers') }}
)

select
    customerid as id_usuario,
    name as nome_usuario,
    email as email_usuario,
    state as estado,
    creditlimit as limite_credito,
    customer_type as tipo_cliente,
    created_at,
    updated_at
from source_customers
