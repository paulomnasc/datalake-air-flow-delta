# Backlog de Problemas - Wizard Pipeline

## 1. Wizard não aparece na UX ao clicar no botão
- O botão "Criar Novo Pipeline" redireciona, mas a view não é exibida corretamente.

## 2. Erros de duplicidade do Monaco Editor
- Mensagem: "Duplicate definition of module 'vs/editor/editor.main'".
- Indica que Monaco está sendo carregado mais de uma vez.

## 3. Console mostra erros de scripts e listeners
- Conflitos entre Alpine.js e Monaco.
- Erros de listeners e promessas não resolvidas.

## 4. Ajustes feitos para carregar Monaco localmente não resolveram
- O wizard ainda não renderiza corretamente.

## 5. Outras views que usam Monaco não podem ser afetadas
- code-editor.php e outras dependem do Monaco global.

## 6. Dashboard/index.php pode interferir no fluxo do wizard
- Alpine.js pode estar impedindo o redirecionamento ou renderização.

## 7. Nenhuma alteração recente resolveu o problema principal
- O wizard funcional em view dedicada ainda não está operacional.

---

## Plano de Ação
1. Diagnosticar por que a view wizard/create-pipeline.php não é renderizada corretamente após o redirecionamento.
2. Corrigir duplicidade de carregamento do Monaco Editor.
3. Eliminar conflitos de scripts entre Alpine.js e Monaco.
4. Garantir que o wizard funcione sem afetar outras views.
5. Validar o fluxo completo do botão até o wizard.

---

## Histórico
- Últimas alterações: ajuste de botão, carregamento local do Monaco, criação de rota/controller.
- Persistem erros de renderização e duplicidade de scripts.
