# Guia Operacional de Grupos, Usuários e Segurança (Airflow + Web App)

Este documento descreve as operações administrativas necessárias para gerenciar usuários, grupos e a visibilidade de DAGs no Apache Airflow, além de apresentar a implementação recomendada para o fluxo de convites por e-mail com senha temporária no sistema web.

---

## 1. Funcionamento da Visibilidade de DAGs no Airflow

Com a nova arquitetura implementada:
- **Usuários Comuns:** Só visualizam as DAGs das quais são donos (matching exato por e-mail ou username) ou que pertençam a algum grupo ao qual estão associados na tabela `grupo_usuario` do banco de dados MySQL do sistema web.
- **DAGs sem proprietário (ou com proprietário `airflow` padrão):** São ocultadas de usuários comuns e ficam visíveis **apenas para usuários com perfil Admin** no Airflow. Os administradores podem delegar essas DAGs alterando o campo `owner` no código da própria DAG para o e-mail de um grupo cadastrado.
- **Administradores:** Continuam visualizando 100% das DAGs da plataforma.

---

## 2. Estrutura do Banco de Dados no MySQL (lista_revisao2)

As seguintes tabelas gerenciam os grupos e seus vínculos com os usuários no banco de dados do sistema web:

```sql
-- Tabela para cadastro dos grupos e e-mails de grupo
CREATE TABLE IF NOT EXISTS grupo (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabela relacional de Usuários e Grupos (Muitos para Muitos)
CREATE TABLE IF NOT EXISTS grupo_usuario (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT UNSIGNED NOT NULL,
    id_grupo INT UNSIGNED NOT NULL,
    FOREIGN KEY (id_usuario) REFERENCES usuario(id) ON DELETE CASCADE,
    FOREIGN KEY (id_grupo) REFERENCES grupo(id) ON DELETE CASCADE,
    UNIQUE KEY uq_usuario_grupo (id_usuario, id_grupo)
);
```

---

## 3. Guia de Operações via SQL (Para Administradores)

Caso o administrador queira operar essas tabelas diretamente via banco de dados (ex: via DBeaver ou CLI):

### A. Criar um Novo Grupo
Cadastre o grupo especificando o nome e o e-mail que será utilizado como `owner` nas DAGs do Airflow:
```sql
INSERT INTO grupo (nome, email) 
VALUES ('Time Financeiro', 'financeiro@empresa.com');
```

### B. Criar um Usuário
Cadastre o usuário na tabela `usuario` (certificando-se de fornecer o e-mail correto):
```sql
INSERT INTO usuario (nome, email, senha, status_assinatura) 
VALUES ('Lucas Silva', 'lucas@empresa.com', 'SENHA_HASH_AQUI', 'trial');
```

### C. Associar um Usuário a um Grupo
Localize os IDs do usuário e do grupo e faça a associação na tabela relacional:
```sql
-- Associa o usuário ID 12 ao grupo ID 5
INSERT INTO grupo_usuario (id_usuario, id_grupo) 
VALUES (12, 5);
```

---

## 4. Fluxo de Criação e Envio de E-mail (Senha Temporária e Redefinição)

Quando um administrador adiciona um usuário a um grupo, o sistema web deve disparar um e-mail para o usuário. O fluxo sugerido e a implementação em PHP (CodeIgniter 4) estão descritos abaixo:

### Lógica do Fluxo:
1. O administrador adiciona o e-mail do usuário ao grupo na interface.
2. Se o usuário ainda não existir no sistema, ele é criado automaticamente:
    - É gerada uma senha temporária aleatória de 8 caracteres.
    - A senha é salva em texto plano no banco de dados (conforme o padrão de autenticação do restante da aplicação).
    - O campo `email_confirmado` é definido como `1` para permitir o login imediato do usuário.
3. Um e-mail transacional é enviado ao usuário com:
    - A senha temporária gerada.
    - O link de acesso direto para a página de login.

---

### Código de Implementação Recomendado para o Web App (PHP / CodeIgniter 4)

Você pode adicionar a seguinte lógica ao seu `UsuarioController.php` (ou um controller específico de grupos):

