#!/bin/bash
# -------------------------------------------------------------
# Script de Parada da Stack Airflow/Spark/CodeIgniter
# -------------------------------------------------------------

COMPOSE_FILE="docker-compose.yml"

echo "1. 🛑 Parando todos os serviços da stack..."
# docker-compose down para parar e remover contêineres e redes.
# NOTA: Ele PRESERVA os volumes nomeados (pg_data, mysql_data).
docker-compose -f $COMPOSE_FILE down

echo "--------------------------------------------------------"
echo "✅ Stack Desligada com Sucesso!"
echo "   Os dados (Airflow Metastore, MySQL, MinIO) foram preservados."
echo "--------------------------------------------------------"

# OPCIONAL: Se você quisesse apagar TUDO (contêineres, redes e dados/volumes), você usaria:
# echo "Para apagar todos os dados e volumes, execute: docker-compose -f $COMPOSE_FILE down -v"