#!/bin/bash
# Script para limpar duplicados no PostgreSQL BI
# Uso: ./clean_duplicates.sh

set -e

POSTGRES_HOST="${POSTGRES_HOST:-localhost}"
POSTGRES_PORT="${POSTGRES_PORT:-5432}"
POSTGRES_DB="${POSTGRES_DB:-datalake_bi}"
POSTGRES_USER="${POSTGRES_USER:-pbi_user}"
POSTGRES_PASSWORD="${POSTGRES_PASSWORD:-pbi_password}"

echo "🧹 Limpando registros duplicados no PostgreSQL BI"
echo "   Host: $POSTGRES_HOST"
echo "   Database: $POSTGRES_DB"
echo ""

# Exportar senha para psql não pedir
export PGPASSWORD="$POSTGRES_PASSWORD"

# Função para listar duplicados
list_duplicates() {
    echo "📊 Verificando duplicados em todas as tabelas delta_*..."
    psql -h $POSTGRES_HOST -p $POSTGRES_PORT -U $POSTGRES_USER -d $POSTGRES_DB -c "
        SELECT 
            schemaname,
            tablename,
            (SELECT COUNT(*) FROM information_schema.columns WHERE table_name = t.tablename) as columns
        FROM pg_tables t
        WHERE schemaname = 'public' AND tablename LIKE 'delta_%'
        ORDER BY tablename;
    "
}

# Função para contar registros antes de limpar
count_before() {
    local table=$1
    psql -h $POSTGRES_HOST -p $POSTGRES_PORT -U $POSTGRES_USER -d $POSTGRES_DB -t -c \
        "SELECT COUNT(*) FROM $table;" | xargs
}

# Função para contar registros únicos
count_unique() {
    local table=$1
    psql -h $POSTGRES_HOST -p $POSTGRES_PORT -U $POSTGRES_USER -d $POSTGRES_DB -t -c \
        "SELECT COUNT(DISTINCT *) FROM $table;" 2>/dev/null | xargs || echo "?"
}

# Listar tabelas delta
echo "🔍 Tabelas a processar:"
TABLES=$(psql -h $POSTGRES_HOST -p $POSTGRES_PORT -U $POSTGRES_USER -d $POSTGRES_DB -t -c \
    "SELECT tablename FROM pg_tables WHERE schemaname = 'public' AND tablename LIKE 'delta_%' ORDER BY tablename;")

if [ -z "$TABLES" ]; then
    echo "❌ Nenhuma tabela delta_* encontrada!"
    exit 1
fi

echo "$TABLES" | while read table; do
    echo "   - $table"
done

echo ""
echo "⚠️  AVISO: Este script vai deletar registros duplicados!"
echo "   Você tem 5 segundos para cancelar (Ctrl+C)"
sleep 5

# Processar cada tabela
echo ""
echo "🧹 Iniciando limpeza..."

TABLES | while read table; do
    if [ -z "$table" ]; then
        continue
    fi
    
    echo ""
    echo "📌 Processando: $table"
    
    # Contar antes
    count_before_val=$(count_before "$table")
    echo "   Total de registros: $count_before_val"
    
    # Listar colunas para GROUP BY
    columns=$(psql -h $POSTGRES_HOST -p $POSTGRES_PORT -U $POSTGRES_USER -d $POSTGRES_DB -t -c \
        "SELECT column_name FROM information_schema.columns WHERE table_name = '$table' LIMIT 1;" | xargs)
    
    echo "   Removendo duplicados..."
    
    # Delete duplicados usando CTE
    psql -h $POSTGRES_HOST -p $POSTGRES_PORT -U $POSTGRES_USER -d $POSTGRES_DB -c "
        WITH ranked AS (
            SELECT 
                ctid,
                ROW_NUMBER() OVER (PARTITION BY * ORDER BY ctid) as rn
            FROM $table
        )
        DELETE FROM $table
        WHERE ctid IN (
            SELECT ctid FROM ranked WHERE rn > 1
        );
    " 2>&1 | grep -i "DELETE" || echo "   ✓ DELETE executado"
    
    # Contar depois
    count_after_val=$(count_before "$table")
    removed=$((count_before_val - count_after_val))
    
    if [ $removed -gt 0 ]; then
        echo "   ✅ Removidos: $removed registros (antes: $count_before_val → depois: $count_after_val)"
    else
        echo "   ℹ️  Sem duplicados encontrados ($count_after_val registros)"
    fi
done

echo ""
echo "✅ Limpeza completa!"
echo ""
echo "🔄 Próximos passos:"
echo "   1. Verificar dados em PostgreSQL:"
echo "      psql -h $POSTGRES_HOST -U $POSTGRES_USER -d $POSTGRES_DB -c \\"
echo "        \"SELECT * FROM delta_customer LIMIT 5;\""
echo ""
echo "   2. Re-sincronizar via Airflow:"
echo "      airflow dags trigger sync_delta_dw_{seu_usuario} --conf '{\"bucket_name\":\"seu_bucket\"}'"
echo ""
echo "   3. Verificar Power BI para dados atualizados"
echo ""

unset PGPASSWORD
