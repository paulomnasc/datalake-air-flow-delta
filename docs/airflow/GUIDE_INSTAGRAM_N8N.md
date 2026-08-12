# Walkthrough: Automação do Instagram com n8n

Nós integramos com sucesso o **n8n** à sua stack do Docker-Compose e o roteamos de forma segura através do proxy reverso Nginx. Ele já está ativo, seguro e pronto para automatizar.

Abaixo está o guia passo a passo atualizado com a **Abordagem ManyChat + n8n** (altamente recomendada para produção imediata) e a **Abordagem Direta via Meta Developers** (avançada).

---

## 🚪 Passo 1: Acessando sua Instância do n8n

Você pode acessar o editor visual do n8n utilizando as seguintes rotas:

* **Produção (HTTPS/Seguro)**: [https://myflow.estudotabela.com.br/n8n/](https://myflow.estudotabela.com.br/n8n/)
* **Alternativa Local (HTTP)**: [http://localhost:28080/n8n/](http://localhost:28080/n8n/)

> [!NOTE]
> Em sua primeira visita, o n8n solicitará que você crie uma **Conta de Proprietário** (Owner Account). Este é o seu usuário administrador privado salvo com segurança dentro da sua VM.

---

## 🛢️ Passo 2: Conectando o n8n ao seu Banco de Dados do Datalake

Para consultar seus produtos da Lomadee diretamente pelo fluxo de automação, precisamos registrar a credencial do Postgres no n8n:

1. Na barra lateral do n8n, vá em **Credentials** (Credenciais) -> **Add Credential** (Adicionar credencial).
2. Pesquise e selecione **PostgreSQL**.
3. Preencha os detalhes de conexão (usando as coordenadas internas da rede Docker):
   * **Host**: `postgres-bi`
   * **Port**: `5432`
   * **Database**: `datalake_bi`
   * **User**: `pbi_user`
   * **Password**: `pbi_password` (definido no seu arquivo [.env](file:///root/datalake-air-flow-delta/.env))
4. Clique em **Save**. O n8n exibirá um selo verde "Connected" confirmando o sucesso.

---

## 🚀 Passo 3: Abordagem ManyChat + n8n (Recomendada para Produção)

Esta abordagem é a mais utilizada profissionalmente porque **evita toda a burocracia da Meta** (como o processo de análise de aplicativo - App Review, verificação de empresa e restrições de contas de teste em modo de desenvolvimento). Ela funciona instantaneamente para 100% dos seus seguidores.

### 1. Importando o Workflow no n8n
1. No n8n, clique em **Workflows** no menu esquerdo.
2. Clique no botão **Add workflow** (Adicionar fluxo) no canto superior direito para abrir uma tela em branco.
3. Clique em qualquer área vazia do editor e pressione **`Ctrl + V`** (ou `Cmd + V` no Mac) para colar o JSON abaixo:

```json
{
  "name": "ManyChat - Obter Link Lomadee",
  "nodes": [
    {
      "parameters": {
        "httpMethod": "POST",
        "path": "manychat-link",
        "responseMode": "responseNode",
        "options": {}
      },
      "type": "n8n-nodes-base.webhook",
      "typeVersion": 2,
      "position": [
        100,
        240
      ],
      "id": "webhook-manychat-001",
      "name": "Webhook"
    },
    {
      "parameters": {
        "operation": "executeQuery",
        "query": "SELECT name, shortened_url FROM lomadee.products WHERE shortened_url IS NOT NULL AND shortened_url != '' ORDER BY RANDOM() LIMIT 1;",
        "options": {}
      },
      "type": "n8n-nodes-base.postgres",
      "typeVersion": 2.4,
      "position": [
        320,
        240
      ],
      "id": "postgres-manychat-002",
      "name": "Get Product Link",
      "credentials": {
        "postgres": {
          "id": "FzYW9HRUEa41hzB6",
          "name": "Postgres account"
        }
      }
    },
    {
      "parameters": {
        "options": {},
        "responseBody": "{\n  \"link\": \"{{ $json.shortened_url }}\"\n}"
      },
      "type": "n8n-nodes-base.respondToWebhook",
      "typeVersion": 1,
      "position": [
        540,
        240
      ],
      "id": "respond-manychat-003",
      "name": "Respond to Webhook"
    }
  ],
  "connections": {
    "Webhook": {
      "main": [
        [
          {
            "node": "Get Product Link",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Get Product Link": {
      "main": [
        [
          {
            "node": "Respond to Webhook",
            "type": "main",
            "index": 0
          }
        ]
      ]
    }
  },
  "settings": {},
  "staticData": null,
  "meta": null,
  "pinData": {}
}
```
4. Dê duplo clique no nó **PostgreSQL** e selecione a credencial **"Postgres account"** criada no Passo 2.
5. Clique em **Save** no topo e ative o fluxo mudando a chave para **Active** no canto superior direito.

---

### 2. Configurando a Integração no ManyChat
Com o n8n ativo, configure a chamada dentro do fluxo do seu **ManyChat**:

1. No ManyChat, edite o botão **"Send me the link"** (ou o botão correspondente do fluxo de boas-vindas do comentário).
2. Adicione uma Ação (Action) do tipo **External Request** (Requisição Externa).
3. Dê duplo clique na caixinha da requisição externa e configure:
   * **Method**: `POST`
   * **Request URL**: `https://myflow.estudotabela.com.br/n8n/webhook/manychat-link`
   * **Aba Body**: Selecione `JSON` e deixe apenas chaves vazias: `{}`
   * **Aba Response (Mapeamento)**:
     * Clique em **+ Add Mapping**.
     * Em **JSONPath**, digite: `$.link`
     * Em **Custom Field**, selecione ou crie um campo personalizado de texto (ex: `link_afiliado`).
     * Clique em **Save** no canto superior direito.
4. Ligue a saída de sucesso da Requisição Externa a uma nova mensagem de Instagram.
5. Escreva a mensagem de resposta usando a variável:
   `Aqui está o seu link: {{link_afiliado}}`
6. Clique em **Publish** no topo direito do ManyChat para ativar tudo.

---

## 🛠️ Abordagem Direta via Meta Developers (Avançado)

Se preferir utilizar a API da Meta de forma direta (sem o ManyChat), você precisará configurar o webhook no portal de desenvolvedores da Meta.

> [!WARNING]
> Em modo de desenvolvimento, os comentários e mensagens de contas que **não** são testadores oficiais adicionados nas funções do aplicativo da Meta serão silenciosamente descartados pela Meta.

### 1. Configurando o Aplicativo na Meta
1. Acesse o **[Portal Meta for Developers](https://developers.facebook.com/)**.
2. Crie um aplicativo (tipo **Outro** -> **Empresa / Business**).
3. Em **Casos de uso** (Use cases) no menu lateral, adicione o caso de uso **Gerenciar mensagens e conteúdo no Instagram**.
4. **Vincular contas (Essencial)**: Conecte o perfil comercial do Instagram à página do Facebook e certifique-se de que ambas as contas estão vinculadas com **Controle Total (Total Access)** no seu [Meta Business Settings](https://business.facebook.com/settings).
5. Configurar o Webhook:
   * URL de retorno: `https://myflow.estudotabela.com.br/n8n/webhook/instagram-comments`
   * Token de verificação: Crie uma senha (ex: `cristiane_n8n_123`).
   * Assine o evento **comments** (comentários) sob a categoria **Instagram**.

### 2. Importando o Workflow Direto no n8n
Crie um fluxo em branco e cole o JSON abaixo:

```json
{
  "name": "Instagram - Send Product on Comment 'QUERO'",
  "nodes": [
    {
      "parameters": {
        "httpMethod": "GET",
        "path": "instagram-comments",
        "responseMode": "responseNode",
        "options": {}
      },
      "type": "n8n-nodes-base.webhook",
      "typeVersion": 2,
      "position": [
        0,
        140
      ],
      "id": "webhook-meta-get",
      "name": "Meta Webhook GET"
    },
    {
      "parameters": {
        "httpMethod": "POST",
        "path": "instagram-comments",
        "responseMode": "responseNode",
        "options": {}
      },
      "type": "n8n-nodes-base.webhook",
      "typeVersion": 2,
      "position": [
        0,
        340
      ],
      "id": "webhook-meta-post",
      "name": "Meta Webhook POST"
    },
    {
      "parameters": {
        "respondWith": "text",
        "responseBody": "={{ $json.query[\"hub.challenge\"] }}",
        "options": {}
      },
      "type": "n8n-nodes-base.respondToWebhook",
      "typeVersion": 1,
      "position": [
        250,
        140
      ],
      "id": "respond-challenge",
      "name": "Respond Hub Challenge"
    },
    {
      "parameters": {
        "respondWith": "text",
        "responseBody": "OK",
        "options": {}
      },
      "type": "n8n-nodes-base.respondToWebhook",
      "typeVersion": 1,
      "position": [
        250,
        340
      ],
      "id": "respond-meta-ok",
      "name": "Ack Webhook 200"
    },
    {
      "parameters": {
        "conditions": {
          "options": {
            "caseSensitive": false,
            "leftValue": "",
            "typeValidation": "strict"
          },
          "conditions": [
            {
              "id": "has-quero-keyword",
              "leftValue": "={{ $json.body.entry[0].changes[0].value.text }}",
              "rightValue": "quero",
              "operator": {
                "type": "string",
                "operation": "contains"
              }
            }
          ],
          "combinator": "and"
        },
        "options": {}
      },
      "type": "n8n-nodes-base.if",
      "typeVersion": 2,
      "position": [
        500,
        340
      ],
      "id": "has-quero-word",
      "name": "Comment has 'QUERO'?"
    },
    {
      "parameters": {
        "operation": "executeQuery",
        "query": "SELECT name, shortened_url FROM lomadee.products WHERE shortened_url IS NOT NULL AND shortened_url != '' ORDER BY RANDOM() LIMIT 1;"
      },
      "type": "n8n-nodes-base.postgres",
      "typeVersion": 2.4,
      "position": [
        750,
        340
      ],
      "id": "get-product-from-db",
      "name": "Get Product Link",
      "credentials": {
        "postgres": {
          "id": "1"
        }
      }
    },
    {
      "parameters": {
        "method": "POST",
        "url": "https://graph.instagram.com/v20.0/me/messages",
        "sendHeaders": true,
        "headerParameters": {
          "parameters": [
            {
              "name": "Authorization",
              "value": "Bearer INSIRA_SEU_META_ACCESS_TOKEN_AQUI"
            },
            {
              "name": "Content-Type",
              "value": "application/json"
            }
          ]
        },
        "sendBody": true,
        "bodyParameters": {
          "parameters": []
        },
        "specifyBody": "json",
        "jsonBody": "={\n  \"recipient\": {\n    \"id\": \"{{ $('Meta Webhook POST').item.json.body.entry[0].changes[0].value.from.id }}\"\n  },\n  \"message\": {\n    \"text\": \"Olá! Aqui está o link que você pediu para *{{ $json.name }}*:\\n\\n👉 {{ $json.shortened_url }}\"\n  }\n}",
        "options": {}
      },
      "type": "n8n-nodes-base.httpRequest",
      "typeVersion": 4.2,
      "position": [
        236,
        340
      ],
      "id": "send-instagram-direct",
      "name": "Send IG Direct"
    }
  ],
  "connections": {
    "Meta Webhook GET": {
      "main": [
        [
          {
            "node": "Respond Hub Challenge",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Meta Webhook POST": {
      "main": [
        [
          {
            "node": "Ack Webhook 200",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Ack Webhook 200": {
      "main": [
        [
          {
            "node": "Comment has 'QUERO'?",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Comment has 'QUERO'?": {
      "main": [
        [
          {
            "node": "Get Product Link",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Get Product Link": {
      "main": [
        [
          {
            "node": "Send IG Direct",
            "type": "main",
            "index": 0
          }
        ]
      ]
    }
  },
  "settings": {
    "executionOrder": "v1"
  },
  "id": "instagram-automation-flow",
  "meta": {
    "templateCredsSetupCompleted": true
  }
}
```

---

## 🛠️ Resolução de Problemas (Troubleshooting)

### 1. Erro: "Não foi possível validar a URL de callback..." ao salvar o Webhook na Meta
* **Solução**: No editor do n8n, certifique-se de salvar as alterações (`Ctrl + S`) e clicar no botão **Publish (Publicar)** antes de salvar as configurações na Meta.

### 2. Erro: "Função de desenvolvedor é insuficiente"
* **Causa**: O perfil pessoal ou o Instagram usado para testes não está registrado como desenvolvedor/testador nas funções do aplicativo da Meta Developers.
* **Solução**: Vá em **Funções do app** -> **Funções** -> Adicionar testador, e convide as contas do Facebook e do Instagram. Ambos os perfis precisam aceitar o convite através do link [https://developers.facebook.com/](https://developers.facebook.com/) clicando na notificação.

### 3. A opção "Permitir acesso às mensagens" não aparece nas configurações do Instagram no celular
* **Causa**: A conta do Instagram Comercial e a Página do Facebook não estão vinculadas de forma oficial no seu Gerenciador de Negócios.
* **Solução**: Vá em [Meta Business Settings](https://business.facebook.com/settings), acesse **Contas do Instagram**, selecione sua conta e em **Ativos conectados** certifique-se de que a sua página do Facebook correspondente está adicionada com acesso total de administrador.
