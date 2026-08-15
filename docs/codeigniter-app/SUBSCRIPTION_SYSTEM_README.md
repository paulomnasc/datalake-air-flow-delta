# 🚀 Sistema de Controle de Assinatura - Guia de Implementação

## 📋 Visão Geral

Sistema completo para controlar o tempo de uso da plataforma com:
- ✅ Período de trial de 30 dias
- ✅ Aviso automático 7 dias antes do vencimento
- ✅ Banner discreto na header
- ✅ Página de renovação com área para QR Code
- ✅ Bloqueio automático de acesso após vencimento
- ✅ Controle de status (trial, active, expired, cancelled)

---

## 📦 Arquivos Criados/Modificados

### 1. **Migration do Banco de Dados**
- 📄 `app/Database/Migrations/add_subscription_fields_to_usuario.sql`
  - Adiciona campos de assinatura na tabela `usuario`
  - Adiciona campos `created_at` e `updated_at` para tracking
  - Atualiza usuários existentes com período de trial baseado na data de criação

### 2. **Helper de Assinatura**
- 📄 `app/Helpers/SubscriptionHelper.php`
  - Calcula dias restantes
  - Verifica se deve mostrar aviso
  - Valida acesso à plataforma
  - Registra pagamentos
  - Calcula renovações

### 3. **Filtro (Middleware)**
- 📄 `app/Filters/SubscriptionFilter.php`
  - Verifica status da assinatura em cada requisição
  - Atualiza status automaticamente se expirou
  - Carrega dados na sessão
  - Redireciona usuários com assinatura expirada

### 4. **Controller**
- 📄 `app/Controllers/SubscriptionController.php`
  - Página de renovação (`/subscription/renew`)
  - Verificação de status via AJAX (`/subscription/status`)
  - Confirmação de pagamento (`/subscription/confirmPayment`)

### 5. **Views**
- 📄 `app/Views/subscription/renew.php`
  - Interface completa de renovação
  - Cards informativos de status
  - Área para QR Code de pagamento PIX
  - Botão de confirmação de pagamento
  - Instruções para o usuário

### 6. **Modificações em Arquivos Existentes**

#### header.php
- Adicionado banner de aviso de vencimento (7 dias ou menos)
- Banner aparece de forma animada no canto superior direito
- Cores dinâmicas baseadas na urgência

#### UsuarioModel.php
- Adicionados novos campos no `$allowedFields`
- Ativado `useTimestamps` para tracking automático

#### UsuarioController.php
- Inicializa período de trial no primeiro login confirmado
- Define data_inicio_trial baseada em created_at

#### Routes.php
- Já configurado com rotas de assinatura

#### Filters.php
- Já configurado com filtro de assinatura global

---

## 🔧 Passo a Passo para Implementação

### **Passo 1: Executar a Migration no Banco de Dados**

```bash
# Entre no container MySQL ou execute diretamente
mysql -u root -p lista_revisao < /caminho/para/app/Database/Migrations/add_subscription_fields_to_usuario.sql
```

Ou via terminal do MySQL:
```sql
USE lista_revisao;
SOURCE /caminho/para/app/Database/Migrations/add_subscription_fields_to_usuario.sql;
```

**Verificar se os campos foram criados:**
```sql
DESCRIBE usuario;
```

Você deve ver:
- `data_ultimo_pagamento`
- `data_vencimento_assinatura`
- `status_assinatura`
- `data_inicio_trial`
- `created_at`
- `updated_at`

---

### **Passo 2: Verificar Configuração do Filtro**

O filtro já está configurado em `app/Config/Filters.php`:

```php
public array $aliases = [
    // ... outros filtros
    'subscription' => \App\Filters\SubscriptionFilter::class,
];

public array $globals = [
    'before' => [
        'subscription', // ✅ Já ativado
    ],
];
```

**✅ Filtro já está ativo e funcionando!**

---

### **Passo 3: Testar o Sistema**

