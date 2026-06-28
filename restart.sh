#!/bin/bash
# -------------------------------------------------------------
# Script de Reinicialização Rápida da Stack Airflow/Spark/CodeIgniter
# -------------------------------------------------------------

COMPOSE_FILE="docker-compose.yml"

echo "=================================================="
echo "  🔄 REINICIALIZAÇÃO COMPLETA DA STACK DATALAKE"
echo "=================================================="

echo ""
echo "1. 🛑 Parando todos os containers..."
docker compose -f $COMPOSE_FILE down

echo ""
echo "2. 🛠️  Reconstruindo imagens customizadas..."
echo "   - Airflow (webserver + scheduler)"
echo "   - CodeIgniter App"
echo "   - DuckDB (via ODBC no cliente)"
echo "   - Spark SQL (desativado)"
docker compose -f $COMPOSE_FILE build --no-cache airflow-webserver airflow-scheduler codeigniter-app

echo ""
echo "3. 🚀 Iniciando serviços base (Postgres, MySQL, Redis, MinIO)..."
docker compose -f $COMPOSE_FILE up -d postgres postgres-bi mysql redis minio

echo ""
echo "4. ⏳ Aguardando bancos de dados (15s)..."
sleep 15

echo ""
echo "5. 🦆 Iniciando DuckDB Query API (duckdb-api)..."
docker compose -f $COMPOSE_FILE up -d duckdb-api
echo "   ▸ Healthcheck: aguarde alguns segundos e verifique em http://localhost:5000/health"

echo ""
#echo "5. 📊 Iniciando Apache Atlas (Catálogo de Dados)..."
#docker compose -f $COMPOSE_FILE up -d atlas

echo ""
#echo "6. ⏳ Aguardando Atlas inicializar (30s)..."
#sleep 30

echo ""
echo "7. ✈️  Iniciando Airflow (webserver + scheduler + worker)..."
docker compose -f $COMPOSE_FILE up -d airflow-webserver airflow-scheduler airflow-worker

echo ""
echo "8. 🦆 DuckDB via ODBC: configure o driver no cliente (Power BI)."

echo ""
echo "9. 🔥 Iniciando Spark (master + worker)..."
docker compose -f $COMPOSE_FILE up -d spark spark-worker

echo ""
echo "9.1. ✅ Spark Thrift desativado. DuckDB é o endpoint SQL."

echo ""
echo "9. 🌐 Iniciando CodeIgniter App..."
docker compose -f $COMPOSE_FILE up -d codeigniter-app

echo ""
echo "9.2. 🌐 Iniciando FiscalWeb e Metabase..."
docker compose -f $COMPOSE_FILE up -d fiscalweb metabase

echo ""
echo "10. 📚 [OPCIONAL] Subindo Jupyter Lab (profile: atlas)..."
echo "    Execute manualmente se necessário: docker compose --profile atlas up -d pyspark-aula"

echo ""
echo "=========================================================="
echo "✅ STACK INICIALIZADA COM SUCESSO!"
echo "=========================================================="
echo ""
echo "🌐 URLs de Acesso:"
echo "   • Airflow UI:        http://localhost:8085"
echo "   • CodeIgniter App:   http://localhost:8088"
#echo "   • Apache Atlas:      http://localhost:21000 (admin/admin)"
echo "   • MinIO Console:     http://localhost:9001 (admin/admin123)"
echo "   • Spark Master UI:   http://localhost:8080"
echo ""
echo "📊 Serviços Ativos:"
docker compose ps
echo ""
echo "=========================================================="

# 🔄 Reiniciando e forçando recreate do Nginx (proxy reverso)
echo "11. 🔁 Forçando recreate do Nginx (proxy reverso)..."
docker compose up -d --force-recreate nginx

echo "12. 📋 Logs recentes do Nginx (proxy reverso):"
docker logs nginx-gateway --tail=30

echo ""
echo "=========================================================="