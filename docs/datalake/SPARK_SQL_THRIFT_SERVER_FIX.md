# Fix: Spark SQL Thrift Server - "spark-submit: command not found"

## Problema Identificado

O container `spark-sql` (id: c69108b0f868cf6900eb02b9508b1ec851ffbc9fba6caa082d7c55c73bb10932) estava falhando ao iniciar com o erro:

```
bash: spark-submit: command not found
```

### Causas Raiz

1. **PATH não configurado**: O comando `spark-submit` não estava acessível pois o diretório `/opt/spark/bin` não estava no `PATH` da variável de ambiente.

2. **Comando complexo inline**: O comando estava definido como uma string bash complexa dentro do `docker-compose.yml`, dificultando o debugging e a manutenção.

3. **Falta de verificação**: Não havia validação se os binários do Spark estavam realmente disponíveis antes de tentar executá-los.

4. **Variável de ambiente: AWS_SECRET_ACCESS_KEY** estava malformada (`admin12/var/www/html/vendor3` em vez de `admin123`).

## Solução Implementada

### 1. Criação de Script de Inicialização
Arquivo: `start-thriftserver.sh`

- Script bash dedicado que:
  - Define explicitamente `SPARK_HOME=/opt/spark`
  - Adiciona `$SPARK_HOME/bin` ao `PATH`
  - Verifica se `spark-submit` existe antes de executar
  - Fornece mensagens de erro claras se algo der errado
  - Executa o Spark SQL Thrift Server com todas as configurações necessárias

### 2. Atualização do Dockerfile.spark

- Adicionado:
  ```dockerfile
  ENV SPARK_HOME=/opt/spark
  ENV PATH=$SPARK_HOME/bin:$PATH
  ```
  
- Cópia e permissão de execução do script:
  ```dockerfile
  COPY start-thriftserver.sh /opt/spark-apps/start-thriftserver.sh
  RUN chmod +x /opt/spark-apps/start-thriftserver.sh
  ```

### 3. Atualização do docker-compose.yml

- Simplificado o comando para:
  ```yaml
  command: ["/opt/spark-apps/start-thriftserver.sh"]
  ```

- Adicionada variável de ambiente:
  ```yaml
  - SPARK_HOME=/opt/spark
  ```

- Corrigida a variável `AWS_SECRET_ACCESS_KEY` de `admin12/var/www/html/vendor3` para `admin123`

## Como Testar

### 1. Reconstruir a imagem do container
```bash
docker-compose build spark-sql
```

### 2. Reiniciar o container
```bash
docker-compose restart spark-sql
```

### 3. Verificar os logs
```bash
docker-compose logs -f spark-sql
```

Você deve ver:
```
🚀 Iniciando Spark SQL Thrift Server...
SPARK_HOME: /opt/spark
PATH: /opt/spark/bin:...
```

### 4. Verificar conexão ao Thrift Server
```bash
# Via JDBC (porta 10000)
# Via HTTP (porta 10001)
curl http://localhost:10001/
```

## Arquivos Modificados

1. **Criado**: `start-thriftserver.sh` - Script de inicialização
2. **Modificado**: `Dockerfile.spark` - Adicionadas variáveis de ambiente
3. **Modificado**: `docker-compose.yml` - Simplificado comando e corrigidas variáveis

## Próximos Passos (se necessário)

Se o container ainda não iniciar:

1. Verificar se a imagem `apache/spark:3.5.1` foi construída com sucesso
2. Checar se o container `spark` (master) está rodando
3. Validar conectividade de rede entre `spark-sql` e `spark`
4. Revisar logs: `docker logs spark-sql -f`

## Referências

- [Apache Spark Documentation](https://spark.apache.org/docs/latest/)
- [Spark SQL Thrift Server](https://spark.apache.org/docs/latest/sql-distributed-sql-engine.html)
