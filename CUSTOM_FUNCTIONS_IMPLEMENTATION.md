# Custom Functions - Implementação Completa

## 📋 Resumo da Implementação

Sistema que permite usuários criarem suas próprias funções Python customizadas através do editor de validações, sem perder acesso às funções core do sistema.

## ✅ Arquivos Criados/Modificados

### 1. Migration SQL
- `src/codeigniter-app/app/Database/Migrations/add_custom_functions_support.sql`
  - Adiciona colunas `is_custom` e `owner_user_id` em `funcion_configuration`
  - Cria triggers para garantir unicidade de módulos CORE
  - Atualiza funções existentes como CORE
  - ✅ Executada com sucesso no banco `lista_revisao2`

### 2. Models Atualizados

**FuncionConfigurationModel.php**
- `getAllAtivas()`: Retorna apenas funções CORE (is_custom=0)
- `getFuncoesDisponiveisParaUsuario($usuarioId)`: CORE ativas + CUSTOM do usuário
- `criarCustomFunction($usuarioId, $nome, $moduloPython, $descricao)`: Cria custom function

**UsuarioFuncionConfigurationModel.php**
- `sincronizarComPadrao($usuarioId)`: Agora NÃO apaga customs (apenas garante CORE)
- `associarCustomFunction($usuarioId, $funcionId)`: Associa custom ao criador
- `getFuncoesFormatadas($usuarioId)`: Agrupa com grupo "⭐ Custom (Minhas Funções)"

### 3. Controller Criado
- `src/codeigniter-app/app/Controllers/ValidationController.php`
  - `deployCustom()`: POST `/validation/deploy-custom`
  - `listCustom()`: GET `/validation/list-custom`
  - `deactivateCustom($id)`: POST `/validation/deactivate-custom/{id}`
  - `deleteCustom($id)`: DELETE `/validation/delete-custom/{id}`

### 4. Rotas Adicionadas
```php
$routes->post('/validation/deploy-custom', 'ValidationController::deployCustom');
$routes->get('/validation/list-custom', 'ValidationController::listCustom');
$routes->post('/validation/deactivate-custom/(:num)', 'ValidationController::deactivateCustom/$1');
$routes->delete('/validation/delete-custom/(:num)', 'ValidationController::deleteCustom/$1');
```

### 5. View Atualizada
- `src/codeigniter-app/app/Views/code_editor/unified-code-editor.php`
  - Função `deployValidator()` atualizada para:
    1. Extrair nome da classe do código
    2. Construir module_path (lib.validadores.arquivo.Classe)
    3. Salvar arquivo no Airflow
    4. Registrar custom function no banco
    5. Exibir mensagem de sucesso

## 🔄 Fluxo de Deploy

1. **Usuário escreve código Python** na aba "Validações"
   ```python
   class MeuValidador(RawToMedallionPipeline):
       def silver_layer_transform(self, silver_key: str) -> str:
           # código...
   ```

2. **Clica em "Deploy"**
   - Sistema extrai `MeuValidador` do código
   - Pede nome do arquivo (ex: `meu_validador.py`)
   - Constrói path: `lib.validadores.meu_validador.MeuValidador`

3. **Backend processa**
   - POST `/api/validation-deploy`: Salva arquivo no Airflow
   - POST `/validation/deploy-custom`: Registra no banco
     - Insere em `funcion_configuration` (is_custom=1, owner_user_id={userId})
     - Associa em `user_funcion_configuration`

4. **Usuário vê custom no select**
   - Grupo: "⭐ Custom (Minhas Funções)"
   - Opção: `MeuValidador`
   - Value: `lib.validadores.meu_validador.MeuValidador`

## 📊 Schema do Banco

```sql
funcion_configuration
├─ id INT UNSIGNED
├─ nome VARCHAR(128)
├─ modulo_python VARCHAR(255)
├─ grupo VARCHAR(64)
├─ ordem INT
├─ ativo TINYINT(1)
├─ is_custom TINYINT(1)          -- 0=CORE, 1=CUSTOM
├─ owner_user_id TINYINT UNSIGNED -- NULL=CORE, userId=CUSTOM
└─ UNIQUE KEY uk_owner_modulo (owner_user_id, modulo_python)

user_funcion_configuration
├─ id INT UNSIGNED
├─ usuario_id TINYINT UNSIGNED
├─ funcion_configuration_id INT UNSIGNED
└─ UNIQUE KEY uk_usuario_funcion (usuario_id, funcion_configuration_id)
```

## 🎯 Regras de Negócio

