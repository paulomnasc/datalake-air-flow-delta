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
        /* echo "logou fake";
        die(); */

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
                    $airflowResult = AirflowHelper::syncUserWithAirflow(
                        $usuario->id,
                        $usuario->email ?? "",
                        explode(' ', $usuario->nome)[0] ?? 'User',
                        (count(explode(' ', $usuario->nome)) > 1) ? implode(' ', array_slice(explode(' ', $usuario->nome), 1)) : $usuario->id,
                        $data['senha']
                    );
                    
                    if ($airflowResult['success']) {
                        log_message('info', "[AIRFLOW] {$airflowResult['message']}");
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
            $subject = 'Confirmação de cadastro';
            $message = "Olá, $nome. <br><br> Confirmação de cadastro de usuário na plataforma Smart-Tables.";
            $message .= "Clique no link abaixo para confirmar seu e-mail:<br>";
            $message .= "<a href='" . base_url("confirmEmail?token=$token") . "'>Confirmar E-mail</a>";

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
            throw $e;
            // Garantir que a mensagem de erro seja codificada corretamente
            /* $errorMessage = mb_convert_encoding($mail->ErrorInfo . $e->getMessage(), 'UTF-8', 'auto');
            $response = [
                'status' => 'error',
                'mensagem' => 'Falha ao inserir o registro: ' . $errorMessage
            ];
            return $this->response->setJSON($response, JSON_UNESCAPED_UNICODE); */
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
                        //return redirect()->route('home')->with('success-message', 'Seu e-mail foi confirmado com sucesso!');
                        return view("bemVindoNovoUsuario");    
                        /* return $this->response->setJSON([
                            'status' => 'success',
                            'mensagem' => 'Seu e-mail foi confirmado com sucesso!'
                        ]); */
                    } else {
                        log_message('error', 'Erro ao atualizar o registro: ' . $model->getLastQuery());
                        return $this->response->setJSON([
                            'status' => 'error',
                            'mensagem' => 'Não foi possível confirmar seu e-mail.'
                        ]);
                    }
                } else {
                    return $this->response->setJSON([
                        'status' => 'error',
                        'mensagem' => 'Nenhum registro encontrado para atualização.'
                    ]);
                }
            } else {
                return $this->response->setJSON([
                    'status' => 'error',
                    'mensagem' => 'Token inválido.'
                ]);
            }
        } catch (Exception $e) {
            return $this->response->setJSON([
                'status' => 'error',
                'mensagem' => 'Falha ao confirmar seu e-mail... :-( : ' . $e->getMessage()
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
