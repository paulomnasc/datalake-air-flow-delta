# Exemplo de Rotas para UC Progress Monitor

Adicione estas rotas no arquivo `app/Config/Routes.php`:

```php
<?php

namespace Config;

// ... código existente ...

/*
 * --------------------------------------------------------------------
 * UC Progress Monitor Routes
 * --------------------------------------------------------------------
 */

// View principal
$routes->get('curso/progress-monitor', 'CursoController::progressMonitor');

// API Endpoints para tracking
$routes->group('api', function($routes) {
    // Video Progress
    $routes->post('video-progress', 'Api\ProgressController::videoProgress');
    $routes->get('video-progress/(:segment)/(:segment)', 'Api\ProgressController::getVideoProgress/$1/$2');
    
    // UC Progress (tasks/modules)
    $routes->post('uc-progress', 'Api\ProgressController::ucProgress');
    $routes->get('uc-progress/(:segment)/(:segment)', 'Api\ProgressController::getUcProgress/$1/$2');
});

// ... restante do arquivo ...
```

## Exemplos de Uso

### 1. Acessar a interface
```
GET http://localhost:8080/curso/progress-monitor
```

### 2. Salvar progresso de vídeo
```
POST http://localhost:8080/api/video-progress
Content-Type: application/json

{
  "user_id": "user-123",
  "course_id": "curso-001",
  "lesson_id": "aula-001",
  "video_id": "6073YAGEq08",
  "percent": 45.50,
  "completed": false,
  "timestamp": "2026-02-06T14:30:00Z"
}
```

### 3. Buscar progresso de vídeo
```
GET http://localhost:8080/api/video-progress/user-123/6073YAGEq08
```

### 4. Salvar progresso de UC
```
POST http://localhost:8080/api/uc-progress
Content-Type: application/json

{
  "userId": "user-123",
  "courseId": "curso-001",
  "moduleId": "mod-006",
  "activeTask": 2,
  "progress": 66.67,
  "points": 350,
  "isCompleted": false,
  "completedTaskIds": [0, 1],
  "timestamp": "2026-02-06T14:30:00Z"
}
```
