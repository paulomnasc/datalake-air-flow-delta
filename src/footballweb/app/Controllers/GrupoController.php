<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;
use App\Models\GrupoModel;
use App\Models\GrupoUsuarioModel;
use App\Models\UsuarioModel;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/phpMailer/PHPMailer.php'; 
require 'vendor/phpMailer/Exception.php'; 
require 'vendor/phpMailer/SMTP.php';

class GrupoController extends BaseController
{
    private function checkAdmin()
    {
        if (session()->get('perfil_usuario_logado') !== 'Admin') {
            // Se for chamada AJAX, retorna JSON
            if ($this->request->isAJAX()) {
                return $this->response->setJSON([
                    'status' => 'error',
                    'mensagem' => 'Acesso negado. Apenas administradores podem executar esta ação.'
                ])->send();
            }
            // Senão, redireciona
            header('Location: ' . base_url('loginUsuario'));
            exit;
        }
    }

    public function index()
    {
        $this->checkAdmin();
        $model = new GrupoModel();
        $list = $model->findAll();
        return view('listGrupo', ['list' => $list]);
    }

    public function add()
    {
        $this->checkAdmin();
        return view('addGrupo');
    }

    public function upd()
    {
        $this->checkAdmin();
        $id = $this->request->getPost('id');
        $model = new GrupoModel();
        $grupo = $model->find($id);

        if (!$grupo) {
            return redirect()->to(base_url('listGrupo'));
        }

        return view('updGrupo', [
            'id' => $grupo->id,
            'nome' => $grupo->nome,
            'email' => $grupo->email
        ]);
    }

    public function insert()
    {
        $this->checkAdmin();
        $data = [
            'nome' => $this->request->getPost('nome'),
            'email' => $this->request->getPost('email')
        ];

        $model = new GrupoModel();

        try {
            $model->insert($data);
            return $this->response->setJSON([
                'status' => 'success',
                'mensagem' => 'Grupo inserido com sucesso!'
            ]);
        } catch (\Exception $e) {
            return $this->response->setJSON([
                'status' => 'error',
                'mensagem' => 'Falha ao inserir o grupo: ' . $e->getMessage()
            ]);
        }
    }

    public function update()
    {
        $this->checkAdmin();
        $id = $this->request->getPost('id');
        $data = [
            'nome' => $this->request->getPost('nome'),
            'email' => $this->request->getPost('email')
        ];

        $model = new GrupoModel();

        try {
            $model->update($id, $data);
            return $this->response->setJSON([
                'status' => 'success',
                'mensagem' => 'Grupo atualizado com sucesso!'
            ]);
        } catch (\Exception $e) {
            return $this->response->setJSON([
                'status' => 'error',
                'mensagem' => 'Falha ao atualizar o grupo: ' . $e->getMessage()
            ]);
        }
    }

    public function delete($id)
    {
        $this->checkAdmin();
        $model = new GrupoModel();

        try {
            $model->delete($id);
            return $this->response->setJSON([
                'status' => 'success',
                'mensagem' => 'Grupo excluído com sucesso!'
            ]);
        } catch (\Exception $e) {
            return $this->response->setJSON([
                'status' => 'error',
                'mensagem' => 'Falha ao excluir o grupo: ' . $e->getMessage()
            ]);
        }
    }

    public function membros($idGrupo)
    {
        $this->checkAdmin();
        $grupoModel = new GrupoModel();
        $grupo = $grupoModel->find($idGrupo);

        if (!$grupo) {
            return redirect()->to(base_url('listGrupo'));
        }

        $db = \Config\Database::connect();
        $membros = $db->table('grupo_usuario')
            ->select('grupo_usuario.id as id_rel, usuario.id as id_usuario, usuario.nome, usuario.email')
            ->join('usuario', 'usuario.id = grupo_usuario.id_usuario')
            ->where('grupo_usuario.id_grupo', $idGrupo)
            ->get()->getResult();

        return view('listGrupoUsuario', [
            'grupo' => $grupo,
            'membros' => $membros
        ]);
    }

