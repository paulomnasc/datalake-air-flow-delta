#!/bin/bash
# -------------------------------------------------------------
# Script de Inicialização da Stack Airflow/Spark/CodeIgniter
# -------------------------------------------------------------

COMPOSE_FILE="docker-compose.yml"

echo "1. 🛠️ Construindo as imagens customizadas (Airflow, CodeIgniter, DuckDB)..."
# Inclui DuckDB pgwire e imagens com build local
docker compose -f $COMPOSE_FILE build --no-cache

echo "2. 🗄️ Iniciando serviços de infraestrutura (Postgres, MySQL, MinIO)..."
# Inicia os BDs e o MinIO em modo detached
docker compose -f $COMPOSE_FILE up -d postgres mysql minio

echo "Aguardando 10 segundos para o PostgreSQL e MySQL iniciarem..."
sleep 10

echo "3. ⚙️ Inicializando o Airflow Metastore (Executando 'initdb')..."
# Executa a migração do banco de dados (initdb)
docker compose -f $COMPOSE_FILE run --rm airflow-webserver airflow db migrate

echo "4. 👤 Verificando e Criando Usuário Admin (se não existir)..."

# Executa o 'airflow users list' e filtra a saída para verificar se 'admin' existe.
# O --rm é crucial para não deixar contêineres parados.
USER_EXISTS=$(docker compose -f $COMPOSE_FILE run --rm airflow-webserver airflow users list | grep 'admin')

# Condição para criar o usuário: se a variável USER_EXISTS estiver vazia (usuário não encontrado)
if [ -z "$USER_EXISTS" ]; then
    echo "  -> Usuário 'admin' não encontrado. Criando agora..."
    docker compose -f $COMPOSE_FILE run --rm airflow-webserver airflow users create \
        --username admin \
        --firstname Airflow \
        --lastname Admin \
        --role Admin \
        --email admin@example.com \
        --password admin
else
    echo "  -> Usuário 'admin' já existe. Pulando a criação."
fi

echo "5. 🚀 Subindo a stack completa (Airflow, CodeIgniter, Spark)..."
# Inicia todos os serviços restantes (Spark Thrift desativado; DuckDB via ODBC no cliente)
docker compose -f $COMPOSE_FILE up -d

echo "5.1. 🦆 DuckDB via ODBC: configure o driver no cliente (Power BI)."

echo "5.4. 🔥 Spark SQL Thrift desativado (usar DuckDB ODBC no cliente)"

echo "6. 📚 Subindo serviços de Catalogação (Apache Atlas) e Jupyter (profile atlas)..."
# Sobe Atlas e o Jupyter/PySpark do laboratório (perfil opcional 'atlas')
#docker compose -f $COMPOSE_FILE --profile atlas up -d atlas pyspark-aula

echo "--------------------------------------------------------"
echo "✅ Stack Iniciada com Sucesso!"
echo "   - Airflow Webserver (DAGs): http://localhost:8085"
echo "   - CodeIgniter App (Front-end): http://localhost:8088"
echo "   - MinIO Console: http://localhost:9001"
echo "   - DuckDB ODBC (cliente): configure DSN/local"
echo "   - Spark Master UI: http://localhost:8080"
#echo "   - Apache Atlas (Catálogo): http://localhost:21000 (admin/admin)"
#echo "   - Jupyter Notebook (Lab Atlas): http://localhost:8888 (token: tavares1234)"
echo "--------------------------------------------------------"