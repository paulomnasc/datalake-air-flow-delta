## 🛠️ Corrigindo a DAG que não aparece no Airflow

Se você criou uma DAG em `src/dags/validate_and_move_raw_to_trusted.py` e ela **não aparece no console do Airflow**, verifique os seguintes pontos:

---

### ✅ 1. Localização correta do arquivo

Certifique-se de que o arquivo `.py` está na pasta mapeada como `dags` no `docker-compose.yml`:

```yaml
volumes:
  - ./src/dags:/opt/airflow/dags
```

---

### ✅ 2. Nome do arquivo

- O nome deve terminar com `.py`
- Evite espaços ou caracteres especiais

Exemplo válido: `validate_and_move_raw_to_trusted.py`

---

### ✅ 3. Estrutura mínima da DAG

O arquivo precisa conter uma DAG válida. Exemplo mínimo:

```python
from airflow import DAG
from airflow.operators.empty import EmptyOperator
from datetime import datetime

with DAG(
    dag_id='exemplo_dag',
    start_date=datetime(2023, 1, 1),
    schedule_interval='@daily',
    catchup=False
) as dag:
    tarefa = EmptyOperator(task_id='tarefa_inicial')
```

---

### ✅ 4. Verificar logs do Airflow

Use o comando:

```bash
docker logs airflow-webserver
```

Procure por mensagens como:

```
Failed to import DAG file ...
```

Isso indica erro de sintaxe ou import no seu script.

---

### ✅ 5. Permissões do arquivo

Verifique se o arquivo tem permissões de leitura:

```bash
ls -l src/dags/
```

---

### ✅ 6. Reiniciar o Airflow

Se tudo estiver certo, reinicie o webserver:

```bash
docker restart airflow-webserver
```

---

### ✅ 7. Corrigir indentação da DAG

No seu script original, a tarefa `PythonOperator` estava fora do bloco `with DAG`. Aqui está a versão corrigida:

```python
with DAG(
    dag_id='validate_and_move_raw_to_trusted',
    schedule_interval='@daily',
    default_args=default_args,
    description='Valida arquivos da zona raw e move para trusted com versionamento',
) as dag:

    validar_e_mover_task = PythonOperator(
        task_id='validar_e_mover',
        python_callable=validar_e_mover
    )
```

> A tarefa precisa estar **indentada dentro do bloco `with DAG`** para que o Airflow reconheça corretamente.

---

Após esses ajustes, sua DAG deve aparecer no console do Airflow em [http://localhost:8085](http://localhost:8085). Se ainda tiver problemas, revise os logs ou teste com uma DAG mínima para validar o ambiente.
