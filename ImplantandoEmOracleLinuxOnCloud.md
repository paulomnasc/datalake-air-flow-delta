````markdown
# Setup de Laboratório Airflow + PostgreSQL em Oracle Cloud

Este guia descreve os passos para configurar uma VM na **Oracle Cloud**, preparar rede e rodar **Airflow + PostgreSQL via Docker**.

---

## 0. Criar a instância Oracle Cloud

1. Acesse o console: [https://cloud.oracle.com](https://cloud.oracle.com)  
2. Navegue em **Compute → Instances → Create Instance**  
3. Configure a VM:
   - **Shape:** `VM.Standard.E2.4`  
     - 4 OCPUs  
     - 16 GB RAM  
   - **Sistema operacional:** Ubuntu 22.04 LTS  
   - **Boot Volume:** 50–100 GB SSD (dependendo do uso)  
4. Clique em **Create** para inicializar a instância.


Claro, Cristiane! Aqui está o passo a passo para instalar o Docker no Oracle Linux, formatado em **Markdown compatível com GitHub**:

```markdown
# 🐳 Instalação do Docker no Oracle Linux 8

Este guia mostra como instalar o Docker Engine em uma instância Oracle Linux 8 usando o repositório oficial da Docker.

---

## ✅ Pré-requisitos

- Instância Oracle Linux 8 ativa
- Acesso via terminal com permissões de `sudo`
- Conexão com a internet

---

## 📦 Passo a passo

### 1. Atualize o sistema

```bash
sudo dnf update -y
```

### 2. Instale utilitários necessários

```bash
sudo dnf install -y dnf-utils zip unzip
```

### 3. Adicione o repositório oficial da Docker

```bash
sudo dnf config-manager --add-repo https://download.docker.com/linux/centos/docker-ce.repo
```

> ℹ️ Apesar de mencionar CentOS, esse repositório funciona para Oracle Linux.

### 4. Instale o Docker Engine

```bash
sudo dnf install -y docker-ce docker-ce-cli containerd.io
```

### 5. Inicie e habilite o serviço Docker

```bash
sudo systemctl start docker
sudo systemctl enable docker
```

### 6. Verifique se o Docker está funcionando

```bash
docker --version
sudo docker run hello-world
```

### 7. (Opcional) Use Docker sem sudo

```bash
sudo usermod -aG docker $USER
```

> 🔁 Faça logout e login novamente para aplicar a mudança.

---

## 🔍 Verifique a distribuição (opcional)

```bash
cat /etc/os-release
```

---

## 📚 Fontes úteis

- [Instalação via ORACLE-BASE](https://oracle-base.com/articles/linux/docker-install-docker-on-oracle-linux-ol8)
- [Guia Collabnix](https://collabnix.com/how-to-install-docker-on-oracle-linux-a-step-by-step-guide/)

Isso significa que o **Docker Compose não está instalado** na sua máquina Oracle Linux. Vamos resolver isso rapidinho! 🚀

---

## 🛠️ Como instalar o Docker Compose no Oracle Linux

### ✅ Passo 1: Verifique a versão mais recente
```bash
sudo curl -s https://api.github.com/repos/docker/compose/releases/latest | grep tag_name
```

Isso vai mostrar algo como `"tag_name": "v2.24.1"` — copie esse número.

### ✅ Passo 2: Baixe o binário do Docker Compose
Substitua `v2.24.1` pela versão mais recente que você encontrou:

```bash
sudo curl -L "https://github.com/docker/compose/releases/download/v2.24.1/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose
```

### ✅ Passo 3: Dê permissão de execução
```bash
sudo chmod +x /usr/local/bin/docker-compose
```

### ✅ Passo 4: Verifique se funcionou
```bash
docker-compose --version
```

Você deve ver algo como:

```bash
Docker Compose version v2.24.1
```

---

## 💡 Dica extra: Autocompletar no terminal (opcional)

```bash
sudo curl -L https://raw.githubusercontent.com/docker/compose/v2.24.1/contrib/completion/bash/docker-compose -o /etc/bash_completion.d/docker-compose
```

---

Esse erro indica que o usuário `opc` **não tem permissão para acessar o Docker daemon**, que é necessário para executar comandos como `docker-compose build`. Vamos resolver isso com um ajuste simples:

---

## ✅ Solução: Adicionar o usuário `opc` ao grupo `docker`

### 1. Execute este comando:
```bash
sudo usermod -aG docker opc
```

> Isso adiciona o usuário `opc` ao grupo `docker`, permitindo que ele execute comandos Docker sem precisar de `sudo`.

---

### 2. **Importante**: Faça logout e login novamente

Para que a mudança de grupo tenha efeito, você precisa **encerrar a sessão SSH e reconectar**.

---

### 3. Verifique se funcionou

Depois de reconectar, execute:

```bash
docker ps
```

Se não aparecer erro de permissão, está tudo certo! Agora você pode rodar:

```bash
docker-compose build
```

---

## 🔍 Sobre o aviso `version is obsolete`

Esse é só um alerta informativo. O Docker Compose v2 não exige mais o campo `version:` no `docker-compose.yml`. Você pode remover essa linha para evitar o aviso, mas ele **não impede o funcionamento**.


### Configurando regra de entrada para acessar os componentes via navegador
Ótimo, Cristiane — isso mostra que sua **tabela de rotas está correta** e permite que a instância acesse a internet via o **Internet Gateway**. Agora o que está faltando é liberar a **porta 8085 nas regras de segurança** (Security List), que são separadas da rota.

Vamos direto ao ponto:

---

## ✅ Como liberar a porta 8085 na Oracle Cloud (Security List)

### 1. No Console da Oracle Cloud:
- Vá para **"Networking" > "Virtual Cloud Networks" (VCNs)**.
- Clique no VCN chamado **`vcn-airflow-pub`**.

### 2. Na página do VCN:
- Clique em **"Security Lists"** no menu lateral.
- Deve haver uma lista chamada algo como **"Default Security List for vcn-airflow-pub"** — clique nela.

### 3. Adicione uma nova regra de entrada (Ingress Rule):
- Clique em **"Add Ingress Rules"**.
- Preencha assim:

  | Campo                | Valor                     |
  |----------------------|---------------------------|
  | **Source Type**      | CIDR                      |
  | **Source CIDR**      | `0.0.0.0/0`               |
  | **IP Protocol**      | TCP                       |
  | **Destination Port Range** | `8085`             |
  | **Stateless**        | Deixe desmarcado          |

- Clique em **"Add Ingress Rules"** para salvar.

---

## 🔁 Agora teste novamente

Depois de adicionar a regra, volte ao seu navegador e tente acessar:

```
http://137.131.212.68:8085
```

Se tudo estiver certo, a página deve carregar 🎉

---

Se ainda não funcionar, posso te ajudar a:
- Verificar se o container está escutando em `0.0.0.0`
- Confirmar se o `docker-compose.yml` está expondo a porta corretamente
- Testar com `curl` de fora da instância

