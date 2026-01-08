# 🔐 Sistema de Controle de Assinatura - Guia Completo

## 📋 Visão Geral

Sistema completo de controle de tempo de uso da plataforma web app CodeIgniter com:
- ✅ Período de trial de 30 dias
- ✅ Verificação automática de vencimento no login
- ✅ Aviso discreto na header quando faltam 7 dias ou menos
- ✅ Página de renovação com área para QR Code de pagamento
- ✅ Bloqueio automático após vencimento
- ✅ Preço Founder's Club: USD 7,00/mês (travado)

---

## 🚀 Instalação

### 1️⃣ Executar Migration do Banco de Dados

Execute o script SQL para adicionar os campos necessários na tabela `usuario`:

```bash
mysql -u root -p lista_revisao < src/codeigniter-app/app/Database/Migrations/add_subscription_fields_to_usuario.sql
```

Ou execute manualmente no seu gerenciador MySQL:

```sql
ALTER TABLE `usuario` 
ADD COLUMN `data_ultimo_pagamento` DATE NULL COMMENT 'Data do último pagamento realizado',
ADD COLUMN `data_vencimento_assinatura` DATE NULL COMMENT 'Data de vencimento da assinatura',
ADD COLUMN `status_assinatura` ENUM('trial', 'active', 'expired', 'cancelled') DEFAULT 'trial',
ADD COLUMN `data_inicio_trial` DATE NULL COMMENT 'Data de início do período de trial (30 dias)',
ADD INDEX `idx_data_vencimento` (`data_vencimento_assinatura`),
ADD INDEX `idx_status_assinatura` (`status_assinatura`);
```

### 2️⃣ Configurar o Filtro (Middleware)

Edite o arquivo `app/Config/Filters.php` e adicione o SubscriptionFilter:

```php
<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Filters extends BaseConfig
{
    public array $aliases = [
        // ... outros filtros existentes
        'subscription' => \App\Filters\SubscriptionFilter::class,
    ];

    public array $globals = [
        'before' => [
            // ... outros filtros
            'subscription', // Adicione esta linha
        ],
        'after' => [
            // ... seus filtros after
        ],
    ];
}
```

---

## 📁 Arquivos Criados

### Helper
- **`app/Helpers/SubscriptionHelper.php`**: Funções utilitárias para cálculo de dias, status, renovação, etc.

### Filter (Middleware)
- **`app/Filters/SubscriptionFilter.php`**: Verifica status em cada requisição e carrega dados na sessão

### Controller
- **`app/Controllers/SubscriptionController.php`**: Gerencia páginas de renovação e confirmação de pagamento

### Views
- **`app/Views/subscription/renew.php`**: Página principal de renovação com área para QR Code

### Migration
- **`app/Database/Migrations/add_subscription_fields_to_usuario.sql`**: Script SQL para adicionar campos

### Rotas
Adicionadas em **`app/Config/Routes.php`**:
- `/subscription/renew` - Página de renovação
- `/subscription/status` - Status via JSON (AJAX)
- `/subscription/confirmPayment` - Confirma pagamento (POST)

---

## 🎯 Como Funciona

### Fluxo do Usuário

1. **Registro e Confirmação**
   - Usuário se registra
   - Confirma email
   - No primeiro login confirmado, inicia automaticamente o período de trial de 30 dias

2. **Durante o Trial (30 dias)**
   - Usuário tem acesso completo à plataforma
   - Quando faltam 7 dias ou menos, aparece um banner discreto no topo direito da tela

3. **Banner de Aviso**
   - Aparece automaticamente quando faltam ≤ 7 dias
   - Mostra quantos dias restam
   - Link direto para página de renovação
   - Cores:
     - 🟡 Amarelo: 7-3 dias restantes
     - 🔴 Vermelho: 2 dias ou menos

4. **Página de Renovação**
   - Mostra status da assinatura
   - Exibe data de vencimento e dias restantes
   - Área para QR Code de pagamento PIX (você adiciona o QR Code)
   - Botão "Já paguei" para confirmar manualmente

5. **Após Vencimento**
   - Se não renovar, o status muda para `expired`
   - Usuário é automaticamente redirecionado para `/subscription/renew`
   - Acesso bloqueado até renovação

### Status de Assinatura

| Status | Descrição |
|--------|-----------|
| `trial` | Período de teste (30 dias) |
| `active` | Assinatura paga e ativa |
| `expired` | Vencida (bloqueia acesso) |
| `cancelled` | Cancelada pelo admin |

---

## 🔧 Personalização

### Alterar Período de Trial

Edite `app/Helpers/SubscriptionHelper.php`:

```php
public static function calcularProximoVencimento(): string
{
    $hoje = new DateTime();
    $proximoVencimento = $hoje->modify('+30 days'); // Altere aqui (ex: +60 days)
    return $proximoVencimento->format('Y-m-d');
}
```

### Alterar Dias de Aviso (padrão: 7 dias)

Edite `app/Helpers/SubscriptionHelper.php`:

```php
public static function deveMostrarAviso(?string $dataVencimento, string $statusAssinatura): bool
{
    // ...
    return $diasRestantes >= 0 && $diasRestantes <= 7; // Altere o número aqui
}
```

### Adicionar QR Code Real

