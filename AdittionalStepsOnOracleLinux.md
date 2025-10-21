Perfeito, Paulo 👍 — aqui está um **passo a passo completo**, em formato **Markdown compatível com GitHub**, reunindo tudo o que você fez e o que expliquei sobre criar e configurar uma **máquina Oracle Linux no Oracle Cloud** para usar **Airflow + PostgreSQL + MinIO via Docker**, incluindo como definir a senha root com a política adequada.

---

````markdown
# 🚀 Laboratório Oracle Cloud: Instalação de Airflow + PostgreSQL + MinIO em Docker

Este guia descreve o processo de criação e configuração de uma **máquina Linux persistente** na **Oracle Cloud Infrastructure (OCI)**, configurando acesso root, senha compatível com a política de segurança e preparando o ambiente para executar **Airflow**, **PostgreSQL** e **MinIO** via Docker.

---

## 🧩 1. Criar a Instância Linux na Oracle Cloud

### Passos:
1. Acesse o painel da Oracle Cloud:  
   👉 [https://cloud.oracle.com/](https://cloud.oracle.com/)
2. Vá em **Compute → Instances → Create Instance**.
3. Selecione:
   - **Nome:** `airflow-lab`
   - **Image:** `Oracle Linux 9.x`
   - **Shape:** `VM.Standard.E2.1.Micro` (gratuita, 1 OCPU, 1 GB RAM)
4. Em **Network**, selecione:
   - Rede Virtual (VCN): `Rede principal`
   - Sub-rede: **Sub-rede pública**
   - Marque ✅ **Designar endereço IPv4 público automaticamente**  
     *(Se a opção não aparecer, crie uma **sub-rede pública** com Internet Gateway e tabela de rotas padrão).*
5. Em **SSH keys**, selecione:
   - 🔑 **Gerar um par de chaves SSH para mim**
   - Faça download da **chave privada** (`.pem`)
6. Clique em **Create**.

---

## 🔐 2. Acessar a Instância via SSH

Após a criação:
1. Localize o **Endereço IP Público** da instância.
2. Conecte via terminal:

```bash
chmod 400 ~/Downloads/oracle_key.pem
ssh -i ~/Downloads/oracle_key.pem opc@<IP_PUBLICO_DA_VM>
````

> O usuário padrão é `opc` e tem privilégios `sudo`.

---

## 🧙 3. Tornar-se Root e Definir Senha

Dentro da VM:

```bash
sudo su -
```

Agora defina a senha root:

```bash
passwd root
```

Quando solicitado, digite a senha:

```
Docker!Admin2
```

✅ **Essa senha atende à política de segurança padrão do Oracle Linux**, que exige:

* Mínimo de 8 caracteres
* 1 letra maiúscula
* 1 letra minúscula
* 1 número
* 1 caractere especial

---

## ⚙️ 4. (Opcional) Relaxar a Política de Senhas (ambiente de laboratório)

Caso queira usar senhas simples (não recomendado em produção):

```bash
sudo tee /etc/security/pwquality.conf <<'EOF'
minlen = 6
dcredit = 0
ucredit = 0
lcredit = 0
ocredit = 0
EOF
```

Depois disso, senhas como `root123` serão aceitas.

---

## 🧱 5. (Opcional) Permitir Login Root via SSH

Por padrão, o Oracle Linux bloqueia login direto do root por senha.
Para habilitar (uso em laboratório):

```bash
sudo sed -i 's/^#PermitRootLogin.*/PermitRootLogin yes/' /etc/ssh/sshd_config
sudo sed -i 's/^PasswordAuthentication.*/PasswordAuthentication yes/' /etc/ssh/sshd_config || echo 'PasswordAuthentication yes' | sudo tee -a /etc/ssh/sshd_config
sudo systemctl restart sshd
```

Agora você pode acessar:

```bash
ssh root@<IP_PUBLICO_DA_VM>
```

> Login: `root`
> Senha: `Docker!Admin2`

---

## 🐳 6. Instalar Docker e Docker Compose

```bash
sudo dnf install -y dnf-plugins-core
sudo dnf config-manager --add-repo https://download.docker.com/linux/centos/docker-ce.repo
sudo dnf install -y docker-ce docker-ce-cli containerd.io docker-compose-plugin
sudo systemctl enable --now docker
sudo usermod -aG docker opc
```

Verifique a instalação:

```bash
docker version
docker compose version
```

---

## 🗄️ 7. Instalar e Configurar PostgreSQL + MinIO + Airflow via Docker Compose

Crie um diretório de trabalho:

```bash
mkdir ~/airflow-lab && cd ~/airflow-lab
```

Crie um arquivo `docker-compose.yml`:

```yaml
version: '3'

services:
  postgres:
    image: postgres:15
    container_name: postgres
    restart: always
    environment:
      POSTGRES_USER: airflow
      POSTGRES_PASSWORD: airflow
      POSTGRES_DB: airflow
    ports:
      - "5432:5432"
    volumes:
      - ./postgres_data:/var/lib/postgresql/data

  minio:
    image: minio/minio
    container_name: minio
    command: server /data
    environment:
      MINIO_ROOT_USER: admin
      MINIO_ROOT_PASSWORD: admin123
    ports:
      - "9000:9000"
      - "9001:9001"
    volumes:
      - ./minio_data:/data

  airflow:
    image: apache/airflow:2.10.0
    container_name: airflow
    restart: always
    depends_on:
      - postgres
    environment:
      - AIRFLOW__CORE__EXECUTOR=LocalExecutor
      - AIRFLOW__CORE__SQL_ALCHEMY_CONN=postgresql+psycopg2://airflow:airflow@postgres:5432/airflow
    volumes:
      - ./dags:/opt/airflow/dags
    ports:
      - "8080:8080"
    command: bash -c "airflow db init && airflow users create --username admin --password admin --firstname Admin --lastname User --role Admin --email admin@example.com && airflow webserver"
```

Inicie tudo:

```bash
docker compose up -d
```

---

## 🌐 8. Acessar os Serviços

| Serviço        | URL                                           | Usuário | Senha    |
| -------------- | --------------------------------------------- | ------- | -------- |
| **Airflow UI** | `http://<IP_PUBLICO>:8080`                    | admin   | admin    |
| **MinIO UI**   | `http://<IP_PUBLICO>:9001`                    | admin   | admin123 |
| **PostgreSQL** | `jdbc:postgresql://<IP_PUBLICO>:5432/airflow` | airflow | airflow  |

---

## 🧹 9. Manutenção e Reinicialização

Reiniciar containers:

```bash
docker compose restart
```

Parar tudo:

```bash
docker compose down
```

Ver logs:

```bash
docker compose logs -f airflow
```

---

## 🧾 10. Dicas de Segurança e Persistência

* Não exponha porta 22 (SSH) e 8080 (Airflow) publicamente sem firewall.
* Use `Security Lists` no Oracle Cloud para restringir o acesso ao seu IP.
* Faça backup de `~/airflow-lab/docker-compose.yml` e volumes locais.

---

## ✅ Conclusão

Agora você tem um **ambiente Oracle Linux persistente na Oracle Cloud** com:

* Acesso root funcional (`Docker!Admin2`)
* Docker e Docker Compose instalados
* Airflow, PostgreSQL e MinIO rodando via containers

Perfeito para **estudos de pipelines de dados** e **testes de orquestração** em nuvem.

---

### 🧠 Autor

**Paulo Nascimento**
Infraestrutura e Orquestração de Dados
Ambiente Oracle Cloud Free Tier + Docker Stack

```

