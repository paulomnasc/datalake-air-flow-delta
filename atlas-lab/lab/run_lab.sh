#!/bin/bash

echo "🧪 Iniciando Lab Apache Atlas + Python"

# Verificar se Atlas está rodando
echo "🔍 Verificando Apache Atlas..."
if curl -s -f http://localhost:21000 > /dev/null 2>&1; then
    echo "✅ Atlas está rodando"
else
    echo "❌ Atlas não está disponível. Execute: docker-compose up -d"
    exit 1
fi

# Instalar dependências
echo "📦 Instalando dependências Python..."
pip install -r requirements.txt

# Executar cliente básico
echo "🚀 Testando cliente Atlas..."
python3 atlas_client.py

echo "✅ Lab pronto! Consulte LAB_ATLAS_PYTHON.md para exercícios"