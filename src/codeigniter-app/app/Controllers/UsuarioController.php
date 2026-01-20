<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;
use App\Models\UsuarioModel;
use App\Models\PerfilModel;
use App\Models\TokenModel;
use App\Models\UsuarioPerfilModel;
use App\Helpers\MinioHelper;
use App\Helpers\AirflowHelper;
use App\Models\ActivityLogModel;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/phpMailer/PHPMailer.php'; 
require 'vendor/phpMailer/Exception.php'; 
require 'vendor/phpMailer/SMTP.php';

class UsuarioController extends BaseController
{
    public function index()
    {
        $list = $this->list();
        return view('listUsuario', ['list' => $list]);
    }

    public function login()
    {
        return view('frmLogin');
    }

    public function logOut()
    {
        $_SESSION['id_usuario_logado'] = null;
        $_SESSION['nome_usuario_logado'] = null;
        $_SESSION['perfil_usuario_logado'] = null;
        $_SESSION['email_usuario_logado'] = null;
        $_SESSION['usuario_logado'] = 0;
        return view('frmLogin');
    }

    public function logar()
    {
        $data = [
            'email' => $this->request->getPost('email'),
            'senha' => $this->request->getPost('senha')
        ];
        
        try {
            $list = $this->listByEmailSenha($data['email'], $data['senha']);
            if (!empty($list) && isset($list[0])) {
                $usuario = $list[0]; // Acessa o primeiro usuário na lista
                
                // Carregando o usuário na sessão
                $_SESSION['id_usuario_logado'] = $usuario->id;
                $_SESSION['nome_usuario_logado'] = $usuario->nome;
                $_SESSION['perfil_usuario_logado'] = $usuario->perfil_descricao;
                $_SESSION['email_usuario_logado'] = $usuario->email;
                $_SESSION['usuario_logado'] = 1;
                
                // Registra evento de login
                try {
                    $logModel = new ActivityLogModel();
                    $logModel->insert([
                        'user_id'    => (int) $usuario->id,
                        'method'     => strtoupper($this->request->getMethod()),
                        'uri'        => $this->request->getUri()->getPath(),
                        'controller' => 'UsuarioController',
                        'action'     => 'logar',
                        'route_alias'=> 'Usuario.logar',
                        'ip_address' => $this->request->getIPAddress(),
                        'user_agent' => ($this->request->getUserAgent() ? (method_exists($this->request->getUserAgent(), 'getAgent') ? $this->request->getUserAgent()->getAgent() : (string) $this->request->getUserAgent()) : ($_SERVER['HTTP_USER_AGENT'] ?? null)),
                        'session_id' => (function_exists('session_id') ? session_id() : null),
                    ]);
                } catch (\Throwable $e) {
                    log_message('warning', '[ActivityLog] Falha ao registrar login: ' . $e->getMessage());
                }
                
                // Garante que o bucket do usuário existe no MinIO
                $bucketResult = MinioHelper::createUserBucket($usuario->id);
                
                // Log do resultado (opcional - pode ser removido em produção)
                if ($bucketResult['success']) {
                    log_message('info', "Bucket do usuário {$usuario->id}: {$bucketResult['message']}");
                } else {
                    log_message('error', "Falha ao criar bucket do usuário {$usuario->id}: {$bucketResult['message']}");
                }
                
                // Sincroniza usuário com Airflow (cria ou atualiza credenciais)
                if (AirflowHelper::isAirflowAvailable()) {
                    log_message('info', "[LOGIN] Sincronizando usuário {$usuario->id} com Airflow - senha fornecida: " . (!empty($data['senha']) ? 'SIM' : 'NÃO'));
                    $airflowResult = AirflowHelper::syncUserWithAirflow(
                        $usuario->id,
                        $usuario->email ?? "",
                        explode(' ', $usuario->nome)[0] ?? 'User',
                        (count(explode(' ', $usuario->nome)) > 1) ? implode(' ', array_slice(explode(' ', $usuario->nome), 1)) : $usuario->id,
                        $data['senha']
                    );
                    
                    if ($airflowResult['success']) {
                        log_message('info', "[AIRFLOW] {$airflowResult['message']}");
                        // Tentar anexar a role do dono (prefixo-idusuario) se ela existir
                        $ownerRoleName = $airflowResult['username'] ?? null;
                        if (!empty($ownerRoleName)) {
                            $attached = AirflowHelper::addExistingRoleToUser($airflowResult['username'], $ownerRoleName);
                            if (!$attached) {
                                log_message('warning', "[AIRFLOW] Role {$ownerRoleName} não anexada (pode não existir); usuário segue com Viewer.");
                            }
                        }
                    } else {
                        log_message('warning', "[AIRFLOW] {$airflowResult['message']}");
                    }
                } else {
                    log_message('warning', "[AIRFLOW] Serviço Airflow não disponível no momento");
                }
                
                return $this->response->setJSON([
                    'status' => 'success',
                    'mensagem' => 'Obrigado por retorna à nossa plataforma !  :-)'
                ]); 

            }
            else{

                $_SESSION['usuario_logado'] = 0;
                
                return $this->response->setJSON([
                    'status' => 'error',
                    'mensagem' => 'Usuário e senha não encontrao ! Se já é um usuário cadastrado pode ser que ainda não tenha 
                    confirmado seu e-mail no link entregue na sua caixa de email... :-/'
                ]);    
            }
            
        } catch (\Exception $e) {
            return $this->response->setJSON([
                'status' => 'error',
                'mensagem' => 'Falha ao logar o usuário: ' . $e->getMessage()
            ]);
        }
    }

