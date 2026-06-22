{{ config(materialized='table') }}

with source_orders as (
    select * from {{ source('raw_lakehouse', 'orders') }}
)

select
    coalesce(ordernumber, id) as id_venda,
    coalesce(customernumber, customerid) as id_usuario,
    coalesce(orderdate, order_date) as data_venda,
    coalesce(requireddate, required_date) as data_limite,
    coalesce(shippeddate, shipped_date) as data_envio,
    status as status_venda,
    comments as observacoes
from source_orders
