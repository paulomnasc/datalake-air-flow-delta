# 🐛 Como Debugar Airflow DAGs no VS Code

## ✅ Configuração Completa

Tudo já está configurado! O debugpy está rodando no container `airflow-scheduler` na porta 5678.

## 📋 Como Usar

### 1. **Coloque Breakpoints no Código**

Abra qualquer arquivo Python em `src/dags/` (ex: `factory_master.py` ou `lib/minio_tasks.py`) e clique na margem esquerda para adicionar breakpoints (bolinhas vermelhas).

### 2. **Conecte o Debugger**

1. No VS Code, pressione `F5` ou vá em **Run > Start Debugging**
2. Selecione: **"Python: Remote Attach (Airflow Scheduler)"**
3. O VS Code conectará ao container (você verá a barra de debug laranja aparecer)

### 3. **Trigger a DAG**

Depois de conectado, dispare sua DAG:

```bash
# Via Airflow CLI
docker-compose exec airflow-scheduler airflow dags trigger ingestao_customers_raw_ui_test3

# Ou via UI
# Acesse http://localhost:8085 e clique no botão "Play" da DAG
```

### 4. **Debug!**

Quando o código atingir seu breakpoint:
- ✅ A execução pausará
- ✅ Você verá variáveis no painel lateral
- ✅ Pode usar Step Over (F10), Step Into (F11), Continue (F5)
- ✅ Pode avaliar expressões no Debug Console

## 🎯 Breakpoints Úteis

### Em `factory_master.py`:
- **Linha ~280**: Quando processa cada registro do MySQL
- **Linha ~115**: Quando cria a DAG
- **Linha ~155**: Quando cria o PythonOperator (ETL task)

### Em `lib/minio_tasks.py`:
- **Início da função `transform_data_with_pandas`**: Ver os argumentos recebidos
- **Após download**: Verificar se arquivo baixou
- **Antes do upload**: Ver o destino do arquivo

## 🔍 Verificar se Está Funcionando

```bash
# Ver logs do debugpy
docker-compose logs airflow-scheduler | grep debugpy

# Ver se a porta está aberta
docker-compose exec airflow-scheduler netstat -tuln | grep 5678
```

## ⚠️ Troubleshooting

### Debugger não conecta?
```bash
# Reinicie o scheduler
docker-compose restart airflow-scheduler

# Aguarde 10 segundos e tente conectar novamente
```

### Breakpoint não para?
- Certifique-se que o código está sendo executado (trigger a DAG)
- Verifique se o breakpoint está em uma linha executável (não em comentários/linhas em branco)
- Verifique o path mapping em `.vscode/launch.json`

### Debugger conecta mas não para nos breakpoints?
- Desmarque "Just My Code" nas configurações do debugger (já está desabilitado no `launch.json`)
- Tente adicionar `import debugpy; debugpy.breakpoint()` direto no código

## 🚀 Dicas Pro

### Debug sem esperar no inicio
Se não quiser que o scheduler fique aguardando conexão no boot, mude no `entrypoint.sh`:
```bash
# De:
python -m debugpy --listen 0.0.0.0:5678 --wait-for-client -m airflow scheduler &

# Para:
python -m debugpy --listen 0.0.0.0:5678 -m airflow scheduler &
```
(Remove `--wait-for-client`)

### Debug apenas uma DAG específica
Use `airflow tasks test` para rodar apenas uma task:
```bash
docker-compose exec airflow-scheduler airflow tasks test \
  ingestao_customers_raw_ui_test3 \
  etl_process_for_customers_test \
  2025-11-23
```

### Adicionar breakpoint programático
Coloque direto no código Python:
```python
import debugpy
debugpy.breakpoint()  # Execution vai pausar aqui quando o debugger estiver conectado
```
