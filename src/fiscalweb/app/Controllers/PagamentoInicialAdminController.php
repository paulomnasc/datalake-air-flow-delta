<?php
namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\UsuarioModel;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/phpMailer/PHPMailer.php'; 
require 'vendor/phpMailer/Exception.php'; 
require 'vendor/phpMailer/SMTP.php';

class PagamentoInicialAdminController extends BaseController
{
    protected function checkAdminAuth()
    {
        if (!isset($_SESSION['perfil_usuario_logado']) || $_SESSION['perfil_usuario_logado'] !== 'Admin') {
            return redirect()->to('/')->with('error', 'Acesso negado. Somente administradores podem acessar esta área.');
        }
        return null;
    }

    public function index()
    {
        $check = $this->checkAdminAuth();
        if ($check) return $check;

        $usuarioModel = new UsuarioModel();
        $usuarios = $usuarioModel->where('pagamento_inicial', 0)->findAll();
        return view('admin/pagamento_inicial', ['usuarios' => $usuarios]);
    }

    public function autorizar($id)
    {
        $check = $this->checkAdminAuth();
        if ($check) return $check;

        $usuarioModel = new UsuarioModel();
        $usuario = $usuarioModel->find($id);
        if (!$usuario) {
            return redirect()->back()->with('error', 'Usuário não encontrado.');
        }
        $usuarioModel->update($id, ['pagamento_inicial' => 1]);
        
        // Disparar email de agradecimento
        $this->enviarEmailAgradecimento($usuario);
        
        return redirect()->back()->with('success', "Pagamento inicial autorizado e e-mail enviado para {$usuario->email}");
    }

    private function enviarEmailAgradecimento($usuario)
    {
        $mail = new PHPMailer(true);

        try {
            $nomeAluno = $usuario->nome ?? '';
            $emailAluno = $usuario->email ?? '';
            $dataVencimento = $usuario->data_vencimento_assinatura ?? 'Não definida';
            
            if (!empty($dataVencimento) && $dataVencimento !== 'Não definida' && $dataVencimento !== '0000-00-00' && $dataVencimento !== '0000-00-00 00:00:00') {
                $dataVencimento = date('d/m/Y', strtotime($dataVencimento));
            } else {
                $dataVencimento = '-';
            }
            
            $logoUrl = base_url('assets/img/carcara-logo.png');

            $to = $emailAluno;
            $subject = 'Acesso Liberado - MyDataFlow Lab';
            
            $message = "
            <!DOCTYPE html>
            <html lang='pt-BR'>
            <head>
                <meta charset='UTF-8'>
                <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                <style>
                    body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 0; padding: 0; background: #f5f5f5; }
                    .container { max-width: 600px; margin: 20px auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
                    .content { color: #333; font-size: 16px; line-height: 1.6; }
                    .footer { margin-top: 40px; padding-top: 20px; border-top: 1px solid #eee; text-align: center; }
                    .footer img { max-width: 150px; height: auto; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='content'>
                        <p>Prezado {$nomeAluno}, tudo bem ?</p>
                        <p>Detectamos sua colaboração com a plataforma MyDataFlow Lab e ativamos seu acesso.</p>
                        <p>Sua assinatura vai ficar ativa até {$dataVencimento} .</p>
                        <p>Caso tenha problemas para acessar dentro desse prazo entre em contato com admin@estudotabela.com.br.</p>
                        <p>Bons estudos,</p>
                        <p>Time MyDataFlow</p>
                    </div>
                    <div class='footer'>
                        <img src='{$logoUrl}' alt='Logo MyDataFlow'>
                    </div>
                </div>
            </body>
            </html>
            ";

            $subject = mb_convert_encoding($subject, 'UTF-8', 'auto');
            $message = mb_convert_encoding($message, 'UTF-8', 'auto');
            
            // Configurações do SMTP
            $smtpHost = getenv('smtp_host');
            $smtpPort = getenv('smtp_port');
            $username = getenv('smtp_username');
            $password = getenv('smtp_password');
            $SMTPSecure = getenv('smtp_secure');

            $mail->isSMTP();
            $mail->Host = $smtpHost;
            $mail->SMTPAuth = true;
            $mail->Username = $username;
            $mail->Password = $password;
            $mail->SMTPSecure = $SMTPSecure;
            $mail->Port = $smtpPort;
            $mail->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                )
            );

            $mail->CharSet = 'UTF-8';
            $mail->setFrom($username, getenv('smtp_nome_remetente') ?: 'MyDataFlow Lab');
            $mail->addAddress($to);
            $mail->Subject = $subject;
            $mail->Body = $message;
            $mail->isHTML(true);

            $mail->send();
            return true;

        } catch (Exception $e) {
            log_message('error', 'Erro SMTP ao enviar email de pagamento inicial: ' . $e->getMessage());
            log_message('error', 'PHPMailer ErrorInfo: ' . $mail->ErrorInfo);
            return false;
        }
    }
}
