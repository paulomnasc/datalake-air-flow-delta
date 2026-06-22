{{ config(materialized='table') }}

with source_logs as (
    select * from {{ source('raw_lakehouse', 'activity_logs') }}
)

select
    id as id_acesso,
    user_id as id_usuario,
    method as metodo,
    uri,
    controller,
    action as acao,
    route_alias as rota,
    ip_address as ip,
    user_agent as navegador,
    created_at as data_acesso
from source_logs
