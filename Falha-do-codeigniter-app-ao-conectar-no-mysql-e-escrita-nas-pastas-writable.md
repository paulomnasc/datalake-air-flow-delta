# Falha do codeigniter-app ao conectar no mysql e escrita nas pastas writable

Resumo das ações realizadas para diagnosticar e corrigir a falha do container `codeigniter-app` ao conectar no MySQL e problemas relacionados à escrita em `writable`.

**Sintomas observados**:
- Acesso ao endpoint de login retornava HTTP 500.
- Nos logs da aplicação (resposta JSON de erro) aparecia:
  - "Unable to connect to the database.\nMain connection [MySQLi]: No such file or directory"
- Mensagens do Apache sobre `ServerName` (informativas) e múltiplos `SIGWINCH` durante reinícios.

**Causa**:
- O `.env` da aplicação estava configurado com `database.default.hostname = localhost`. Dentro do container Docker isso faz com que o driver MySQLi tente usar socket local (arquivo) em vez de conectar pelo host de rede do Docker. Como o MySQL está em outro container, a conexão via socket falhou.
- A pasta `writable/logs` não existia no host mapeado (ou era removida no entrypoint), então não havia logs persistidos para inspeção.

**Ações efetuadas**:
- Inspecionei logs do container: `docker logs --tail 500 codeigniter-app`.
- Verifiquei arquivos de configuração e código: `app/Config/Database.php`, `src/codeigniter-app/.env`, `app/Controllers/UsuarioController.php`.
- Testei conectividade MySQL a partir do container:
  - `docker exec -it codeigniter-app bash -lc "php -r 'var_dump(mysqli_connect(\"mysql\", \"root\", \"root\", \"lista_revisao2\") !== false);'"` — retornou `bool(true)`.
- Reproduzi a requisição que dava 500 com curl:
  - `curl -i -X POST "http://localhost:8088/logarUsuario" -d "email=admin@gmail.com" -d "senha=123"`
  - Recebi o JSON de erro do CodeIgniter confirmando a exceção de DB.

**Correções aplicadas**:
- Atualizei o arquivo `src/codeigniter-app/.env` para usar o host do serviço Docker `mysql`:
  - Antes: `database.default.hostname = localhost`
  - Depois: `database.default.hostname = mysql`
- Criei a pasta de logs local e ajustei permissões para que o container consiga gravar:
  - `mkdir -p src/codeigniter-app/writable/logs && chmod -R 775 src/codeigniter-app/writable`
- Reiniciei o container `codeigniter-app` para aplicar as alterações:
  - `docker compose restart codeigniter-app`
- Testei novamente a rota `/logarUsuario` com `curl`. Resultado: retornou `200` com JSON de sucesso indicando que o login funcionou.

**Arquivos modificados / criados**:
- `src/codeigniter-app/.env` — alterado `database.default.hostname` de `localhost` para `mysql`.
- `src/codeigniter-app/writable/logs` — diretório criado no host (mapeado para o container) para persistência de logs.

**Comandos executados (seleção principal)**:
```
docker logs --tail 500 codeigniter-app
docker exec -it codeigniter-app bash -lc "php -r 'var_dump(mysqli_connect(\"mysql\", \"root\", \"root\", \"lista_revisao2\") !== false);'"
curl -i -X POST "http://localhost:8088/logarUsuario" -d "email=admin@gmail.com" -d "senha=123"
mkdir -p src/codeigniter-app/writable/logs && chmod -R 775 src/codeigniter-app/writable
docker compose restart codeigniter-app
```

**Status atual**:
- A requisição de login `/logarUsuario` responde com HTTP 200 e JSON indicando sucesso.
- A aplicação consegue conectar ao MySQL no container `mysql`.
- `writable/logs` agora existe e pode ser usada pela aplicação para gravar logs.

**Recomendações / próximos passos**:
- Evitar que o entrypoint do container apague `writable/logs/*` a cada start — isso remove histórico útil para debug. Em vez disso, usar rotação de logs ou manter os últimos N arquivos.
- Ajustar `ServerName` no Apache (dentro da imagem ou via configuração) para evitar as mensagens de aviso:
  - Exemplo: adicionar `ServerName localhost` em `/etc/apache2/apache2.conf` na imagem.
- Garantir que variáveis de conexão estejam definidas pelo `docker compose` (ou `env_file`) para facilitar configuração entre ambientes (dev/prod).
- Mapear `writable` como volume persistente se quiser manter sessões e logs entre reinícios de container.

Se quiser, posso:
- Remover/alterar a linha que faz `rm -rf /var/www/html/writable/logs/*` no entrypoint/startup.
- Adicionar checagem automática da pasta `writable/logs` no `Dockerfile`/entrypoint para criá-la com permissões corretas.
- Implementar uma rotação simples de logs em shell (logrotate ou script) dentro do container.

Arquivo gerado: `Falha-do-codeigniter-app-ao-conectar-no-mysql-e-escrita-nas-pastas-writable.md`

---
Gerado automaticamente com o resumo das ações realizadas em ambiente local (22/Nov/2025).
