# Landing Page Smart-Tables

Landing page estática (HTML puro) para o domínio principal. Já inclui redireciono HTTPS via `.htaccess`.

## Arquivos
- index.html — página única
- .htaccess — força HTTPS e otimiza cache/compressão

## Deploy
1) Acesse o hosting (FTP/cPanel/FileZilla).
2) Vá para a raiz do domínio (ex.: `public_html/` ou `www/`).
3) Envie **index.html** e **.htaccess** mantendo esses nomes.
4) Teste: abra `https://seu-dominio.com`.

## Comportamento
- HTTP → HTTPS automático (301).
- Carrega `index.html` como padrão.
- Cache: HTML 1h, CSS 1 mês, imagens 1 ano.
- Gzip ativo para HTML/CSS/JS.

## Links da página
- Cadastro: `http://smarttables.x10.mx/sigInUsuario`
- Login: `http://smarttables.x10.mx/loginUsuario`
- Política/Termos/Contato: links apontam para o subdomínio do Lab.

## Observação
- Se quiser forçar remoção de `www`, descomente as duas linhas indicadas no `.htaccess`.
