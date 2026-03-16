import pandas as pd
from airflow import DAG
from airflow.operators.python import PythonOperator
from airflow.providers.mysql.hooks.mysql import MySqlHook
from datetime import datetime
import os

def get_student_data_from_db(conn):
    query = """
    SELECT 
        u.id as id,
        u.nome as Nome,
        u.email as Email,
        u.perfil_comportamental,
        COALESCE((
            SELECT SUM(vp.percent) / (SELECT COUNT(*) FROM video WHERE is_active = 1)
            FROM video_progress vp 
            JOIN video v ON v.id = vp.video_id
            WHERE vp.user_id = u.id AND v.is_active = 1
        ), 0) as progresso_videos,
        (
            SELECT COUNT(up.id) 
            FROM uc_progress up 
            JOIN uc_definition ud ON ud.id = up.uc_definition_id 
            WHERE up.user_id = u.id AND up.completed = 1 AND ud.is_active = 1
        ) as tasks_completed,
        (SELECT COUNT(id) FROM uc_definition WHERE is_active = 1) as total_tasks_available,
        (
            SELECT CONCAT(m2.name, ' / ', v2.title)
            FROM video_progress vp2
            JOIN video v2 ON v2.id = vp2.video_id
            JOIN module m2 ON m2.id = v2.module_id
            WHERE vp2.user_id = u.id
            ORDER BY vp2.updated_at DESC
            LIMIT 1
        ) as last_content,
        (
            SELECT uri 
            FROM activity_logs 
            WHERE user_id = u.id 
            ORDER BY created_at DESC 
            LIMIT 1
        ) as ultima_uri,
        (SELECT MAX(created_at) FROM activity_logs WHERE user_id = u.id) as last_login,
        COALESCE((
            SELECT SUM(ud2.xp_points)
            FROM uc_progress up2
            JOIN uc_definition ud2 ON ud2.id = up2.uc_definition_id
            WHERE up2.user_id = u.id AND up2.completed = 1 AND ud2.is_active = 1
        ), 0) as xp_obtido
    FROM usuario u
    WHERE u.status_assinatura IN ('trial', 'active')
       OR EXISTS (SELECT 1 FROM video_progress WHERE user_id = u.id)
    GROUP BY u.id
    ORDER BY progresso_videos DESC, tasks_completed DESC
    """
    cursor = conn.cursor()
    cursor.execute("SET NAMES utf8mb4;")
    cursor.execute(query)
    rows = cursor.fetchall()
    cols = [desc[0] for desc in cursor.description]
    df = pd.DataFrame(rows, columns=cols)
    cursor.close()
    df['ultima_uri'] = df['ultima_uri'].fillna('')
    df['progresso_videos'] = df['progresso_videos'].fillna(0)
    df['xp_obtido'] = df['xp_obtido'].fillna(0)
    return df

def categorizar_perfil(row):
    try:
        xp = float(row.get('xp_obtido', 0))
    except (ValueError, TypeError):
        xp = 0
    try:
        video = float(row.get('progresso_videos', 0))
    except (ValueError, TypeError):
        video = 0
    uri = str(row.get('ultima_uri', ''))
    if xp > 300 and video < 10:
        return "Pragmático (Quer o Lab)"
    elif xp == 0 and "subscription" in uri.lower():
        return "Oportunista (Pulou o S3 p/ ver preço)"
    elif xp > 500 and video > 50:
        return "Power User"
    elif xp < 50 and video == 0:
        return "Zumbi"
    return "Em Evolução"

def categorizar_alunos(**context):
    hook = MySqlHook(mysql_conn_id='mydataflow-conn')
    conn = hook.get_conn()
    df = get_student_data_from_db(conn)
    df['Categoria'] = df.apply(categorizar_perfil, axis=1)
    oportunistas = df[df['Categoria'].str.startswith('Oportunista')]
    if not oportunistas.empty:
        colunas_display = ['id', 'Nome', 'xp_obtido', 'progresso_videos', 'ultima_uri']
        colunas_display = [c for c in colunas_display if c in df.columns]
        print(f"Encontrado(s) {len(oportunistas)} 'Oportunista(s)'. Exemplo:")
        print(oportunistas[colunas_display].head())
    # Atualiza o DB
    cursor = conn.cursor()
    cursor.execute("SET NAMES utf8mb4;")
    updates = 0
    for _, row in df.iterrows():
        try:
            user_id = int(float(row['id']))
        except (ValueError, TypeError):
            continue
        nova_categoria = row['Categoria']
        categoria_atual = row.get('perfil_comportamental')
        if pd.isna(categoria_atual) or categoria_atual != nova_categoria:
            cursor.execute(
                "UPDATE usuario SET perfil_comportamental = %s WHERE id = %s",
                (nova_categoria, user_id)
            )
            updates += 1
    conn.commit()
    cursor.close()
    conn.close()
    print(f"Sucesso! {updates} perfil(is) de aluno(s) foram atualizados no banco de dados.")
    # Salva CSV (opcional)
    output_path = os.path.join(os.path.dirname(__file__), 'progresso_alunos_categorizado.csv')
    df.to_csv(output_path, index=False)
    print(f"Processamento concluído. CSV salvo em: {output_path}")

default_args = {
    'owner': 'airflow',
    'start_date': datetime(2024, 1, 1),
    'retries': 1
}

dag = DAG(
    'categorizar_alunos_dag',
    default_args=default_args,
    schedule_interval='0 0 * * *',
    catchup=False
)

categorizar_task = PythonOperator(
    task_id='categorizar_alunos',
    python_callable=categorizar_alunos,
    dag=dag
)
