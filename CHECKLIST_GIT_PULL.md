# Checklist de Segurança para git pull

1. Backups dos arquivos de configuração (.env, .env-prd, .env-test, src/codeigniter-app/.env) realizados
2. Todos arquivos sensíveis estão no .gitignore da raiz
3. Revisadas diferenças locais vs remoto (git diff limpo nos arquivos sensíveis)
4. Proteção contra sobrescrita dos arquivos de configuração garantida
5. Antes do git pull, confirme:
   - Não há senhas reais em arquivos versionados
   - Templates (.env.template, .env-prd.template, etc.) estão presentes no repositório
   - Merge de docker-compose.yml será manual/cuidadoso
   - Dados de banco (MySQL/PostgreSQL) estão em volumes separados
   - Após o pull, revise novamente os arquivos sensíveis antes de subir containers

Se todos os itens acima estiverem OK, o git pull pode ser feito com segurança.