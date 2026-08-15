# Restaurando myflow.conf de produção após restart.sh

## Problema
O script `restart.sh` apaga o arquivo de configuração `/etc/nginx/conf.d/myflow.conf` do container nginx, mas não restaura automaticamente o arquivo correto para produção.

## Solução
Para restaurar o arquivo de configuração de produção:

1. Certifique-se de que o arquivo backup está disponível:
   - `/root/datalake-air-flow-delta/nginx/backup/myflow.conf`

2. Execute o comando abaixo para copiar o arquivo para o container nginx e recarregar o serviço:

```bash
docker cp /root/datalake-air-flow-delta/nginx/backup/myflow.conf nginx-gateway:/etc/nginx/conf.d/myflow.conf

docker exec nginx-gateway nginx -s reload
```

3. O serviço HTTPS estará restaurado e funcionando normalmente.

## Observação
- Sempre mantenha o arquivo `myflow.conf` atualizado no diretório de backup.
- Se alterar variáveis de ambiente, atualize o arquivo antes de restaurar.
- Este procedimento é seguro para produção e segue o padrão do deploy guide.
