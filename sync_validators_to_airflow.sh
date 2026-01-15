#!/bin/bash
# ============================================================================
# SCRIPT: Sincronizar validadores do Git para Airflow DAGs
# ============================================================================
# Este script puxa os validadores salvos no GitHub e copia para o container Airflow
#
# Uso:
#   ./sync_validators_to_airflow.sh <arquivo_ou_padrao>
#
# Exemplos:
#   ./sync_validators_to_airflow.sh seu_validador.py
#   ./sync_validators_to_airflow.sh "*.py"
#   ./sync_validators_to_airflow.sh  # sincroniza TODOS os validadores
#
# ============================================================================

set -e  # Parar em erro

# Cores para output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configurações
REPO_ROOT=$(pwd)
VALIDADORES_PATTERN="${1:-.}"  # Padrão de arquivo (default: todos)
AIRFLOW_CONTAINER="airflow-scheduler"
AIRFLOW_DAGS_PATH="/opt/airflow/dags"

echo -e "${BLUE}════════════════════════════════════════════════════════════${NC}"
echo -e "${BLUE}SINCRONIZANDO VALIDADORES PARA AIRFLOW${NC}"
echo -e "${BLUE}════════════════════════════════════════════════════════════${NC}\n"

# ─────────────────────────────────────────────────────────────────────────
# ETAPA 1: Verificar se container existe
# ─────────────────────────────────────────────────────────────────────────

echo -e "${YELLOW}[1/5]${NC} Verificando container Airflow..."

if ! docker ps --filter "name=$AIRFLOW_CONTAINER" --format "{{.Names}}" | grep -q "$AIRFLOW_CONTAINER"; then
    echo -e "${RED}❌ Container '$AIRFLOW_CONTAINER' não está rodando!${NC}"
    echo "Containers disponíveis:"
    docker ps --format "table {{.Names}}\t{{.Status}}" | grep -i airflow || echo "  (nenhum)"
    exit 1
fi

echo -e "${GREEN}✅ Container $AIRFLOW_CONTAINER está rodando${NC}\n"

# ─────────────────────────────────────────────────────────────────────────
# ETAPA 2: Encontrar arquivos de validadores
# ─────────────────────────────────────────────────────────────────────────

echo -e "${YELLOW}[2/5]${NC} Procurando validadores em $REPO_ROOT..."

# Procurar por arquivos Python que contêm 'class' (validadores)
VALIDADORES=$(find "$REPO_ROOT" -maxdepth 1 -type f -name "*.py" 2>/dev/null | \
    grep -E "(validador|validator|cep_|CEP_)" | \
    sort)

if [ -z "$VALIDADORES" ]; then
    echo -e "${YELLOW}⚠️  Nenhum validador encontrado com padrão 'validador|validator|cep'${NC}"
    echo -e "${YELLOW}   Procurando por todos os arquivos .py com 'class' e 'def validate'...${NC}"
    VALIDADORES=$(find "$REPO_ROOT" -maxdepth 1 -type f -name "*.py" 2>/dev/null | \
        xargs grep -l "def validate\|class.*:" 2>/dev/null | \
        sort)
fi

if [ -z "$VALIDADORES" ]; then
    echo -e "${RED}❌ Nenhum arquivo Python encontrado!${NC}"
    exit 1
fi

echo -e "${GREEN}✅ Encontrados validadores:${NC}"
echo "$VALIDADORES" | while read -r file; do
    echo "   • $(basename "$file")"
done
echo ""

# ─────────────────────────────────────────────────────────────────────────
# ETAPA 3: Copiar para container
# ─────────────────────────────────────────────────────────────────────────

echo -e "${YELLOW}[3/5]${NC} Copiando validadores para container...\n"

COPIED_COUNT=0
FAILED_COUNT=0

echo "$VALIDADORES" | while read -r file; do
    if [ -z "$file" ]; then
        continue
    fi
    
    filename=$(basename "$file")
    echo -n "   → $filename ... "
    
    if docker cp "$file" "$AIRFLOW_CONTAINER:$AIRFLOW_DAGS_PATH/$filename" 2>/dev/null; then
        echo -e "${GREEN}✅${NC}"
        ((COPIED_COUNT++))
    else
        echo -e "${RED}❌${NC}"
        ((FAILED_COUNT++))
    fi
done

echo ""

# ─────────────────────────────────────────────────────────────────────────
# ETAPA 4: Verificar integridade
# ─────────────────────────────────────────────────────────────────────────

echo -e "${YELLOW}[4/5]${NC} Verificando integridade dos arquivos...\n"

echo "$VALIDADORES" | while read -r file; do
    if [ -z "$file" ]; then
        continue
    fi
    
    filename=$(basename "$file")
    echo -n "   • $filename: "
    
    # Testar import
    if docker exec "$AIRFLOW_CONTAINER" python3 -c "
import sys
sys.path.insert(0, '$AIRFLOW_DAGS_PATH')
try:
    import $(echo "$filename" | sed 's/.py$//')
    print('OK')
except Exception as e:
    print(f'ERROR: {e}')
    sys.exit(1)
" 2>&1 | grep -q "OK"; then
        echo -e "${GREEN}✅ Importação OK${NC}"
    else
        echo -e "${RED}❌ Erro ao importar${NC}"
        docker exec "$AIRFLOW_CONTAINER" python3 -c "
import sys
sys.path.insert(0, '$AIRFLOW_DAGS_PATH')
import $(echo "$filename" | sed 's/.py$//')
" 2>&1 | sed 's/^/      /'
    fi
done

echo ""

# ─────────────────────────────────────────────────────────────────────────
# ETAPA 5: Listar arquivos no container
# ─────────────────────────────────────────────────────────────────────────

echo -e "${YELLOW}[5/5]${NC} Validadores em $AIRFLOW_DAGS_PATH:\n"

docker exec "$AIRFLOW_CONTAINER" ls -lah "$AIRFLOW_DAGS_PATH"/*.py 2>/dev/null | grep -E "(validador|validator|cep_)" | \
    awk '{print "   • " $9 " (" $5 ")"}' || \
    echo "   (nenhum encontrado)"

echo ""
echo -e "${BLUE}════════════════════════════════════════════════════════════${NC}"
echo -e "${GREEN}✅ SINCRONIZAÇÃO CONCLUÍDA!${NC}"
echo -e "${BLUE}════════════════════════════════════════════════════════════${NC}\n"

echo "Próximos passos:"
echo "  1. Aguarde 30 segundos para Airflow recarregar DAGs"
echo "  2. Verifique no Airflow Web UI se a DAG apareceu"
echo "  3. Se não aparecer, reinicie o Airflow scheduler:"
echo ""
echo -e "     ${YELLOW}docker restart $AIRFLOW_CONTAINER${NC}"
echo ""

# Dar tempo para Airflow recarregar
echo -e "${YELLOW}⏳ Aguardando 30s para Airflow detectar novos arquivos...${NC}"
for i in {30..1}; do
    printf "\r   [%-30s] %2d segundos restantes" "$(printf '#%.0s' $(seq 1 $((30-i))))" "$i"
    sleep 1
done
echo ""

echo -e "${GREEN}✅ Feito! Verifique o Airflow Web UI.${NC}\n"