    public function logarUsuarioConfirmaEmail($email, $senha)
    {
        /* echo "logou fake";
        die(); */

        $data = [
            'email' => $email,
            'senha' => $senha
        ];
        
        try {
            $list = $this->listByEmailSenha($data['email'], $data['senha']);
            if (!empty($list) && isset($list[0])) {
                $usuario = $list[0]; // Acessa o primeiro usuário na lista
                
                // Verifica e inicializa período de trial se necessário
                $usuarioModel = new UsuarioModel();
                $needsUpdate = false;
                $updateData = [];
                
                if (empty($usuario->data_inicio_trial) && empty($usuario->data_vencimento_assinatura)) {
                    // Primeiro login confirmado - inicia período de trial
                    $updateData['data_inicio_trial'] = date('Y-m-d');
                    $updateData['data_vencimento_assinatura'] = date('Y-m-d', strtotime('+30 days'));
                    $updateData['status_assinatura'] = 'trial';
                    $needsUpdate = true;
                }
                
                // Atualiza o usuário no banco se necessário
                if ($needsUpdate) {
                    $usuarioModel->update($usuario->id, $updateData);
                    // Atualiza o objeto local também
                    foreach ($updateData as $key => $value) {
                        $usuario->$key = $value;
                    }
                }
                
                // Carregando o usuário na sessão
                $_SESSION['id_usuario_logado'] = $usuario->id;
                $_SESSION['nome_usuario_logado'] = $usuario->nome;
                $_SESSION['perfil_usuario_logado'] = $usuario->perfil_descricao;
                $_SESSION['email_usuario_logado'] = $usuario->email;
                $_SESSION['usuario_logado'] = 1;
                
                // Garante que o bucket do usuário existe no MinIO
                $bucketResult = MinioHelper::createUserBucket($usuario->id);
                
                if ($bucketResult['success']) {
                    log_message('info', "Bucket do usuário {$usuario->id}: {$bucketResult['message']}");
                } else {
                    log_message('error', "Falha ao criar bucket do usuário {$usuario->id}: {$bucketResult['message']}");
                }
                
                // Sincroniza usuário com Airflow (cria ou atualiza credenciais)
                if (AirflowHelper::isAirflowAvailable()) {
                    $airflowResult = AirflowHelper::syncUserWithAirflow(
                        $usuario->id,
                        $usuario->email ?? "",
                        explode(' ', $usuario->nome)[0] ?? 'User',
                        (count(explode(' ', $usuario->nome)) > 1) ? implode(' ', array_slice(explode(' ', $usuario->nome), 1)) : $usuario->id,
                        $senha
                    );
                    
                    if ($airflowResult['success']) {
                        log_message('info', "[AIRFLOW] {$airflowResult['message']}");
                        // Tentar anexar a role do dono (prefixo-idusuario) se ela existir
                        $ownerRoleName = $airflowResult['username'] ?? null;
                        if (!empty($ownerRoleName)) {
                            $attached = AirflowHelper::addExistingRoleToUser($airflowResult['username'], $ownerRoleName);
                            if (!$attached) {
                                log_message('warning', "[AIRFLOW] Role {$ownerRoleName} não anexada (pode não existir); usuário segue com Viewer.");
                            }
                        }
                    } else {
                        log_message('warning', "[AIRFLOW] {$airflowResult['message']}");
                    }
                } else {
                    log_message('warning', "[AIRFLOW] Serviço Airflow não disponível no momento");
                }
                
                return view('menu_smart');

            }
            else{
                $_SESSION['usuario_logado'] = 0;
                return $this->response->setJSON([
                    'status' => 'error',
                    'mensagem' => 'Usuário e senha não encontrao !'
                ]);    
            }
            
        } catch (\Exception $e) {
            return $this->response->setJSON([
                'status' => 'error',
                'mensagem' => 'Falha ao logar o usuário: ' . $e->getMessage()
            ]);
        }
    }



