"""
Carregador de regras de validação customizadas do usuário.

Este módulo permite que usuários criem validações Python via interface web
sem precisar editar código das DAGs diretamente.

Uso na DAG:
    from lib.custom_validators import load_user_validators, execute_validator
    
    validators = load_user_validators(bucket='lab01', layer='silver')
    results = execute_validator('check_nulls', df, context)
"""

import logging
import os
import tempfile
from typing import Dict, Any, List, Callable, Optional
import importlib.util
import sys

log = logging.getLogger(__name__)


def load_user_validators(
    bucket: str,
    layer: str,
    table: Optional[str] = None
) -> Dict[str, Callable]:
    """
    Carrega validadores customizados do usuário do MinIO.
    
    Args:
        bucket: Nome do bucket do usuário (ex: 'lab01')
        layer: Camada medallion ('bronze', 'silver', 'gold')
        table: Nome da tabela (opcional, filtra validadores específicos)
    
    Returns:
        Dicionário {nome_validador: função_validate}
    """
    try:
        from airflow.providers.amazon.aws.hooks.s3 import S3Hook
        
        hook = S3Hook(aws_conn_id='minio_conn')
        validators = {}
        
        # Buscar todos os arquivos .py em validation-rules/{layer}/
        prefix = f"validation-rules/{layer}/"
        
        try:
            files = hook.list_keys(bucket_name=bucket, prefix=prefix)
            if not files:
                log.info(f"[VALIDATORS] Nenhum validador encontrado em {bucket}/{prefix}")
                return {}
            
            for file_key in files:
                if not file_key.endswith('.py'):
                    continue
                
                validator_name = os.path.basename(file_key).replace('.py', '')
                
                # Se table especificada, verificar se validador é aplicável
                if table:
                    # Carregar metadata JSON para verificar
                    json_key = file_key.replace('.py', '.json')
                    try:
                        import json
                        metadata_obj = hook.get_key(json_key, bucket_name=bucket)
                        metadata = json.loads(metadata_obj.get()['Body'].read())
                        
                        # Pular se validador é específico para outra tabela
                        if metadata.get('table') and metadata['table'] != table:
                            continue
                            
                    except Exception:
                        pass  # Se não tem metadata, aplicar em todas as tabelas
                
                # Baixar código Python
                try:
                    py_obj = hook.get_key(file_key, bucket_name=bucket)
                    py_code = py_obj.get()['Body'].read().decode('utf-8')
                    
                    # Criar módulo Python dinamicamente
                    validator_func = _load_validator_from_code(validator_name, py_code)
                    
                    if validator_func:
                        validators[validator_name] = validator_func
                        log.info(f"[VALIDATORS] ✓ Carregado: {validator_name}")
                    
                except Exception as e:
                    log.error(f"[VALIDATORS] Erro ao carregar {validator_name}: {e}")
                    continue
            
            log.info(f"[VALIDATORS] Total carregados: {len(validators)}")
            return validators
            
        except Exception as e:
            log.error(f"[VALIDATORS] Erro ao listar arquivos: {e}")
            return {}
            
    except Exception as e:
        log.error(f"[VALIDATORS] Erro ao carregar validadores: {e}")
        return {}


def _load_validator_from_code(name: str, code: str) -> Optional[Callable]:
    """
    Carrega função validate() de código Python.
    
    Args:
        name: Nome do validador
        code: Código Python contendo def validate(df, **context)
    
    Returns:
        Função validate ou None se erro
    """
    try:
        # Criar módulo temporário
        module_name = f"custom_validator_{name}"
        
        spec = importlib.util.spec_from_loader(module_name, loader=None)
        module = importlib.util.module_from_spec(spec)
        
        # Executar código no namespace do módulo
        exec(code, module.__dict__)
        
        # Buscar função validate
        if hasattr(module, 'validate'):
            return module.validate
        else:
            log.warning(f"[VALIDATORS] {name}: função validate() não encontrada")
            return None
            
    except Exception as e:
        log.error(f"[VALIDATORS] Erro ao compilar {name}: {e}")
        return None


def execute_validator(
    validator_name: str,
    df,
    context: Dict[str, Any],
    validators: Dict[str, Callable]
) -> Dict[str, Any]:
    """
    Executa um validador específico.
    
    Args:
        validator_name: Nome do validador a executar
        df: DataFrame pandas com dados
        context: Contexto do Airflow (task_instance, etc)
        validators: Dict de validadores carregados
    
    Returns:
        Resultado da validação (dict com 'status', 'message', etc)
    """
    if validator_name not in validators:
        raise ValueError(f"Validador '{validator_name}' não encontrado")
    
    try:
        log.info(f"[VALIDATOR] Executando: {validator_name}")
        result = validators[validator_name](df, **context)
        
        if not isinstance(result, dict):
            result = {'status': 'ok', 'result': result}
        
        log.info(f"[VALIDATOR] ✓ {validator_name}: {result.get('status', 'ok')}")
        return result
        
    except Exception as e:
        log.error(f"[VALIDATOR] ✗ {validator_name} falhou: {e}")
        raise


