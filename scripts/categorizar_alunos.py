import os
import argparse
import pandas as pd
import pymysql

def load_ci_env(env_path, env_mode='dev'):
    env_vars = os.environ.copy()
    if os.path.exists(env_path):
        with open(env_path, 'r', encoding='utf-8') as f:
            for line in f:
                line = line.strip()
                if not line or line.startswith('#'):
                    continue
                if '=' in line:
                    key, val = line.split('=', 1)
                    env_vars[key.strip()] = val.strip().strip("'").strip('"')
    
    db_config = {}
    prefixes = [f"database.{env_mode}.", "database.default."]
    fields = ['hostname', 'username', 'password', 'database', 'port']
    
    for field in fields:
        for prefix in prefixes:
            key = f"{prefix}{field}"
            if key in env_vars:
                db_config[field] = env_vars[key]
                break

    # Se não obteve pelo .env, tenta pelo formato genérico (inserido via Airflow, etc)
    if 'hostname' not in db_config and 'MYSQL_HOSTNAME' in env_vars:
        db_config['hostname'] = env_vars['MYSQL_HOSTNAME']
    if 'username' not in db_config and 'MYSQL_USERNAME' in env_vars:
        db_config['username'] = env_vars['MYSQL_USERNAME']
    if 'password' not in db_config and 'MYSQL_PASSWORD' in env_vars:
        db_config['password'] = env_vars['MYSQL_PASSWORD']
    if 'database' not in db_config and 'MYSQL_DATABASE' in env_vars:
        db_config['database'] = env_vars['MYSQL_DATABASE']

    if 'port' not in db_config and 'MYSQL_PORT' in env_vars:
        db_config['port'] = env_vars['MYSQL_PORT']
    elif 'port' not in db_config:
        db_config['port'] = '3306'

    if db_config.get('hostname') == 'mysql' and not env_vars.get('RUNNING_IN_DOCKER'):
        db_config['hostname'] = '127.0.0.1'
        if str(db_config.get('port')) == '3306':
            db_config['port'] = '23306'

    if not db_config.get('hostname'):
        raise FileNotFoundError(f"Arquivo .env não encontrado em: {env_path} e falta variáveis de ambiente (MYSQL_HOSTNAME).")

    return db_config

def get_db_connection(db_config):
    port = int(db_config.get('port', 3306))
    return pymysql.connect(
        host=db_config.get('hostname', '127.0.0.1'),
        user=db_config.get('username', 'root'),
        password=db_config.get('password', ''),
        database=db_config.get('database', ''),
        port=port,
        cursorclass=pymysql.cursors.DictCursor,
        charset='utf8mb4'
    )

def get_student_data_from_db(db_config):
    print(f"Conectando ao banco de dados para leitura: {db_config.get('hostname')}:{db_config.get('port', 3306)}...")
    conn = get_db_connection(db_config)
    
    query = """
    SELECT 
        u.id as user_id,
        u.nome as user_name,
        u.email,
        u.perfil_comportamental,
        COALESCE((
            SELECT SUM(vp.percent) / (SELECT COUNT(*) FROM video WHERE is_active = 1)
            FROM video_progress vp 
            JOIN video v ON v.id = vp.video_id
            WHERE vp.user_id = u.id AND v.is_active = 1
        ), 0) as video_progress,
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
        ) as last_uri,
        (SELECT MAX(created_at) FROM activity_logs WHERE user_id = u.id) as last_login,
        COALESCE((
            SELECT SUM(ud2.xp_points)
            FROM uc_progress up2
            JOIN uc_definition ud2 ON ud2.id = up2.uc_definition_id
            WHERE up2.user_id = u.id AND up2.completed = 1 AND ud2.is_active = 1
        ), 0) as xp_earned
    FROM usuario u
    WHERE u.status_assinatura IN ('trial', 'active')
       OR EXISTS (SELECT 1 FROM video_progress WHERE user_id = u.id)
    GROUP BY u.id
    ORDER BY video_progress DESC, tasks_completed DESC
    """
    
    print("Executando a query de progresso dos alunos...")
    cursor = conn.cursor()
    cursor.execute("SET NAMES utf8mb4;")
    cursor.execute(query)
    rows = cursor.fetchall()
    
    df = pd.DataFrame(rows)
    cursor.close()
    conn.close()
    
    df = df.rename(columns={
        'xp_earned': 'XP Obtido',
        'video_progress': 'Progresso Vídeos (%)',
        'last_uri': 'Última URI',
        'user_id': 'id',
        'user_name': 'Nome',
        'email': 'Email'
    })
    
    df['Última URI'] = df['Última URI'].fillna('')
    df['Progresso Vídeos (%)'] = df['Progresso Vídeos (%)'].fillna(0)
    df['XP Obtido'] = df['XP Obtido'].fillna(0)
    
    return df

