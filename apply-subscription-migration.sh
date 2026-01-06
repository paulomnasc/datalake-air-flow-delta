#!/bin/bash

# Script para aplicar a migration do sistema de assinatura
# Autor: Sistema de Assinatura MyFlow Lab
# Data: 2026-01-05

echo "=================================================="
echo "  Sistema de Controle de Assinatura"
echo "  Aplicando Migration no Banco de Dados"
echo "=================================================="
echo ""

# Cores para output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Caminho para o arquivo SQL
MIGRATION_FILE="src/codeigniter-app/app/Database/Migrations/add_subscription_fields_to_usuario.sql"

# Verificar se o arquivo existe
if [ ! -f "$MIGRATION_FILE" ]; then
    echo -e "${RED}❌ Erro: Arquivo de migration não encontrado!${NC}"
    echo "Caminho esperado: $MIGRATION_FILE"
    exit 1
fi

echo -e "${GREEN}✅ Arquivo de migration encontrado${NC}"
echo ""

# Solicitar credenciais do MySQL
echo "Por favor, informe as credenciais do MySQL:"
read -p "Host (padrão: localhost): " DB_HOST
DB_HOST=${DB_HOST:-localhost}

read -p "Porta (padrão: 3306): " DB_PORT
DB_PORT=${DB_PORT:-3306}

read -p "Usuário (padrão: root): " DB_USER
DB_USER=${DB_USER:-root}

read -sp "Senha: " DB_PASS
echo ""

read -p "Nome do banco (padrão: lista_revisao): " DB_NAME
DB_NAME=${DB_NAME:-lista_revisao}

echo ""
echo -e "${YELLOW}Conectando ao banco de dados...${NC}"

# Executar migration
mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < "$MIGRATION_FILE" 2>&1

if [ $? -eq 0 ]; then
    echo ""
    echo -e "${GREEN}=================================================="
    echo "✅ Migration aplicada com sucesso!"
    echo "==================================================${NC}"
    echo ""
    echo "Campos adicionados à tabela 'usuario':"
    echo "  - data_ultimo_pagamento"
    echo "  - data_vencimento_assinatura"
    echo "  - status_assinatura"
    echo "  - data_inicio_trial"
    echo ""
    echo "Próximos passos:"
    echo "  1. Adicionar QR Code real em app/Views/subscription/renew.php"
    echo "  2. Testar o sistema com um novo usuário"
    echo "  3. Consultar SUBSCRIPTION_SYSTEM_GUIDE.md para mais detalhes"
    echo ""
else
    echo ""
    echo -e "${RED}=================================================="
    echo "❌ Erro ao aplicar migration!"
    echo "==================================================${NC}"
    echo ""
    echo "Possíveis causas:"
    echo "  - Credenciais incorretas"
    echo "  - Banco de dados não existe"
    echo "  - Campos já existem na tabela"
    echo "  - Permissões insuficientes"
    echo ""
    echo "Verifique os logs acima para mais detalhes"
    exit 1
fi
