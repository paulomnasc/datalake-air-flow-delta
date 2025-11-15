<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/phpMailer/PHPMailer.php'; 
require 'vendor/phpMailer/Exception.php'; 
require 'vendor/phpMailer/SMTP.php';

class MarketPlaceController extends BaseController
{
    public function index()
    {
        //
    }

    public function contactUs()
    {
        //
        return view("contactUs");
    }

    public function politica()
    {
        //
        return view("politicaPrivacidade");
    }

    public function tdu()
    {
        //
        return view("termosUso");
    }

    public function donate() {
        return view("donate");
    }

    public function sendMailNoSecurity()
    {
        $mail = new PHPMailer(true);

        try {


            
            // Configurações do e-mail
            $to = getenv('smtp_username');
            $from = $this->request->getPost('email');
            $subject = $this->request->getPost('assunto');
            $message = $this->request->getPost('mensagem');
            
            
            // Configurações do SMTP
            $smtpHost = getenv('smtp_host');
            $smtpPort = getenv('smtp_port'); // Porta para STARTTLS
            $username = getenv('smtp_username');
            $password = getenv('smtp_password');

            // Configurações do PHPMailer
            $mail->isSMTP(); // Define o uso de SMTP
            $mail->Host = $smtpHost;
            $mail->SMTPAuth = true;
            $mail->Username = $username;
            $mail->Password = $password;
            $mail->SMTPSecure = 'tls'; // Habilitar STARTTLS
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
            $mail->setFrom($from, $from);
            $mail->addAddress($to);
            $mail->Subject = $subject;
            $mail->Body = $message;
            $mail->isHTML(true);

            // Envio do e-mail
            $mail->send();

            return $this->response->setJSON([
                'status' => 'success',
                'mensagem' => 'Sua mensagem foi enviada com sucesso! Assim que possível estaremos respondendo.'
                
            ]); 

            /* return $this->response->setJSON([
                'status' => 'success',
                'mensagem' => 'Sua nova senha foi registrada com sucesso! Por gentileza, verifique sua 
                caixa de email para confirmar essa operação'
            ]); */

        } catch (Exception $e) {
            return $this->response->setJSON([
                'status' => 'error',
                'mensagem' => 'Falha ao inserir o registro: '. $mail->ErrorInfo . $e->getMessage()
            ]);
        }
    }

    /* public function sendMail()
    {

        try {

            // Configurações do e-mail
            $to = $this->request->getPost('email');; // Substitua pelo endereço de e-mail do destinatário
            $subject = $this->request->getPost('assunto');;
            $message = $this->request->getPost('mensagem');

            // Cabeçalhos do e-mail
            $headers = "From: paulomnasc@gmail.com\r\n"; // Substitua pelo endereço de e-mail do remetente
            //$headers .= "Reply-To: remetente@example.com\r\n"; // Endereço de resposta
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n"; // Formatação em HTML

            mail($to, $subject, $message, $headers);

            return view("mensagemSucesso");    


        } catch (\Exception $e) {
            return $this->response->setJSON([
                'status' => 'error',
                'mensagem' => 'Falha ao enviar o email: ' . $e->getMessage()
            ]);
        }
        
        
        
    }
 */
    public function saibaMais()
    {
        return view("saibaMais");
    }
}
