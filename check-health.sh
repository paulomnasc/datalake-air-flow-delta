#!/bin/bash
# -------------------------------------------------------------
# Script de Verificação de Saúde da Stack Datalake
# -------------------------------------------------------------

COMPOSE_FILE="docker-compose.yml"

echo "=========================================="
echo "  🏥 VERIFICAÇÃO DE SAÚDE DA STACK"
echo "=========================================="
echo ""

# Função para verificar status de um serviço
check_service() {
    local service=$1
    local port=$2
    local description=$3
    
    if docker compose -f $COMPOSE_FILE ps $service | grep -q "Up"; then
        echo "✅ $description ($service) - Rodando"
        if [ ! -z "$port" ]; then
            echo "   └─ Porta: $port"
        fi
        return 0
    else
        echo "❌ $description ($service) - PARADO"
        return 1
    fi
}

# Verificando serviços base
echo "📦 INFRAESTRUTURA:"
check_service "postgres" "5432" "PostgreSQL (Airflow Metastore)"
check_service "mysql" "3306" "MySQL (Dados de Negócio)"
check_service "redis" "6379" "Redis (Celery Backend)"
check_service "minio" "9000/9001" "MinIO (Object Storage)"

echo ""
echo "✈️  AIRFLOW:"
check_service "airflow-webserver" "8085" "Webserver"
check_service "airflow-scheduler" "" "Scheduler"
check_service "airflow-worker" "" "Worker (Celery)"

echo ""
echo "🔥 SPARK:"
check_service "spark" "7077/8080" "Master"
check_service "spark-worker" "8081" "Worker"

# Verificação especial para Spark SQL
echo ""
if check_service "spark-sql" "10000" "Thrift Server (ODBC/JDBC)"; then
    echo "   └─ Verificando logs de inicialização..."
    if docker compose -f $COMPOSE_FILE logs spark-sql | grep -q "HiveThriftServer2 started"; then
        echo "   └─ ✅ Thrift Server iniciado com sucesso"
        echo "   └─ 📊 Pronto para conexões Power BI/Tableau/DBeaver"
    else
        echo "   └─ ⚠️  Thrift Server pode não ter iniciado completamente"
        echo "   └─ Últimas linhas do log:"
        docker compose -f $COMPOSE_FILE logs spark-sql --tail=5 | sed 's/^/      /'
    fi
fi

echo ""
echo "🌐 APLICAÇÕES:"
check_service "codeigniter-app" "8088" "WebApp (Config de DAGs)"
check_service "atlas" "21000" "Apache Atlas (Catálogo)"

echo ""
echo "=========================================="
echo "  📋 RESUMO DE PORTAS"
echo "=========================================="
echo "Airflow UI:      http://localhost:8085"
echo "Spark Master UI: http://localhost:8080"
echo "MinIO Console:   http://localhost:9001"
echo "WebApp Config:   http://localhost:8088"
echo "Apache Atlas:    http://localhost:21000"
echo "Spark SQL JDBC:  jdbc:hive2://localhost:10000/default"
echo ""
echo "=========================================="

# Retorna erro se algum serviço crítico estiver parado
CRITICAL_SERVICES="postgres mysql spark spark-sql airflow-webserver airflow-scheduler"
FAILED=0

for service in $CRITICAL_SERVICES; do
    if ! docker compose -f $COMPOSE_FILE ps $service | grep -q "Up"; then
        FAILED=1
    fi
done

if [ $FAILED -eq 1 ]; then
    echo "⚠️  ATENÇÃO: Alguns serviços críticos não estão rodando!"
    echo "Execute: ./restart.sh para reiniciar a stack"
    exit 1
else
    echo "✅ Todos os serviços críticos estão rodando!"
    exit 0
fi