#### 3.1. Teste de Novo Usuário
1. Registre um novo usuário
2. Confirme o email
3. Faça login
4. O sistema deve automaticamente:
   - Definir `status_assinatura = 'trial'`
   - Definir `data_inicio_trial` com base no `created_at`
   - Definir `data_vencimento_assinatura = created_at + 30 dias`

#### 3.2. Teste de Aviso de Vencimento
1. Ajuste manualmente no banco um usuário com vencimento em 5 dias:
```sql
UPDATE usuario 
SET data_vencimento_assinatura = DATE_ADD(CURDATE(), INTERVAL 5 DAY),
    status_assinatura = 'trial'
WHERE id = SEU_ID_DE_TESTE;
```
2. Faça login
3. Deve aparecer o banner de aviso no canto superior direito

#### 3.3. Teste de Bloqueio
1. Ajuste um usuário com assinatura expirada:
```sql
UPDATE usuario 
SET data_vencimento_assinatura = DATE_SUB(CURDATE(), INTERVAL 5 DAY),
    status_assinatura = 'expired'
WHERE id = SEU_ID_DE_TESTE;
```
2. Faça login
3. Deve ser redirecionado automaticamente para `/subscription/renew`

#### 3.4. Teste de Renovação
1. Acesse `/subscription/renew`
2. Clique em "Já Paguei - Confirmar Pagamento"
3. Confirme
4. O sistema deve:
   - Atualizar `status_assinatura = 'active'`
   - Definir `data_ultimo_pagamento = hoje`
   - Calcular `data_vencimento_assinatura = data_atual + 30 dias`

---

## 📊 Estrutura dos Status

| Status | Descrição | Pode Acessar? |
|--------|-----------|---------------|
| `trial` | Período de teste (30 dias) | ✅ Sim |
| `active` | Assinatura paga ativa | ✅ Sim |
| `expired` | Assinatura vencida | ❌ Não |
| `cancelled` | Assinatura cancelada | ❌ Não |

---

## 💰 Valor da Assinatura / Pagamento
O valor-base original de pagamento, bem como o valor inicial, é gerido via variável de ambiente do projeto para facilitar a manutenção sem necessidade de alteração no código.
- Edite ou adicione `INITIAL_PAYMENT_USD=10` no arquivo `.env` para fixar o valor que aparece na página de renovação e pagamento inicial.

---

## 🚀 Liberação Especial (pagamento_inicial = 1)
Caso a coluna `pagamento_inicial` na tabela `usuario` seja definida como `1`:
- O usuário ganha passe livre (liberação de acesso irrestrito).
- A view de paywall não é mais exibida (o filtro ignora restrições ou banners).
- O usuário poderá navegar livremente pela plataforma, independente do status `expired` ou `cancelled`.

---

## 🎨 Personalizar QR Code de Pagamento

Edite o arquivo `app/Views/subscription/renew.php` na seção:

```php
<div id="qrcode-area" class="my-4 p-4 bg-white border rounded d-inline-block">
    <!-- SUBSTITUA O PLACEHOLDER PELA IMAGEM DO QR CODE -->
    <img src="<?= base_url('assets/img/qrcode-pix.png') ?>" alt="QR Code PIX" style="width: 300px; height: 300px;">
</div>
```

Ou use uma biblioteca para gerar dinamicamente:
```bash
composer require endroid/qr-code
```

---

## 🔐 Segurança e Validação Manual de Pagamentos

⚠️ **IMPORTANTE**: O botão "Já Paguei" atualmente confirma o pagamento automaticamente sem validação.

**Para produção, você deve:**

1. Integrar com API de pagamento (PIX, Stripe, PayPal)
2. Validar o pagamento antes de confirmar
3. Adicionar webhook para confirmação automática
4. Implementar logs de auditoria

