# 🚀 Manual: Gerando Token Google Analytics API (Service Account)

## 1. Pré-requisitos

- Ambiente virtual Python criado e ativado (`venv`)
- Pacotes `google-auth` e `google-auth-oauthlib` instalados
- Arquivo JSON da Service Account do Google Analytics no mesmo diretório do script
- Script `keygen.py` pronto para uso

---

## 2. Gerando o Token de Acesso

### 2.1. Ative o Ambiente Virtual

Se ainda não ativou, execute:

```bash
source ../../venv/bin/activate
```

Ou, a partir da raiz do projeto:

```bash
source venv/bin/activate
```

---

### 2.2. Execute o Script de Geração de Token

No diretório onde está o `keygen.py` e o JSON:

```bash
python keygen.py
```

Ou, a partir da raiz do projeto:

```bash
python scripts/api-ga4/keygen.py
```

O script irá imprimir na tela o token de acesso gerado.

---

## 3. Exemplo de Uso do Token

Utilize o token gerado para autenticar requisições à API Google Analytics. Exemplo de header:

```json
{
  "Authorization": "Bearer <token_gerado_aqui>"
}
```

---

## 4. Dicas e Troubleshooting

- Se aparecer `ModuleNotFoundError: No module named 'google'`, ative o ambiente virtual e instale as dependências:
  ```bash
  pip install google-auth google-auth-oauthlib
  ```
- Se aparecer `FileNotFoundError` para o JSON, confira se o nome e o caminho do arquivo estão corretos.
- O token gerado tem validade limitada (geralmente 1 hora). Gere um novo token sempre que necessário.
- Para salvar o token em um arquivo, adapte o script adicionando:
  ```python
  with open('token.txt', 'w') as f:
      f.write(creds.token)
  ```

---

## 5. Validação

- O token pode ser testado em ferramentas como Postman ou curl, usando o header `Authorization: Bearer <token>`.
- Consulte a documentação oficial do Google Analytics API para exemplos de endpoints e uso do token.

---

Pronto! Agora você pode gerar e usar tokens de acesso para a API Google Analytics usando sua Service Account.
