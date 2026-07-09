{{ config(materialized='table') }}

with source_scraped as (
    select distinct 
        categoria,
        site
    from {{ source('raw_lakehouse', 'produtos_scraped') }}
    where categoria is not null
)

select
    -- Chave substituta única baseada no site e nome da categoria
    md5(coalesce(site, '') || '_' || coalesce(categoria, '')) as id_categoria,
    categoria as nome_categoria,
    site as site_origem
from source_scraped
