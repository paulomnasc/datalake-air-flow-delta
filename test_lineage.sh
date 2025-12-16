#!/bin/bash
# Script de Teste Rápido do Data Lineage
# Execute este script após reiniciar os containers

set -e

echo "╔════════════════════════════════════════════════════════════════╗"
echo "║    TESTE DE DATA LINEAGE - APACHE ATLAS                        ║"
echo "╚════════════════════════════════════════════════════════════════╝"
echo ""

# 1. Verificar se containers estão rodando
echo "1️⃣  Verificando containers..."
ATLAS_STATUS=$(docker ps --filter "name=apache-atlas" --format "{{.Status}}" | head -n 1)
SCHEDULER_STATUS=$(docker ps --filter "name=airflow-scheduler" --format "{{.Status}}" | head -n 1)

if [ -z "$ATLAS_STATUS" ]; then
    echo "   ❌ Container apache-atlas não está rodando!"
    echo "   💡 Execute: docker-compose up -d"
    exit 1
fi

if [ -z "$SCHEDULER_STATUS" ]; then
    echo "   ❌ Container airflow-scheduler não está rodando!"
    echo "   💡 Execute: docker-compose up -d"
    exit 1
fi

echo "   ✅ Containers rodando:"
echo "      • apache-atlas: $ATLAS_STATUS"
echo "      • airflow-scheduler: $SCHEDULER_STATUS"
echo ""

# 2. Verificar variáveis de ambiente do Atlas
echo "2️⃣  Verificando configuração do Atlas..."
ATLAS_REGISTER=$(docker exec airflow-scheduler env | grep ATLAS_REGISTER_PROCESSES | cut -d'=' -f2)

if [ "$ATLAS_REGISTER" != "true" ]; then
    echo "   ⚠️  ATLAS_REGISTER_PROCESSES=$ATLAS_REGISTER"
    echo "   ❌ Deveria ser 'true' para habilitar lineage!"
    echo ""
    echo "   💡 Corrija no docker-compose.yml e reinicie:"
    echo "      docker-compose down && docker-compose up -d"
    exit 1
fi

echo "   ✅ ATLAS_REGISTER_PROCESSES=true"
echo ""

# 3. Aguardar Atlas ficar pronto
echo "3️⃣  Aguardando Atlas inicializar..."
MAX_ATTEMPTS=30
ATTEMPT=0

while [ $ATTEMPT -lt $MAX_ATTEMPTS ]; do
    if curl -s -u admin:admin "http://localhost:21000/api/atlas/admin/version" > /dev/null 2>&1; then
        echo "   ✅ Atlas está pronto!"
        break
    fi
    ATTEMPT=$((ATTEMPT + 1))
    echo -n "."
    sleep 5
done

if [ $ATTEMPT -eq $MAX_ATTEMPTS ]; then
    echo ""
    echo "   ⚠️  Atlas não respondeu após 150 segundos"
    echo "   💡 Verifique os logs: docker logs apache-atlas"
    exit 1
fi

echo ""

# 4. Executar validador Python
echo "4️⃣  Executando validador de lineage..."
echo ""

if [ -f "scripts/validate_atlas_lineage.py" ]; then
    python3 scripts/validate_atlas_lineage.py
else
    echo "   ⚠️  Script de validação não encontrado!"
    echo "   💡 Certifique-se de estar no diretório correto"
    exit 1
fi

echo ""
echo "╔════════════════════════════════════════════════════════════════╗"
echo "║                    TESTE CONCLUÍDO                             ║"
echo "╚════════════════════════════════════════════════════════════════╝"
