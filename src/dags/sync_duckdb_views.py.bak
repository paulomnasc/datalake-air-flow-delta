"""
DAG para sincronizar views do DuckDB com dados do MinIO.
Mantém o arquivo DuckDB atualizado automaticamente para consumo no Power BI.
Descobre dinamicamente a estrutura de pastas no MinIO.
"""
from datetime import datetime, timedelta
from airflow import DAG
from airflow.operators.python import PythonOperator
from airflow.operators.bash import BashOperator
import os
import subprocess
import duckdb
from pathlib import Path
import re
import shutil

# Configurações
DUCKDB_PATH = '/opt/duckdb/datalake.duckdb'
DUCKDB_BI_PATH = '/opt/duckdb/datalake_bi.duckdb'
MINIO_ENDPOINT = 'minio:9000'  # Dentro do Docker
MINIO_ACCESS_KEY = 'admin'
MINIO_SECRET_KEY = 'admin123'
MINIO_BUCKET = 'lab01'

# Configuração: buscar dados em delta/ e materializar uma tabela por pasta
# Estrutura: s3://lab01/delta/{table_name_with_timestamp}/*.parquet
SEARCH_GLOBS = [
    's3://lab01/delta/*/*.parquet',          # delta/{folder}/*.parquet
    's3://lab01/delta/*/*/*.parquet',        # delta/{folder}/{part}/*.parquet (particionado)
]

# Caminhos explícitos conhecidos (forçam materialização quando informados)
EXACT_PATHS = [
    's3://lab01/delta/customers_202512230532/*.parquet',
]

default_args = {
    'owner': 'airflow',
    'depends_on_past': False,
    'email_on_failure': False,
    'email_on_retry': False,
    'retries': 1,
    'retry_delay': timedelta(minutes=5),
}


def setup_duckdb(**context):
    """Configura DuckDB e instala extensões."""
    import subprocess
    
    con = None
    try:
        # Garante que o diretório existe
        Path(DUCKDB_PATH).parent.mkdir(parents=True, exist_ok=True)
        
        # Garante permissões para acesso remoto (WSL/Windows)
        try:
            subprocess.run(['chmod', '777', str(Path(DUCKDB_PATH).parent)], check=True)
        except Exception as e:
            print(f"⚠️  Aviso ao definir permissões do diretório: {e}")
        
        # Remove arquivo corrompido se existir
        if Path(DUCKDB_PATH).exists():
            try:
                con_test = duckdb.connect(DUCKDB_PATH)
                con_test.close()
            except Exception as e:
                print(f"⚠️  Arquivo corrompido detectado, removendo: {DUCKDB_PATH}")
                Path(DUCKDB_PATH).unlink()
        
        # Conecta
        con = duckdb.connect(DUCKDB_PATH)
        
        # Instala e carrega extensões
        print("📦 Instalando extensões...")
        con.execute("INSTALL httpfs;")
        con.execute("LOAD httpfs;")
        
        # Configura S3/MinIO
        print("🔧 Configurando acesso ao MinIO...")
        con.execute(f"SET s3_endpoint='{MINIO_ENDPOINT}';")
        con.execute(f"SET s3_access_key_id='{MINIO_ACCESS_KEY}';")
        con.execute(f"SET s3_secret_access_key='{MINIO_SECRET_KEY}';")
        con.execute("SET s3_use_ssl=false;")
        con.execute("SET s3_url_style='path';")
        
        # Garante permissões de leitura para acesso remoto (WSL/Windows)
        try:
            subprocess.run(['chmod', '777', DUCKDB_PATH], check=True)
            print(f"✅ Permissões configuradas para acesso remoto")
        except Exception as e:
            print(f"⚠️  Aviso ao definir permissões do arquivo: {e}")
        
        print(f"✅ DuckDB configurado: {DUCKDB_PATH}")
        
    except Exception as e:
        print(f"❌ ERRO ao configurar DuckDB: {str(e)}")
        import traceback
        traceback.print_exc()
        raise
    finally:
        if con:
            try:
                con.close()
                print("✅ Conexão DuckDB fechada após setup")
            except Exception as e:
                print(f"⚠️  Erro ao fechar conexão em setup_duckdb: {e}")


