#!/bin/bash

# Script de validação do isolamento de buckets por usuário
# Testa se usuários conseguem acessar apenas seus próprios buckets

echo "🔒 Teste de Isolamento de Buckets por Usuário"
echo "=============================================="
echo ""

# Cores
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# Configuração
WEBAPP_URL="http://localhost:8088"
QUERY_BUILDER_ENDPOINT="${WEBAPP_URL}/query-builder/execute"

# Função para testar query
test_query() {
    local test_name=$1
    local sql=$2
    local expected_result=$3
    
    echo -e "${BLUE}Test:${NC} ${test_name}"
    echo "SQL: ${sql}"
    
    response=$(curl -s -X POST "${QUERY_BUILDER_ENDPOINT}" \
        -H "Content-Type: application/json" \
        -H "Cookie: ci_session=YOUR_SESSION_HERE" \
        -d "{\"sql\":\"${sql}\",\"limit\":10}")
    
    if echo "$response" | grep -q "$expected_result"; then
        echo -e "${GREEN}✓ PASS${NC}"
    else
        echo -e "${RED}✗ FAIL${NC}"
        echo "Response: $response"
    fi
    echo ""
}

echo "📋 Checklist de Implementação"
echo "=============================="
echo ""

# 1. Verificar se SessionHelper existe
echo -n "1. SessionHelper criado... "
if [ -f "src/codeigniter-app/app/Helpers/SessionHelper.php" ]; then
    echo -e "${GREEN}✓${NC}"
else
    echo -e "${RED}✗${NC}"
fi

# 2. Verificar se MinioHelper existe
echo -n "2. MinioHelper criado... "
if [ -f "src/codeigniter-app/app/Helpers/MinioHelper.php" ]; then
    echo -e "${GREEN}✓${NC}"
else
    echo -e "${RED}✗${NC}"
fi

# 3. Verificar se migration existe
echo -n "3. Migration SQL criada... "
if [ -f "migrations/add_user_bucket_to_dag_config.sql" ]; then
    echo -e "${GREEN}✓${NC}"
else
    echo -e "${RED}✗${NC}"
fi

# 4. Verificar se documentação existe
echo -n "4. Documentação criada... "
if [ -f "docs/BUCKET_ISOLATION_DAGS.md" ]; then
    echo -e "${GREEN}✓${NC}"
else
    echo -e "${RED}✗${NC}"
fi

# 5. Verificar imports no ConfigController
echo -n "5. ConfigController atualizado... "
if grep -q "use App\\\\Helpers\\\\SessionHelper;" src/codeigniter-app/app/Controllers/ConfigController.php; then
    echo -e "${GREEN}✓${NC}"
else
    echo -e "${RED}✗${NC}"
fi

# 6. Verificar imports no QueryBuilderController
echo -n "6. QueryBuilderController atualizado... "
if grep -q "use App\\\\Helpers\\\\SessionHelper;" src/codeigniter-app/app/Controllers/QueryBuilderController.php; then
    echo -e "${GREEN}✓${NC}"
else
    echo -e "${RED}✗${NC}"
fi

# 7. Verificar imports no UsuarioController
echo -n "7. UsuarioController atualizado... "
if grep -q "use App\\\\Helpers\\\\MinioHelper;" src/codeigniter-app/app/Controllers/UsuarioController.php; then
    echo -e "${GREEN}✓${NC}"
else
    echo -e "${RED}✗${NC}"
fi

echo ""
echo "📦 Verificando Buckets MinIO"
echo "=============================="
echo ""

# Listar buckets existentes
echo "Buckets disponíveis:"
docker exec minio mc ls minio/ 2>/dev/null | awk '{print "  - " $NF}' || echo -e "${RED}Erro ao listar buckets${NC}"

echo ""
echo "🧪 Testes de Validação Manual"
echo "=============================="
echo ""

echo -e "${YELLOW}Para executar testes completos:${NC}"
echo ""
echo "1. Faça login como Usuário A (ID=1)"
echo "   curl -X POST ${WEBAPP_URL}/logar -d 'email=userA@test.com&senha=123'"
echo ""
echo "2. Teste acesso ao próprio bucket (deve funcionar):"
echo "   SELECT * FROM read_parquet('s3://user-1/bronze/dados.parquet') LIMIT 10"
echo ""
echo "3. Teste acesso a bucket de outro usuário (deve falhar):"
echo "   SELECT * FROM read_parquet('s3://user-2/bronze/dados.parquet') LIMIT 10"
echo "   Esperado: 'Acesso negado: você não pode consultar dados de outros usuários'"
echo ""
echo "4. Verifique que o header mostra o bucket correto:"
echo "   Acesse ${WEBAPP_URL}/query-builder"
echo "   Deve aparecer: '📦 Seu bucket: user-1'"
echo ""

echo "🔍 Verificações de Segurança"
echo "=============================="
echo ""

echo "Arquivos modificados para isolamento:"
grep -l "SessionHelper" src/codeigniter-app/app/Controllers/*.php 2>/dev/null | \
    sed 's/^/  ✓ /' || echo -e "${YELLOW}Nenhum controller usando SessionHelper ainda${NC}"

echo ""
echo "Validações de acesso implementadas:"
grep -r "validateUserS3Path" src/codeigniter-app/app/Controllers/*.php 2>/dev/null | \
    cut -d: -f1 | uniq | sed 's/^/  ✓ /' || echo -e "${YELLOW}Nenhuma validação encontrada${NC}"

echo ""
echo "=============================================="
echo -e "${GREEN}✅ Verificação Concluída${NC}"
echo ""
echo "⚠️  Próximos passos:"
echo "  1. Executar migration SQL no banco de dados"
echo "  2. Fazer login na webapp e verificar criação automática de buckets"
echo "  3. Testar upload de arquivo (deve ir para user-{id}/raw/)"
echo "  4. Executar DAG e verificar processamento em bucket isolado"
echo "  5. Validar que Query Builder mostra apenas arquivos do usuário"
echo ""
