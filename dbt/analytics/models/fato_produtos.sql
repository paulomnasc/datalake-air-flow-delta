{{ config(materialized='table') }}

with source_scraped as (
    select * from {{ source('raw_lakehouse', 'produtos_scraped') }}
)

select
    -- Chave substituta única para o fato do produto
    md5(coalesce(site, '') || '_' || coalesce(categoria, '') || '_' || coalesce(produto, '')) as id_fato_produto,
    
    -- Chave estrangeira ligando à dim_categoria
    md5(coalesce(site, '') || '_' || coalesce(categoria, '')) as id_categoria,
    
    produto as descricao_produto,
    preco_original,
    preco_final,
    (coalesce(preco_original, preco_final) - preco_final) as valor_desconto,
    site as site_origem,
    _silver_processed_at as data_extracao
from source_scraped