def sync_views(**context):
    """Cria ou atualiza views descobrindo dinamicamente subpastas no MinIO."""
    con = None
    try:
        print(f"\n🔌 Conectando ao DuckDB: {DUCKDB_PATH}")
        con = duckdb.connect(DUCKDB_PATH)
        
        # Reaplica configurações S3 (necessário a cada conexão)
        print("📡 Carregando extensão httpfs...")
        con.execute("LOAD httpfs;")
        print(f"🔐 Configurando S3: endpoint={MINIO_ENDPOINT}")
        con.execute(f"SET s3_endpoint='{MINIO_ENDPOINT}';")
        con.execute(f"SET s3_access_key_id='{MINIO_ACCESS_KEY}';")
        con.execute(f"SET s3_secret_access_key='{MINIO_SECRET_KEY}';")
        con.execute("SET s3_use_ssl=false;")
        con.execute("SET s3_url_style='path';")
        print("✅ Extensão e S3 configurados com sucesso")
        
        print("\n📊 Descobrindo estrutura no MinIO e criando tabelas...")
        results = []

        # Descobre pastas únicas lendo filenames via glob
        folders = set()
        glob_list = EXACT_PATHS + SEARCH_GLOBS
        for search_path in glob_list:
            try:
                print(f"  🔍 Procurando arquivos em: {search_path}")
                rows = con.execute(
                    """
                    SELECT DISTINCT regexp_extract(filename, '.*/delta/([^/]+)/.*', 1) AS folder
                    FROM read_parquet(?, filename=true)
                    WHERE folder IS NOT NULL AND folder <> ''
                    """,
                    [search_path],
                ).fetchall()
                for (folder,) in rows:
                    folders.add(folder)
            except Exception as e:
                print(f"    ⚠️  Falha ao listar {search_path}: {str(e)[:80]}")
                continue

        if not folders:
            print("\n⚠️  Nenhuma pasta encontrada em delta/. DAG segue sem erro para não travar a operação.")
        else:
            print(f"\n📂 Pastas encontradas em delta/: {', '.join(sorted(folders))}")

        # Para cada pasta encontrada, materializa tabela delta_{folder}
        for folder in sorted(folders):
            safe_folder = re.sub(r"[^a-zA-Z0-9_]+", "_", folder)
            table_name = f"delta_{safe_folder}"

            candidate_paths = [
                f"s3://{MINIO_BUCKET}/delta/{folder}/*.parquet",
                f"s3://{MINIO_BUCKET}/delta/{folder}/*/*.parquet",
            ]

            materialized = False
            for search_path in candidate_paths:
                try:
                    print(f"  📥 Carregando {table_name} de {search_path}...")
                    con.execute(f"DROP VIEW IF EXISTS {table_name};")
                    con.execute(f"DROP TABLE IF EXISTS {table_name};")
                    con.execute(f"CREATE TABLE {table_name} AS SELECT * FROM read_parquet('{search_path}');")
                    count = con.execute(f"SELECT COUNT(*) FROM {table_name};").fetchone()[0]
                    print(f"  ✅ {table_name}: {count} registros")
                    results.append({'view': table_name, 'status': 'OK', 'count': count, 'path': search_path})
                    materialized = True
                    break
                except Exception as e:
                    print(f"    ⚠️  Erro ao materializar de {search_path}: {str(e)[:100]}")
                    continue

            if not materialized:
                print(f"  ⚠️  {table_name}: nenhum Parquet carregado")
                results.append({'view': table_name, 'status': 'NOT_FOUND', 'path': f'delta/{folder}'})
        
        # Push para XCom para próxima task
        context['task_instance'].xcom_push(key='sync_results', value=results)
        
        # Avisa se nenhuma tabela foi criada (não falha a DAG)
        success_count = sum(1 for r in results if r['status'] == 'OK')
        total_targets = len(results)
        if success_count == 0:
            print("\n⚠️  AVISO: Nenhuma tabela foi criada!")
            print("   Possíveis causas:")
            print("   1. Não há dados em delta/")
            print("   2. Verifique MinIO em http://localhost:9001 (admin/admin123)")
            print("   3. Estrutura diferente do esperado")
            print("   ✅ A DAG continuará para permitir verificação e acesso ao arquivo DuckDB.")
        else:
            print(f"\n✅ Sincronização concluída: {success_count}/{total_targets} tabelas OK")
        
    except Exception as e:
        print(f"\n❌ ERRO na sincronização: {str(e)}")
        import traceback
        traceback.print_exc()
        raise
    finally:
        if con:
            try:
                con.close()
                print("✅ Conexão DuckDB fechada após sync_views")
            except Exception as e:
                print(f"⚠️  Erro ao fechar conexão em sync_views: {e}")