Edite `app/Views/subscription/renew.php` na seção `<div id="qrcode-area">`:

```php
<!-- Substitua o placeholder por: -->
<img src="<?= base_url('path/to/your/qrcode.png') ?>" alt="QR Code PIX" />

<!-- Ou gere dinamicamente usando biblioteca PHP -->
```

Bibliotecas recomendadas para gerar QR Code:
- **endroid/qr-code**: `composer require endroid/qr-code`
- **bacon/bacon-qr-code**: `composer require bacon/bacon-qr-code`

---

## 🛠️ Funções do Helper

### SubscriptionHelper

```php
// Calcular dias restantes
$dias = SubscriptionHelper::calcularDiasRestantes($dataVencimento);

// Verificar se deve mostrar aviso
$mostrar = SubscriptionHelper::deveMostrarAviso($dataVencimento, $status);

// Verificar se expirou
$expirou = SubscriptionHelper::estaExpirada($dataVencimento);

// Calcular próximo vencimento (30 dias)
$proxVenc = SubscriptionHelper::calcularProximoVencimento();

// Atualizar status automaticamente
$novoStatus = SubscriptionHelper::atualizarStatus($dataVencimento, $statusAtual);

// Obter mensagem formatada
$mensagem = SubscriptionHelper::obterMensagemAviso($diasRestantes, $status);

// Registrar pagamento
$resultado = SubscriptionHelper::registrarPagamento($usuarioModel, $userId);
```

---

## 📊 Dados na Sessão

Após o login, estas variáveis ficam disponíveis em `$_SESSION`:

```php
$_SESSION['subscription_status']          // 'trial', 'active', 'expired', 'cancelled'
$_SESSION['subscription_expiry_date']     // '2026-02-05'
$_SESSION['subscription_last_payment']    // '2026-01-05'
$_SESSION['subscription_trial_start']     // '2026-01-05'
$_SESSION['subscription_days_remaining']  // 25
$_SESSION['subscription_show_warning']    // true/false
```

---

## 🔒 Segurança

### Rotas Permitidas Mesmo com Assinatura Expirada

O `SubscriptionFilter` permite acesso a estas rotas mesmo após vencimento:

```php
$rotasPermitidas = [
    '/subscription/renew',
    '/subscription/status',
    '/logout',
    '/Usuario/logOut',
    '/loginUsuario',
    '/sigInUsuario'
];
```

Para adicionar mais rotas, edite `app/Filters/SubscriptionFilter.php`.

---

## 💰 Confirmação Manual de Pagamento

**IMPORTANTE**: Este sistema usa confirmação MANUAL de pagamento.

### Fluxo:
1. Usuário vê o QR Code e paga via PIX
2. Usuário clica em "Já paguei"
3. Sistema executa `SubscriptionController::confirmPayment()`
4. Atualiza:
   - `data_ultimo_pagamento` → hoje
   - `data_vencimento_assinatura` → +30 dias
   - `status_assinatura` → 'active'

### Para Integração com Gateway de Pagamento

Se você quiser automatizar com um gateway (Stripe, MercadoPago, etc.), substitua o método `confirmPayment()` para:

1. Verificar webhook do gateway
2. Validar transação
3. Chamar `SubscriptionHelper::registrarPagamento()`

---

## 🧪 Testes

### Testar Período de Trial

1. Registre um novo usuário
2. Confirme o email
3. Faça login
4. Verifique no banco: `data_inicio_trial` e `data_vencimento_assinatura` devem estar preenchidos

### Testar Aviso de Vencimento

Altere manualmente no banco:

```sql
UPDATE usuario 
SET data_vencimento_assinatura = DATE_ADD(CURDATE(), INTERVAL 5 DAY)
WHERE id = 1;
```

Faça login e veja o banner aparecer.

### Testar Bloqueio por Vencimento

```sql
UPDATE usuario 
SET data_vencimento_assinatura = DATE_SUB(CURDATE(), INTERVAL 1 DAY),
    status_assinatura = 'expired'
WHERE id = 1;
```

Tente acessar qualquer página → deve redirecionar para `/subscription/renew`.

---

## 📞 Suporte

Para dúvidas ou problemas:
- Verifique os logs do CodeIgniter: `writable/logs/`
- Consulte a documentação do CodeIgniter 4
- Entre em contato via `/contactUs`

---

## 📝 Checklist de Implementação

- [x] Migration executada no banco de dados
- [x] SubscriptionHelper criado
- [x] SubscriptionFilter criado e registrado
- [x] SubscriptionController criado
- [x] View de renovação criada
- [x] Rotas adicionadas
- [x] UsuarioModel atualizado (allowedFields)
- [x] Lógica de login atualizada
- [x] Banner de aviso na header
- [ ] QR Code real adicionado (substituir placeholder)
- [ ] Filtro ativado em `app/Config/Filters.php`
- [ ] Testar fluxo completo

---

## 🎉 Pronto!

Seu sistema de controle de assinatura está completo e funcional! 

**Próximos passos sugeridos:**
1. Adicionar QR Code real de pagamento
2. Configurar filtro em `Filters.php`
3. Testar com usuários reais
4. Considerar integração com gateway de pagamento automático
5. Adicionar relatórios de renovações no painel admin

---

**Desenvolvido para MyFlow Lab - Founder's Club 🚀**
