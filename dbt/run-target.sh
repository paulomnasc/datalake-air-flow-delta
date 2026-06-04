#!/bin/bash

# Com o ambiente virtual ativado, acesse o diretório do dbt e execute os comandos apontando para
 a pasta local de perfis (--profiles-dir .).
# 
# Você pode rodar os comandos especificando o target (ambiente de execução):
# 
# No Host local / Dev (aponta para localhost na porta exposta 5433):
# dbt run --profiles-dir . --target dev
# dbt test --profiles-dir . --target dev
# Dentro do Docker / Produção (aponta para o container postgres-bi na porta interna 5432):
# dbt run --profiles-dir . --target prod
# dbt test --profiles-dir . --target prod

# 🎯 Script auxiliar para executar dbt com target específico
# Uso: ./run-target.sh [dev|prod]

TARGET=${1:-dev}

cd "$(dirname "$0")/analytics" || exit 1

echo "🚀 Rodando dbt com target: $TARGET"
echo "────────────────────────────────────"

case $TARGET in
  dev)
    echo "📍 Conectando a localhost:5433 (Host Local - Homologação)"
    dbt run --profiles-dir . --target dev
    dbt test --profiles-dir . --target dev
    ;;
  prod)
    echo "📍 Conectando a postgres-bi:5432 (Docker - Produção)"
    dbt run --profiles-dir . --target prod
    dbt test --profiles-dir . --target prod
    ;;
  debug)
    echo "🔍 Testando conectividade..."
    dbt debug --profiles-dir . --target "${2:-dev}"
    ;;
  docs)
    echo "📖 Gerando documentação e linhagem..."
    dbt docs generate --profiles-dir .
    echo "🌐 Iniciando servidor na porta 8001..."
    dbt docs serve --profiles-dir . --port 8001
    ;;
  *)
    echo "❌ Target inválido!"
    echo ""
    echo "Opções disponíveis:"
    echo "  ./run-target.sh dev          # Rodar em homologação (localhost:5433)"
    echo "  ./run-target.sh prod         # Rodar em produção (postgres-bi:5432)"
    echo "  ./run-target.sh debug [dev|prod]  # Testar conectividade"
    echo "  ./run-target.sh docs         # Gerar docs e iniciar servidor"
    exit 1
    ;;
esac