    public function logarUsuarioAnonimo()
    {
        /* echo "logou fake";
        die(); */

        $data = [
            'email' => 'anonimo@gmail.com',
            'senha' => '123'
        ];
        
        try {
            $list = $this->listByEmailSenha($data['email'], $data['senha']);
            if (!empty($list) && isset($list[0])) {
                $usuario = $list[0]; // Acessa o primeiro usuário na lista
                // Carregando o usuário na sessão
                $_SESSION['id_usuario_logado'] = $usuario->id;
                $_SESSION['nome_usuario_logado'] = $usuario->nome;
                $_SESSION['perfil_usuario_logado'] = $usuario->perfil_descricao;
                $_SESSION['usuario_logado'] = 1;
                
                // Redireciona para a tela listQuadro após o login anônimo
                return redirect()->route('listQuadro');

            }
            else{
                $_SESSION['usuario_logado'] = 0;
                return $this->response->setJSON([
                    'status' => 'error',
                    'mensagem' => 'Usuário e senha não encontrao !'
                ]);    
            }
            
        } catch (\Exception $e) {
            return $this->response->setJSON([
                'status' => 'error',
                'mensagem' => 'Falha ao logar o usuário: ' . $e->getMessage()
            ]);
        }
    }


    public function list()
    {
        $model = new UsuarioModel();
        $usuarios = $model->findAll();
        
        // Para cada usuário, buscar seus perfis
        $usuarioPerfilModel = new UsuarioPerfilModel();
        foreach ($usuarios as $usuario) {
            $perfis = $usuarioPerfilModel->getPerfisUsuario($usuario->id);
            $perfisDescricao = [];
            foreach ($perfis as $perfil) {
                $perfisDescricao[] = $perfil->perfil_descricao;
            }
            $usuario->perfis_descricao = implode(', ', $perfisDescricao);
        }
        
        return $usuarios;
    }

    public function listByEmailSenha($email, $senha)
    {
        $model = new UsuarioModel();
        $model->where('usuario.senha', $senha);
        $model->where('usuario.email_confirmado', 1);
        $model->like('usuario.email', $email);
        $usuarios = $model->findAll();
        
        // Para cada usuário, buscar seus perfis
        $usuarioPerfilModel = new UsuarioPerfilModel();
        foreach ($usuarios as $usuario) {
            $perfis = $usuarioPerfilModel->getPerfisUsuario($usuario->id);
            $perfisDescricao = [];
            foreach ($perfis as $perfil) {
                $perfisDescricao[] = $perfil->perfil_descricao;
            }
            $usuario->perfil_descricao = isset($perfisDescricao[0]) ? $perfisDescricao[0] : '';
            $usuario->perfis_descricao = implode(', ', $perfisDescricao);
        }
        
        return $usuarios;
    }

    public function listByEmail($email)
    {
        $model = new UsuarioModel();
        $model->where('usuario.email_confirmado', 1);
        $model->like('usuario.email', $email);
        $usuarios = $model->findAll();
        
        // Para cada usuário, buscar seus perfis
        $usuarioPerfilModel = new UsuarioPerfilModel();
        foreach ($usuarios as $usuario) {
            $perfis = $usuarioPerfilModel->getPerfisUsuario($usuario->id);
            $perfisDescricao = [];
            foreach ($perfis as $perfil) {
                $perfisDescricao[] = $perfil->perfil_descricao;
            }
            $usuario->perfil_descricao = isset($perfisDescricao[0]) ? $perfisDescricao[0] : '';
            $usuario->perfis_descricao = implode(', ', $perfisDescricao);
        }
        
        return $usuarios;
    }


    
    public function add()
    {

        $perfilModel = new PerfilModel();
        $data['perfis'] = $perfilModel->listToCombo();
        /* var_dump($data);
        die(); */
        return view('addUsuario',$data);

    }

