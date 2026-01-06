#!/bin/bash
# -------------------------------------------------------------
# Script de Restauração do MySQL - Metadados das DAGs
# -------------------------------------------------------------

COMPOSE_FILE="docker-compose.yml"
BACKUP_DIR="./backups/mysql"

echo "=========================================="
echo "  🔄 RESTAURAÇÃO DO MYSQL - METADADOS"
echo "=========================================="
echo ""

# Lista backups disponíveis
echo "📋 Backups disponíveis:"
echo ""
ls -lht $BACKUP_DIR/lista_revisao2_*.sql 2>/dev/null | head -10 | nl

if [ ! -f "$BACKUP_DIR"/lista_revisao2_*.sql ]; then
    echo ""
    echo "❌ Nenhum backup encontrado em $BACKUP_DIR"
    echo ""
    echo "💡 Execute primeiro: ./backup-mysql.sh"
    exit 1
fi

echo ""
echo "Selecione o backup para restaurar (número) ou pressione Enter para o mais recente:"
read -r BACKUP_NUM

# Se não especificado, usa o mais recente
if [ -z "$BACKUP_NUM" ]; then
    BACKUP_FILE=$(ls -t $BACKUP_DIR/lista_revisao2_*.sql 2>/dev/null | head -1)
else
    BACKUP_FILE=$(ls -t $BACKUP_DIR/lista_revisao2_*.sql 2>/dev/null | sed -n "${BACKUP_NUM}p")
fi

if [ -z "$BACKUP_FILE" ]; then
    echo "❌ Backup não encontrado!"
    exit 1
fi

echo ""
echo "📂 Arquivo selecionado: $BACKUP_FILE"
echo "⚠️  Isso vai SUBSTITUIR todos os dados atuais do schema lista_revisao2!"
echo ""
echo "Continuar? (s/N)"
read -r CONFIRM

if [ "$CONFIRM" != "s" ] && [ "$CONFIRM" != "S" ]; then
    echo "Operação cancelada."
    exit 0
fi

echo ""
echo "🗄️  Restaurando backup..."
docker-compose exec -T mysql mysql -uroot -proot lista_revisao2 < "$BACKUP_FILE"

if [ $? -eq 0 ]; then
    echo ""
    echo "✅ Backup restaurado com sucesso!"
    
    # Mostra estatísticas
    DAGS=$(docker-compose exec -T mysql mysql -uroot -proot lista_revisao2 -N -e "SELECT COUNT(*) FROM dag_configurations" 2>/dev/null | tr -d '\r')
    TABLES=$(docker-compose exec -T mysql mysql -uroot -proot lista_revisao2 -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='lista_revisao2'")
    
    echo ""
    echo "📊 Dados restaurados:"
    echo "   - Tabelas: $TABLES"
    echo "   - DAGs configuradas: $DAGS"
else
    echo ""
    echo "❌ Erro ao restaurar backup!"
    exit 1
fi
