# Video Feedback System - Documentação

## 📋 Visão Geral

Este sistema coleta feedback dos alunos quando atingem **80% de progresso** no vídeo 5 (laboratório MinIO).

A micro-pesquisa leva **~30 segundos** para ser respondida e fornece insights valiosos sobre:
- ✅ Taxa de sucesso do laboratório
- 🛠️ Problemas técnicos encontrados
- 💡 Percepção de valor do conteúdo
- 📝 Feedback qualitativo dos alunos

---

## 🗄️ Estrutura do Banco de Dados

### Tabela: `video_feedback`

```sql
CREATE TABLE video_feedback (
    id                INT UNSIGNED PRIMARY KEY          -- ID único do feedback
    user_id          INT UNSIGNED (FK usuario.id)      -- Qual aluno respondeu
    video_id         INT UNSIGNED (FK video.id)        -- Video 5 (MinIO Lab)
    lab_status       VARCHAR(100)                      -- Status do laboratorio
    value_perception VARCHAR(100)                      -- Percepcao de valor
    open_feedback    TEXT                              -- Resposta livre do aluno
    created_at       DATETIME                          -- Quando foi respondido
)
```

### Campos Especiais

#### `lab_status` - 3 opções possíveis:
| Valor | Significado |
|-------|-----------|
| `consegui_rodar` | ✅ Aluno conseguiu executar o MinIO corretamente |
| `erro_docker` | 🛠️ Aluno teve problema com Docker/S3 |
| `so_assistindo` | 📺 Aluno está apenas assistindo a teoria |

#### `value_perception` - 3 opções possíveis:
| Valor | Significado |
|-------|-----------|
| `sim_sentido` | ✨ Aluno entendeu a relação com AWS Glue/Azure Data Factory |
| `nao_sabia` | 💡 Foi nova informação para o aluno |
| `direto_nuvem` | ☁️ Aluno prefere aprender direto na nuvem |

#### `open_feedback` - Campo livre (opcional)
Resposta do aluno à pergunta: **"O que falta para você prosseguir agora?"**

---

## 📊 Analisando os Dados

### 1. **Taxa de Sucesso do Laboratório**

```sql
SELECT 
    ROUND((SUM(CASE WHEN lab_status = 'consegui_rodar' THEN 1 ELSE 0 END) / COUNT(*) * 100), 1) as 'Taxa %'
FROM video_feedback 
WHERE video_id = 5;
```

### 2. **Alunos com Problemas (Suporte Prioritário)**

```sql
SELECT u.nome, u.email, vf.open_feedback
FROM video_feedback vf
JOIN usuario u ON vf.user_id = u.id
WHERE vf.video_id = 5 AND vf.lab_status = 'erro_docker'
ORDER BY vf.created_at DESC;
```

### 3. **Distribuição por Status**

```sql
SELECT lab_status, COUNT(*) as total
FROM video_feedback
WHERE video_id = 5
GROUP BY lab_status;
```

### 4. **Alunos que Responderam vs Total**

```sql
SELECT 
    (SELECT COUNT(DISTINCT user_id) FROM video_feedback WHERE video_id = 5) as 'Responderam',
    (SELECT COUNT(*) FROM usuario) as 'Total de Alunos',
    ROUND(((SELECT COUNT(DISTINCT user_id) FROM video_feedback WHERE video_id = 5) / 
           (SELECT COUNT(*) FROM usuario) * 100), 1) as 'Taxa de Resposta %';
```

---

## 🔌 Integração com o Código

### Frontend (video_player.php)

Modal dispara automaticamente quando:
```javascript
if (percent >= 80 && !feedbackShown) {
    showFeedbackModal();
}
```

### Backend (ProgressController.php)

Endpoint que recebe os dados:
```php
POST /api/video-feedback
{
    video_id: 5,
    lab_status: "consegui_rodar",
    value_perception: "sim_sentido",
    open_feedback: "Texto livre do aluno"
}
```

### Model (VideoFeedbackModel.php)

Método que salva/atualiza:
```php
$feedbackModel->saveFeedback($userId, $videoId, $labStatus, $valuePerception, $openFeedback);
```

---

## 📈 Casos de Uso

### 1. **Identificar Gargalos Técnicos**
- Quantos alunos tiveram erro no Docker?
- Quais são os erros mais comuns?
- Precisamos melhorar a documentação?

### 2. **Validar Relevância do Conteúdo**
- Alunos estão entendo a relação com AWS Glue?
- É informação nova ou conhecimento prévio?

### 3. **Segmentação de Alunos**
- Quem conseguiu rodar vs quem teve problemas?
- Quem quer estudar direto na nuvem?

### 4. **Contato Proativo**
- Entrar em contato com alunos que tiveram erros
- Oferecer recursos adicionais baseado em feedback

---

## 🚀 Próximos Passos

### v1.1 - Melhorias Propostas

1. **Dashboard em Tempo Real**
   - Gráficos de taxa de sucesso
   - Feed de feedbacks críticos
   - Alertas de problemas comuns

2. **Automação**
   - Email automático para alunos com erro
   - Sugestão de recursos baseado em feedback
   - Relatório semanal de métricas

3. **Gamificação**
   - Badges para alunos que completam com sucesso
   - Leaderboard de alunos que conseguem rodar

4. **Análise Deeper**
   - Correlação entre sucesso no lab e conclusão geral do curso
   - Tempo médio para conclusão do vídeo
   - Taxa de drop-off após 80%

---

## 🛠️ Manutenção do Banco

### Backup dos Dados Importantes
```bash
mysqldump -u root -proot lista_revisao2_test video_feedback > video_feedback_backup.sql
```

### Restaurar Backup
```bash
mysql -u root -proot lista_revisao2_test < video_feedback_backup.sql
```

### Resetar a Tabela (Limpar Testes)
```sql
DELETE FROM video_feedback WHERE video_id = 5;
```

### Dropar Tabela Completamente
```sql
DROP TABLE video_feedback;
```

---

## 📝 Logs e Monitoramento

O sistema loga todas as ações em `app/Logs/`:
```
[2026-03-22 18:35:12] info: [VideoFeedback] User: 123 | Video: 5 | Lab Status: consegui_rodar
```

---

## ❓ FAQ

**P: Por que 80% e não 100%?**
R: Aos 80%, o aluno já passou pela parte difícil e está com emoção à flor da pele (frustração ou sucesso). É o **momento ideal** para capturar feedback autêntico.

**P: Posso desabilitar o modal?**
R: Sim, comente a linha `showFeedbackModal()` no `video_player.php`.

**P: Os dados são públicos?**
R: Não. São armazenados no banco privado. O `open_feedback` pode conter informações sensíveis.

**P: Como exportar relatório para Excel?**
R: Use o MySQL Workbench ou execute:
```bash
mysql -u root -proot lista_revisao2_test -e "SELECT * FROM video_feedback WHERE video_id = 5" > feedback.csv
```

---

## 📞 Suporte

Dúvidas sobre implementação? Verifique:
- [VideoFeedbackModel.php](../app/Models/VideoFeedbackModel.php)
- [ProgressController.php](../app/Controllers/Api/ProgressController.php)
- [video_player.php](../app/Views/student/video_player.php)

---

**Last Updated**: 2026-03-22  
**Status**: ✅ Production Ready
