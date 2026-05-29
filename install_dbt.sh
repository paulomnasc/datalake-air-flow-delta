#!/usr/bin/env bash

# ==============================================================================
# Script de Instalação e Configuração do dbt Core (PostgreSQL) - MyDataFlow
# Uso: ./install_dbt.sh
# ==============================================================================

set -e

# Cores para output
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

echo -e "${BLUE}=== Iniciando Instalação do dbt Core ===${NC}"

# 1. Verificar Python 3
if ! command -v python3 &> /dev/null; then
    echo -e "${RED}Erro: Python 3 não está instalado no servidor.${NC}"
    exit 1
fi

PYTHON_VERSION=$(python3 -c 'import sys; print(".".join(map(str, sys.version_info[:2])))')
echo -e "Python detectado: ${GREEN}v${PYTHON_VERSION}${NC}"

# 2. Criar ambiente virtual python (.venv) se não existir
if [ ! -d ".venv" ]; then
    echo -e "${YELLOW}Criando ambiente virtual Python (.venv)...${NC}"
    python3 -m venv .venv
    echo -e "${GREEN}Ambiente virtual criado com sucesso.${NC}"
else
    echo -e "Ambiente virtual ${GREEN}.venv${NC} já existe."
fi

# 3. Ativar o ambiente virtual
echo -e "${YELLOW}Ativando o ambiente virtual...${NC}"
source .venv/bin/activate

# 4. Atualizar o PIP
echo -e "${YELLOW}Atualizando o pip...${NC}"
pip install --upgrade pip

# 5. Instalar dbt-core e dbt-postgres
echo -e "${YELLOW}Instalando dbt-core e dbt-postgres...${NC}"
# Se o arquivo requirements.txt existir na raiz, instala a partir dele. Caso contrário, faz pip install direto.
if [ -f "requirements.txt" ]; then
    echo -e "Instalando dependências via requirements.txt..."
    pip install -r requirements.txt
else
    echo -e "requirements.txt não encontrado na raiz. Instalando pacotes principais diretamente..."
    pip install dbt-core dbt-postgres
fi

# 6. Validar instalação do dbt
echo -e "${GREEN}=== Instalação Concluída com Sucesso! ===${NC}"
echo -e "Versão do dbt instalada:"
dbt --version

echo -e "\n${BLUE}Para rodar os comandos do dbt, lembre-se de ativar o ambiente virtual:${NC}"
echo -e "  ${YELLOW}source .venv/bin/activate${NC}"
echo -e "E execute os comandos apontando para a pasta local de perfis:"
echo -e "  ${YELLOW}cd dbt/analytics${NC}"
echo -e "  ${YELLOW}dbt debug --profiles-dir .${NC}"