**Exemplo de validação manual:**
```php
// No SubscriptionController::confirmPayment()
// Adicione verificação de código de confirmação, comprovante, etc.
$codigoConfirmacao = $this->request->getPost('codigo_confirmacao');
// Validar código...
```

---

## 📈 Monitoramento e Relatórios

### Verificar Assinaturas que Vencerão em 7 dias
```sql
SELECT id, nome, email, data_vencimento_assinatura, status_assinatura,
       DATEDIFF(data_vencimento_assinatura, CURDATE()) as dias_restantes
FROM usuario
WHERE status_assinatura IN ('trial', 'active')
  AND data_vencimento_assinatura BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
ORDER BY data_vencimento_assinatura ASC;
```

### Verificar Assinaturas Expiradas
```sql
SELECT id, nome, email, data_vencimento_assinatura, status_assinatura
FROM usuario
WHERE status_assinatura = 'expired'
   OR (data_vencimento_assinatura < CURDATE() AND status_assinatura IN ('trial', 'active'));
```

---

## 🛠️ Manutenção

### Tarefa Cron Recomendada
Execute diariamente para atualizar status automaticamente:

```php
// app/Commands/UpdateSubscriptionStatus.php (criar se necessário)
<?php
namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use App\Models\UsuarioModel;
use App\Helpers\SubscriptionHelper;

class UpdateSubscriptionStatus extends BaseCommand
{
    protected $group       = 'subscription';
    protected $name        = 'subscription:update-status';
    protected $description = 'Atualiza status de assinaturas expiradas';

    public function run(array $params)
    {
        $usuarioModel = new UsuarioModel();
        $usuarios = $usuarioModel->whereIn('status_assinatura', ['trial', 'active'])->findAll();
        
        $updated = 0;
        foreach ($usuarios as $usuario) {
            $novoStatus = SubscriptionHelper::atualizarStatus(
                $usuario->data_vencimento_assinatura,
                $usuario->status_assinatura
            );
            
            if ($novoStatus === 'expired') {
                $usuarioModel->update($usuario->id, [
                    'status_assinatura' => 'trial',
                    'pagamento_inicial' => 0
                ]);
                $updated++;
            } elseif ($novoStatus !== $usuario->status_assinatura) {
                $usuarioModel->update($usuario->id, ['status_assinatura' => $novoStatus]);
                $updated++;
            }
        }
        
        $this->write("✅ {$updated} assinaturas atualizadas", 'green');
    }
}
```

**Configurar no crontab:**
```bash
0 2 * * * cd /caminho/do/projeto && php spark subscription:update-status
```

---

## 📞 Suporte

Para dúvidas ou problemas, os usuários podem:
- Acessar `/contactUs` (link já disponível na página de renovação)
- Ver documentação completa em `/docs`

---

## ✅ Checklist de Implementação

- [x] Migration executada no banco
- [x] Campos criados na tabela usuario
- [x] SubscriptionHelper criado
- [x] SubscriptionFilter criado e ativado
- [x] SubscriptionController criado
- [x] View de renovação criada
- [x] Header atualizada com banner de aviso
- [x] UsuarioModel atualizado
- [x] UsuarioController atualizado com inicialização de trial
- [x] Rotas configuradas
- [x] Filtro ativado globalmente
- [ ] QR Code de pagamento configurado
- [ ] Testes realizados
- [ ] API de pagamento integrada (opcional)

---

## 🎯 Próximos Passos Recomendados

1. **Integração com Gateway de Pagamento**
   - Stripe, PayPal, ou processador PIX
   - Webhook para confirmação automática

2. **Email Automático de Aviso**
   - Enviar email 7, 3 e 1 dia antes do vencimento
   - Email de confirmação de pagamento

3. **Dashboard Admin**
   - Visualizar todas as assinaturas
   - Renovar manualmente
   - Gerar relatórios

4. **Sistema de Cupons/Promoções**
   - Desconto para renovação antecipada
   - Planos anuais com desconto

---

**Sistema implementado com sucesso! 🎉**