    public function adicionarMembro()
    {
        $this->checkAdmin();
        $idGrupo = $this->request->getPost('id_grupo');
        $email = $this->request->getPost('email');
        $nome = $this->request->getPost('nome') ?? 'Novo Membro';

        if (empty($idGrupo) || empty($email)) {
            return $this->response->setJSON([
                'status' => 'error',
                'mensagem' => 'Preencha o e-mail do membro.'
            ]);
        }

        $db = \Config\Database::connect();
        $db->transStart();

        try {
            $usuarioModel = new UsuarioModel();
            $usuario = $usuarioModel->where('email', $email)->first();
            $senhaTemporaria = '';
            $novoUsuario = false;

            if (!$usuario) {
                // Usuário não existe: Criar conta com senha temporária
                $novoUsuario = true;
                $senhaTemporaria = bin2hex(random_bytes(4)); // Gera senha de 8 caracteres
                
                $novoUsuarioData = [
                    'nome' => $nome,
                    'email' => $email,
                    'senha' => $senhaTemporaria,
                    'email_confirmado' => 1,
                    'status_assinatura' => 'trial',
                    'data_inicio_trial' => date('Y-m-d'),
                    'data_vencimento_assinatura' => date('Y-m-d', strtotime('+30 days')),
                ];

                $idUsuario = $usuarioModel->insert($novoUsuarioData);
                if (!$idUsuario) {
                    throw new \Exception('Falha ao criar o novo usuário.');
                }

                // Salvar perfil padrão para o novo usuário ("Teste")
                $perfilModel = new \App\Models\PerfilModel();
                $perfilTeste = $perfilModel->where('descricao', 'Teste')->first();
                if ($perfilTeste) {
                    $usuarioPerfilModel = new \App\Models\UsuarioPerfilModel();
                    $usuarioPerfilModel->savePerfisUsuario($idUsuario, [$perfilTeste->id]);
                }
            } else {
                $idUsuario = $usuario->id;
            }

            // Verificar se o usuário já pertence ao grupo
            $grupoUsuarioModel = new GrupoUsuarioModel();
            $existente = $grupoUsuarioModel->where([
                'id_usuario' => $idUsuario,
                'id_grupo' => $idGrupo
            ])->first();

            if ($existente) {
                $db->transRollback();
                return $this->response->setJSON([
                    'status' => 'warning',
                    'mensagem' => 'Este usuário já está associado a este grupo.'
                ]);
            }

            // Associar
            $grupoUsuarioModel->insert([
                'id_usuario' => $idUsuario,
                'id_grupo' => $idGrupo
            ]);

            // Se for novo, enviar email com a senha temporária
            if ($novoUsuario) {
                $this->enviarEmailSenhaTemporaria($nome, $email, $senhaTemporaria);
            }

            $db->transComplete();

            if ($db->transStatus() === false) {
                throw new \Exception('Erro na transação ao associar usuário.');
            }

            return $this->response->setJSON([
                'status' => 'success',
                'mensagem' => $novoUsuario 
                    ? 'Usuário criado e associado ao grupo. E-mail de convite enviado!' 
                    : 'Usuário existente associado ao grupo com sucesso!'
            ]);

        } catch (\Exception $e) {
            $db->transRollback();
            return $this->response->setJSON([
                'status' => 'error',
                'mensagem' => 'Erro ao associar usuário: ' . $e->getMessage()
            ]);
        }
    }

    public function removerMembro($id)
    {
        $this->checkAdmin();
        $model = new GrupoUsuarioModel();

        try {
            $model->delete($id);
            return $this->response->setJSON([
                'status' => 'success',
                'mensagem' => 'Associação removida com sucesso!'
            ]);
        } catch (\Exception $e) {
            return $this->response->setJSON([
                'status' => 'error',
                'mensagem' => 'Falha ao remover associação: ' . $e->getMessage()
            ]);
        }
    }

    private function enviarEmailSenhaTemporaria($nome, $email, $senhaTemporaria)
    {
        $mail = new PHPMailer(true);

        try {
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

            $mail->setFrom(getenv('smtp_username'), getenv('smtp_nome_remetente') ?: 'FiscalWeb Admin');
            $mail->addAddress($email, $nome);

            $mail->isHTML(true);
            $mail->Subject = 'Sua conta de acesso ao FiscalWeb foi criada!';
            
            $linkConfirmacao = base_url('loginUsuario'); 

            $mail->Body = "
                <h2>Olá, {$nome}!</h2>
                <p>Você foi adicionado a um novo grupo de trabalho na plataforma FiscalWeb.</p>
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
        } catch (Exception $e) {
            log_message('error', "Erro ao enviar e-mail de convite para {$email}: {$mail->ErrorInfo}");
        }
    }
}