    public function signIn()
    {

        $perfilModel = new PerfilModel();
        $data['perfis'] = $perfilModel->listToComboPerfilTeste();
        /* var_dump($data);
        die(); */
        $data['descricao_perfil_selecionado'] = 'Teste';



        return view('signUpUsuario',$data);

    }

    public function upd()
    {
        $id = $this->request->getPost('id');
        $model = new UsuarioModel();
        $Usuario = $model->find($id);

        $perfilModel = new PerfilModel();
        $data['perfis'] = $perfilModel->listToCombo();
        
        // Buscar os perfis associados ao usuário
        $usuarioPerfilModel = new UsuarioPerfilModel();
        $perfisUsuario = $usuarioPerfilModel->getPerfisUsuario($id);
        $perfisSelecionados = [];
        foreach ($perfisUsuario as $perfil) {
            $perfisSelecionados[] = $perfil->id_perfil;
        }
        
        $data['perfis_selecionados'] = $perfisSelecionados;
        $data['id'] = $Usuario->id;
        $data['nome'] = $Usuario->nome;
        $data['email'] = $Usuario->email;
        $data['senha'] = $Usuario->senha;

        return view('updUsuario', $data);
    }


    public function del($id)
    {

        $this->delete($id);
        

    }


    

    public function findById(int $id )  {
        
        $model = new UsuarioModel();
        $Usuario = $model->find($id);
        return $Usuario;
    }

    public function insert() {
        $data = [
            'id' => $this->request->getPost('id'),
            'nome' => $this->request->getPost('nome'),
            'email' => $this->request->getPost('email'),
            'senha' => $this->request->getPost('senha')
        ];
        
        $perfis = $this->request->getPost('id_perfil');
        
        $model = new UsuarioModel();
        $usuarioPerfilModel = new UsuarioPerfilModel();
        
        $db = \Config\Database::connect();
        $db->transStart();
        
        try {
            $idUsuario = $model->insert($data);
            
            if ($idUsuario && !empty($perfis)) {
                $usuarioPerfilModel->savePerfisUsuario($idUsuario, $perfis);
            }
            
            $db->transComplete();
            
            if ($db->transStatus() === false) {
                throw new \Exception('Falha na transação ao inserir usuário e perfis.');
            }
            
            return $this->response->setJSON([
                'status' => 'success',
                'mensagem' => 'Registro inserido com sucesso!'
            ]);
        } catch (\Exception $e) {
            $db->transRollback();
            return $this->response->setJSON([
                'status' => 'error',
                'mensagem' => 'Falha ao inserir o registro: ' . $e->getMessage()
            ]);
        }
    }

    public function insertSigIn() {

        $nome = $this->request->getPost('nome');
        $email= $this->request->getPost('email');

        $data = [
            'id' => $this->request->getPost('id'),
            'nome' => $nome,
            'email' => $email,
            'senha' => $this->request->getPost('senha')
        ];
        
        $perfis = $this->request->getPost('id_perfil');
        
        $model = new UsuarioModel();
        $usuarioPerfilModel = new UsuarioPerfilModel();
        
        $db = \Config\Database::connect();
        $db->transStart();
        
        try {
            
            //Enviando e-mail de confirmação

            if($this->sendMailNoSecurity($nome, $email))    
            {
                $idUsuario = $model->insert($data);
                if($idUsuario)
                {
                    // Salvar perfis do usuário
                    if (!empty($perfis)) {
                        $usuarioPerfilModel->savePerfisUsuario($idUsuario, $perfis);
                    }
                    
                    $db->transComplete();
                    
                    if ($db->transStatus() === false) {
                        throw new \Exception('Falha na transação ao inserir usuário e perfis.');
                    }

                    $mensagem = 'Seu cadastro foi enviado com sucesso! Por gentileza, verifique sua caixa de e-mail (' . $email . ') e clique no link para confirmar seu cadastro.';
                    $mensagem = $mensagem . '<br> Caso não encontre seu e-mail verifique sua pasta SPAM';

                    return $this->response->setJSON([
                        'status' => 'success',
                        'mensagem' => $mensagem,
                        'redirect' => base_url('loginUsuario')
                        ]);
                }
                else{
                    $db->transRollback();
                    return $this->response->setJSON([
                        'status' => 'error',
                        'mensagem' => 'Falha ao processar a requisição, tente novamente mais tarde.'
                    ]);

                }
            
            }
             



        } catch (\Exception $e) {
            $db->transRollback();
            return $this->response->setJSON([
                'status' => 'error',
                'mensagem' => 'Falha ao inserir o registro: ' . $e->getMessage()
            ]);
        }
    }


