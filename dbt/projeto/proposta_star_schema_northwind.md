# Proposta de Modelagem Dimensional Star Schema (Northwind)

Este documento apresenta a especificação técnica para a modelagem do Star Schema de **Vendas (Sales)** a partir do banco de dados relacional OLTP Northwind. Toda a lógica de mapeamento, conversão e regras de negócio foi traduzida para os modelos do **dbt (Data Build Tool)** utilizando a sintaxe SQL combinada com macros Jinja, incluindo a geração de **Surrogate Keys (SK)** recomendadas pelas melhores práticas de Data Warehousing (Kimball).

---

## 📐 Desenho Conceitual do Star Schema

Abaixo está a representação visual das relações entre a tabela Fato de Vendas e suas respectivas dimensões no modelo utilizando as **Surrogate Keys**:

```mermaid
erDiagram
    fato_sales }|--|| dim_customers : "fk_cliente = sk_cliente"
    fato_sales }|--|| dim_employees : "fk_funcionario = sk_funcionario"
    fato_sales }|--|| dim_products : "fk_produto = sk_produto"
    fato_sales }|--|| dim_date : "fk_data_pedido = sk_data"
    dim_products }|--|| dim_categories : "fk_categoria = sk_categoria"
```

> [!NOTE]
> Como a tabela original de produtos do Northwind não possui uma tabela separada de categorias (a categoria é guardada como uma string simples na coluna `category` da tabela `products`), nós normalizamos essa informação extraindo uma dimensão separada chamada `dim_categories` ligada via surrogate key `sk_categoria`.

---

## 🗺️ Mapeamento de Dados ("De-Para")

