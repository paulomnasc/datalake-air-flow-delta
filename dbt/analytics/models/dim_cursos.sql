{{ config(materialized='table') }}

with source_products as (
    select * from {{ source('raw_lakehouse', 'products') }}
)

select
    coalesce(productcode, id) as id_produto,
    coalesce(productname, name) as nome_produto,
    coalesce(productline, category) as categoria,
    coalesce(quantityinstock, quantity_in_stock, 0) as quantidade_estoque,
    coalesce(buyprice, buy_price, 0.0) as preco_compra,
    coalesce(msrp, 0.0) as preco_sugerido
from source_products
