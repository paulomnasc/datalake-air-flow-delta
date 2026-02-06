# 🎓 Admin Panel - Sistema de Gerenciamento de Cursos

## ✅ Implementação Completa

Sistema administrativo para gerenciar a estrutura hierárquica de cursos, módulos, vídeos e tarefas (UCs).

---

## 📁 Estrutura Criada

### **1. Models (app/Models/)**

#### ✅ Novos Models:
- **CourseModel.php** - Gerencia cursos
- **ModuleModel.php** - Gerencia módulos
- **VideoModel.php** - Gerencia vídeos
- **UcDefinitionModel.php** - Gerencia templates de UCs/tarefas

#### ✅ Models Atualizados:
- **VideoProgressModel.php** - Agora usa FK para `video.id` (removido course_id, lesson_id)
- **UcProgressModel.php** - Agora usa FK para `uc_definition.id` (removidos campos denormalizados)

---

### **2. Controller (app/Controllers/)**

#### ✅ ProgressAdminController.php
- **CRUD Completo** para 4 entidades:
  - **Courses**: indexCourses, addCourse, insertCourse, editCourse, updateCourse, deleteCourse
  - **Modules**: indexModules, addModule, insertModule, editModule, updateModule, deleteModule
  - **Videos**: indexVideos, addVideo, insertVideo, editVideo, updateVideo, deleteVideo
  - **UCs**: indexUCs, addUC, insertUC, editUC, updateUC, deleteUC

- **Proteção**: Todos os métodos verificam se usuário é Admin via `checkAdminAuth()`
- **Navegação Hierárquica**: Permite filtrar por curso/módulo/vídeo

---

### **3. Middleware (app/Filters/)**

#### ✅ AdminAuthFilter.php
- Verifica se `$_SESSION['perfil_usuario_logado'] === 'Admin'`
- Redireciona para home se não for Admin
- Registrado em `app/Config/Filters.php` como `'adminauth'`

---

### **4. Rotas (app/Config/Routes.php)**

#### ✅ Grupo `/admin/*` protegido por filtro `adminauth`:

```php
$routes->group('admin', ['filter' => 'adminauth'], function($routes) {
    // Courses
    $routes->get('courses', 'ProgressAdminController::indexCourses');
    $routes->post('courses/insert', 'ProgressAdminController::insertCourse');
    // ... (40+ rotas criadas)
});
```

**Principais Rotas:**
- `/admin/courses` - Lista cursos
- `/admin/modules` - Lista módulos
- `/admin/modules/course/1` - Módulos de um curso específico
- `/admin/videos` - Lista vídeos
- `/admin/videos/module/1` - Vídeos de um módulo específico
- `/admin/ucs` - Lista UCs
- `/admin/ucs/video/1` - UCs de um vídeo específico

---

### **5. Views (app/Views/admin/)**

#### ✅ Estrutura de Diretórios:
```
app/Views/admin/
├── courses/
│   ├── index.php    ✅ Lista cursos (completo)
│   ├── add.php      ✅ Formulário de adição (completo)
│   └── edit.php     ✅ Formulário de edição (completo)
├── modules/
│   ├── index.php    ✅ Lista módulos (completo)
│   ├── add.php      🟡 Placeholder (TODO)
│   └── edit.php     🟡 Placeholder (TODO)
├── videos/
│   └── index.php    🟡 Placeholder (TODO)
└── ucs/
    └── index.php    🟡 Placeholder (TODO)
```

**Views Completas (Courses):**
- Listagem com filtro, botões de ação
- Formulários com validação JavaScript
- Integração AJAX para insert/update/delete
- Mensagens de sucesso/erro

**Views Pendentes:**
- Módulos: add.php, edit.php
- Vídeos: index.php, add.php, edit.php
- UCs: index.php, add.php, edit.php

> **Nota**: As views pendentes podem seguir o mesmo padrão das views de `courses/`.

---

## 🔐 Segurança e Autenticação

### **Níveis de Proteção:**

1. **Filter de Rota** (`adminauth`):
   - Aplicado a TODAS as rotas `/admin/*`
   - Redireciona não-admins automaticamente

2. **Verificação no Controller**:
   - Método `checkAdminAuth()` em cada ação
   - Dupla camada de proteção

3. **Validação de Dados**:
   - Models têm regras de validação definidas
   - Validação de FKs (curso existe? módulo existe?)
   - Validação de unicidade (course_id, video_id, etc.)

---

## 🚀 Como Usar

### **1. Acessar Painel Admin**

```
http://localhost:8080/admin/courses
```

**Requisitos:**
- Usuário logado
- Perfil = "Admin" (`$_SESSION['perfil_usuario_logado']`)

---

### **2. Fluxo de Cadastro (Hierárquico)**

```
1. Criar Curso (Course)
   ↓
2. Criar Módulo(s) dentro do curso
   ↓
3. Criar Vídeo(s) dentro do módulo
   ↓
4. Criar UC(s)/Tarefa(s) dentro do vídeo
```