| Modelo de Destino | Tipo | Modelos de Origem (dbt Gold) | Surrogate Key (Gerada via dbt_utils) | Descrição |
| :--- | :--- | :--- | :--- | :--- |
| **[fato_sales](file:///c:/Users/cblna/OneDrive/Documentos/datalake-air-flow-delta/dbt/projeto/proposta_star_schema_northwind.md#fato_sales)** | Fato | `gold_orders`, `gold_order_details` | `sk_venda` (chave composta do item e pedido) | Itens de pedidos, quantidades, valores e relacionamentos. |
| **[dim_customers](file:///c:/Users/cblna/OneDrive/Documentos/datalake-air-flow-delta/dbt/projeto/proposta_star_schema_northwind.md#dim_customers)** | Dimensão | `gold_customers` | `sk_cliente` (hash do `id` de cliente) | Dados cadastrais e de localização dos clientes. |
| **[dim_employees](file:///c:/Users/cblna/OneDrive/Documentos/datalake-air-flow-delta/dbt/projeto/proposta_star_schema_northwind.md#dim_employees)** | Dimensão | `gold_employees` | `sk_funcionario` (hash do `id` do funcionário) | Informações sobre a equipe de vendas. |
| **[dim_products](file:///c:/Users/cblna/OneDrive/Documentos/datalake-air-flow-delta/dbt/projeto/proposta_star_schema_northwind.md#dim_products)** | Dimensão | `gold_products` | `sk_produto` (hash do `id` do produto) | Catálogo de produtos com custos e preços sugeridos. |
| **[dim_categories](file:///c:/Users/cblna/OneDrive/Documentos/datalake-air-flow-delta/dbt/projeto/proposta_star_schema_northwind.md#dim_categories)** | Dimensão | `gold_products` | `sk_categoria` (hash do nome da categoria) | Lista distinta das categorias dos produtos. |
| **[dim_date](file:///c:/Users/cblna/OneDrive/Documentos/datalake-air-flow-delta/dbt/projeto/proposta_star_schema_northwind.md#dim_date)** | Dimensão | Gerador sintético dbt | `sk_data` (número no formato `YYYYMMDD`) | Dimensão de calendário para análise temporal. |

---

## 💻 Códigos dos Modelos dbt (Jinja + SQL)

<a id="dim_customers"></a>
### 👥 1. Dimensão Clientes (`dim_customers.sql`)

Gera a dimensão de clientes com sua chave substituta (`sk_cliente`) a partir do hash md5 do ID de origem.

```sql
{{ config(materialized='table') }}

with source_customers as (
    select * from {{ ref('gold_customers') }}
)

select
    -- Geração da Surrogate Key
    {{ dbt_utils.generate_surrogate_key(['customer_id']) }} as sk_cliente,
    customer_id as id_cliente,
    company_name as empresa,
    contact_name as nome_completo,
    contact_title as cargo,
    city as cidade,
    region as regiao,
    country as pais
from source_customers
```

---

<a id="dim_employees"></a>
### 👔 2. Dimensão Funcionários (`dim_employees.sql`)

Gera a dimensão de funcionários com sua respectiva chave substituta (`sk_funcionario`).

```sql
{{ config(materialized='table') }}

with source_employees as (
    select * from {{ ref('gold_employees') }}
)

select
    -- Geração da Surrogate Key
    {{ dbt_utils.generate_surrogate_key(['employee_id']) }} as sk_funcionario,
    employee_id as id_funcionario,
    concat(first_name, ' ', last_name) as nome_completo,
    title as cargo,
    city as cidade,
    region as regiao
    country as pais
from source_employees
```

---

<a id="dim_categories"></a>
### 🏷️ 3. Dimensão Categorias (`dim_categories.sql`)

Extrai as categorias únicas e gera a surrogate key (`sk_categoria`) com base no nome textual da categoria.

```sql
{{ config(materialized='table') }}

with unique_categories as (
    select distinct category from {{ ref('gold_products') }}
    where category is not null
)

select
    -- Geração da Surrogate Key baseada no nome textual único
    {{ dbt_utils.generate_surrogate_key(['category']) }} as sk_categoria,
    category_name as nome_categoria
from unique_categories
```

---

<a id="dim_products"></a>
### 📦 4. Dimensão Produtos (`dim_products.sql`)

Liga o produto à dimensão de categoria utilizando as respectivas surrogate keys geradas.

```sql
{{ config(materialized='table') }}

with source_products as (
    select * from {{ ref('gold_products') }}
),

categories as (
    select * from {{ ref('dim_categories') }}
)

select
    -- Geração da Surrogate Key do produto
    {{ dbt_utils.generate_surrogate_key(['p.product_id']) }} as sk_produto,
    p.product_id as id_produto,
    p.product_name as nome_produto,
    p.unit_price as preco_unitario,
    case when p.discontinued = 1 then 'Sim' else 'Não' end as descontinuado,
    c.sk_categoria as fk_categoria
from source_products p
left join categories c on p.category = c.nome_categoria
```

---

<a id="dim_date"></a>
### 📅 5. Dimensão Data (`dim_date.sql`)

Gera a dimensão de calendário. O campo numérico `sk_data` (YYYYMMDD) funciona como chave primária natural/substituta da dimensão.

```sql
{{ config(materialized='table') }}

with date_series as (
    select 
        cast(range as date) as data_completa
    from range(
        cast('2000-01-01' as date), 
        cast('2030-12-31' as date), 
        interval '1 day'
    )
)

select
    -- Chave de data numérica
    cast(strftime(data_completa, '%Y%m%d') as integer) as sk_data,
    data_completa as data,
    year(data_completa) as ano,
    month(data_completa) as mes,
    day(data_completa) as dia,
    quarter(data_completa) as trimestre,
    dayofweek(data_completa) as dia_semana,
    strftime(data_completa, '%B') as nome_mes,
    case 
        when dayofweek(data_completa) in (0, 6) then 'Fim de Semana' 
        else 'Dia Útil' 
    end as tipo_dia
from date_series
```

---

<a id="fato_sales"></a>
### 📊 6. Tabela Fato Vendas (`fato_sales.sql`)

A tabela fato consome os dados transacionais e substitui as chaves de negócio naturais (ex: `customer_id`) pelas surrogate keys das dimensões correspondentes.

```sql
{{ config(materialized='table') }}

with orders as (
    select * from {{ ref('gold_orders') }}
),

order_details as (
    select * from {{ ref('gold_order_details') }}
)

select
    -- 1. Chave Primária da Fato (Surrogate Key)
    {{ dbt_utils.generate_surrogate_key(['od.id', 'od.order_id']) }} as sk_venda,
    
    -- 2. Chaves Estrangeiras apontando para as SKs das Dimensões
    {{ dbt_utils.generate_surrogate_key(['o.customer_id']) }} as fk_cliente,
    {{ dbt_utils.generate_surrogate_key(['o.employee_id']) }} as fk_funcionario,
    {{ dbt_utils.generate_surrogate_key(['od.product_id']) }} as fk_produto,
    cast(strftime(o.order_date, '%Y%m%d') as integer) as fk_data_pedido,
    
    -- 3. Dimensões Degeneradas / Identificadores do Negócio (Rastreabilidade)
    od.order_id as id_pedido,
    od.id as id_item_pedido,
    
    -- 4. Métricas e Valores
    od.quantity as quantidade,
    od.unit_price as preco_unitario,
    od.discount as desconto,
    
    -- Valores gerais do pedido
    cast(o.shipping_fee as double) as frete_total_pedido,
    cast(o.taxes as double) as imposto_total_pedido,
    
    -- Métricas Analíticas Calculadas
    (od.quantity * od.unit_price) as receita_bruta,
    (od.quantity * od.unit_price * (1 - od.discount)) as receita_liquida

from order_details od
inner join orders o on od.order_id = o.id
```

---

## 🧪 Validações de Qualidade e Integridade (`schema.yml`)

Este arquivo define os testes no dbt que validarão a consistência e integridade das chaves substitutas no Star Schema:

```yaml
version: 2

models:
  - name: dim_customers
    description: "Tabela dimensional de Clientes"
    columns:
      - name: sk_cliente
        tests:
          - unique
          - not_null

  - name: dim_employees
    description: "Tabela dimensional de Funcionários"
    columns:
      - name: sk_funcionario
        tests:
          - unique
          - not_null

  - name: dim_products
    description: "Catálogo de Produtos"
    columns:
      - name: sk_produto
        tests:
          - unique
          - not_null
      - name: fk_categoria
        tests:
          - not_null
          - relationships:
              to: ref('dim_categories')
              field: sk_categoria

  - name: dim_categories
    description: "Tabela dimensional normalizada de Categorias"
    columns:
      - name: sk_categoria
        tests:
          - unique
          - not_null

  - name: dim_date
    description: "Dimensão de Calendário"
    columns:
      - name: sk_data
        tests:
          - unique
          - not_null

  - name: fato_sales
    description: "Tabela fato consolidada das transações de Vendas (Sales)"
    columns:
      - name: sk_venda
        tests:
          - unique
          - not_null
      - name: fk_cliente
        tests:
          - relationships:
              to: ref('dim_customers')
              field: sk_cliente
      - name: fk_funcionario
        tests:
          - relationships:
              to: ref('dim_employees')
              field: sk_funcionario
      - name: fk_produto
        tests:
          - relationships:
              to: ref('dim_products')
              field: sk_produto
      - name: fk_data_pedido
        tests:
          - relationships:
              to: ref('dim_date')
              field: sk_data
```
