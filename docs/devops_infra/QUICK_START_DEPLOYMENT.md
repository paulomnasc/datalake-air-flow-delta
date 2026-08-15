# 🎯 Quick Start - Usando o Botão de Deploy

## ⚡ Em 3 Passos

### 1️⃣ Escreva seu Validador
```python
class MeuValidador:
    def __call__(self, source_filename, target_table_name, **context):
        result = raw_to_medallion(source_filename, target_table_name, **context)
        self.custom_validations(result, target_table_name, **context)
        return result
    
    def custom_validations(self, pipeline_result, target_table_name, **context):
        # Sua lógica aqui
        pass

def validate(df):
    return df
```

### 2️⃣ Clique: 💾 Salvar → 🚀 Implantar
- Editor detecta arquivo automaticamente
- Confirma: "Sincronizar 'seu_validador.py' para Airflow?"
- Clique OK

### 3️⃣ Aguarde 30s e Procure a DAG
- Airflow detecta automaticamente
- DAG aparece no Airflow Web UI
- Pronto! 🎉

---

## 🎮 Botões Disponíveis

| Botão | Função | Quando Usar |
|-------|--------|-----------|
| ▶️ Testar | Valida sintaxe Python | Antes de salvar |
| 💾 Salvar | Commit no Git | Depois de escrever |
| 🚀 **Implantar** | Sincroniza para Airflow | Depois de salvar |
| 🗑️ Limpar | Limpa editor | Começar do zero |

---

## 📊 Fluxo Completo

```
[Editor]
  ↓
[▶️ Testar] → Verifica sintaxe
  ↓
[💾 Salvar] → Armazena no Git
  ↓
[🚀 Implantar] → Copia para Airflow
  ↓
[✅ DAG aparece no Airflow]
```

---

## ✅ Checklist

- [ ] Arquivo aberto no editor Git
- [ ] Código Python syntáticamente correto
- [ ] Função `validate(df)` presente
- [ ] Arquivo salvo (botão 💾)
- [ ] Confirmou deploy (botão 🚀)
- [ ] Aguardou 30 segundos
- [ ] DAG visível no Airflow

---

## ❌ Erros Comuns

| Erro | Solução |
|------|---------|
| "Editor vazio" | Escreva o código primeiro |
| "Nenhum arquivo aberto" | Crie/abra um arquivo no Git |
| "Função validate não encontrada" | Adicione `def validate(df):` |
| "DAG não aparece" | Aguarde mais 30s ou verifique logs |

---

## 🔍 Verificar Resultado

1. **Sucesso**: Mensagem "✅ [arquivo] sincronizado para Airflow!"
2. **Erro**: Mensagem com descrição detalhada
3. **Logs**: Console do navegador (F12 → Console)

---

## 📚 Mais Informações

- Guia Completo: [DEPLOYMENT_BUTTON_README.md](DEPLOYMENT_BUTTON_README.md)
- Template de Validador: [MEU_VALIDADOR_CORRETO.py](MEU_VALIDADOR_CORRETO.py)
- Documentação de Validações: [CUSTOM_VALIDATIONS_README.md](CUSTOM_VALIDATIONS_README.md)

---

**Pronto para começar?** Abra `/validation-rules-editor` e teste agora! 🚀
