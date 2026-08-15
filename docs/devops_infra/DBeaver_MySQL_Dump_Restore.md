# Passo a Passo: Dump e Restore de Banco MySQL via DBeaver

Este guia mostra como realizar um dump do banco de produção MySQL usando o DBeaver e restaurar esse dump sobrescrevendo o banco de desenvolvimento.

## Pré-requisitos
- DBeaver instalado
- Acesso ao banco de produção e desenvolvimento
- Permissões de leitura no banco de produção e escrita no banco de desenvolvimento

---

## 1. Realizando o Dump do Banco de Produção

1. **Abra o DBeaver** e conecte-se ao banco de produção.
2. No painel esquerdo, clique com o botão direito sobre o banco de dados de produção.
3. Selecione **Tools > Dump Database**.
4. Na janela que abrir:
    - Escolha o diretório e nome do arquivo para salvar o dump (ex: `dump_producao.sql`).
    - Selecione o formato SQL.
    - Marque as opções desejadas (ex: "Create table", "Insert data").
    - Clique em **Start** para iniciar o dump.
5. Aguarde até o processo finalizar. O arquivo estará salvo no local escolhido.

---

## 2. Restaurando o Dump no Banco de Desenvolvimento

1. Conecte-se ao banco de desenvolvimento no DBeaver.
2. Clique com o botão direito sobre o banco de desenvolvimento.
3. Selecione **Tools > Restore Database** ou **Tools > Execute Script**.
4. Na janela que abrir:
    - Selecione o arquivo de dump gerado (`dump_producao.sql`).
    - Confirme que o banco de destino é o de desenvolvimento.
    - Clique em **Start** para iniciar a restauração.
5. Aguarde até o processo finalizar. Os dados do banco de produção agora estarão no banco de desenvolvimento.

---

## Observações Importantes
- **Atenção:** Este procedimento irá sobrescrever os dados do banco de desenvolvimento.
- Faça backup do banco de desenvolvimento antes, se necessário.
- Verifique se as conexões e permissões estão corretas.

---

## Referências
- [Documentação Oficial DBeaver](https://dbeaver.io/docs/)
- [MySQL Backup & Restore](https://dev.mysql.com/doc/)

---

**Dúvidas ou problemas? Consulte o time de DBA ou suporte técnico.**
