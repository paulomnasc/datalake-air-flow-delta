{% macro generate_schema_name(custom_schema_name, node) -%}

    {%- if target.name == 'dev' -%}
        
        {# Força toda a materialização do ambiente DEV no schema homolog_analytics #}
        homolog_analytics

    {%- else -%}

        {# Em produção, materializa no schema principal de analytics #}
        analytics

    {%- endif -%}

{%- endmacro %}
