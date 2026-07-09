from airflow import DAG
from airflow.operators.python import PythonOperator
from airflow.models.param import Param
from datetime import datetime, timedelta
import logging

log = logging.getLogger(__name__)

def run_crawler_pipeline(**context):
    """
    Executa o crawler para o nicho definido nos parâmetros da DAG.
    Descobre os sites, tira os screenshots e envia os dados estruturados
    e imagens de auditoria para a camada Raw do MinIO.
    """
    from lib.groq_crawler import find_niche_websites, capture_screenshot, process_and_ingest_site
    
    # 1. Recupera o nicho a partir dos parâmetros da DAG (com fallback)
    niche = context['params'].get('niche', 'varejo farmácia')
    log.info(f"[CRAWLER-DAG] Iniciando pipeline de webscraping para nicho: '{niche}'")
    
    # 2. Descobre os sites com o Groq e busca URLs manuais no banco
    try:
        urls = find_niche_websites(niche, limit=3)
        log.info(f"[CRAWLER-DAG] URLs descobertas pelo Groq: {urls}")
    except Exception as e:
        log.error(f"[CRAWLER-DAG] Falha na descoberta de sites via Groq: {e}")
        urls = []

    # Busca URLs manuais no banco de dados (enriquecimento/direcionamento)
    db_urls = []
    try:
        from airflow.providers.mysql.hooks.mysql import MySqlHook
        mysql_hook = MySqlHook(mysql_conn_id='mysql_dag_metadata')
        sql = """
            SELECT cu.url 
            FROM crawler_urls cu
            JOIN crawler_categorias cc ON cu.categoria_id = cc.id
            WHERE LOWER(cc.nome) = LOWER(%s)
        """
        log.info(f"[CRAWLER-DAG] Buscando URLs manuais no MySQL para o nicho '{niche}'...")
        connection = mysql_hook.get_conn()
        with connection.cursor() as cursor:
            cursor.execute(sql, (niche.strip(),))
            rows = cursor.fetchall()
            db_urls = [row[0] for row in rows if row and row[0]]
        connection.close()
        log.info(f"[CRAWLER-DAG] URLs manuais encontradas no banco: {db_urls}")
    except Exception as e:
        log.warning(f"[CRAWLER-DAG] Não foi possível carregar URLs manuais do banco (o crawler continuará): {e}")

    # Mescla as URLs (banco + Groq) removendo duplicatas e mantendo a ordem
    combined_urls = []
    seen = set()
    for u in db_urls + urls:
        u_clean = u.strip()
        if u_clean and u_clean not in seen:
            seen.add(u_clean)
            combined_urls.append(u_clean)
    urls = combined_urls

    if not urls:
        log.warning("[CRAWLER-DAG] Nenhuma URL retornada para o nicho (IA ou Banco).")
        return {"status": "no_urls", "niche": niche}

    results = []
    failed_urls = []
    
    # 3. Processa cada URL encontrada
    for url in urls:
        log.info(f"[CRAWLER-DAG] --------------------------------------------------")
        log.info(f"[CRAWLER-DAG] Processando site: {url}")
        log.info(f"[CRAWLER-DAG] --------------------------------------------------")
        
        try:
            # 3.1. Captura de tela do site via Playwright Headless
            screenshot_path = capture_screenshot(url)
            log.info(f"[CRAWLER-DAG] Captura concluída para {url}. Arquivo temporário: {screenshot_path}")
            
            # 3.2. Extração via Groq Vision e ingestão no MinIO
            meta = process_and_ingest_site(
                url=url,
                screenshot_path=screenshot_path,
                target_table="produtos_scraped",
                owner=context.get('dag').owner, # Passa o proprietário para determinar a pasta/bucket
                **context
            )
            results.append(meta)
            log.info(f"[CRAWLER-DAG] ✅ Sucesso no processamento de {url}: {meta}")
            
        except Exception as e:
            log.error(f"[CRAWLER-DAG] ❌ Erro ao processar URL {url}: {e}")
            failed_urls.append({"url": url, "error": str(e)})
            continue # Avança para o próximo site, garantindo resiliência

    # 4. Finalização e sumário
    log.info(f"[CRAWLER-DAG] Resumo da execução: {len(results)} sucessos, {len(failed_urls)} falhas.")
    
    if len(failed_urls) == len(urls):
        raise RuntimeError("[CRAWLER-DAG] Falha crítica: Todas as URLs falharam no processamento.")
        
    return {
        "status": "completed",
        "niche": niche,
        "success_count": len(results),
        "failed_count": len(failed_urls),
        "details": results,
        "failures": failed_urls
    }

default_args = {
    'owner': 'admin-146', # Owner personalizado que se torna a subpasta/bucket no MinIO
    'start_date': datetime(2026, 1, 1),
    'retries': 0,
    'retry_delay': timedelta(minutes=2)
}

dag = DAG(
    'groq_crawler_dag',
    default_args=default_args,
    schedule_interval=None, # Executável sob demanda / via API
    catchup=False,
    params={
        "niche": Param(
            default="varejo farmácia",
            type="string",
            description="Nicho de produtos para buscar lojas e extrair dados (ex: 'varejo farmácia', 'suplementos', 'eletrônicos')"
        )
    },
    description="Crawler inteligente que descobre e-commerces, tira screenshots com Playwright e extrai dados de produtos/preços para o Delta Lake via Groq Vision."
)

run_crawler_task = PythonOperator(
    task_id='run_groq_crawler',
    python_callable=run_crawler_pipeline,
    provide_context=True,
    dag=dag
)