def verify_views(**context):
    """Verifica e lista tabelas/views criadas."""
    con = None
    try:
        con = duckdb.connect(DUCKDB_PATH, read_only=True)
        
        print("\n📋 Tabelas e views disponíveis no DuckDB:")
        result = con.execute("""
            SELECT table_schema, table_name, table_type
            FROM information_schema.tables 
            WHERE table_schema = 'main'
            ORDER BY table_name;
        """).fetchall()
        
        if result:
            for schema, name, ttype in result:
                print(f"  - [{ttype}] {schema}.{name}")
        else:
            print("  (nenhuma tabela ou view encontrada)")
        
        print(f"\n💡 Arquivo DuckDB pronto para Power BI:")
        print(f"   Caminho: {DUCKDB_PATH}")
        print(f"   Total de tabelas/views: {len(result)}")
        print(f"   ✅ Todos os dados já estão materializados (sem depender de S3)")
        
    except Exception as e:
        print(f"❌ ERRO na verificação: {str(e)}")
        import traceback
        traceback.print_exc()
        raise
    finally:
        if con:
            try:
                con.close()
                print("✅ Conexão DuckDB fechada após verify_views")
            except Exception as e:
                print(f"⚠️  Erro ao fechar conexão em verify_views: {e}")


def copy_for_bi(**context):
    """
    Copia datalake.duckdb para datalake_bi.duckdb para evitar locks com Power BI.
    datalake_bi.duckdb é o arquivo que Power BI ODBC acessa (read-only).
    datalake.duckdb é atualizado pela DAG e depois copiado.
    """
    try:
        print(f"\n📋 Copiando arquivo DuckDB para Power BI...")
        print(f"   De:  {DUCKDB_PATH}")
        print(f"   Para: {DUCKDB_BI_PATH}")
        
        # Verifica se arquivo fonte existe
        if not Path(DUCKDB_PATH).exists():
            print(f"⚠️  Arquivo fonte não encontrado: {DUCKDB_PATH}")
            return
        
        # Verifica tamanho do arquivo fonte
        src_size = Path(DUCKDB_PATH).stat().st_size
        print(f"   📥 Arquivo fonte: {src_size} bytes")
        
        # Remove arquivo de destino se existir
        if Path(DUCKDB_BI_PATH).exists():
            try:
                Path(DUCKDB_BI_PATH).unlink()
                print(f"   🗑️  Removido arquivo anterior de destino")
            except Exception as e:
                print(f"   ⚠️  Erro ao remover arquivo anterior: {e}")
        
        # Copia arquivo (sem preservação de metadata para forçar novo mtime)
        shutil.copy(DUCKDB_PATH, DUCKDB_BI_PATH)
        print(f"   ✅ Arquivo copiado com sucesso")
        
        # Toca o arquivo para atualizar mtime (garante que foi atualizado agora)
        Path(DUCKDB_BI_PATH).touch()
        print(f"   🔄 Timestamp atualizado")
        
        # Garante permissões de leitura
        try:
            subprocess.run(['chmod', '666', DUCKDB_BI_PATH], check=True)
            print(f"   ✅ Permissões configuradas para acesso ODBC")
        except Exception as e:
            print(f"   ⚠️  Aviso ao definir permissões: {e}")
        
        # Valida que arquivo de destino foi criado e atualizado
        if Path(DUCKDB_BI_PATH).exists():
            dst_size = Path(DUCKDB_BI_PATH).stat().st_size
            print(f"   📦 Arquivo final: {DUCKDB_BI_PATH} ({dst_size} bytes)")
            
            if src_size == dst_size:
                print(f"\n✅ Power BI pode acessar: {DUCKDB_BI_PATH}")
            else:
                print(f"⚠️  AVISO: Tamanho diferente! src={src_size}, dst={dst_size}")
        else:
            print(f"❌ ERRO: Arquivo de destino não foi criado!")
            raise Exception(f"Cópia falhou: {DUCKDB_BI_PATH} não existe")
        
    except Exception as e:
        print(f"❌ ERRO ao copiar para Power BI: {str(e)}")
        import traceback
        traceback.print_exc()
        raise


