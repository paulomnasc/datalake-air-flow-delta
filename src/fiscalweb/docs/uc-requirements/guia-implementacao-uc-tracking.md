# Guia de Implementacao — UC: Tracking de Progresso (CodeIgniter 4)

## 1. Objetivo
Implementar o rastreamento de progresso de video e tarefas praticas com envio server-side e persistencia em CodeIgniter 4.

## 2. Escopo
- Tracking de video (YouTube IFrame API)
- Checklist de tarefas com pontuacao
- Persistencia local (localStorage) e remota (MySQL)
- Dashboard simples de status

## 3. Requisitos Funcionais
- Enviar progresso do video em marcos (ex.: 5%, 10%, 15%).
- Salvar status de tarefas (pendente/concluida).
- Atualizar UI em tempo real.
- Registrar usuario, curso, aula e timestamp.

## 4. Requisitos Nao Funcionais
- Evitar spam de requests (throttle/debounce).
- Resiliencia a falhas de rede (retry simples).
- Tempo de resposta do endpoint < 500ms.

## 5. Estrutura de Dados

### Tabela: video_progress
```sql
CREATE TABLE video_progress (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id VARCHAR(100) NOT NULL,
    course_id VARCHAR(50) NOT NULL,
    lesson_id VARCHAR(50) NOT NULL,
    video_id VARCHAR(50) NOT NULL,
    percent DECIMAL(5,2) NOT NULL,
    completed TINYINT(1) DEFAULT 0,
    timestamp DATETIME NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_progress (user_id, video_id),
    INDEX idx_user (user_id),
    INDEX idx_course (course_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### Tabela: uc_progress
```sql
CREATE TABLE uc_progress (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id VARCHAR(100) NOT NULL,
    course_id VARCHAR(50) NOT NULL,
    module_id VARCHAR(50) NOT NULL,
    active_task INT NOT NULL,
    progress DECIMAL(5,2) NOT NULL,
    points INT NOT NULL,
    is_completed TINYINT(1) DEFAULT 0,
    completed_task_ids JSON,
    timestamp DATETIME NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_uc (user_id, module_id),
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

## 6. Backend (CodeIgniter 4)

### 6.1 Controllers
- **CursoController**: renderiza a view `uc_progress_monitor.php`
- **Api\ProgressController**: endpoints REST para receber progresso

### 6.2 Rotas (app/Config/Routes.php)
```php
// Views
$routes->get('curso/progress-monitor', 'CursoController::progressMonitor');

// API
$routes->post('api/video-progress', 'Api\ProgressController::videoProgress');
$routes->post('api/uc-progress', 'Api\ProgressController::ucProgress');
$routes->get('api/video-progress/(:segment)/(:segment)', 'Api\ProgressController::getVideoProgress/$1/$2');
```

### 6.3 Models
- `VideoProgressModel`: gerencia progresso de videos
- `UcProgressModel`: gerencia progresso de tarefas/modulos

Metodos principais:
- `upsertProgress()`: insere ou atualiza progresso
- `getUserVideoProgress()`: busca progresso especifico
- `getCourseProgress()`: calcula progresso geral do curso

## 7. Frontend — Etapas

### 7.1 View
- Arquivo: `app/Views/uc_progress_monitor.php`
- Usa header/footer do CodeIgniter
- JavaScript vanilla para gerenciar estado
- localStorage para cache local
- Sync automatico com backend (debounced)

### 7.2 Fluxo de Tracking de Video
1. Inicializar YouTube IFrame API
2. Ao PLAYING, iniciar loop de coleta (1s)
3. Calcular % e enviar apenas em marcos (5%, 10%, etc)
4. Atualizar UI do status
5. Parar tracking em PAUSED ou ENDED

### 7.3 Logica de Envio (implementada)
- Manter `lastSentPercent` e `lastSentAt`
- Enviar apenas se:
  - `percent % 5 === 0` (marco)
  - `percent !== lastSentPercent` (nao repetir)
  - Passou mais de 2s desde ultimo envio (throttle)
- Retry automatico em caso de falha (2 tentativas)

## 8. Integracao com Tarefas
- Relacionar tarefas com timestamps do video
- Marcar tarefa quando:
  - Usuario executa acao manual (botao), OU
  - Progresso ultrapassa timestamp associado (automatico)

## 9. Testes

### 9.1 Unit
- Calculo de progresso e marcos
- Debounce/throttle
- Validacao de payload

### 9.2 E2E
- Fluxo completo: play → enviar → finalizar
- Persistencia local e sync
- Recuperacao de estado ao recarregar

## 10. Deployment

### 10.1 Executar Migrations
```bash
php spark migrate
```

### 10.2 Checklist
- [x] View PHP criada
- [x] Controller criado
- [x] API Controller criado
- [x] Models criados
- [x] Migrations criadas
- [ ] Adicionar rotas em Routes.php
- [ ] Configurar permissoes/autenticacao
- [ ] Testar endpoints com Postman/Insomnia
- [ ] Validar tracking no frontend
- [ ] Deploy em producao

### 10.3 Configuracao
```php
// app/Config/App.php
public $baseURL = 'https://seu-dominio.com/';

// Habilitar CORS se necessario (app/Config/Filters.php)
public $globals = [
    'before' => ['cors'],
];
```

## 11. Arquivos Criados

### Backend
- ✅ `app/Views/uc_progress_monitor.php` - View principal
- ✅ `app/Controllers/CursoController.php` - Metodo progressMonitor()
- ✅ `app/Controllers/Api/ProgressController.php` - API endpoints
- ✅ `app/Models/VideoProgressModel.php` - Model de progresso de video
- ✅ `app/Models/UcProgressModel.php` - Model de progresso de UC
- ✅ `app/Database/Migrations/*_CreateVideoProgressTable.php`
- ✅ `app/Database/Migrations/*_CreateUcProgressTable.php`

### Frontend
- ✅ `docs/uc-requirements/ratreia-progresso.html` - Prototipo tracking video
- ✅ `docs/uc-requirements/prototipo.txt` - Prototipo UI (referencia)

## 12. Como Usar

### 12.1 Acessar a View
```
http://seu-dominio.com/curso/progress-monitor
```

### 12.2 Testar API (Postman/cURL)
```bash
# Enviar progresso de video
curl -X POST http://seu-dominio.com/api/video-progress \
  -H "Content-Type: application/json" \
  -d '{
    "user_id": "user-123",
    "course_id": "curso-001",
    "lesson_id": "aula-001",
    "video_id": "6073YAGEq08",
    "percent": 25.50,
    "completed": false,
    "timestamp": "2026-02-06T12:00:00Z"
  }'

# Enviar progresso de UC
curl -X POST http://seu-dominio.com/api/uc-progress \
  -H "Content-Type: application/json" \
  -d '{
    "userId": "user-123",
    "courseId": "curso-001",
    "moduleId": "mod-006",
    "activeTask": 1,
    "progress": 50,
    "points": 250,
    "isCompleted": false,
    "completedTaskIds": [0],
    "timestamp": "2026-02-06T12:00:00Z"
  }'
```

## 13. Proximos Passos
1. ✅ Criar migrations para as tabelas
2. ✅ Implementar Models com metodos de upsert
3. ⏳ Adicionar rotas no Routes.php
4. ⏳ Adicionar autenticacao/autorizacao nos endpoints
5. ⏳ Implementar logs de auditoria
6. ⏳ Dashboard administrativo de progresso
7. ⏳ Notificacoes de conclusao (email/push)
8. ⏳ Gamificacao (badges, achievements)
