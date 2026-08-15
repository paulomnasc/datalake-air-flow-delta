# 🔄 Fluxo Completo de Persistência - Video Feedback

## Diagrama do Fluxo

```
┌─────────────────────────────────────────────────────────────────┐
│                    USUÁRIO ASSISTE O VÍDEO 5                     │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
                    ⏱️ Atinge 80% do Vídeo
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│  FRONTEND - checkVideoProgressForFeedback() (video_player.php)   │
│  ✅ Detecta: percent >= 80                                       │
│  ✅ Dispara: showFeedbackModal()                                │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│           MODAL APARECE NA TELA (CSS + HTML)                    │
│  - Status do Lab (3 opções)                                      │
│  - Percepção de Valor (3 opções)                                │
│  - Campo Aberto (opcional)                                       │
│  - Botões: "Pular" e "Enviar Feedback"                          │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
                    👤 Usuário Preenche e Clica
                         "Enviar Feedback"
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│       FRONTEND - submitFeedback() (video_player.php:663)         │
│  ✅ Coleta dados do formulário                                   │
│  ✅ Valida: labStatus e valuePerception obrigatórios            │
│  ✅ Desabilita botão (submitBtn.disabled = true)                │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│       $.ajax() POST /api/video-feedback (jQuery)                 │
│  Data:                                                            │
│  {                                                                │
│    video_id: 5                                           ✅      │
│    lab_status: "consegui_rodar|erro_docker|so_assistindo"       │
│    value_perception: "sim_sentido|nao_sabia|direto_nuvem"       │
│    open_feedback: "texto livre..."                       ✅      │
│  }                                                                │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│  BACKEND - HTTP Request POST /api/video-feedback                │
│  Route: (Config/Routes.php:22)                                   │
│  $routes->post('/api/video-feedback',                           │
│                'Api\ProgressController::saveVideoFeedback')     │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│  CONTROLLER - saveVideoFeedback() (ProgressController.php:166)   │
│  ✅ Valida sessão: $_SESSION['id_usuario_logado']               │
│  ✅ Extrai dados: $data = $this->request->getPost()            │
│  ✅ Valida campos obrigatórios                                   │
│  ✅ Prepara array: $feedbackData                                 │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│  MODEL - saveFeedback() (VideoFeedbackModel.php:32)              │
│  ✅ Verifica se já existe feedback (user_id + video_id)         │
│  ✅ Se existe: UPDATE                                            │
│  ✅ Se não existe: INSERT                                        │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│         DATABASE - INSERT/UPDATE video_feedback                 │
│                                                                   │
│  video_feedback:                                                  │
│  ┌──────────┬──────────┬──────────┬────────────┬────────────┐   │
│  │ id       │ user_id  │ video_id │ lab_status │ value_perc.│   │
│  ├──────────┼──────────┼──────────┼────────────┼────────────┤   │
│  │ 1        │ 42       │ 5        │ consegui   │ sim_sentido│   │ ✅
│  │ 2        │ 43       │ 5        │ erro_docker│ nao_sabia  │   │ ✅
│  │ ...      │ ...      │ ...      │ ...        │ ...        │   │
│  └──────────┴──────────┴──────────┴────────────┴────────────┘   │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│  RESPONSE - JSON (ProgressController.php:195)                   │
│  {                                                                │
│    "status": "success",                                          │
│    "message": "Feedback salvo com sucesso",                      │
│    "data": { video_id, lab_status, value_perception, ... }      │
│  }                                                                │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│  FRONTEND - success: function(data) (video_player.php:689)       │
│  ✅ Alert: "Obrigado pelo seu feedback!"                        │
│  ✅ closeFeedbackModal()                                         │
│  ✅ Habilita botão novamente                                     │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
                    ✅ DADOS PERSISTIDOS NO BANCO!
```

---

## 📝 Arquivos Envolvidos

### Frontend (Coleta de Dados)
1. **[video_player.php](src/codeigniter-app/app/Views/student/video_player.php)**
   - Linha 578: Definição de `videoId`
   - Linha 617: `checkVideoProgressForFeedback()` - Detecta 80%
   - Linha 650-660: HTML do Modal
   - Linha 663-700: `submitFeedback()` - Envia dados via AJAX

### Backend (Processamento)
2. **[Routes.php](src/codeigniter-app/app/Config/Routes.php)**
   - Linha 22: Definição da rota POST

3. **[ProgressController.php](src/codeigniter-app/app/Controllers/Api/ProgressController.php)**
   - Linha 166-200: `saveVideoFeedback()` - Processa requisição

4. **[VideoFeedbackModel.php](src/codeigniter-app/app/Models/VideoFeedbackModel.php)**
   - Linha 32-50: `saveFeedback()` - Persiste no banco

### Banco de Dados
5. **[12-create_video_feedback_table.sql](mysql-init/12-create_video_feedback_table.sql)**
   - DDL da tabela `video_feedback`

---

## ✅ Checklist de Implementação

```
[✅] Modal HTML criado (lines 650-730)
[✅] Detecção de 80% implementada (line 617)
[✅] Função submitFeedback criada (line 663)
[✅] AJAX POST para /api/video-feedback (line 680)
[✅] Rota criada (Routes.php)
[✅] Controller method criado (ProgressController.php)
[✅] Model method criado (VideoFeedbackModel.php)
[✅] Tabela criada (video_feedback)
[✅] Tratamento de sucesso implementado (line 689)
[✅] Tratamento de erro implementado (line 699)
```

---

## 🧪 Como Testar

### 1. Abrir o navegador (F12)
```javascript
// No console do navegador, execute:
$ = jQuery; // Se precisar

// Simular envio de feedback
$.ajax({
    url: '/api/video-feedback',
    type: 'POST',
    data: {
        video_id: 5,
        lab_status: 'consegui_rodar',
        value_perception: 'sim_sentido',
        open_feedback: 'Teste manual'
    },
    success: function(data) {
        console.log('✅ Resposta servidor:', data);
    },
    error: function(error) {
        console.log('❌ Erro:', error);
    }
});
```

### 2. Verificar no banco
```sql
SELECT * FROM video_feedback WHERE video_id = 5;

-- Deve retornar os registros inseridos
```

### 3. Ver Logs
```bash
tail -f src/codeigniter-app/writable/logs/log-*.log | grep VideoFeedback
```

---

## 🎯 Conclusão

**TUDO ESTÁ IMPLEMENTADO E CONECTADO!**

Quando um aluno:
1. 👁️ Assiste o vídeo 5
2. ⏱️ Alcança 80% de progresso
3. 📝 Vê o modal e responde
4. 🔘 Clica "Enviar"
5. 💾 **Os dados vão direto para a tabela `video_feedback`**

O fluxo é **100% funcional** e está de acordo com a arquitetura CodeIgniter 4.
