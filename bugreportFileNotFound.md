

```markdown
# 🐞 Bug Report: `FileNotFoundError` ao acessar arquivo JSON em DAG do Airflow

## Descrição do Problema

Ao executar uma DAG que realiza o upload de um arquivo JSON para o MinIO usando `S3Hook`, o Airflow retorna a seguinte exceção:

```

FileNotFoundError: [Errno 2] No such file or directory: 'datasources/Persons.json'

````

O erro ocorre apesar do arquivo estar presente no contêiner e o volume ter sido corretamente montado via Docker Compose, indicando um problema com a resolução de caminhos relativos pelo Airflow no contexto do contêiner.

---

## 🛠️ Ambiente

- **Airflow**: 2.x (em contêiner Docker)
- **MinIO**: Local via Docker
- **Sistema Operacional (host)**: Linux
- **Local do arquivo esperado no contêiner**: `/opt/airflow/dags/datasources/Persons.json`

### Configuração de Volumes no `docker compose.yml`

A configuração de volumes estava correta, mas o problema persistia:

```yaml
volumes:
  - ./dags:/opt/airflow/dags
  - ./datasources:/opt/airflow/dags/datasources
````

-----

## 💻 Código da DAG (Trecho Problemático)

O caminho relativo utilizado que resultou no erro:

```python
local_path = 'datasources/Persons.json'
```

-----

## ✅ Solução Aplicada

O problema foi corrigido ajustando o caminho do arquivo para uma das seguintes opções, garantindo que o caminho fosse **absoluto** dentro do ambiente do contêiner.

### Opção 1: Caminho Absoluto Direto

```python
local_path = '/opt/airflow/dags/datasources/Persons.json'
```

### Opção 2: Usando `os.path.join` com `__file__` (Abordagem Preferida)

Esta abordagem garante portabilidade e resolve o caminho em relação ao diretório da DAG:

```python
import os

base_path = os.path.dirname(__file__)
local_path = os.path.join(base_path, 'datasources', 'Persons.json')
```

-----

## 🏁 Resultado

Após a aplicação da correção, a DAG executou com sucesso e o arquivo foi enviado ao MinIO conforme o esperado.

-----

**Nota:** Este documento serve como um registro da resolução para o `FileNotFoundError` ao tentar acessar arquivos com caminhos relativos em DAGs do Airflow em ambientes Dockerizados.

```
```