```php
<?php

namespace App\Controllers;

use App\Models\UsuarioModel;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class GrupoController extends BaseController
{
    /**
     * Adiciona um usuário a um grupo e envia e-mail com senha temporária se for um novo usuário.
     */
    public function adicionarUsuarioAoGrupo()
    {
        $email = $this->request->getPost('email');
        $nome = $this->request->getPost('nome') ?? 'Novo Usuário';
        $idGrupo = $this->request->getPost('id_grupo');

        $db = \Config\Database::connect();
        $db->transStart();

        try {
            $usuarioModel = new UsuarioModel();
            
            // 1. Verificar se o usuário já existe
            $usuario = $usuarioModel->where('email', $email)->first();
            $senhaTemporaria = '';
            $novoUsuario = false;

            if (!$usuario) {
                // Usuário não existe: Criar conta com senha temporária
                $novoUsuario = true;
                $senhaTemporaria = bin2hex(random_bytes(4)); // Gera senha aleatória de 8 caracteres (ex: 4e9a12cf)
                
                $novoUsuarioData = [
                    'nome' => $nome,
                    'email' => $email,
                    'senha' => $senhaTemporaria, // Armazenada em texto puro conforme o resto da aplicação
                    'email_confirmado' => 1, // Habilitado para permitir o login imediato
                    'status_assinatura' => 'trial'
                ];

                $idUsuario = $usuarioModel->insert($novoUsuarioData);
                if (!$idUsuario) {
                    throw new \Exception('Falha ao criar o novo usuário no banco de dados.');
                }

                // Salvar perfil padrão para o novo usuário ("Teste", ID 23)
                $usuarioPerfilModel = new \App\Models\UsuarioPerfilModel();
                $usuarioPerfilModel->savePerfisUsuario($idUsuario, [23]);
            } else {
                $idUsuario = $usuario->id;
            }

            // 2. Associar o usuário ao grupo na tabela grupo_usuario
            $db->table('grupo_usuario')->insert([
                'id_usuario' => $idUsuario,
                'id_grupo' => $idGrupo
            ]);

            // 3. Se for novo usuário, envia e-mail com a senha temporária
            if ($novoUsuario) {
                $this->enviarEmailSenhaTemporaria($nome, $email, $senhaTemporaria);
            }

            $db->transComplete();

            if ($db->transStatus() === false) {
                throw new \Exception('Erro na transação de banco de dados.');
            }

            return $this->response->setJSON([
                'status' => 'success',
                'mensagem' => $novoUsuario 
                    ? 'Usuário adicionado ao grupo e e-mail com credenciais enviado com sucesso!' 
                    : 'Usuário existente adicionado ao grupo com sucesso!'
            ]);

        } catch (\Exception $e) {
            $db->transRollback();
            return $this->response->setJSON([
                'status' => 'error',
                'mensagem' => 'Erro ao processar a requisição: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Envia o e-mail com a senha temporária usando PHPMailer e Brevo SMTP.
     */
    private function enviarEmailSenhaTemporaria($nome, $email, $senhaTemporaria)
    {
        $mail = new PHPMailer(true);

        try {
            // Configurações do Servidor SMTP (Puxadas do .env do contêiner)
            $mail->isSMTP();
            $mail->Host       = getenv('smtp_host') ?: 'smtp-relay.brevo.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = getenv('smtp_username');
            $mail->Password   = getenv('smtp_password');
            $mail->SMTPSecure = getenv('smtp_secure') ?: PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = getenv('smtp_port') ?: 587;
            $mail->CharSet    = 'UTF-8';
            $mail->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                )
            );

            // Destinatários
            $mail->setFrom(getenv('smtp_username'), getenv('smtp_nome_remetente') ?: 'MyDataFlow Admin');
            $mail->addAddress($email, $nome);

            // Conteúdo do E-mail
            $mail->isHTML(true);
            $mail->Subject = 'Sua conta de acesso ao MyDataFlow foi criada!';
            
            // Link de confirmação que exige redefinir a senha
            $linkConfirmacao = base_url('loginUsuario'); 

            $mail->Body = "
                <h2>Olá, {$nome}!</h2>
                <p>Você foi adicionado a um novo grupo de trabalho na plataforma MyDataFlow.</p>
                <p>Sua conta de acesso temporária foi gerada com sucesso:</p>
                <ul>
                    <li><strong>E-mail:</strong> {$email}</li>
                    <li><strong>Senha Temporária:</strong> {$senhaTemporaria}</li>
                </ul>
                <p>Para sua segurança, ao realizar o primeiro acesso utilizando o link abaixo, você deverá confirmar sua conta e alterar esta senha:</p>
                <p><a href='{$linkConfirmacao}' style='background-color:#007bff; color:white; padding:10px 20px; text-decoration:none; border-radius:5px;'>Confirmar Conta e Acessar</a></p>
                <br>
                <p>Se você não solicitou este acesso, por favor ignore este e-mail.</p>
            ";

            $mail->send();
            return true;
        } catch (Exception $e) {
            log_message('error', "Erro ao enviar e-mail de senha temporária para {$email}: {$mail->ErrorInfo}");
            throw new \Exception("Erro no envio do e-mail: " . $mail->ErrorInfo);
        }
    }
}
```
