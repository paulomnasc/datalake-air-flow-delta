#!/bin/bash
# -------------------------------------------------------------
# Script de Backup do MySQL - Metadados das DAGs
# -------------------------------------------------------------

COMPOSE_FILE="docker-compose.yml"
BACKUP_DIR="./backups/mysql"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)

echo "=========================================="
echo "  💾 BACKUP DO MYSQL - METADADOS"
echo "=========================================="
echo ""

# Cria diretório de backup se não existir
mkdir -p $BACKUP_DIR

echo "📦 Fazendo backup do schema lista_revisao2 (metadados das DAGs)..."
docker-compose exec -T mysql mysqldump -uroot -proot \
    --single-transaction \
    --routines \
    --triggers \
    lista_revisao2 > "$BACKUP_DIR/lista_revisao2_${TIMESTAMP}.sql"

if [ $? -eq 0 ]; then
    echo "✅ Backup criado: $BACKUP_DIR/lista_revisao2_${TIMESTAMP}.sql"
    
    # Informações sobre o backup
    TABLES=$(docker-compose exec -T mysql mysql -uroot -proot lista_revisao2 -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='lista_revisao2'")
    DAGS=$(docker-compose exec -T mysql mysql -uroot -proot lista_revisao2 -N -e "SELECT COUNT(*) FROM dag_configurations" 2>/dev/null | tr -d '\r')
    
    echo ""
    echo "📊 Estatísticas do backup:"
    echo "   - Tabelas: $TABLES"
    echo "   - DAGs configuradas: $DAGS"
    echo "   - Tamanho: $(du -h "$BACKUP_DIR/lista_revisao2_${TIMESTAMP}.sql" | cut -f1)"
else
    echo "❌ Erro ao criar backup!"
    exit 1
fi

echo ""
echo "📦 Fazendo backup do schema lista_revisao (Northwind - opcional)..."
docker-compose exec -T mysql mysqldump -uroot -proot \
    --single-transaction \
    --routines \
    --triggers \
    lista_revisao > "$BACKUP_DIR/lista_revisao_${TIMESTAMP}.sql"

if [ $? -eq 0 ]; then
    echo "✅ Backup Northwind criado: $BACKUP_DIR/lista_revisao_${TIMESTAMP}.sql"
fi

echo ""
echo "📦 Fazendo backup do schema fiscal..."
docker-compose exec -T mysql mysqldump -uroot -proot \
    --single-transaction \
    --routines \
    --triggers \
    fiscal > "$BACKUP_DIR/fiscal_${TIMESTAMP}.sql"

if [ $? -eq 0 ]; then
    echo "✅ Backup Fiscal criado: $BACKUP_DIR/fiscal_${TIMESTAMP}.sql"
fi

echo ""
echo "📝 Mantendo apenas os últimos 10 backups..."
ls -t $BACKUP_DIR/lista_revisao2_*.sql | tail -n +11 | xargs -r rm
ls -t $BACKUP_DIR/lista_revisao_*.sql | tail -n +11 | xargs -r rm
ls -t $BACKUP_DIR/fiscal_*.sql | tail -n +11 | xargs -r rm

echo ""
echo "✅ Backup concluído!"
echo ""
echo "Backups disponíveis:"
ls -lh $BACKUP_DIR/*.sql | tail -5
