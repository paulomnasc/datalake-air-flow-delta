# Guia Passo a Passo: Configuração do Webhook e API da Meta (Instagram)

Este guia documenta o passo a passo completo e atualizado para conectar sua conta do Instagram Comercial ao **n8n** através do painel **Meta for Developers** (Interface por Casos de Uso), permitindo a captura de comentários em tempo real e respostas automáticas por mensagem privada (Direct).

---

## 📌 Pré-requisitos Obrigatórios (Instagram & Facebook)

1. **Conta Profissional no Instagram:**
   - No celular: **Configurações e privacidade** -> **Mudar para conta profissional** (*Empresa* ou *Criador de Conteúdo*).
2. **Vinculação à Página do Facebook:**
   - No Meta Business Suite ou Facebook: Vincule a conta do Instagram à sua Página do Facebook. *(Ao visualizar ambos os ícones sobrepostos no Business Suite, a vinculação está confirmada)*.
3. **Liberar Acesso às Mensagens no Instagram:**
   - No app do celular: **Configurações** -> **Privacidade** -> **Mensagens** (ou *Controles de mensagem*).
   - Ative a chave **"Permitir acesso às mensagens"** (Toggle ligado/direita).

---

## 🚀 Passo 1: Criar o Aplicativo no Meta for Developers

1. Acesse **[developers.facebook.com](https://developers.facebook.com/)** com a conta do Facebook responsável pela página.
2. Clique em **Meus aplicativos** -> **Criar aplicativo**.
3. Na escolha do tipo, selecione **Casos de uso** -> **Empresa** (Business).
4. Na tela *"A qual portfólio empresarial você quer conectar o app?"*:
   - Selecione: **`Ainda não quero me conectar a um portfólio empresarial`**. *(Isso evita exigências de verificação de documentos da empresa no momento)*.
5. Defina o nome do aplicativo (ex: `MyFlow Listener`) e conclua a criação.

---

## ⚙️ Passo 2: Configurar Permissões e Webhook

No painel do aplicativo criado (`MyFlow Listener`):

1. Clique na opção: **Personalizar o caso de uso "Gerenciar mensagens e conteúdo no Instagram"** *(ou no menu esquerdo vá em **Casos de uso** -> **Gerenciar mensagens e conteúdo no Instagram**)*.
2. No **Item 1 (Adicionar permissões)**:
   - Clique no botão azul **`Add all required permissions`** para incluir:
     - `instagram_business_basic`
     - `instagram_business_manage_comments`
     - `instagram_business_manage_messages`
3. No **Item 3 (Configurar Webhooks)**:
   - **URL de callback:** `https://myflow.estudotabela.com.br/n8n/webhook/instagram-comments`
   - **Verificar token:** `myflow_instagram_secret_2026`
   - Clique em **Verificar e Salvar**.
   - No campo **`comments`**, ative a chave para o status **Assinado**.

---

## 🔑 Passo 3: Gerar o Token de Acesso de Página (Page Access Token)

Para autorizar o n8n a enviar a mensagem privada (Direct):

1. Acesse o **[Graph API Explorer](https://developers.facebook.com/tools/explorer/)**.
2. No painel direito da tela:
   - **App da Meta:** Selecione `MyFlow Listener`.
   - **Usuário ou Página:** Clique na caixinha **`Obter token`** -> escolha **`Obter Token de Acesso do Usuário`** (ou *Get Page Access Token*).
3. Na janela pop-up do Facebook que se abrir:
   - Autorize e marque a sua **Página do Facebook** e **Instagram**.
4. Verifique a lista de permissões ativas (remover permissões antigas como `manage_pages` se houver). As corretas são:
   - `instagram_business_basic`
   - `instagram_business_manage_comments`
   - `instagram_business_manage_messages`
5. O token de acesso será gerado na caixa de texto do topo. Clique no ícone de **Copiar**.

---

## 📥 Passo 4: Configurar e Ativar no n8n

1. Acesse o n8n: [https://myflow.estudotabela.com.br/n8n/](https://myflow.estudotabela.com.br/n8n/)
2. Importe o arquivo de fluxo: [instagram_comments_listener_workflow.json](file:///root/datalake-air-flow-delta/n8n_workflows/instagram_comments_listener_workflow.json).
3. Abra o nó **`Enviar Mensagem Privada (Direct)`**:
   - No campo `Authorization`, cole o token copiado mantendo o prefixo `Bearer `:
     `Bearer EAAG...`
4. Clique na chave do canto superior direito para mudar de *Inactive* para **Active (Verde)**.

---

## ⚠️ Solução para Erro "Conflicting Webhook Path" no n8n

Se o n8n bloquear a ativação dizendo que o caminho `instagram-comments` já está em uso:
- Vá na lista de **Workflows** no n8n.
- Desative qualquer fluxo antigo que esteja usando a mesma rota (`Instagram - Send Product on Comment 'QUERO'`).
- Ative o novo fluxo unificado.

---

## 🧪 Passo 5: Testar o Envio Automático

1. Faça um comentário em qualquer post do Instagram com as palavras `QUERO` ou `LINK`.
2. Acompanhe a execução em tempo real na aba **Executions** do n8n.
3. Confira o recebimento do link promocional no Direct da conta!
