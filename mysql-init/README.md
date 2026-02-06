# 📊 DDLs e Estrutura de Banco de Dados - MySQL Init

Este diretório contém os scripts SQL (DDL) que são executados automaticamente na inicialização do container MySQL.

## 📁 Arquivos (Ordem de Execução)

```
01-ddl.sql                               ← Tabelas existentes (pasta, usuario, dag_configurations, etc)
02-dml.sql                               ← Dados existentes (inserts iniciais)
03-add_subscription_fields.sql           ← Migration antiga
04-create_activity_and_function_tables.sql ← Tabelas de funcionalidades
05-add_custom_function_fields.sql        ← Migration de funções customizadas

06-create_course_table.sql               ← NEW: Catálogo de cursos
06b-create_module_table.sql              ← NEW: Módulos por curso (N:1)
06c-create_video_table.sql               ← NEW: Vídeos por módulo (N:1)
06d-create_uc_definition_table.sql       ← NEW: Template de UCs/tarefas por vídeo (N:1)

07-create_video_progress_table.sql       ← NEW: Progresso do aluno assistindo vídeos
08-create_uc_progress_table.sql          ← NEW: Progresso do aluno em tarefas/UCs (instances)
```

---

## 🏗️ Diagrama de Entidades (Hierarquia Admin)

```
ADMIN JÁ CRIOU:
curso (course)
  ↓
  módulo (module)
    ↓
    vídeo (video)
      ↓
      uc_definition (definição de tarefas)

ALUNOS VEEM:
video_progress  ← quanto assistiu do vídeo
uc_progress     ← quais tarefas completou
```

---

## 📋 Detalhes de Cada Tabela

### **Tabelas de Estrutura (Admin cria)**

