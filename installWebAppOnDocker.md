Excelente\! É uma satisfação saber que o plano funcionou e seu ambiente está finalmente estável.

A seguir, está o Plano de Implantação e Soluções de Contorno em formato Markdown, contendo apenas as ações que foram efetivas para resolver os problemas de infraestrutura e permissão do seu ambiente Docker + CodeIgniter.

-----

# 🚀 Plano de Implantação: Correção de Infraestrutura Docker CodeIgniter

Este documento detalha as modificações essenciais aplicadas aos arquivos de configuração Docker para resolver os ciclos de reinicialização (`Restarting (127)`), erros de redirecionamento (`301 Moved Permanently`) e problemas de permissão de escrita do CodeIgniter (`Cache unable to write`).

## 1\. Problemas Resolvidos

| Erro Principal | Causa Raiz | Solução Efetiva |
| :--- | :--- | :--- |
| `Restarting (127)` / `no such file` | Formato de arquivo ANSI/Quebra de Linha CRLF no `entrypoint.sh`. | Adição do `dos2unix` no *build* para conversão. |
| `301 Moved Permanently` | Regra de redirecionamento HTTPS no `.htaccess` do Host (Volume). | Remoção/Substituição do `.htaccess` no Host e limpeza da lógica no `entrypoint.sh`. |
| `500 Internal Server Error` (Cache) | Conflito de permissão de volume (usuário Host vs. `www-data` no Contêiner). | Forçar permissão (`chmod -R 777`) e limpar o cache via *entrypoint*. |

## 2\. Ajustes no `Dockerfile.webapp`

As seguintes modificações garantem que a imagem seja construída corretamente, resolva problemas de codificação e se prepare para executar comandos de permissão como `root`.

| Ação | Localização | Código (Adicionar/Modificar) |
| :--- | :--- | :--- |
| **Correção de Codificação** | Seção de `RUN apt-get install` | Adicionar `dos2` à lista de pacotes a serem instalados: `RUN apt-get install -y git ... dos2unix \` |
| **Conversão de Script** | Após o `COPY entrypoint` | Adicionar a linha para corrigir o formato ANSI/CRLF do script: `RUN dos2unix /entrypoint-webapp.sh` |
| **Ordem de Usuário** | Fim do arquivo | Mover a linha `USER www-data` para o **FINAL** do `Dockerfile` (após o `CMD`). Isso permite que o `ENTRYPOINT` execute comandos como `root` (necessário para o `chmod`). |


## Exemplo para outros problemas de erro de formato de arquivo:
'''bash
# 1. Entre no container do Scheduler
docker exec -it airflow-scheduler bash

# 2. Dentro do container, execute o 'dos2unix' no arquivo
# (Assumindo que o caminho é /opt/airflow/dags/factory_master.py)
dos2unix /opt/airflow/dags/factory_master.py

# 3. Saia do container
exit
'''

## 3\. Ajustes no `entrypoint-webapp.sh`

O script de *entrypoint* foi modificado para atuar como uma solução de contorno para o problema de permissão de volume, garantindo que o CodeIgniter consiga inicializar o cache.

| Ação | Finalidade | Código (Completo e Final) |
| :--- | :--- | :--- |
| **Permissão e Cache** | Garante permissão total de escrita para o `www-data` e remove arquivos de cache corrompidos. | `bash\n#!/bin/bash\nset -x\n\n# 1. Limpa e Recria as pastas de cache e logs (Solução mais robusta)\nrm -rf /var/www/html/writable/cache/*\nrm -rf /var/www/html/writable/logs/*\n\n# 2. Garante a permissão total (recursiva) na pasta writable\nchmod -R 777 /var/www/html/writable\n\n# 3. Executa o comando principal (Apache)\nexec "$@"\n` |

## 4\. Solução de Contorno no Host

| Ação | Finalidade |
| :--- | :--- |
| **Desabilitar HTTPS** | Eliminar o erro `301 Moved Permanently`. **Remova ou substitua** o arquivo `.htaccess` na pasta `./src/codeigniter-app/` do seu Host por uma versão que **NÃO contenha** regras de redirecionamento para HTTPS (mantendo apenas as regras do CodeIgniter, como a remoção do `index.php`). |

## 5\. Comando de Execução

Após aplicar todas as modificações nos arquivos, a implantação é realizada com o comando que força a reconstrução da imagem do serviço `codeigniter-app`:

```bash
docker compose up -d --build codeigniter-app
```

**Resultado Esperado:** O contêiner `codeigniter-app` estará no status `Up` e acessível via `http://localhost:8088`.