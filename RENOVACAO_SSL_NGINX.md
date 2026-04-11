# Guia de Renovação do Certificado SSL (Nginx Docker)

Este documento descreve o passo a passo seguro para renovar o certificado SSL (Let's Encrypt) usado na stack do Docker para o domínio da aplicação (`myflow.estudotabela.com.br`).

## ⚠️ Observação Crítica

> **NÃO RECRIE O CONTÊINER DO NGINX**
> Sob nenhuma hipótese utilize comandos que destruam e recriem o contêiner (como `docker compose down && docker compose up -d` ou forçar o rebuild do serviço nginx). Fazer isso sobrescreverá as configurações no diretório do nginx, causando **a perda do arquivo `myflow.conf` em produção**, o que deixará o sistema fora do ar (retornando a um problema já resolvido).

## Por que esse passo a passo é necessário?

O `certbot` instalado na máquina host está configurado no modo `standalone`. Isso significa que, para renovar o certificado, ele precisa subir um servidor temporário na **porta 80**. 
Como o nosso contêiner do `nginx` já está mapeado e ocupando a porta 80, o processo de renovação automática falha por conflito de porta.

Para resolver isso, basta paralisar temporariamente o nginx, efetuar a renovação e ligá-lo novamente.

## Passo a Passo para Renovação

Execute os comandos abaixo a partir da raiz do repositório (`/root/datalake-air-flow-delta/`):

### 1. Pare o serviço do Nginx
Isso vai liberar a porta 80 do servidor temporariamente (geralmente por menos de 5 segundos).

```bash
docker compose stop nginx
```

### 2. Execute a renovação do certificado
Chame o certbot para renovar os certificados que estiverem perto de expirar ou já expirados.

```bash
sudo certbot renew
```

### 3. Inicie o serviço do Nginx novamente
O nginx será iniciado e, ao subir, já carregará os novos certificados automaticamente.

```bash
docker compose start nginx
```

## Verificação

Após o término, verifique se o site está acessível e se o certificado reflete a nova data de validade no seu navegador (clicando no ícone do cadeado).
