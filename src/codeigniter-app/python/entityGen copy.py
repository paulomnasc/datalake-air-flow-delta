import os
import configparser
import mysql.connector
from mysql.connector import Error

# 1. Leitura de arquivos no diretório 'templates'
def read_templates(directory):
    files = []
    for filename in os.listdir(directory):
        if filename.endswith('.txt') or filename.endswith('.php') or filename.endswith('.js'):
            with open(os.path.join(directory, filename), 'r', encoding='utf-8') as file:
                files.append((filename, file.read()))
    return files

# 2. Conexão com o banco de dados MySQL
def connect_db(config_file):
    config = configparser.ConfigParser()
    config.read(config_file)

    try:
        connection = mysql.connector.connect(
            host=config['mysql']['host'],
            database=config['mysql']['database'],
            user=config['mysql']['user'],
            password=config['mysql']['password']
        )
        if connection.is_connected():
            return connection
    except Error as e:
        print(f"Erro ao conectar ao MySQL: {e}")
        return None

# 3. Obtenção do nome da tabela do usuário
def get_table_name():
    return input("Digite o nome da tabela desejada: ")

# Método genérico para processar templates
def process_template_generic(filename, content, table_name, columns, out_directory):
    new_content = content.replace('<%entity%>', table_name)
    new_filename = filename.replace('<%entity%>', table_name)
    new_filepath = os.path.join(out_directory, new_filename)

    # Identificar se é um arquivo de formulário e se contém o form-group existente
    if 'form-group' in new_content:
        existing_fields = ['descricao']  # Campos já presentes no template

        form_groups = []
        for column in columns:
            col_name = column[0]
            if col_name not in existing_fields:
                form_group = f"""<div class="form-group">
    <label for="{col_name}">{col_name.capitalize()}:</label>
    <input type="text" id="{col_name}" name="{col_name}" placeholder="{col_name}" required>
</div>\n"""
                form_groups.append(form_group)

        # Adicionar novos form-groups após os existentes
        form_groups_str = "\n".join(form_groups)
        new_content = new_content.replace('</div>\n<div class="form-actions">', f"{form_groups_str}</div>\n<div class=\"form-actions\">")

    with open(new_filepath, 'w', encoding='utf-8') as new_file:
        for column in columns:
            col_name = column[0]
            new_content = new_content.replace('<%entity.field%>', col_name)
        new_file.write(new_content)
    print(f"Arquivo '{new_filename}' criado com sucesso.")

def process_templates(files, table_name, connection, out_directory):
    if not os.path.exists(out_directory):
        os.makedirs(out_directory)

    cursor = connection.cursor()
    cursor.execute(f"SHOW COLUMNS FROM {table_name}")
    columns = cursor.fetchall()

    for filename, content in files:
        process_template_generic(filename, content, table_name, columns, out_directory)

    cursor.close()

def main():
    templates_directory = 'C:\\Users\\Public\\Downloads\\samrtTablesIgniter\\templates'
    config_file = 'db_config.ini'
    out_directory = os.path.join(os.getcwd(), 'out')  # Subpasta 'out' onde o script está sendo executado

    files = read_templates(templates_directory)
    connection = connect_db(config_file)
    if not connection:
        return

    table_name = get_table_name()
    process_templates(files, table_name, connection, out_directory)
    connection.close()

if __name__ == "__main__":
    main()
