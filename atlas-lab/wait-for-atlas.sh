#!/bin/bash

echo "⏳ Aguardando Apache Atlas inicializar..."

timeout=600
counter=0

while [ $counter -lt $timeout ]; do
    if curl -s -f http://localhost:21000 > /dev/null 2>&1; then
        echo "✅ Apache Atlas disponível!"
        echo "🌐 URL: http://localhost:21000"
        echo "👤 Usuário: admin"
        echo "🔑 Senha: admin"
        exit 0
    fi
    
    echo "⏳ Aguardando... ($counter/$timeout segundos)"
    sleep 30
    counter=$((counter + 30))
done

echo "❌ Timeout: Atlas não iniciou em $timeout segundos"
echo "📋 Verificar logs: docker-compose logs atlas"