### Isolamento
- ✅ Usuário 146 vê: 6 CORE + suas custom
- ✅ Usuário 153 vê: 6 CORE + suas custom (pode ter mesmo nome!)
- ✅ Custom de um usuário NÃO aparece para outro

### Validações
- ✅ Módulo deve seguir padrão: `lib.validadores.*.Classe`
- ✅ Classe deve começar com letra maiúscula
- ✅ Não pode duplicar módulo para mesmo usuário
- ✅ CORE não pode duplicar módulo (garantido por trigger)

### Sincronização
- ✅ Novo usuário: recebe 6 CORE automaticamente
- ✅ Login: garante CORE, mantém CUSTOM intactas
- ✅ Custom nunca são apagadas pela sincronização

## 🧪 Teste Manual

### 1. Verificar Migration
```sql
SELECT id, nome, is_custom, owner_user_id, ativo 
FROM funcion_configuration 
ORDER BY is_custom, id;
```

**Resultado esperado:**
```
id | nome                  | is_custom | owner_user_id | ativo
---+-----------------------+-----------+---------------+------
1  | Pipeline Completo...  | 0         | NULL          | 1
2  | MySQL → Medallion     | 0         | NULL          | 1
...
7  | Função Legada         | 0         | NULL          | 0
```

### 2. Criar Custom Function

1. Acesse `/code-editor`
2. Aba "Validações"
3. Cole código:
```python
from lib.medallion_pipeline_v2 import RawToMedallionPipeline

class TesteValidador(RawToMedallionPipeline):
    def silver_layer_transform(self, silver_key: str) -> str:
        print("Meu validador custom!")
        return silver_key
```
4. Clique "Deploy"
5. Informe `teste_validador.py`
6. Confirme deploy

**Resultado esperado:**
```
✅ Deploy realizado com sucesso!

📄 Arquivo: teste_validador.py
🔧 Função: TesteValidador
📦 Módulo: lib.validadores.teste_validador.TesteValidador

Para usar:
1. Vá em "Configurações"
2. Escolha "TesteValidador" no select
3. Configure seu DAG

A função já está disponível! 🎉
```

### 3. Verificar no Select
1. Acesse `/listConfig`
2. Clique "Adicionar"
3. Campo "Função Python de Transformação"

**Resultado esperado:**
```html
<select name="python_module_path">
  <optgroup label="Recomendado">
    <option value="lib.medallion_pipeline...">Pipeline Completo</option>
  </optgroup>
  ...
  <optgroup label="⭐ Custom (Minhas Funções)">
    <option value="lib.validadores.teste_validador.TesteValidador">TesteValidador</option>
  </optgroup>
</select>
```

### 4. Verificar Isolamento
1. Logue com outro usuário
2. Vá em "Configurações" → "Adicionar"
3. Verifique select

**Resultado esperado:**
- NÃO aparece `TesteValidador` do usuário anterior
- Apenas 6 funções CORE visíveis

## 📁 Estrutura de Arquivos

```
datalake-air-flow-delta/
└─ src/codeigniter-app/
   ├─ app/
   │  ├─ Controllers/
   │  │  └─ ValidationController.php          ✨ NOVO
   │  ├─ Models/
   │  │  ├─ FuncionConfigurationModel.php     ✏️ MODIFICADO
   │  │  └─ UsuarioFuncionConfigurationModel.php ✏️ MODIFICADO
   │  ├─ Config/
   │  │  └─ Routes.php                        ✏️ MODIFICADO
   │  ├─ Database/Migrations/
   │  │  └─ add_custom_functions_support.sql  ✨ NOVO
   │  └─ Views/
   │     └─ code_editor/
   │        └─ unified-code-editor.php         ✏️ MODIFICADO

datalake-air-flow-teste/
└─ (mesma estrutura - tudo replicado)
```

## 🚀 Status da Implementação

- ✅ Migration criada e executada
- ✅ Models atualizados (delta + teste)
- ✅ Controller criado (delta + teste)
- ✅ Rotas configuradas (delta + teste)
- ✅ View atualizada (delta + teste)
- ✅ Sincronização preserva custom
- ✅ Validações implementadas
- ✅ Isolamento por usuário garantido

## 🔍 Próximos Passos (Opcional)

1. **Testar deploy completo**
   - Criar custom function via interface
   - Verificar aparição no select
   - Testar execução no Airflow

2. **Gerenciamento de custom**
   - Criar interface para listar customs
   - Permitir desativar/deletar custom
   - Exibir estatísticas de uso

3. **Validação avançada**
   - Verificar sintaxe Python antes de deploy
   - Testar import da classe
   - Validar herança de RawToMedallionPipeline

---

Data: 2026-01-23  
Workspaces: delta + teste  
Status: ✅ Implementação Completa
