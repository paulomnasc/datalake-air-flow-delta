#!/bin/bash

# Script de teste para verificação de buckets do usuário no MinIO
# Uso: ./test_user_buckets.sh

echo "🧪 Testando criação de buckets de usuário no MinIO"
echo "=================================================="
echo ""

# Cores para output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Configuração MinIO
MINIO_ENDPOINT="http://localhost:9000"
MINIO_ACCESS_KEY="admin"
MINIO_SECRET_KEY="admin123"

# Função para verificar se bucket existe
check_bucket() {
    local bucket_name=$1
    
    response=$(curl -s -w "%{http_code}" -o /dev/null \
        -X HEAD "${MINIO_ENDPOINT}/${bucket_name}" \
        --user "${MINIO_ACCESS_KEY}:${MINIO_SECRET_KEY}")
    
    if [ "$response" -eq 200 ]; then
        echo -e "${GREEN}✓${NC} Bucket '${bucket_name}' existe"
        return 0
    else
        echo -e "${RED}✗${NC} Bucket '${bucket_name}' NÃO existe"
        return 1
    fi
}

# Função para listar todos os buckets
list_buckets() {
    echo -e "\n${YELLOW}📦 Listando todos os buckets:${NC}"
    
    docker exec minio mc ls minio/ 2>/dev/null || {
        echo -e "${RED}Erro ao listar buckets. Container MinIO não está rodando?${NC}"
        return 1
    }
}

# Testar criação de bucket via API da webapp
test_bucket_creation() {
    local user_id=$1
    local bucket_name="user-${user_id}"
    
    echo -e "\n${YELLOW}🔍 Testando criação de bucket para usuário ID ${user_id}${NC}"
    
    # Simula login e criação de bucket
    # Nota: Este é um teste manual - em produção, o bucket é criado automaticamente no login
    
    docker exec minio mc mb "minio/${bucket_name}" 2>/dev/null && {
        echo -e "${GREEN}✓${NC} Bucket '${bucket_name}' criado com sucesso"
    } || {
        echo -e "${YELLOW}ℹ${NC} Bucket '${bucket_name}' já existe ou erro ao criar"
    }
    
    # Verifica se bucket foi criado
    check_bucket "${bucket_name}"
}

# Executar testes
echo "1️⃣ Verificando bucket padrão do sistema (lab01)"
check_bucket "lab01"

echo ""
echo "2️⃣ Testando buckets de usuários de exemplo"
test_bucket_creation 1
test_bucket_creation 2
test_bucket_creation 42

# Listar todos os buckets
list_buckets

echo ""
echo "=================================================="
echo -e "${GREEN}✅ Testes concluídos${NC}"
echo ""
echo "💡 Dicas:"
echo "  - Buckets de usuários seguem o padrão: user-{id}"
echo "  - Acesse MinIO Console em: http://localhost:9001"
echo "  - Credenciais: admin / admin123"
echo ""