    private function sendMailNoSecurity($nome, $email): bool
    {
        $mail = new PHPMailer(true);

        try {

            // Gerar o token
            $token = bin2hex(random_bytes(16)); // Gera um token aleatório
            $this->saveToken($email, $token); // Função para salvar o token no banco de dados

            // Configurações do e-mail
            log_message('info', 'Email de signup usuário destino:' . $email);
            $to = $email;
            //$from = getenv('smtp_username');
            $subject = '✉️ Bem-vindo ao MyFlow! Confirme seu email para começar';
            
            // HTML profissional e atrativo
            $confirmLink = base_url("confirmEmail?token=$token");
            $message = "
            <!DOCTYPE html>
            <html lang='pt-BR'>
            <head>
                <meta charset='UTF-8'>
                <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                <style>
                    body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 0; padding: 0; background: #f5f5f5; }
                    .container { max-width: 600px; margin: 20px auto; background: white; border-radius: 10px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); overflow: hidden; }
                    .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 40px 20px; text-align: center; }
                    .header h1 { margin: 0; font-size: 32px; font-weight: bold; }
                    .content { padding: 40px 30px; color: #333; }
                    .greeting { font-size: 18px; margin-bottom: 20px; color: #333; }
                    .message { font-size: 16px; line-height: 1.6; color: #666; margin: 20px 0; }
                    .highlight { color: #667eea; font-weight: bold; }
                    .cta-button { display: inline-block; margin: 30px 0; padding: 15px 40px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; text-decoration: none; border-radius: 50px; font-size: 16px; font-weight: bold; box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4); transition: transform 0.3s ease; }
                    .cta-button:hover { transform: translateY(-2px); }
                    .note { background: #f0f4ff; padding: 15px; border-left: 4px solid #667eea; margin: 20px 0; border-radius: 5px; font-size: 14px; color: #555; }
                    .footer { background: #f8f9fa; padding: 30px; text-align: center; color: #999; font-size: 13px; border-top: 1px solid #e9ecef; }
                    .footer a { color: #667eea; text-decoration: none; }
                    .icon { font-size: 48px; margin-bottom: 10px; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <div class='icon'>🎉</div>
                        <h1>Bem-vindo ao MyFlow!</h1>
                    </div>
                    <div class='content'>
                        <p class='greeting'>Olá, <span class='highlight'>$nome</span>!</p>
                        
                        <p class='message'>
                            Obrigado por se cadastrar no <span class='highlight'>MyFlow</span> – sua plataforma de datalake e analytics inteligente! 🚀
                        </p>
                        
                        <p class='message'>
                            Para ativar sua conta e começar a transformar seus dados em insights poderosos, você precisa <span class='highlight'>confirmar seu endereço de e-mail</span>.
                        </p>
                        
                        <div style='text-align: center;'>
                            <a href='$confirmLink' class='cta-button'>✓ Confirmar Meu Email</a>
                        </div>
                        
                        <div class='note'>
                            <strong>⏱️ Atenção:</strong> Este link expira em 24 horas. Se não conseguir clicar no botão acima, copie e cole este link no seu navegador: <br><br>
                            <code style='background: #f0f0f0; padding: 5px 10px; border-radius: 3px;'>$confirmLink</code>
                        </div>
                        
                        <p class='message'>
                            Assim que confirmar seu email, você terá acesso imediato a todas as funcionalidades do MyFlow:
                        </p>
                        
                        <ul style='color: #666; line-height: 1.8;'>
                            <li>📦 Gerenciamento de buckets e armazenamento</li>
                            <li>📊 Dashboards e relatórios avançados</li>
                            <li>⚙️ Orquestração de fluxos de dados</li>
                            <li>🔐 Segurança e controle de acesso</li>
                        </ul>
                        
                        <p class='message' style='margin-top: 30px;'>
                            Se você não criou esta conta ou tem alguma dúvida, entre em contato conosco pelo email <a href='mailto:suporte@smarttables.x10.mx' style='color: #667eea; text-decoration: none;'>suporte@smarttables.x10.mx</a>.
                        </p>
                        
                        <p class='message'>
                            Estamos aqui para ajudar! 💪
                        </p>
                        
                        <p style='margin-top: 30px; color: #999; font-size: 14px;'>
                            Atenciosamente,<br>
                            <strong style='color: #333;'>Equipe MyFlow</strong>
                        </p>
                    </div>
                    
                    <div class='footer'>
                        <p>© 2025 MyFlow - Inteligência em Dados. Todos os direitos reservados.</p>
                        <p>
                            <a href='http://smarttables.x10.mx/politica'>Política de Privacidade</a> | 
                            <a href='http://smarttables.x10.mx/tdu'>Termos de Uso</a> | 
                            <a href='http://smarttables.x10.mx/contactUs'>Suporte</a>
                        </p>
                    </div>
                </div>
            </body>
            </html>
            ";

            $subject = mb_convert_encoding($subject, 'UTF-8', 'auto');
            $message = mb_convert_encoding($message, 'UTF-8', 'auto');
            
            
            // Configurações do SMTP
            $smtpHost = getenv('smtp_host');
            $smtpPort = getenv('smtp_port'); // Porta para STARTTLS
            $username = getenv('smtp_username');
            $password = getenv('smtp_password');
            $SMTPSecure = getenv('smtp_secure');

            // Configurações do PHPMailer
            $mail->isSMTP(); // Define o uso de SMTP
            $mail->Host = $smtpHost;
            $mail->SMTPAuth = true;
            $mail->Username = $username;
            $mail->Password = $password;
            $mail->SMTPSecure = $SMTPSecure; // Habilitar STARTTLS
            $mail->Port = $smtpPort;
            $mail->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                )
            );

            // Definir charset para UTF-8
            $mail->CharSet = 'UTF-8';

            // Configurações do e-mail
            $mail->setFrom($username, getenv('smtp_nome_remetente'));
            $mail->addAddress($to);
            $mail->Subject = $subject;
            $mail->Body = $message;
            $mail->isHTML(true);

            // Envio do e-mail
            $mail->send();
            
            /* $response = [
                'status' => 'success',
                'mensagem' => 'Sua mensagem foi enviada com sucesso!'
            ];
            return $this->response->setJSON($response, JSON_UNESCAPED_UNICODE); */ 
            return true;

        } catch (Exception $e) {
            // Loga o erro completo
            log_message('error', 'Erro SMTP ao enviar email: ' . $e->getMessage());
            log_message('error', 'PHPMailer ErrorInfo: ' . $mail->ErrorInfo);
            throw $e;
        }
    }

    private function sendMail($nome, $email)
    {

        $mail = new PHPMailer(true);

        try {
            // Configurações do e-mail
            $to = $email;
            $subject = 'Confirmação de cadastro';
            $message = "Olá, $nome. <br><br> Confirmação de cadastro de usuário na plataforma Smart-Tables.";

            // Configurações do SMTP
            $smtpHost = 'smtp.gmail.com';
            $smtpPort = 587; // Porta para STARTTLS
            $username = 'seu-email@gmail.com';
            $password = 'sua-senha';

            // Configurações do PHPMailer
            $mail->isSMTP(); // Define o uso de SMTP
            $mail->Host = $smtpHost;
            $mail->SMTPAuth = true;
            $mail->Username = $username;
            $mail->Password = $password;
            $mail->SMTPSecure = 'tls'; // Habilitar STARTTLS
            $mail->Port = $smtpPort;

            // Configurações do e-mail
            $mail->setFrom($username, 'Nome do Remetente');
            $mail->addAddress($to);
            $mail->Subject = $subject;
            $mail->Body = $message;
            $mail->isHTML(true);

            // Envio do e-mail
            $mail->send();
            return $this->response->setJSON([
                'status' => 'success',
                'mensagem' => 'Seu cadastro foi enviado com sucesso! Por gentileza, verifique sua caixa de e-mail e clique no link para confirmar seu cadastro.',
                'redirect' => base_url('loginUsuario')
            ]); 

        } catch (Exception $e) {
            return $this->response->setJSON([
                'status' => 'error',
                'mensagem' => 'Falha ao inserir o registro: '. $mail->ErrorInfo . $e->getMessage()
            ]);
        }
    
    }
    
    public function confirmEmail()
    {
        try {

            //Recupera o token enviado pelo link clicado na caixa de e-mail do usuário

            $token = $this->request->getGet('token');
            $dataToken = $this->verifyToken($token);
            
            if ($dataToken) {
                $model = new UsuarioModel();
                
                // A partir da tabela token, recupera o e-mail para obter o uruário

                $list = $model->select('usuario.*')
                            ->where('usuario.email', $dataToken->email)
                            ->first();
                
                if ($list) {

                    $id = $list->id;
                    
                    $usuario = $model->find($id);

                    $data['id'] = $usuario->id;
                    $data['nome'] = $usuario->nome;
                    $data['email'] = $usuario->email;
                    $data['senha'] = $usuario->senha;
                    $data['email_confirmado'] = 1;
                    
                    // Processo de atualização da flag de email confirmado pelo usuário na tabela Usuario
                   
                    if ($model->update($id,$data)) {
                        
                        // Realiza o login do usuário autorizado na plataforma

                        $this->logarUsuarioConfirmaEmail($usuario->email, $usuario->senha);
                        return view("bemVindoNovoUsuario");    
                    } else {
                        log_message('error', 'Erro ao atualizar o registro: ' . $model->getLastQuery());
                        return view('email_confirmation_error', [
                            'mensagem' => 'Não foi possível confirmar seu e-mail. Por favor, tente novamente.'
                        ]);
                    }
                } else {
                    return view('email_confirmation_error', [
                        'mensagem' => 'Nenhum usuário encontrado com este token. O link pode ter expirado.'
                    ]);
                }
            } else {
                return view('email_confirmation_error', [
                    'mensagem' => 'Token inválido ou expirado. Por favor, solicite um novo link de confirmação.'
                ]);
            }
        } catch (Exception $e) {
            log_message('error', 'Erro na confirmação de email: ' . $e->getMessage());
            return view('email_confirmation_error', [
                'mensagem' => 'Ocorreu um erro ao processar sua solicitação. Por favor, tente novamente mais tarde.'
            ]);
        }
    }

    public function recriaSenha()
    {
        return view('forgotPassword');
    }

    //Função chamada por rota
    public function recuperaUsuarioPorEmail()
    {
        /* echo "logou fake";
        die(); */
        $email = $this->request->getPost('email');
        $data = [
            'email' => $email,
        ];
        
        try {
            $list = $this->listByEmail($data['email']);
            if (!empty($list) && isset($list[0])) {
                $usuario = $list[0]; // Acessa o primeiro usuário na lista
                // Carregando o usuário na sessão
                $_SESSION['id_usuario_logado'] = $usuario->id;
                $_SESSION['nome_usuario_logado'] = $usuario->nome;
                $_SESSION['perfil_usuario_logado'] = $usuario->perfil_descricao;
                $_SESSION['usuario_logado'] = 1;
                
                $db = \Config\Database::connect();
                $db->transStart();   

                if($this->salvaRecriaSenha($usuario->id))
                {
                    
                    $db->transComplete();

                    if ($db->transStatus() === false) {
                        // Desfazer a transação em caso de erro
                        throw new \Exception('Transação falhou.');
                    }

                    return $this->response->setJSON([
                        'status' => 'success',
                        'mensagem' => 'Sua nova senha foi registrada com sucesso! Por gentileza, verifique sua 
                        caixa de email (' . $email . ') para confirmar essa operação'
                    ]);

                }
                else
                {
                    $_SESSION['usuario_logado'] = 0;
                    $db->transRollback();
                    return $this->response->setJSON([
                        'status' => 'error',
                        'mensagem' => 'Ocorreu uma falha ao registrar usuário ! Tente novamente mais tarde.'
                    ]); 
                }
                

            }
            else{

                $_SESSION['usuario_logado'] = 0;
                
                return $this->response->setJSON([
                    'status' => 'error',
                    'mensagem' => 'Email de usuário não encontrao ! Verifique se digitou corretamente o e-mail'
                ]);    
            }
            
        } catch (\Exception $e) {

            $db->transRollback();

            return $this->response->setJSON([
                'status' => 'error',
                'mensagem' => 'Falha ao logar o usuário: ' . $e->getMessage()
            ]);
        }
    }

    public function salvaRecriaSenha($id) : bool
    {
        try
        {
            
            $model = new UsuarioModel();
            $usuario = $model->find($id);

            $data['id'] = $usuario->id;
            $data['nome'] = $usuario->nome;
            $data['email'] = $usuario->email;
            $data['senha'] = $this->request->getPost('senha');;
            $data['email_confirmado'] = 0;
            
            // Processo de atualização da flag de email confirmado pelo usuário na tabela Usuario
            
            if($this->sendMailNoSecurity($usuario->nome, $usuario->email))
            {
                if ($model->update($id,$data)) {
                    return true;    
                }
            }
            else
            {
                return false;
            }

        } catch (Exception $e) {
            throw $e;

        }
    } 


/*     public function confirmEmail()
    {
        
        try {

            $token = $this->request->getGet('token');
            $dataToken = $this->verifyToken($token);
                
            if ($dataToken) {

                $model = new UsuarioModel();
                
                $model->select('usuario.*');
                $model->where('usuario.email', $dataToken->email);
                $list = $model->first();

                $email = $list->email;
                $senha = $list->senha;

                $model2 = new UsuarioModel();    

                $model2->set('email_confirmado', 1);
                $model2->where('email', $email);
                $model2->where('senha', $senha);
                
                $model2->update();
    

                $this->logarUsuarioConfirmaEmail($email, $senha);

            }

                
            

        } catch (Exception $e) {
            return $this->response->setJSON([
                'status' => 'error',
                'mensagem' => 'Falha ao confirmar seu e-mail... :-( : ' . $e->getMessage()
            ]);
        }

    }
 */
    private function saveToken($email, $token)
    {
        // Salve o token e o e-mail no banco de dados
        // Exemplo:
        // $db = \Config\Database::connect();
        // $db->table('email_tokens')->insert(['email' => $email, 'token' => $token]);

        $data = [
            'email' => $email,
            'token' => $token
        ];

        try {
            $tokenModel = new TokenModel();
            $tokenModel->insert($data);

        } catch (\Throwable $ex) {
            throw $ex;
        }
        

    }


    private function verifyToken($token)
    {
        // Verifique se o token é válido no banco de dados
        $tokenModel = new TokenModel();
        $data = $tokenModel->where('token', $token)->first();
        return $data;
    
    }

    
    public function update() {
        $model = new UsuarioModel();
        $usuarioPerfilModel = new UsuarioPerfilModel();
        $id = $this->request->getPost('id');
        $data = [
            'nome' => $this->request->getPost('nome'),
            'email' => $this->request->getPost('email'),
            'senha' => $this->request->getPost('senha')
        ];
        
        $perfis = $this->request->getPost('id_perfil');
        
        $db = \Config\Database::connect();
        $db->transStart();

        try {
            $updated = $model->update($id, $data);
            
            if ($updated && !empty($perfis)) {
                $usuarioPerfilModel->savePerfisUsuario($id, $perfis);
            }
            
            $db->transComplete();
            
            if ($db->transStatus() === false) {
                throw new \Exception('Falha na transação ao atualizar usuário e perfis.');
            }
                
            return $this->response->setJSON([
                'status' => 'success',
                'mensagem' => 'Registro atualizado com sucesso!'
        ]);
        } catch (\Exception $e) {
            $db->transRollback();
            return $this->response->setJSON([
                'status' => 'error',
                'mensagem' => 'Falha ao atualizar o registro: ' . $e->getMessage()
            ]);
        }
        
    }
    
    public function delete($id)  {
        
        $model = new UsuarioModel();

        try {
            
            $deleted = $model->delete($id);
            
            return $this->response->setJSON([
                'status' => $deleted ? 'success' : 'warning',
                'mensagem' => $deleted ? 'Registro excluído com sucesso!' : 'Falha ao atualizar o registro. Tente novamente.'
            ]);
        
        } catch (\Exception $e) {
            return $this->response->setJSON([
                'status' => 'error',
                'mensagem' => 'Falha ao excluir o registro: ' . $e->getMessage()
            ]);
        }
    }
}