def execute_all_validators(
    df,
    context: Dict[str, Any],
    bucket: str,
    layer: str,
    table: Optional[str] = None,
    fail_on_error: bool = True
) -> Dict[str, Any]:
    """
    Executa todos os validadores aplicáveis para a camada/tabela.
    
    Args:
        df: DataFrame pandas
        context: Contexto Airflow
        bucket: Bucket do usuário
        layer: Camada ('bronze', 'silver', 'gold')
        table: Nome da tabela (opcional)
        fail_on_error: Se True, lança exceção em falha; se False, apenas loga
    
    Returns:
        Dict com resultados de todas as validações
    """
    validators = load_user_validators(bucket, layer, table)
    
    if not validators:
        log.info(f"[VALIDATORS] Nenhum validador para {layer}/{table or 'all'}")
        return {'status': 'ok', 'validators_run': 0}
    
    results = {}
    failed = []
    
    for name, func in validators.items():
        try:
            result = execute_validator(name, df, context, {name: func})
            results[name] = result
            
            if result.get('status') != 'ok':
                failed.append(name)
                
        except Exception as e:
            results[name] = {'status': 'error', 'error': str(e)}
            failed.append(name)
            
            if fail_on_error:
                raise Exception(f"Validação '{name}' falhou: {e}")
    
    summary = {
        'status': 'ok' if not failed else 'failed',
        'validators_run': len(validators),
        'passed': len(validators) - len(failed),
        'failed': len(failed),
        'failed_validators': failed,
        'results': results
    }
    
    log.info(f"[VALIDATORS] Resumo: {summary['passed']}/{summary['validators_run']} passaram")
    
    return summary


# ─────────────────────────────────────────────────────────────────────────
# Integração com silver_layer.py
# ─────────────────────────────────────────────────────────────────────────

def apply_validations_to_dataframe(
    df,
    bucket: str,
    layer: str,
    table: str,
    context: Optional[Dict[str, Any]] = None
):
    """
    Aplica validações customizadas a um DataFrame durante processamento.
    
    Esta função é chamada automaticamente por silver_layer.py após transformações.
    
    Args:
        df: pandas DataFrame
        bucket: Bucket do usuário
        layer: Camada atual
        table: Nome da tabela
        context: Contexto Airflow (opcional)
    
    Returns:
        DataFrame validado (mesmo objeto, mas validado)
    
    Raises:
        Exception: Se alguma validação falhar
    """
    if context is None:
        context = {}
    
    try:
        results = execute_all_validators(
            df=df,
            context=context,
            bucket=bucket,
            layer=layer,
            table=table,
            fail_on_error=True
        )
        
        # Adicionar metadata de validação ao contexto
        if 'task_instance' in context:
            context['task_instance'].xcom_push(
                key=f'validation_results_{layer}',
                value=results
            )
        
        return df
        
    except Exception as e:
        log.error(f"[VALIDATIONS] Erro crítico: {e}")
        raise


# ─────────────────────────────────────────────────────────────────────────
# Helpers para DAG Builder
# ─────────────────────────────────────────────────────────────────────────

def create_validation_task_func(bucket: str, layer: str, table: str = None) -> Callable:
    """
    Cria função de validação para usar em DAGBuilder.customize_validation_task().
    
    Exemplo de uso em DAGBuilder:
        def customize_validation_task(self) -> Callable:
            from lib.custom_validators import create_validation_task_func
            return create_validation_task_func(
                bucket=self.config.get('bucket_name', 'lab01'),
                layer='silver',
                table=self.config.get('table_name')
            )
    
    Args:
        bucket: Bucket do usuário
        layer: Camada a validar
        table: Nome da tabela (opcional)
    
    Returns:
        Função callable para PythonOperator
    """
    def validation_task(**context):
        """Task de validação com regras customizadas do usuário."""
        import pandas as pd
        
        ti = context['task_instance']
        
        # Buscar dados da task anterior (assumindo nomenclatura padrão)
        task_id = f"{layer}_task"
        data = ti.xcom_pull(task_ids=task_id)
        
        if data is None:
            raise ValueError(f"Task {task_id} não retornou dados")
        
        # Se data é dict com 'df' ou 'data', extrair DataFrame
        if isinstance(data, dict):
            if 'df' in data:
                df = data['df']
            elif 'data' in data:
                df = data['data']
            else:
                # Tentar converter dict para DataFrame
                df = pd.DataFrame([data])
        else:
            df = data
        
        if not isinstance(df, pd.DataFrame):
            raise TypeError(f"Esperado DataFrame, recebido {type(df)}")
        
        # Executar validações customizadas
        results = execute_all_validators(
            df=df,
            context=context,
            bucket=bucket,
            layer=layer,
            table=table,
            fail_on_error=True
        )
        
        return results
    
    return validation_task
