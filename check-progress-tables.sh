#!/bin/bash

# 🔍 Script para Validar DDLs das Tabelas de Progresso
# Verifica se as tabelas foram criadas corretamente no MySQL

# Cores para output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}=== 📊 Validação de Tabelas de Progresso ===${NC}\n"

# Verificar se MySQL está rodando
if ! docker ps | grep -q "mysql"; then
    echo -e "${RED}❌ MySQL não está rodando!${NC}"
    echo "Execute: docker-compose up -d mysql"
    exit 1
fi

# Pegar credenciais do .env
MYSQL_ROOT_PASSWORD=$(grep "^MYSQL_ROOT_PASSWORD=" .env | cut -d'=' -f2)
MYSQL_DATABASE=$(grep "^MYSQL_DATABASE=" .env | cut -d'=' -f2)
MYSQL_CONTAINER=$(docker ps | grep "mysql" | grep -o "mysql[^ ]*" | head -1)

if [ -z "$MYSQL_CONTAINER" ]; then
    echo -e "${RED}❌ Container MySQL não encontrado!${NC}"
    exit 1
fi

echo -e "${YELLOW}Container: ${MYSQL_CONTAINER}${NC}"
echo -e "${YELLOW}Database: ${MYSQL_DATABASE}${NC}\n"

# Função para executar query
run_query() {
    docker exec -i "$MYSQL_CONTAINER" mysql -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE" -e "$1" 2>/dev/null
}

# ============ VALIDAÇÃO 1: TABELA video_progress ============
echo -e "${BLUE}[1/3] Verificando tabela video_progress...${NC}"

if run_query "SHOW TABLES LIKE 'video_progress';" | grep -q "video_progress"; then
    echo -e "${GREEN}✅ Tabela video_progress existe${NC}"
    
    # Verificar estrutura
    echo -e "\n${YELLOW}Estrutura:${NC}"
    run_query "DESCRIBE video_progress;" | head -12
    
    # Verificar índices
    echo -e "\n${YELLOW}Índices:${NC}"
    run_query "SHOW INDEXES FROM video_progress;" | awk '{print $2, $3}' | head -6
    
    # Contar registros
    COUNT=$(run_query "SELECT COUNT(*) FROM video_progress;" | tail -1)
    echo -e "\n${YELLOW}Registros: ${COUNT}${NC}\n"
else
    echo -e "${RED}❌ Tabela video_progress NÃO existe!${NC}\n"
fi

# ============ VALIDAÇÃO 2: TABELA uc_progress ============
echo -e "${BLUE}[2/3] Verificando tabela uc_progress...${NC}"

if run_query "SHOW TABLES LIKE 'uc_progress';" | grep -q "uc_progress"; then
    echo -e "${GREEN}✅ Tabela uc_progress existe${NC}"
    
    # Verificar estrutura
    echo -e "\n${YELLOW}Estrutura:${NC}"
    run_query "DESCRIBE uc_progress;" | head -15
    
    # Verificar índices
    echo -e "\n${YELLOW}Índices:${NC}"
    run_query "SHOW INDEXES FROM uc_progress;" | awk '{print $2, $3}' | head -6
    
    # Contar registros
    COUNT=$(run_query "SELECT COUNT(*) FROM uc_progress;" | tail -1)
    echo -e "\n${YELLOW}Registros: ${COUNT}${NC}\n"
else
    echo -e "${RED}❌ Tabela uc_progress NÃO existe!${NC}\n"
fi

# ============ VALIDAÇÃO 3: QUERIES DE TESTE ============
echo -e "${BLUE}[3/3] Executando queries de teste...${NC}"

# Query 1: Progresso por aluno (video)
echo -e "\n${YELLOW}Progresso em Vídeos por Aluno:${NC}"
run_query "SELECT user_id, COUNT(*) as videos, SUM(completed) as completados, ROUND(AVG(percent), 2) as progresso_medio FROM video_progress GROUP BY user_id;" || echo -e "${YELLOW}(Nenhum dado)${NC}"

# Query 2: XP Total por aluno
echo -e "\n${YELLOW}XP Total por Aluno:${NC}"
run_query "SELECT user_id, SUM(xp_points) as total_xp, COUNT(*) as ucs_totais, SUM(completed) as ucs_completadas FROM uc_progress GROUP BY user_id;" || echo -e "${YELLOW}(Nenhum dado)${NC}"

# Query 3: Status de um módulo específico
echo -e "\n${YELLOW}Status de UC por Módulo (Exemplo):${NC}"
run_query "SELECT task_number, task_title, completed, xp_points FROM uc_progress WHERE module_id = 'mod-006' ORDER BY task_number LIMIT 5;" || echo -e "${YELLOW}(Nenhum dado)${NC}"

# ============ SUMMARY ============
echo -e "\n${BLUE}=== RESUMO ===${NC}"

TABLE1=$(run_query "SHOW TABLES LIKE 'video_progress';" | grep -c "video_progress")
TABLE2=$(run_query "SHOW TABLES LIKE 'uc_progress';" | grep -c "uc_progress")

if [ "$TABLE1" -eq 1 ] && [ "$TABLE2" -eq 1 ]; then
    echo -e "${GREEN}✅ Todas as tabelas foram criadas com sucesso!${NC}"
    echo -e "\n${YELLOW}Próximos passos:${NC}"
    echo "1. Executar migrations CodeIgniter: php spark migrate"
    echo "2. Testar a view progress-monitor: http://localhost:8088/curso/progress-monitor"
    echo "3. Implementar API persistência nos Controllers"
else
    echo -e "${RED}❌ Algumas tabelas estão faltando. Verifique os logs do MySQL:${NC}"
    echo "   docker logs $MYSQL_CONTAINER"
fi

echo -e "\n${BLUE}Script finalizado${NC}"
