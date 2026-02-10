# Guia de Padronização SSL no Ambiente de Teste

Para evitar diferenças entre teste e produção, siga este padrão:

1. **Habilite o template SSL no docker-compose.yml**
   - Certifique-se de que a linha de volume do myflow-ssl.conf.template está ativa.

2. **Use variáveis de ambiente para portas e certificados**
   - Defina NGINX_PORT_HTTPS, NGINX_SSL_CERT e NGINX_SSL_KEY no .env de teste.
   - Use o mesmo formato e caminhos de produção.

3. **Configure o template SSL para escutar na porta variável**
   - O template deve usar listen ${NGINX_PORT_HTTPS} ssl;

4. **Garanta que os certificados estejam disponíveis**
   - Monte o volume /etc/letsencrypt no container nginx.

5. **Teste o acesso HTTPS na porta definida**
   - Use curl ou navegador para validar o endpoint.

Assim, qualquer ajuste ou erro será identificado antes do deploy em produção.

---

> Padronize sempre o ambiente de teste para evitar surpresas em produção.