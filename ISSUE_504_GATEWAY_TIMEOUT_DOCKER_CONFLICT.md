# Resolução de Issue: 504 Gateway Time-out

## 📌 Descrição do Problema
O usuário relatou que a aplicação web hospedada em `https://myflow.estudotabela.com.br:28443/` estava retornando o erro **504 Gateway Time-out**. O erro indicava que o proxy reverso (Nginx) estava rodando e acessível, porém não conseguia se comunicar com o servidor da aplicação upstream (`codeigniter-app`).

## 🔍 Diagnóstico e Sintomas Analisados
1. A verificação do status dos contêineres (`docker ps -a`) mostrou que os principais serviços (Spark, Postgres, MinIO, Redis, etc.) constavam como "Exited" há cerca de 40 minutos. Outros contêineres cruciais da stack sequer apareciam na lista.
2. Ao tentar forçar a inicialização manual da stack com o comando `docker compose up -d`, o daemon do Docker falhou e retornou o erro de restrição de acesso:
   ```text
   Error response from daemon: error while creating mount source path '/var/oled/minio_data': mkdir /var/oled: read-only file system
   ```
3. Testes no File System (`df -h`, `ls -ld`, `touch`) comprovaram que a pasta `/var/oled/` **não** era apenas de leitura. A restrição era específica do contêiner engine.
4. Ao analisar a instalação do Docker, foram encontradas duas instâncias distintas operando no sistema operacional:
   - Uma instalada nativamente via gerenciador de pacotes da distribuição (`apt` / `deb`).
   - Outra versão em modo de confinamento rigoroso (`snap`).

## 🛑 Causa Raiz
A infraestrutura contava acidentalmente com o pacote do Docker via `snap` instalado, apesar da Stack Datalake operar historicamente no pacote clássico (`apt`/`docker-ce`). 

Aproximadamente 40 minutos antes do erro começar, um processo em background do SO (provavelmente um refresh/atualização automática) acordou e iniciou o serviço Docker do `snap`. 
Quando a versão `snap` assumiu o daemon, ela **sequestrou o socket** de comunicação do Docker (`/var/run/docker.sock`).
Isso desencadeou dois problemas gravíssimos e simultâneos:
1. **Ocultação dos contêineres originais:** As consultas e as redes internas que se baseavam no socket não enxergavam mais os contêineres rodando no daemon do `apt`. Com o backend oculto do Nginx, ocorreu o **504 Gateway Time-out**.
2. **Confinamento Restrito:** O Docker via `snap` possui regras AppArmor estritas. Ele bloqueou as tentativas do `docker-compose` de ler ou gravar em diretórios arbitrários do sistema como `/var/oled/`, retornando a mensagem de "read-only file system" no MinIO.

## 🛠️ Passos de Resolução
Para corrigir a arquitetura e restaurar o acesso aos serviços, a versão confinada e problemática do Docker precisava ser eliminada da rota de comunicação para que o Daemon principal (`apt`) voltasse a operar.

Foram executados os seguintes comandos na instância hospedeira:

**1. Parar e desativar permanentemente o serviço do Docker via snap:**
```bash
snap stop docker && snap disable docker
```
*Isso garante que atualizações automáticas futuras do snap não voltarão a habilitar o serviço e sequestrar a porta novamente.*

**2. Reiniciar os sockets e o serviço tradicional do Docker (`apt`):**
```bash
systemctl restart docker.socket docker.service
```
*Este passo devolveu ao daemon tradicional a posse sobre `/var/run/docker.sock`.*

**3. Verificação do Sucesso da Recuperação:**
- Ao validar novamente com `docker ps`, todos os 12 contêineres em execução (entre eles `nginx-gateway`, `codeigniter-app`, `airflow-*`) voltaram a ser mapeados normalmente, com um tempo de Uptime intacto em background.
- Testes locais e requisições cURL restabeleceram comunicações nativas para a aplicação, que deixou de retornar o 504 (timeout) e passou a retornar respostas HTTP (como 404/200) corretamente mapeadas pelo CodeIgniter.
