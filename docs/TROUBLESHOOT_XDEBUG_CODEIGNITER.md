**Troubleshoot Xdebug + CodeIgniter (Docker)**

Resumo do caso: configuração do Xdebug dentro do container `codeigniter-app` não conectava ao VSCode em desenvolvimento. O resultado final foi um fluxo de debug local funcional (VSCode recebeu a sessão DBGp e pausou em breakpoints).

**Sintomas**:
- Breakpoints no VSCode não eram atingidos.
- `/tmp/xdebug.log` dentro do container não era gerado inicialmente ou mostrava erro de conexão: "Could not connect to debugging client" / "Operation now in progress".

**Causas principais identificadas**:
- Xdebug configurado para `client_port=9003` (conflito com processo host). Foi necessário usar `9004`.
- Host original (Docker Desktop backend) estava escutando na porta 9003, causando conflito.
- Em redes bridge customizadas do Docker o container não resolve `host.docker.internal` por padrão.
- VSCode (ou code-server) pode estar escutando apenas no loopback/host, não aceitando conexões vindas da rede Docker.

**Passos executados (resumo)**:

1. Diagnóstico:
   - Ver logs do container: `docker logs --tail 500 codeigniter-app`.
   - Verificar `php -i` e checar Xdebug presente: `docker exec -i codeigniter-app php -i | grep -i xdebug`.

2. Ajustes rápidos para a aplicação:
   - Corrigir host do banco em `src/codeigniter-app/.env` de `localhost` → `mysql` (uso do nome do serviço Docker).
   - Criar diretório `src/codeigniter-app/writable/logs` com permissões corretas para `www-data`.

3. Configuração Xdebug (mudanças aplicadas no container e persistidas no `Dockerfile`):
   - Alterar porta para `9004`: `xdebug.client_port=9004`.
   - Habilitar `xdebug.log` para diagnosticar: `xdebug.log=/tmp/xdebug.log` e `xdebug.log_level=7`.
   - Usar `xdebug.idekey=vscode`.
   - (Inicial) testar com `xdebug.discover_client_host=1` para discovery automático.

4. Ajustes Docker / VSCode:
   - Adicionar `extra_hosts` no `docker-compose.yml` para mapear `host.docker.internal:host-gateway` (permite usar `host.docker.internal` do container).
   - Atualizar `.vscode/launch.json` com `"hostname": "0.0.0.0"` e `"port": 9004` para que a sessão debug aceite conexões externas.
   - Atualizar `.vscode/settings.json` para `"php.debug.ideKey": "vscode"` (opcional, consistência).

5. Build / restart:
   - Rebuild e reiniciar apenas o serviço web (ou toda stack quando necessário):

```
cd /path/to/datalake-air-flow
./restart.sh   # ou docker-compose up --build -d codeigniter-app
```

6. Testes e solução de problemas de rede:
   - Verificar se o host está escutando em 9004: `ss -tulnp | grep 9004`.
   - Testar TCP do container (ex.: `/dev/tcp/172.18.0.1/9004`) — nem sempre disponível.
   - Se necessário, usar proxy local (socat/python) para encaminhar entre interfaces.

7. Resultado final que funcionou:
   - No `Dockerfile.webapp` definimos permanentemente Xdebug configurado com `xdebug.client_host=host.docker.internal`, `xdebug.client_port=9004`, `xdebug.log=/tmp/xdebug.log` e `xdebug.idekey=vscode`.
   - No `docker-compose.yml` adicionamos `extra_hosts:
       - "host.docker.internal:host-gateway"` para garantir resolução dentro do container.
   - Rebuild do image e restart do container.
   - No VSCode: Start Debugging (Listen for Xdebug) com `hostname: 0.0.0.0` e `port: 9004`.
   - Resultado: VSCode recebeu a sessão DBGp e pausou no breakpoint (ex.: `ConfigController::insert`).

**Comandos úteis (copiar/colar)**

Verificar Xdebug no container:
```
docker exec -i codeigniter-app php -i | egrep -i 'xdebug|/tmp/xdebug.log|client_port|start_with_request' 
```

Forçar um POST de teste (ativa a sessão via cookie XDEBUG_SESSION):
```
curl -v -X POST 'http://localhost:8088/insertConfig' -d 'id_source_type=1&id_pasta=1&dag_id=test&description=tmp' -b 'XDEBUG_SESSION=1'
```

Ler log Xdebug (após a requisição):
```
docker exec -i codeigniter-app tail -n 200 /tmp/xdebug.log
```

Rebuild e restart do serviço web (exemplo):
```
cd /home/cblna123456/datalake-air-flow
docker-compose up --build -d codeigniter-app
```

Arquivos alterados durante o troubleshooting (referência):
- `src/codeigniter-app/.env` (DB host)
- `src/codeigniter-app/writable/logs` (criacao/permissoes)
- `src/codeigniter-app/Dockerfile.webapp` (xdebug config permanente)
- `docker-compose.yml` (extra_hosts)
- `.vscode/launch.json` (hostname: 0.0.0.0, port: 9004)
- `.vscode/settings.json` (php.debug.ideKey)
- `src/codeigniter-app/app/Controllers/ConfigController.php` (pequeno patch: definir $idSourceType para evitar undefined variable)

**Notas finais / recomendações**:
- Use `xdebug.start_with_request=trigger` em produção/desenvolvimento intenso para não forçar todas as requisições a iniciar sessão debug; usar cookie/trigger quando quiser debugar.
- Se o host usar Docker Desktop (Windows/Mac), `host.docker.internal` costuma funcionar; em Linux, `extra_hosts` com `host-gateway` é a forma robusta.
- Se preferir, use `pathMappings` no `launch.json` para mapear `/var/www/html` → `${workspaceRoot}/src/codeigniter-app` (isso facilita breakpoints com paths corretos).

Se quiser, eu posso:
- Commitar essas mudanças e abrir um PR com o `Dockerfile` e `docker-compose.yml` atualizados;
- Gerar um passo a passo reduzido para inclusão no README do projeto;
- Ajudar a debugar outra rota/fluxo no CodeIgniter.

---
Gerado em: 2025-11-22
