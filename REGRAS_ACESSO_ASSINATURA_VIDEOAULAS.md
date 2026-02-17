# 📚 Regras de Acesso: Assinatura vs. Videoaulas

## Visão Geral

O sistema possui duas regras principais para controlar o acesso:

1. **Regra de Assinatura da Plataforma**
   - Controla se o usuário pode acessar qualquer área da plataforma.
   - Baseada no campo `status_assinatura` (trial, active, expired, cancelled).
   - Gerenciada pelo filtro global `SubscriptionFilter`.

2. **Regra de Acesso às Videoaulas**
   - Controla se o usuário pode acessar módulos avançados (módulo 2+).
   - Baseada no campo `pagamento_inicial`.
   - Implementada diretamente no método `module()` do `CursoController`.

---

## Exemplo: Kauan Eduardo

| Campo                      | Valor                      |
|----------------------------|----------------------------|
| nome                       | Kauan Eduardo              |
| email                      | kauan.duardo@gmail.com     |
| criado_em                  | 2026-02-05 15:40:31        |
| updated_at                 | 2026-02-17 16:51:03        |
| data_ultimo_pagamento      | 2026-02-14                 |
| data_vencimento_assinatura | 2026-12-02                 |
| status_assinatura          | trial                      |
| data_inicio_trial          | 2026-02-05                 |
| pagamento_inicial          | 1                          |

**Situação:**  
- Kauan Eduardo está com status_assinatura = trial (período de teste).
- pagamento_inicial = 1 (pagamento inicial realizado).
- Ele pode acessar a plataforma normalmente e todos os módulos, inclusive os avançados.

---

## Matriz de Combinações: Acesso do Usuário

| status_assinatura | pagamento_inicial | Pode acessar plataforma? | Pode acessar módulo 1? | Pode acessar módulo 2+? | Bloqueio? | Observação |
|-------------------|-------------------|-------------------------|------------------------|-------------------------|-----------|------------|
| trial/active      | 1                 | ✅ Sim                  | ✅ Sim                 | ✅ Sim                  | Não       | Acesso total |
| trial/active      | 0                 | ✅ Sim                  | ✅ Sim                 | ❌ Não                  | Parcial   | Bloqueia módulos avançados |
| expired/cancelled | 1                 | ❌ Não                  | ❌ Não                 | ❌ Não                  | Total     | Bloqueio geral |
| expired/cancelled | 0                 | ❌ Não                  | ❌ Não                 | ❌ Não                  | Total     | Bloqueio geral |

**Legenda:**
- `status_assinatura` trial/active: Assinatura válida (trial ou paga).
- `status_assinatura` expired/cancelled: Assinatura vencida ou cancelada.
- `pagamento_inicial` 1: Pagamento inicial realizado.
- `pagamento_inicial` 0: Pagamento inicial não realizado.

---

## Como o status_assinatura é atualizado automaticamente

- O campo `status_assinatura` é gerenciado pelo sistema e atualizado por:
  - Filtro global (`SubscriptionFilter`) em cada requisição.
  - Comando automático (cron) que verifica vencimento e atualiza status.
- Quando a data de vencimento (`data_vencimento_assinatura`) passa do dia atual, o status muda para `expired`.
- Ao renovar e confirmar pagamento, o status volta para `active` e a data de vencimento é atualizada.

---

## Fluxo de Bloqueio

- **Assinatura Expirada:** O filtro bloqueia o acesso total e redireciona para `/subscription/renew`.
- **Pagamento Inicial Não Realizado:** O método `module()` bloqueia acesso a módulos avançados e redireciona para `/subscription/initial-payment`.
- **Ambos válidos:** Acesso total liberado.

---

## Resumo

- Kauan Eduardo está com acesso total porque:
  - Sua assinatura está em período trial (válida).
  - Ele realizou o pagamento inicial (`pagamento_inicial = 1`).
- O sistema bloqueia automaticamente conforme a matriz acima, garantindo clareza e segurança.

---

**Dúvidas ou sugestões? Consulte o README do sistema de assinatura ou entre em contato pelo suporte.**
