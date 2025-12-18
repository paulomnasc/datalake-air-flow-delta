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
docker-compose -f $COMPOSE_FILE down

echo ""
echo "2. 🛠️  Reconstruindo imagens customizadas..."
echo "   - Airflow (webserver + scheduler)"
echo "   - CodeIgniter App"
echo "   - Spark SQL"
docker-compose -f $COMPOSE_FILE build --no-cache airflow-webserver airflow-scheduler codeigniter-app spark-sql

echo ""
echo "3. 🚀 Iniciando serviços base (Postgres, MySQL, Redis, MinIO)..."
docker-compose -f $COMPOSE_FILE up -d postgres mysql redis minio

echo ""
echo "4. ⏳ Aguardando bancos de dados (15s)..."
sleep 15

echo ""
echo "5. 📊 Iniciando Apache Atlas (Catálogo de Dados)..."
docker-compose -f $COMPOSE_FILE up -d atlas

echo ""
echo "6. ⏳ Aguardando Atlas inicializar (30s)..."
sleep 30

echo ""
echo "7. ✈️  Iniciando Airflow (webserver + scheduler + worker)..."
docker-compose -f $COMPOSE_FILE up -d airflow-webserver airflow-scheduler airflow-worker

echo ""
echo "8. 🔥 Iniciando Spark (master + worker + SQL)..."
docker-compose -f $COMPOSE_FILE up -d spark spark-worker spark-sql

echo ""
echo "9. 🌐 Iniciando CodeIgniter App..."
docker-compose -f $COMPOSE_FILE up -d codeigniter-app

echo ""
echo "10. 📚 [OPCIONAL] Subindo Jupyter Lab (profile: atlas)..."
echo "    Execute manualmente se necessário: docker-compose --profile atlas up -d pyspark-aula"

echo ""
echo "=========================================================="
echo "✅ STACK INICIALIZADA COM SUCESSO!"
echo "=========================================================="
echo ""
echo "🌐 URLs de Acesso:"
echo "   • Airflow UI:        http://localhost:8085"
echo "   • CodeIgniter App:   http://localhost:8088"
echo "   • Apache Atlas:      http://localhost:21000 (admin/admin)"
echo "   • MinIO Console:     http://localhost:9001 (admin/admin123)"
echo "   • Spark Master UI:   http://localhost:8080"
echo ""
echo "📊 Serviços Ativos:"
docker-compose ps
echo ""
echo "=========================================================="