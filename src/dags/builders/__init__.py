# Builders Customizados para Factory Master

Este diretório contém implementações customizadas de DAGBuilder
para diferentes domínios e casos de uso.

Cada arquivo representa um builder específico que pode ser usado
para gerar DAGs com comportamentos customizados.

## 📁 Estrutura

```
builders/
├── __init__.py                    # Inicialização do pacote
├── hr_builder.py                  # ✨ EXEMPLO: Builder para RH
├── ecommerce_builder.py           # Builder para e-commerce (em breve)
├── finance_builder.py             # Builder para finanças (em breve)
└── base_builders.py               # Builders de propósito geral
```

## 🚀 Como Usar

### 1. Usar um Builder Existente

```python
from dag_builder_examples import DAGBuilderRegistry
from builders.hr_builder import HRDataPipelineBuilder

registry = DAGBuilderRegistry()
registry.register_builder('hr', HRDataPipelineBuilder)

# Agora qualquer DAG com builder_type='hr' usará HRDataPipelineBuilder
```

### 2. Criar um Novo Builder

```python
from dag_builder_base import DAGBuilder

class MeuBuilder(DAGBuilder):
    def customize_silver_transformation(self):
        def my_transform(**context):
            # Sua lógica aqui
            pass
        return my_transform
```

### 3. Registrar no MySQL

```sql
UPDATE dag_configurations 
SET builder_type = 'meu_tipo'
WHERE dag_id = 'minha_dag';
```

## 📚 Documentação

- [EXTENSIBILIDADE_FACTORY_MASTER.md](../EXTENSIBILIDADE_FACTORY_MASTER.md) - Guia completo
- [dag_builder_base.py](../dag_builder_base.py) - Classes base
- [dag_builder_examples.py](../dag_builder_examples.py) - Exemplos de uso

## 🤝 Contribuir

Para contribuir com um novo builder:

1. Crie um arquivo `seu_dominio_builder.py`
2. Herde de `DAGBuilder`
3. Sobrescreva os hooks necessários
4. Adicione documentação e exemplo de uso
5. Registre no `DAGBuilderRegistry`