with DAG(
    'sync_duckdb_views',
    default_args=default_args,
    description='Sincroniza views do DuckDB com dados do MinIO para BI',
    schedule_interval='0 2 * * *',  # Executa diariamente às 2h da manhã
    start_date=datetime(2024, 1, 1),
    catchup=False,
    tags=['duckdb', 'bi', 'minio', 'sync'],
) as dag:
    
    dag.doc_md = """
    ## DAG: Sincronização DuckDB - Delta para Power BI
    
    Carrega tabelas Delta (transacionais) do MinIO para DuckDB como tabelas materializadas.
    **Foco: Power BI acessando dados transacionais prontos para BI!**
    
    ### Fluxo:
    1. **Setup**: Configura DuckDB e instala extensões
    2. **Sync**: Descobre tabelas em delta/ e materializa no DuckDB
    3. **Verify**: Valida tabelas criadas
    4. **Copy for BI**: Copia datalake.duckdb para datalake_bi.duckdb (read-only para Power BI)
    
    ### Estrutura de dados:
    - Origem: `s3://lab01/delta/{table_name_with_timestamp}/*.parquet`
    - Exemplo: `/lab01/delta/customers_202512230532/*.parquet`
    - Resultado: Tabela materializada `delta_customers_202512230532` com todos os registros
    
    ### Power BI:
    1. Configure DSN ODBC para: `{DUCKDB_BI_PATH}`
    2. Conecte e selecione tabelas delta_*
    3. Dados prontos - sem dependência de credenciais S3!
    
    ### Agendamento:
    Executa diariamente às 02:00 AM (sincroniza dados transacionais).
    
    ### Estratégia de Locks:
    - datalake.duckdb: Escrita (DAG atualiza durante sincronização)
    - datalake_bi.duckdb: Leitura (Power BI acessa este arquivo, sem locks)
    - Cópia automática após sync garante que Power BI sempre acessa arquivo consistente
    """
    
    setup_task = PythonOperator(
        task_id='setup_duckdb',
        python_callable=setup_duckdb,
        doc_md="Configura DuckDB e instala extensão httpfs para S3",
    )
    
    sync_task = PythonOperator(
        task_id='sync_views',
        python_callable=sync_views,
        doc_md="Cria ou atualiza views para todos os datasets",
    )
    
    verify_task = PythonOperator(
        task_id='verify_views',
        python_callable=verify_views,
        doc_md="Lista e valida views criadas",
    )
    
    copy_task = PythonOperator(
        task_id='copy_for_bi',
        python_callable=copy_for_bi,
        doc_md="Copia arquivo DuckDB para leitura Power BI (evita locks de escrita)",
    )
    
    # Fluxo: setup → sync → verify → copy (copy_for_bi garante arquivo sempre atualizado)
    setup_task >> sync_task >> verify_task >> copy_task
