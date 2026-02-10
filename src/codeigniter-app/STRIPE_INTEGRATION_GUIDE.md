## 8. Configurando Webhooks Stripe

### Passo a passo Stripe
1. No painel da Stripe, acesse "Desenvolvedores" > "Webhooks".
2. Clique em "Adicionar endpoint" (Add endpoint).
3. Informe a URL do seu endpoint, por exemplo: `https://seusite.com/stripe/webhook`
4. Selecione os eventos desejados, como:
   - checkout.session.completed
   - payment_intent.succeeded
   - payment_intent.payment_failed
   - invoice.paid (para assinaturas)
   - invoice.payment_failed
5. Salve e copie a chave secreta do webhook (Signing secret).

### Exemplo de endpoint na aplicação
Crie uma rota e controller para receber notificações:

```php
$routes->post('/stripe/webhook', 'StripeController::webhook');
```

No StripeController:

```php
public function webhook()
{
  $payload = file_get_contents('php://input');
  $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
  $secret = getenv('STRIPE_WEBHOOK_SECRET');
  \Stripe\Stripe::setApiKey(getenv('STRIPE_SECRET_KEY'));
  try {
    $event = \Stripe\Webhook::constructEvent($payload, $sig_header, $secret);
  } catch (\Exception $e) {
    http_response_code(400);
    exit();
  }
  if ($event->type === 'checkout.session.completed') {
    $session = $event->data->object;
    // Liberar acesso ao curso para o usuário
  }
  http_response_code(200);
}
```

Adicione STRIPE_WEBHOOK_SECRET ao seu .env.

## Visão Geral

Este documento descreve o plano de desenvolvimento e implantação da integração Stripe.com para pagamentos de assinaturas na plataforma CodeIgniter.

---

## 1. Preparação Administrativa

- Criar conta Stripe e acessar o painel.
- Registrar produtos e planos de assinatura (mensal, anual, etc.).
- Obter as chaves de API (public e secret).
- Configurar webhooks para receber notificações de pagamento, renovação e cancelamento.

---

## 2. Desenvolvimento

### 2.1 Backend
- Instalar SDK Stripe PHP via Composer.
- Implementar endpoints para:
  - Criar sessão de checkout Stripe.
  - Receber webhooks e atualizar status da assinatura.
  - Gerenciar upgrades, downgrades e cancelamentos.

### 2.2 Frontend
- Adicionar botão "Assinar com Stripe" na página de renovação.
- Redirecionar usuário para o checkout Stripe.
- Exibir status atualizado após retorno do Stripe.

---

## 3. Implantação

- Testar integração em ambiente sandbox.
- Validar webhooks e atualização automática de status.
- Migrar para ambiente de produção.
- Monitorar logs e pagamentos.
- Treinar equipe administrativa para uso e acompanhamento.

---


## 4. Checklist

- [x] Conta Stripe criada
- [x] Produtos e planos configurados
- [x] Chaves API obtidas
- [x] Webhooks configurados
- [x] SDK Stripe instalado
- [ ] Endpoints implementados
- [ ] Botão Stripe no frontend
- [ ] Testes realizados
- [ ] Implantação em produção
- [ ] Equipe treinada

---

## 5. Escolha de Integração Stripe

Checklist de opções de integração:

- [ ] Links de pagamento compartilháveis (No-code)
- [x] Formulário de checkout pré-integrado (Low-code) — **OPÇÃO ESCOLHIDA**
- [ ] Fluxo de pagamento personalizado (Mais código)

---

## 6. Observações

- Stripe automatiza todo o fluxo de pagamento e renovação.
- Cancelamentos e falhas de pagamento são tratados via webhook.
- Relatórios e acompanhamento podem ser feitos pelo painel Stripe.

---

## 7. Como obter e preencher o stripe_price_id

1. No painel da Stripe, acesse Produtos (Products).
2. Clique no produto desejado e depois no preço (Price) correspondente.
3. Copie o campo "ID do preço" (exemplo: price_1SywOjHgSga95kBkwvpUsR38).
4. No banco de dados, execute:

```sql
UPDATE course SET stripe_price_id = 'price_1SywOjHgSga95kBkwvpUsR38' WHERE id = <ID_DO_CURSO>;
```

Substitua <ID_DO_CURSO> pelo id do curso correto.

---

## Teste de Cobrança Fake Stripe

### Objetivo
Permite testar o envio de dados fake para a Stripe usando o ambiente de testes.

### Método disponível
No controller `StripeController`, foi adicionado o método:

```php
public function testStripeCharge()
{
    \Stripe\Stripe::setApiKey(getenv('STRIPE_SECRET_KEY'));
    try {
        $charge = \Stripe\Charge::create([
            'amount' => 1000, // valor em centavos (R$10,00)
            'currency' => 'brl',
            'source' => 'tok_visa', // cartão de teste
            'description' => 'Teste de cobrança Stripe',
        ]);
        return $this->response->setJSON(['status' => 'success', 'charge' => $charge]);
    } catch (\Exception $e) {
        return $this->response->setJSON(['status' => 'error', 'message' => $e->getMessage()]);
    }
}
```

### Como executar o teste

1. Adicione a rota no arquivo de rotas:

```php
$routes->get('/stripe/testStripeCharge', 'StripeController::testStripeCharge');
```

2. Acesse a URL no navegador ou via ferramenta como Postman:

```
http://localhost:8000/stripe/testStripeCharge
```

3. O método irá enviar uma cobrança fake para a Stripe e retornar o resultado em JSON.

- Use a chave secreta de teste da Stripe (`sk_test_...`) no seu .env.
- O cartão de teste utilizado é o `tok_visa` (4242 4242 4242 4242).

### Observação
Esse teste não gera cobranças reais e pode ser usado para validar a integração.
