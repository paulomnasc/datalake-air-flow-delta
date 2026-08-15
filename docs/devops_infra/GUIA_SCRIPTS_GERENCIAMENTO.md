# 🚀 Guia Rápido de Scripts de Gerenciamento

## Scripts Disponíveis

### 1. `./startup.sh` - Inicialização Completa
**Quando usar**: Primeira vez ou após limpar todos os containers/volumes

**O que faz**:
- ✅ Constrói imagens customizadas
- ✅ Inicia PostgreSQL, MySQL, MinIO
- ✅ Executa migrations do Airflow
- ✅ Cria usuário admin (se não existir)
- ✅ Inicia toda a stack
- ✅ **Garante que Spark SQL Thrift Server está rodando**
- ✅ Valida status do Spark SQL

**Uso**:
```bash
./startup.sh
```

---

### 2. `./restart.sh` - Reinicialização Completa
**Quando usar**: Após mudanças em código, configurações ou quando algo não está funcionando

**O que faz**:
- ✅ Para todos os containers
- ✅ Reconstrói imagens (Airflow, CodeIgniter, **Spark SQL**)
- ✅ Inicia serviços em ordem correta
- ✅ **Aguarda e valida Spark SQL Thrift Server (20s)**
- ✅ Mostra logs de confirmação

**Uso**:
```bash
./restart.sh
```

**Saída esperada para Spark SQL**:
```
8.2. ✅ Verificando status do Spark SQL...
  -> ✅ Spark SQL Thrift Server rodando (porta 10000 para Power BI/ODBC)
  -> 📊 Logs recentes:
  spark-sql  | INFO HiveThriftServer2: HiveThriftServer2 started
```

---

### 3. `./check-health.sh` - Verificação de Saúde
**Quando usar**: Para verificar rapidamente o status de todos os serviços

**O que faz**:
- ✅ Verifica status de cada container
- ✅ **Validação especial para Spark SQL**:
  - Verifica se está rodando
  - Confirma que HiveThriftServer2 iniciou
  - Mostra logs recentes se houver problema
- ✅ Lista todas as portas e URLs
- ✅ Retorna erro se serviços críticos estão parados

**Uso**:
```bash
./check-health.sh
```

**Serviços críticos monitorados**:
- postgres
- mysql
- spark
- **spark-sql** ⭐
- airflow-webserver
- airflow-scheduler

---

## Garantias para Spark SQL Thrift Server

### Em `startup.sh`:
```bash
# Passo 5.1: Garante que está rodando
docker compose up -d spark-sql

# Passo 5.2: Aguarda 15 segundos
sleep 15

# Passo 5.3: Valida status
✅ Spark SQL Thrift Server está rodando (porta 10000)
```

### Em `restart.sh`:
```bash
# Passo 8: Inicia explicitamente
docker compose up -d spark spark-worker spark-sql

# Passo 8.1: Aguarda 20 segundos
sleep 20

# Passo 8.2: Valida e mostra logs
✅ Spark SQL Thrift Server rodando (porta 10000 para Power BI/ODBC)
📊 Logs recentes: HiveThriftServer2 started
```

---

## Troubleshooting

### Se Spark SQL não iniciar:

1. **Verificar logs**:
```bash
docker-compose logs spark-sql --tail=50
```

2. **Verificar se JARs do Delta estão presentes**:
```bash
docker-compose exec spark-sql ls -la /home/spark/.ivy2/jars/ | grep delta
```

3. **Reiniciar apenas o Spark SQL**:
```bash
docker-compose restart spark-sql
sleep 20
docker-compose logs spark-sql | grep "HiveThriftServer2 started"
```

4. **Reconstruir do zero**:
```bash
docker-compose stop spark-sql
docker-compose rm -f spark-sql
docker-compose build spark-sql
docker-compose up -d spark-sql
```

---

## Conexão Power BI

Após executar `./startup.sh` ou `./restart.sh`, o Spark SQL estará disponível em:

- **Host**: `137.131.212.68` (ou `localhost` se local)
- **Porta**: `10000`
- **Database**: `default`
- **User**: `spark`
- **Password**: *(deixar vazio)*

**Documentação completa**: [PowerBI_Conexao_DeltaLake_ODBC.md](./PowerBI_Conexao_DeltaLake_ODBC.md)

---

## Ordem de Execução Recomendada

### Primeira instalação:
```bash
./startup.sh
./check-health.sh
```

### Após mudanças:
```bash
./restart.sh
```

### Verificação diária:
```bash
./check-health.sh
```