**Exemplo:**
```
Curso: "Criando um Data Lake do Zero" (course_id: curso-001)
  └─ Módulo 1: "Fundamentos" (module_id: mod-001, module_number: 1)
      └─ Vídeo 1: "Introdução ao Data Lake" (video_id: vid-001, youtube_id: ABC123)
          ├─ UC 1: "Assistir vídeo completo" (task_number: 1, xp: 50)
          └─ UC 2: "Configurar ambiente" (task_number: 2, xp: 100)
```

---

### **3. Banco de Dados (Inicialização)**

```bash
# 1. Parar containers
docker-compose down

# 2. Remover volume antigo (ATENÇÃO: deleta dados!)
docker volume rm datalake-air-flow_mysql_data

# 3. Subir containers (executa DDLs em mysql-init/)
docker-compose up -d

# 4. Verificar tabelas criadas
docker exec mysql mysql -uroot -proot lista_revisao2_test -e "SHOW TABLES LIKE '%course%';"
```

**Arquivos DDL (mysql-init/):**
- `06-create_course_table.sql`
- `06b-create_module_table.sql`
- `06c-create_video_table.sql`
- `06d-create_uc_definition_table.sql`
- `07-create_video_progress_table.sql`
- `08-create_uc_progress_table.sql`

---

## 📊 Diagrama de Relacionamentos

```
┌─────────────┐
│   usuario   │◄────────┐
└─────────────┘         │
                        │ created_by
                        │
┌─────────────┐         │
│   course    │─────────┘
│ (Catálogo)  │
└──────┬──────┘
       │ 1:N
       ├──────────────────────────────────┐
       │                                  │
┌──────▼──────┐                          │
│   module    │◄───────────────┐         │
│  (Módulos)  │                │         │
└──────┬──────┘                │         │
       │ 1:N                   │         │
       ├───────────────┐       │         │
       │               │       │         │
┌──────▼──────┐        │       │         │
│    video    │◄───────┤       │         │
│  (Vídeos)   │        │       │         │
└──────┬──────┘        │       │         │
       │ 1:N           │ FK    │ FK      │
       │               │       │         │
┌──────▼────────────┐  │       │         │
│  uc_definition    │  │       │         │
│ (Template UCs)    │──┘       │         │
└──────┬────────────┘          │         │
       │ 1:N                   │         │
       │                       │         │
┌──────▼────────────┐  ┌───────▼─────────┐
│   uc_progress     │  │ video_progress  │
│ (Student Tracking)│  │(Student Watching)│
└───────────────────┘  └─────────────────┘
```

---

## 🛠️ Próximos Passos (TODOs)

### **Prioridade Alta:**
- [ ] Implementar views de `modules/add.php` e `edit.php`
- [ ] Implementar views de `videos/index.php`, `add.php`, `edit.php`
- [ ] Implementar views de `ucs/index.php`, `add.php`, `edit.php`

### **Prioridade Média:**
- [ ] Adicionar menu "Admin Panel" no `header.php` (visível só para Admins)
- [ ] Implementar reordenação drag-and-drop (ordem de módulos/vídeos/UCs)
- [ ] Adicionar preview de vídeo do YouTube nas views de vídeos
- [ ] Dashboard Admin com estatísticas (total de cursos, módulos, vídeos, UCs)

### **Prioridade Baixa:**
- [ ] Export/Import de estrutura de curso (JSON/CSV)
- [ ] Duplicar curso completo (com todos os módulos/vídeos/UCs)
- [ ] Histórico de alterações (audit log de quem criou/editou)
- [ ] Validação de YouTube ID (verificar se vídeo existe e está público)

---

## 📝 Comandos Úteis

### **Verificar Models no Banco:**
```bash
docker exec mysql mysql -uroot -proot lista_revisao2_test -e "
SELECT COUNT(*) FROM course;
SELECT COUNT(*) FROM module;
SELECT COUNT(*) FROM video;
SELECT COUNT(*) FROM uc_definition;
"
```

### **Verificar se Admin pode Acessar:**
```bash
# No navegador:
# 1. Fazer login com usuário Admin
# 2. Acessar: http://localhost:8080/admin/courses
# 3. Deve mostrar página de listagem
# 4. Se redirecionar para home = não é Admin ou não está logado
```

### **Debug de Sessão:**
```php
// Adicionar em ProgressAdminController::checkAdminAuth() temporariamente:
var_dump([
    'perfil' => $_SESSION['perfil_usuario_logado'] ?? 'NOT SET',
    'usuario_logado' => $_SESSION['usuario_logado'] ?? 'NOT SET',
    'nome' => $_SESSION['nome_usuario_logado'] ?? 'NOT SET'
]);
die();
```

---

## 📚 Referências

- CodeIgniter 4 Docs: https://codeigniter.com/user_guide/
- MySQL Foreign Keys: https://dev.mysql.com/doc/refman/8.0/en/create-table-foreign-keys.html
- Bootstrap Forms: https://getbootstrap.com/docs/4.6/components/forms/

---

**Status**: ✅ Backend completo | 🟡 Frontend parcial (courses completo, demais pendentes)  
**Última atualização**: 2026-02-06