def categorizar_perfil(row):
    if row.get('perfil_comportamental') == 'Interessados':
        return 'Interessados'
        
    try:
        xp = float(row.get('XP Obtido', 0))
    except (ValueError, TypeError):
        xp = 0
        
    try:
        video = float(row.get('Progresso Vídeos (%)', 0))
    except (ValueError, TypeError):
        video = 0
        
    uri = str(row.get('Última URI', ''))
    
    if xp > 300 and video < 10:
        return "Pragmático (Quer o Lab)"
    elif xp == 0 and "subscription" in uri.lower():
        return "Oportunista (Pulou o S3 p/ ver preço)"
    elif xp > 500 and video > 50:
        return "Power User"
    elif xp < 50 and video == 0:
        return "Zumbi"
        
    return "Em Evolução"

def update_db_categories(df, db_config):
    print("Atualizando a coluna perfil_comportamental no banco de dados...")
    conn = get_db_connection(db_config)
    cursor = conn.cursor()
    cursor.execute("SET NAMES utf8mb4;")
    
    updates = 0
    try:
        for _, row in df.iterrows():
            try:
                user_id = int(float(row['id']))
            except (ValueError, TypeError):
                continue
                
            nova_categoria = row['Categoria']
            categoria_atual = row.get('perfil_comportamental')
            
            # Só atualiza se a categoria mudou (ou se estava nula)
            if pd.isna(categoria_atual) or categoria_atual != nova_categoria:
                cursor.execute(
                    "UPDATE usuario SET perfil_comportamental = %s WHERE id = %s",
                    (nova_categoria, user_id)
                )
                updates += 1
                
        conn.commit()
        print(f"Sucesso! {updates} perfil(is) de aluno(s) foram atualizados no banco de dados.")
    except Exception as e:
        conn.rollback()
        print(f"Erro ao tentar atualizar o banco de dados: {e}")
    finally:
        cursor.close()
        conn.close()

def main():
    script_dir = os.path.dirname(os.path.abspath(__file__))
    default_env_path = os.path.join(script_dir, '..', 'src', 'codeigniter-app', '.env')
    
    parser = argparse.ArgumentParser(description="Categoriza os alunos e atualiza no banco do CodeIgniter.")
    parser.add_argument('--env-file', '-e', default=default_env_path, help="Caminho para o arquivo .env do CodeIgniter")
    parser.add_argument('--env', default='dev', choices=['dev', 'prod'], help="Ambiente de conexão (dev ou prod)")
    parser.add_argument('--output', '-o', default='progresso_alunos_categorizado.csv', help="Caminho para salvar o CSV de saída")
    parser.add_argument('--update-db', action='store_true', help="Se flag for passada, as categorias serão salvas no banco de dados.")
    args = parser.parse_args()

    try:
        db_config = load_ci_env(args.env_file, args.env)
        df = get_student_data_from_db(db_config)
    except Exception as e:
        print(f"Erro ao obter dados do banco: {e}")
        return

    print(f"Dados obtidos: {len(df)} registros.")

    print("Aplicando categorização...")
    df['Categoria'] = df.apply(categorizar_perfil, axis=1)
    
    print("\n--- Resumo das Categorias ---")
    print(df['Categoria'].value_counts())
    print("-----------------------------\n")
    
    oportunistas = df[df['Categoria'].str.startswith('Oportunista')]
    if not oportunistas.empty:
        colunas_display = ['id', 'Nome', 'XP Obtido', 'Progresso Vídeos (%)', 'Última URI']
        colunas_display = [c for c in colunas_display if c in df.columns]
        print(f"Encontrado(s) {len(oportunistas)} 'Oportunista(s)'. Exemplo:")
        print(oportunistas[colunas_display].head())
        print()
    
    # Atualiza o DB caso a flag seja passada (ou se quiser, podemos fazer isso default)
    if args.update_db:
        update_db_categories(df, db_config)
    else:
        print("Aviso: A flag --update-db não foi informada, o banco de dados não foi atualizado.")
    
    df.to_csv(args.output, index=False)
    print(f"Processamento concluído. CSV salvo em: {args.output}")

if __name__ == "__main__":
    main()
