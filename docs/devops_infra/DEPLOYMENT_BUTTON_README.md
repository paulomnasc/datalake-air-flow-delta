# Botão de Deploy - Guia de Uso

## O que foi adicionado?

Um novo botão **🚀 Implantar** foi adicionado ao editor de validações. Este botão automatiza o processo de sincronização de validadores do Git para o Airflow.

## Localização

- **Arquivo**: `/src/codeigniter-app/app/Views/code_editor/validation-rules-editor.php`
- **Localização na UI**: Linha de botões após o editor Python (junto com "▶️ Testar", "💾 Salvar", "🗑️ Limpar")

```
[▶️ Testar] [💾 Salvar] [🚀 Implantar] [🗑️ Limpar]
```

## Como Usar

### Passo 1: Criar ou Abrir um Validador

1. Abra o editor de validações em `/validation-rules-editor`
2. Conecte ao GitHub (se necessário)
3. Crie um novo arquivo (`+ Novo`) ou abra um existente

### Passo 2: Escrever o Código do Validador

```python
from src.datalake.medallion import raw_to_medallion
import pandas as pd

class MeuValidador:
    def __call__(self, source_filename, target_table_name, **context):
        # Executar pipeline padrão
        result = raw_to_medallion(source_filename, target_table_name, **context)
        
        # Suas validações customizadas aqui
        self.custom_validations(result, target_table_name, **context)
        
        return result
    
    def custom_validations(self, pipeline_result, target_table_name, **context):
        # Lógica de validação
        pass

# Função auxiliar para testes no editor
def validate(df):
    """Função auxiliar para validar dados no editor"""
    return df
```

### Passo 3: Testar Localmente

Clique em **▶️ Testar** para validar:
- Sintaxe Python
- Presença da função `validate(df)`
- Problemas básicos de indentação

### Passo 4: Salvar no Git

Clique em **💾 Salvar** para:
- Fazer commit do arquivo no repositório Git
- O arquivo será armazenado no seu usuário/bucket do Git

### Passo 5: Implantar para Airflow

Clique em **🚀 Implantar** para:
- Sincronizar o arquivo do Git para `/opt/airflow/dags/` no container
- Verificar se as importações estão corretas
- Permitir que o Airflow recarregue a DAG automaticamente

## Fluxo Completo

```
Editar Código
     ↓
▶️  Testar (verificar sintaxe)
     ↓
💾 Salvar (commit no Git)
     ↓
🚀 Implantar (sincronizar para Airflow)
     ↓
✅ DAG aparece no Airflow Web UI (~30 segundos)
```

## Mudanças Técnicas Implementadas

### 1. **Controller PHP** - `ValidationRulesController.php`
```php
public function deploy()
{
    // Recebe: { filename: 'seu_validador.py' }
    // Executa: ./sync_validators_to_airflow.sh seu_validador.py
    // Retorna: { success, message, output, next_step }
}
```
- Rota: `POST /api/validation-deploy`
- Sanitização de nomes de arquivo
- Tratamento de erros com mensagens descritivas

### 2. **JavaScript** - `validation-rules-editor.php`
```javascript
async function deployValidator()
{
    // 1. Valida se há arquivo aberto
    // 2. Pede confirmação do usuário
    // 3. Envia POST /api/validation-deploy
    // 4. Mostra resultado com feedback visual
}
```
- Validação de estado (arquivo aberto, editor não vazio)
- Feedback visual (botão desabilitado, spinner)
- Mensagens de sucesso/erro amigáveis

### 3. **Rota** - `Routes.php`
```php
$routes->post('/api/validation-deploy', 'ValidationRulesController::deploy', 
              ['as'=>'validation-deploy']);
```

### 4. **Estilo CSS** - `.btn-success` classe
```css
.btn-success {
    background: #f59e0b;  /* Laranja */
    color: white;
}

.btn-success:hover {
    background: #d97706;
}
```

## Mensagens de Feedback

### ✅ Sucesso
```
✅ seu_validador.py sincronizado para Airflow!
Aguarde 30 segundos e procure a DAG no Airflow Web UI
```

### ❌ Erro - Editor vazio
```
❌ Editor vazio - Salve um arquivo primeiro
```

### ❌ Erro - Nenhum arquivo aberto
```
❌ Nenhum arquivo aberto - Abra ou crie um arquivo no Git
```

### ❌ Erro - Script não disponível
```
❌ Script de sincronização não disponível
```

## O que Acontece no Backend

Quando você clica em **🚀 Implantar**, o seguinte ocorre:

1. **Recebe request JSON**: `{ filename: 'seu_validador.py' }`

2. **Executa script bash**: `./sync_validators_to_airflow.sh seu_validador.py`

3. **O script faz**:
   - Copia arquivo para `/opt/airflow/dags/` dentro do container
   - Valida importações: `python -c "import seu_validador"`
   - Lista arquivos de resultado
   - Aguarda 30s para Airflow detectar

4. **Retorna resposta JSON**:
```json
{
  "success": true,
  "message": "✅ seu_validador.py sincronizado para Airflow!",
  "output": "[docker cp output...]\n[verification output...]\n",
  "next_step": "Aguarde 30 segundos e procure a DAG no Airflow Web UI"
}
```

## Verificação no Airflow

Após clicar em **🚀 Implantar**:

1. Aguarde ~30 segundos
2. Acesse Airflow Web UI em `http://seu-airflow:8080`
3. Procure pela DAG que usa seu validador
4. Ela deve aparecer com status "Enabled"

## Troubleshooting

### "Falha ao sincronizar"
- Verifique se o arquivo foi salvo no Git antes
- Confirme que o `sync_validators_to_airflow.sh` existe
- Verifique permissões de arquivo: `chmod +x sync_validators_to_airflow.sh`

### DAG não aparece no Airflow
- Aguarde mais de 30 segundos
- Verifique logs do Airflow: `docker logs [airflow-container]`
- Confirme que não há erros de sintaxe Python

### Script retorna exit code 1
- Verifique se o arquivo está corretamente formatado em Python
- Teste localmente: `python seu_validador.py`
- Verifique importações necessárias

## Arquivos Modificados

1. ✅ `/src/codeigniter-app/app/Controllers/ValidationRulesController.php`
   - Adicionada função `deploy()`

2. ✅ `/src/codeigniter-app/app/Config/Routes.php`
   - Adicionada rota `POST /api/validation-deploy`

3. ✅ `/src/codeigniter-app/app/Views/code_editor/validation-rules-editor.php`
   - Adicionado botão "🚀 Implantar"
   - Adicionada função JavaScript `deployValidator()`
   - Adicionado CSS `.btn-success`

## Próximos Passos

Agora que o botão de deploy está funcionando:

1. **Criar seu validador** usando `MEU_VALIDADOR_CORRETO.py` como modelo
2. **Testar no editor** com o botão ▶️ Testar
3. **Salvar no Git** com o botão 💾 Salvar
4. **Implantar** com o botão 🚀 Implantar
5. **Verificar no Airflow** que a DAG aparece
6. **Executar manualmente** para testar end-to-end

## Referência

- [CUSTOM_VALIDATIONS_README.md](CUSTOM_VALIDATIONS_README.md) - Guia completo de validações customizadas
- [MEU_VALIDADOR_CORRETO.py](MEU_VALIDADOR_CORRETO.py) - Modelo de validador
- [sync_validators_to_airflow.sh](sync_validators_to_airflow.sh) - Script de sincronização