#### 1. `course` (06-create_course_table.sql)
- **Propósito**: Catálogo de cursos da plataforma
- **Campos principais**:
  - `course_id` (UNIQUE): Ex: 'curso-001', 'ml-fundamentals'
  - `name`: Título do curso
  - `description`: Objetivos
  - `color`: Cor para UI (#4f46e5)
  - `is_active`: Disponível para alunos?

**Exemplo**:
```sql
INSERT INTO course VALUES (..., 'curso-001', 'Criando um Data Lake do Zero', '...', '#4f46e5', 1, 1);
```

---

#### 2. `module` (06b-create_module_table.sql)
- **Propósito**: Módulos dentro de cada curso
- **FK**: `course_id` → `course.id` (CASCADE)
- **Campos principais**:
  - `module_id` (UNIQUE): Ex: 'mod-001', 'mod-006'
  - `course_id`: Qual curso
  - `name`: Ex: "Module 6: Pipeline Essentials"
  - `module_number`: Sequência (1, 2, 3...)
  - `estimated_hours`: Tempo estimado

**Exemplo**:
```sql
INSERT INTO module VALUES (..., 'mod-006', <course_id>, 'Module 6: Pipeline Essentials', '...', 6, ...);
```

---

#### 3. `video` (06c-create_video_table.sql)
- **Propósito**: Vídeos dentro de cada módulo
- **FK**: `module_id` → `module.id` (CASCADE)
- **Campos principais**:
  - `video_id` (UNIQUE): Ex: 'vid-001', 'vid-6073YAGEq08'
  - `youtube_id`: ID do YouTube (6073YAGEq08)
  - `title`: Título do vídeo
  - `duration_seconds`: Duração em segundos (425)

**Exemplo**:
```sql
INSERT INTO video VALUES (..., 'vid-6073YAGEq08', <module_id>, '6073YAGEq08', 'Ecossistema de Dados', ..., 425, ...);
```

---

#### 4. `uc_definition` (06d-create_uc_definition_table.sql)
- **Propósito**: Template de tarefas/UCs por vídeo
- **FK**: `video_id` → `video.id` (CASCADE)
- **Campos principais**:
  - `uc_id` (UNIQUE): Ex: 'uc-001'
  - `video_id`: Qual vídeo
  - `task_number`: Sequência (1, 2, 3...)
  - `task_title`: Ex: "Configurar Fonte S3"
  - `task_description`: Descreve o que fazer
  - `video_checkpoint`: Timestamp (02:15)
  - `xp_points`: Pontos ganhos (+100)

**Exemplo**:
```sql
INSERT INTO uc_definition VALUES (..., 'uc-001', <video_id>, 1, 'Configurar Fonte S3', '...', '02:15', 100, 1);
```

---

### **Tabelas de Progresso (Alunos preenchem)**

#### 5. `video_progress` (07-create_video_progress_table.sql)
- **Tabela**: `video_progress`
- **Propósito**: Rastrear quanto o aluno assistiu cada vídeo
- **Campos principais**:
  - `user_id`: ID/email do aluno (session)
  - `video_id`: Qual vídeo
  - `percent`: % assistido (0-100)
  - `watched_seconds` / `total_seconds`: Rastreamento
  - `completed`: Assistiu 100%?
  - `last_position_seconds`: Para retomar depois

**Índices**:
- `UNIQUE KEY (user_id, video_id)` - Evita duplicatas
- Índices simples: `user_id`, `video_id`, `completed`

**Índices compostos**: `(user_id, completed)`

---

#### 6. `uc_progress` (08-create_uc_progress_table.sql)
- **Tabela**: `uc_progress`
- **Propósito**: Rastrear quais tarefas/UCs o aluno completou
- **FK**: `uc_definition_id` → `uc_definition.id` (CASCADE)
- **Campos principais**:
  - `user_id`: ID/email do aluno
  - `uc_definition_id`: Qual UC completou
  - `completed`: Se a UC foi completada
  - `completed_at`: Data/hora da conclusão
  - `progress_percent`: Percentual de progresso (0-100)
  - `attempts`: Número de tentativas

**Índices**:
- `UNIQUE KEY (user_id, uc_definition_id)` - Evita duplicatas
- Índices simples: `user_id`, `uc_definition_id`, `completed`
- Índices compostos: `(user_id, completed)`

---

## 🎯 Fluxo de Dados

### **Admin cria estrutura:**
```
1. POST /admin/courses → cria row em course
2. POST /admin/courses/1/modules → cria row em module
3. POST /admin/modules/1/videos → cria row em video
4. POST /admin/videos/1/ucs → cria row em uc_definition
```

### **Aluno estuda:**
```
1. Video API → atualiza video_progress (realtime)
2. "Tarefa Completa" button → atualiza uc_progress
3. Frontend calcula stats via queries
```

---

## 📝 Consultas Úteis (via CRUD)

### **Admin vê todos os cursos:**
```sql
SELECT * FROM course WHERE is_active = 1 ORDER BY order;
```

### **Admin vê módulos de um curso:**
```sql
SELECT * FROM module 
WHERE course_id = :course_id AND is_active = 1 
ORDER BY module_number;
```

### **Admin vê vídeos de um módulo:**
```sql
SELECT * FROM video 
WHERE module_id = :module_id AND is_active = 1 
ORDER BY video_order;
```

### **Admin vê UCs de um vídeo:**
```sql
SELECT * FROM uc_definition 
WHERE video_id = :video_id AND is_active = 1 
ORDER BY task_number;
```

---

### **Aluno vê seu progresso em Um vídeo:**
```sql
SELECT vp.percent, vp.watched_seconds, vp.total_seconds, vp.completed
FROM video_progress vp
WHERE vp.user_id = :aluno_email AND vp.video_id = :video_id;
```

### **Aluno vê quais UCs completou em um vídeo:**
```sql
SELECT ud.task_title, ud.xp_points, up.completed, up.completed_at
FROM uc_progress up
JOIN uc_definition ud ON up.uc_definition_id = ud.id
WHERE up.user_id = :aluno_email AND ud.video_id = :video_id
ORDER BY ud.task_number;
```

### **Dashboard: XP total do aluno:**
```sql
SELECT SUM(ud.xp_points) as total_xp
FROM uc_progress up
JOIN uc_definition ud ON up.uc_definition_id = ud.id
WHERE up.user_id = :aluno_email AND up.completed = 1;
```

---

## 🔐 Relacionamentos (ForeignKeys)

```
course ← created_by FK usuario.id (SET NULL)
  ↓
module ← course_id FK course.id (CASCADE)
      ← created_by FK usuario.id (SET NULL)
  ↓
video ← module_id FK module.id (CASCADE)
     ← created_by FK usuario.id (SET NULL)
  ↓
uc_definition ← video_id FK video.id (CASCADE)
             ← created_by FK usuario.id (SET NULL)
  ↓
uc_progress ← uc_definition_id FK uc_definition.id (CASCADE)
```

---

## 🚀 Como Usar

### **Primeira vez (reset completo):**
```bash
# 1. Remover volume antigo
docker volume rm datalake-air-flow_mysql_data

# 2. Subir fresh
docker-compose down
docker-compose up -d --build

# 3. Validar
./check-progress-tables.sh
```

### **Validar tabelas criadas:**
```bash
docker exec mysql mysql -uroot -proot lista_revisao2_test -e "
  SHOW TABLES LIKE 'course';
  SHOW TABLES LIKE 'module';
  SHOW TABLES LIKE 'video';
  SHOW TABLES LIKE 'uc_definition';
  SHOW TABLES LIKE 'video_progress';
  SHOW TABLES LIKE 'uc_progress';
"
```

---

## 🛠️ Próximos Passos (Código)

1. **Criar Models** (CodeIgniter):
   - `CourseModel`, `ModuleModel`, `VideoModel`, `UcDefinitionModel`
   - `VideoProgressModel`, `UcProgressModel`

2. **Criar Controller Admin**:
   - `CourseAdminController` com CRUD hierárquico
   - Middleware para verificar perfil = 'Admin'

3. **Criar Views**:
   - `admin/courses/list`, `edit`, `create`
   - `admin/modules/list`, `edit`, `create`
   - `admin/videos/list`, `edit`, `create`
   - `admin/ucs/list`, `edit`, `create`

4. **Adicionar Rotas**:
   - `/admin/courses/*`
   - `/admin/modules/*`
   - `/admin/videos/*`
   - `/admin/ucs/*`

5. **API Integration**:
   - `/api/video-progress` (POST/PUT realtime)
   - `/api/uc-progress` (POST/PUT realtime)

---

## 📚 Referências

- CodeIgniter Models: https://codeigniter.com/user_guide/models/model.html
- MySQL FOREIGN KEYS: https://dev.mysql.com/doc/refman/8.0/en/create-table-foreign-keys.html
- YouTube IFrame API: https://developers.google.com/youtube/iframe_api_reference

---

**Última atualização**: 2026-02-06  
**Status**: ✅ DDL pronto para desenvolvimento de CRUD Admin 
    attempts 
FROM uc_progress 
WHERE user_id = 'user-001' AND module_id = 'mod-006' 
ORDER BY task_number;
```

### Progresso geral (video + UC):
```sql
SELECT DISTINCT
    'video' as tipo,
    user_id,
    course_id,
    COUNT(*) as total,
    SUM(CASE WHEN completed = 1 THEN 1 ELSE 0 END) as completados
FROM video_progress
WHERE user_id = 'user-001'
GROUP BY user_id, course_id
UNION ALL
SELECT DISTINCT
    'uc' as tipo,
    user_id,
    course_id,
    COUNT(*) as total,
    SUM(CASE WHEN completed = 1 THEN 1 ELSE 0 END) as completados
FROM uc_progress
WHERE user_id = 'user-001'
GROUP BY user_id, course_id;
```

---

## ⚙️ Resetting (Forçar reexecução)

Se precisar recriar as tabelas do zero:

```bash
# 1. Remover o volume de dados do MySQL
docker volume rm datalake-air-flow_mysql_data

# 2. Reconstruir os containers
docker-compose down
docker-compose up -d --build

# 3. Verificar se foi criado
docker exec mysql-dev mysql -uroot -p${MYSQL_ROOT_PASSWORD} ${MYSQL_DATABASE} -e "SHOW TABLES;"
```

---

## 📋 Checklist

- [x] DDLs criados (06, 07)
- [x] Seed data criado (08)
- [x] Volume mapeado no docker-compose
- [ ] Tabelas criadas após `docker-compose up`
- [ ] Migrations CodeIgniter executadas (`php spark migrate`)
- [ ] Models atualizados com feedback do banco
- [ ] API Controllers implementando persistência real

---

## 🐛 Troubleshooting

### Tabelas não foram criadas
```bash
# Verificar logs do MySQL
docker logs mysql-dev | grep -i "error\|creating"

# Verificar volume está montado
docker inspect mysql-dev | grep -A 10 "Mounts"

# Conectar e verificar manualmente
docker exec -it mysql-dev mysql -uroot -p${MYSQL_ROOT_PASSWORD} ${MYSQL_DATABASE} -e "SHOW TABLES;"
```

### Erro de sintaxe SQL
```bash
# Validar arquivo SQL (Linux)
cat mysql-init/06-create_video_progress_table.sql | mysql-check

# Remover volume e tentar novamente
docker volume rm datalake-air-flow_mysql_data
docker-compose down && docker-compose up -d
```

### Dados de teste não foram inseridos
- Verifique se o arquivo `08-seed_progress_tables.sql` existe
- Confirme que nenhum arquivo anterior criou erro (para execução parar)
- Verifique `docker logs mysql-dev` para detalhes

---

## 📚 Referências

- [MySQL Docker Documentation](https://hub.docker.com/_/mysql)
- [docker-entrypoint-initdb.d](https://github.com/docker-library/mysql/blob/master/8.0/docker-entrypoint.sh)
- CodeIgniter Migrations: `spark make:migration`
- MySQL Best Practices: InnoDB, UTF-8mb4, Índices

---

**Última atualização**: 2026-02-06  
**Status**: ✅ Pronto para uso em DEV/TEST/PROD
