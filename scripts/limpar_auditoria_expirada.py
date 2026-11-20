#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Script de Limpeza e Expurgo de Auditoria Expirada (pipeline_lifecycle_logs).
Remove registros de eventos de ciclo de vida com mais de N dias (padrão: 30 dias)
em lotes controlados (batching) para manter alta performance e evitar locks de tabela.

Conformidade com as Regras de Ouro:
- Regra 8: Inicialização prévia mandatória de variáveis numéricas locais.
- Regra 15: Proibição absoluta de silenciamento de exceções com contexto completo.
- Regra 18: Proibição de credenciais hardcoded (utiliza db_config.py).
- Regra 19: Operações DML seguras e idempotentes.
"""

import sys
import os
import argparse
import time
from datetime import datetime

# Adiciona diretório scripts ao path para importar db_config
CURRENT_DIR = os.path.dirname(os.path.abspath(__file__))
if CURRENT_DIR not in sys.path:
    sys.path.insert(0, CURRENT_DIR)

from db_config import get_db_connection


def purgar_auditoria_expirada(dias_retencao: int = 30, tamanho_lote: int = 1000, dry_run: bool = False) -> dict:
    """
    Expurga registros da tabela pipeline_lifecycle_logs mais antigos que dias_retencao.
    Retorna dicionário com métricas da operação.
    """
    # Inicialização prévia obrigatória de variáveis numéricas locais (Regra 8)
    total_elegiveis = 0
    total_removidos = 0
    total_lotes = 0
    duracao_segundos = 0.0

    t_inicio = time.time()
    conn = None
    cursor = None

    print("=" * 70)
    print(f"🧹 [Expurgo de Auditoria] Iniciando rotina de limpeza - {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
    print(f"⚙️ Configuração: Retenção = {dias_retencao} dias | Lote = {tamanho_lote} registros | Modo Dry-Run = {dry_run}")
    print("=" * 70)

    try:
        conn = get_db_connection()
        cursor = conn.cursor()

        # 1. Contagem prévia de registros elegíveis para expurgo
        sql_count = """
            SELECT COUNT(*) AS total 
            FROM pipeline_lifecycle_logs 
            WHERE criado_em < DATE_SUB(NOW(), INTERVAL %s DAY)
        """
        cursor.execute(sql_count, (dias_retencao,))
        row = cursor.fetchone()
        if isinstance(row, dict):
            total_elegiveis = int(row.get('total') or 0)
        elif isinstance(row, (list, tuple)):
            total_elegiveis = int(row[0]) if row and row[0] is not None else 0
        else:
            total_elegiveis = 0

        print(f"📊 Registros identificados com mais de {dias_retencao} dias: {total_elegiveis}")

        if total_elegiveis == 0:
            print("✅ Nenhum registro expirado encontrado. Nenhuma exclusão necessária.")
            duracao_segundos = round(time.time() - t_inicio, 3)
            return {
                "sucesso": True,
                "total_elegiveis": 0,
                "total_removidos": 0,
                "total_lotes": 0,
                "duracao_segundos": duracao_segundos,
                "dry_run": dry_run
            }

        if dry_run:
            print(f"🔍 [DRY-RUN] Simulação concluída: {total_elegiveis} registros seriam excluídos.")
            duracao_segundos = round(time.time() - t_inicio, 3)
            return {
                "sucesso": True,
                "total_elegiveis": total_elegiveis,
                "total_removidos": 0,
                "total_lotes": 0,
                "duracao_segundos": duracao_segundos,
                "dry_run": True
            }

        # 2. Exclusão em lotes controlados (Batching com LIMIT)
        sql_delete = """
            DELETE FROM pipeline_lifecycle_logs 
            WHERE criado_em < DATE_SUB(NOW(), INTERVAL %s DAY) 
            LIMIT %s
        """

        while True:
            cursor.execute(sql_delete, (dias_retencao, tamanho_lote))
            afetados = cursor.rowcount
            conn.commit()

            if afetados <= 0:
                break

            total_removidos += afetados
            total_lotes += 1
            print(f"   • Lote #{total_lotes}: {afetados} registros removidos (Total acumulado: {total_removidos}/{total_elegiveis})")

            # Pequena pausa cooperativa para liberar locks em concorrência
            time.sleep(0.05)

        duracao_segundos = round(time.time() - t_inicio, 3)
        print("-" * 70)
        print(f"✅ [Expurgo Concluído com Sucesso]")
        print(f"   • Registros purgados: {total_removidos}")
        print(f"   • Lotes executados: {total_lotes}")
        print(f"   • Tempo total: {duracao_segundos} segundos")
        print("=" * 70)

        return {
            "sucesso": True,
            "total_elegiveis": total_elegiveis,
            "total_removidos": total_removidos,
            "total_lotes": total_lotes,
            "duracao_segundos": duracao_segundos,
            "dry_run": False
        }

    except Exception as e:
        # Visibilidade obrigatória de falhas de banco de dados (Regra 15)
        err_code = getattr(e, 'args', [None])[0] if hasattr(e, 'args') else 'UNKNOWN'
        print(f"❌ [FALHA DE BANCO DE DADOS] Erro na exclusão de auditoria expirada:", file=sys.stderr)
        print(f"   • Operação: DELETE FROM pipeline_lifecycle_logs", file=sys.stderr)
        print(f"   • Código do Erro: {err_code}", file=sys.stderr)
        print(f"   • Mensagem: {e}", file=sys.stderr)
        if conn:
            try:
                conn.rollback()
            except Exception:
                pass
        raise
    finally:
        if cursor:
            cursor.close()
        if conn:
            conn.close()


def main():
    parser = argparse.ArgumentParser(description="Expurgo de registros antigos da tabela pipeline_lifecycle_logs.")
    parser.add_argument("--days", type=int, default=30, help="Dias de retenção da auditoria (padrão: 30 dias)")
    parser.add_argument("--batch-size", type=int, default=1000, help="Quantidade de registros por lote de exclusão (padrão: 1000)")
    parser.add_argument("--dry-run", action="store_true", help="Simula o expurgo sem efetivar exclusões no banco")
    args = parser.parse_args()

    purgar_auditoria_expirada(
        dias_retencao=args.days,
        tamanho_lote=args.batch_size,
        dry_run=args.dry_run
    )


if __name__ == "__main__":
    main()
