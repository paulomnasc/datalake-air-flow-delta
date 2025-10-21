````{"variant":"standard","title":"Guia completo — Criar e restaurar snapshots Docker do datalake-air-flow","id":"52841"}
# 🐳 Guia completo — Criar e restaurar snapshots Docker do datalake-air-flow

Este documento descreve como criar **snapshots completos** (imagens de backup) dos containers que compõem o ambiente do *datalake-air-flow* (Airflow, Spark, MinIO, PostgreSQL e MySQL) e como restaurá-los posteriormente.

---

## 🚀 1️⃣ Criar snapshots de todos os containers

Para cada container em execução, execute um `docker commit` para salvar o estado atual como uma nova imagem.

```bash
docker commit airflow-webserver airflow-webserver-snap:v1
docker commit airflow-scheduler airflow-scheduler-snap:v1
docker commit spark spark-master-snap:v1
docker commit spark-worker spark-worker-snap:v1
docker commit postgres postgres-snap:v1
docker commit mysql mysql-snap:v1
docker commit datalake-air-flow-minio-1 minio-snap:v1
```

> 💡 Isso cria imagens com o estado completo dos containers, exceto volumes externos.

---

## 📦 2️⃣ Verificar as imagens criadas

```bash
docker images
```

Saída esperada:

```
REPOSITORY              TAG       IMAGE ID       CREATED          SIZE
airflow-webserver-snap  v1        abc123...      5 seconds ago    2.1GB
spark-master-snap       v1        def456...      5 seconds ago    2.3GB
...
```

---

## 💾 3️⃣ (Opcional) Exportar as imagens para arquivos `.tar`

Para criar arquivos de backup que podem ser movidos ou restaurados depois:

```bash
docker save -o airflow-webserver-snap-v1.tar airflow-webserver-snap:v1
docker save -o airflow-scheduler-snap-v1.tar airflow-scheduler-snap:v1
docker save -o spark-master-snap-v1.tar spark-master-snap:v1
docker save -o spark-worker-snap-v1.tar spark-worker-snap:v1
docker save -o postgres-snap-v1.tar postgres-snap:v1
docker save -o mysql-snap-v1.tar mysql-snap:v1
docker save -o datalake-air-flow-minio-1-snap-v1.tar minio-snap:v1
```

---

## 💾 4️⃣ Restaurar imagem a partir do snapshot

Verifique o arquivo:
```bash
ls -lh datalake-air-flow-minio-1-snap-v1.tar
```

Restaure a imagem:
```bash
docker load -i datalake-air-flow-minio-1-snap-v1.tar
```

Confirme:
```bash
docker images | grep minio
```

Resultado esperado:
```
datalake-air-flow-minio-1-snap   v1    a1b2c3d4e5f6   10 seconds ago   1.8GB
```

---

## 🧱 5️⃣ Subir um novo container restaurado

Para executar o container MinIO restaurado:

```bash
docker run -d \
  --name minio-restaurado \
  -p 9000:9000 -p 9001:9001 \
  datalake-air-flow-minio-1-snap:v1
```

Para substituir o original:

```bash
docker stop datalake-air-flow-minio-1
docker rm datalake-air-flow-minio-1
docker run -d \
  --name datalake-air-flow-minio-1 \
  -p 9000:9000 -p 9001:9001 \
  datalake-air-flow-minio-1-snap:v1
```

---

## ⚙️ 6️⃣ Integrar ao `docker-compose.yml` (opcional)

Defina a imagem snapshot no compose:

```yaml
minio:
  image: datalake-air-flow-minio-1-snap:v1
  container_name: datalake-air-flow-minio-1
  ports:
    - "9000:9000"
    - "9001:9001"
```

E suba novamente:
```bash
docker compose up -d minio
```

---

## 🧠 7️⃣ Dica: versionar snapshots automaticamente

Crie snapshots com data no nome para manter histórico:

```bash
docker commit datalake-air-flow-minio-1 datalake-air-flow-minio-1-snap:$(date +%Y%m%d)
docker save -o datalake-air-flow-minio-1-snap-$(date +%Y%m%d).tar datalake-air-flow-minio-1-snap:$(date +%Y%m%d)
```

Resultado:
```
datalake-air-flow-minio-1-snap-20251019.tar
```

---

## 🔁 8️⃣ Automatizar com script

Salve o script abaixo como `snapshot.sh`:

```bash
#!/bin/bash
containers=(
  airflow-webserver
  airflow-scheduler
  spark
  spark-worker
  postgres
  mysql
  datalake-air-flow-minio-1
)

for c in "${containers[@]}"; do
  name="${c}-snap"
  echo "📸 Criando snapshot de $c..."
  docker commit "$c" "${name}:v1"
  docker save -o "${name}-v1.tar" "${name}:v1"
done

echo "✅ Snapshots criados e exportados!"
```

Torne executável e rode:
```bash
chmod +x snapshot.sh
./snapshot.sh
```

---

## 📘 Resumo rápido

| Ação | Comando |
|------|----------|
| Criar imagem snapshot | `docker commit <container> <nome>:v1` |
| Exportar para arquivo | `docker save -o <arquivo>.tar <nome>:v1` |
| Reimportar imagem | `docker load -i <arquivo>.tar` |
| Restaurar container | `docker run -d --name <novo> <nome>:v1` |

---

Com esses comandos, você tem **backups completos e versionáveis** de todo o seu ambiente `datalake-air-flow`, podendo restaurar ou mover para qualquer servidor Docker.
````
