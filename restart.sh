#!/bin/bash
# -------------------------------------------------------------
# Script de Reinicialização Rápida da Stack Airflow/Spark/CodeIgniter
# -------------------------------------------------------------

COMPOSE_FILE="docker-compose.yml"

echo "1. 🛑 Parando a stack (shutdown)..."
# Garante uma parada limpa. Volumes nomeados são preservados.
docker-compose -f $COMPOSE_FILE down

echo "2. 🛠️ Reconstruindo imagens (se houver alterações nos Dockerfiles)..."
# Usa --no-cache para garantir que pegue o último código
docker-compose -f $COMPOSE_FILE build --no-cache

echo "3. 🚀 Subindo a stack novamente..."
# Reinicia todos os contêineres.
docker-compose -f $COMPOSE_FILE up -d

echo "--------------------------------------------------------"
echo "✅ Stack Reiniciada com Sucesso!"
echo "   - Airflow Webserver (DAGs): http://localhost:8085"
echo "   - CodeIgniter App (Front-end): http://localhost:8088"
echo "--------------------------------------------------------"