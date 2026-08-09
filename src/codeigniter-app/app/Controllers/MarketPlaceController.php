<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/phpMailer/PHPMailer.php'; 
require 'vendor/phpMailer/Exception.php'; 
require 'vendor/phpMailer/SMTP.php';

class MarketPlaceController extends BaseController {
    /**
     * Envia e-mail customizado sem depender de request
     */
    public function sendMailCustom($email, $assunto, $mensagem)
    {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        try {
            $to = getenv('smtp_username');
            $from = $email;
            $subject = $assunto;
            $message = $mensagem;
            $smtpHost = getenv('smtp_host');
            $smtpPort = getenv('smtp_port');
            $username = getenv('smtp_username');
            $password = getenv('smtp_password');
            $mail->isSMTP();
            $mail->Host = $smtpHost;
            $mail->SMTPAuth = true;
            $mail->Username = $username;
            $mail->Password = $password;
            $mail->SMTPSecure = 'tls';
            $mail->Port = $smtpPort;
            $mail->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                )
            );
            $mail->CharSet = 'UTF-8';
            $mail->setFrom($from, $from);
            $mail->addAddress($to);
            $mail->Subject = $subject;
            $mail->Body = $message;
            $mail->isHTML(true);
            $mail->send();
            return true;
        } catch (\Exception $e) {
            log_message('error', '[MARKETPLACE] Falha ao enviar email custom: ' . $e->getMessage());
            return false;
        }
    }

    public function index()
    {
        //
    }

    public function contactUs()
    {
        //
        return view("contactUs");
    }

    public function reportError()
    {
        return view("reportError");
    }

    public function sendReportErrorEmail()
    {
        $mail = new PHPMailer(true);

        try {
            $to = 'admin@estudotabela.com.br';
            $from = $this->request->getPost('email');
            $subject = '[Reportar Erro] ' . $this->request->getPost('assunto');
            $mensagem = $this->request->getPost('mensagem');

            $htmlBody = "
            <html>
            <head>
              <style>
                body { font-family: Arial, sans-serif; background-color: #f4f6f9; padding: 20px; color: #333; }
                .container { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 8px; padding: 25px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
                .header { border-bottom: 2px solid #e3e6f0; padding-bottom: 15px; margin-bottom: 20px; }
                .header h2 { color: #e74a3b; margin: 0; }
                .field { margin-bottom: 15px; }
                .label { font-weight: bold; color: #4e73df; }
                .content-box { background: #f8f9fc; border-left: 4px solid #e74a3b; padding: 15px; margin-top: 10px; white-space: pre-wrap; font-family: inherit; }
                .footer { margin-top: 25px; font-size: 12px; color: #858796; border-top: 1px solid #e3e6f0; padding-top: 15px; text-align: center; }
              </style>
            </head>
            <body>
              <div class='container'>
                <div class='header'>
                  <h2>⚠️ Novo Relatório de Erro - MyDataFlow</h2>
                </div>
                <div class='field'><span class='label'>Remetente:</span> " . htmlspecialchars($from) . "</div>
                <div class='field'><span class='label'>Assunto:</span> " . htmlspecialchars($subject) . "</div>
                <div class='field'>
                  <span class='label'>Descrição do Erro:</span>
                  <div class='content-box'>" . nl2br(htmlspecialchars($mensagem)) . "</div>
                </div>
                <div class='footer'>
                  Este e-mail foi gerado automaticamente através do formulário 'Reportar um erro' da plataforma MyDataFlow.
                </div>
              </div>
            </body>
            </html>";

            $smtpHost   = getenv('smtp_host') ?: 'smtp-relay.brevo.com';
            $smtpPort   = getenv('smtp_port') ?: 587;
            $username   = getenv('smtp_username');
            $password   = getenv('smtp_password');
            $smtpSecure = strtolower(trim(getenv('smtp_secure') ?: ''));

            $configureSmtp = function(&$m, $forceNoTls = false) use ($smtpHost, $smtpPort, $username, $password, $smtpSecure) {
                $m->isSMTP();
                $m->Host = $smtpHost;
                $m->SMTPAuth = !empty($username);
                $m->Username = $username;
                $m->Password = $password;
                $m->Port = $smtpPort;

                if ($forceNoTls) {
                    $m->SMTPSecure = '';
                    $m->SMTPAutoTLS = false;
                } elseif ($smtpSecure === 'ssl' || $smtpPort == 465) {
                    $m->SMTPSecure = 'ssl';
                } elseif ($smtpSecure === 'tls' || $smtpSecure === 'starttls') {
                    $m->SMTPSecure = 'tls';
                } elseif (!empty($smtpSecure)) {
                    $m->SMTPSecure = $smtpSecure;
                } else {
                    $m->SMTPSecure = '';
                    $m->SMTPAutoTLS = false;
                }

                $m->SMTPOptions = array(
                    'ssl' => array(
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                        'allow_self_signed' => true
                    )
                );
                $m->CharSet = 'UTF-8';
            };

            $configureSmtp($mail, false);

            if (!empty($username)) {
                $mail->setFrom($username, 'MyDataFlow Report Error');
            } else {
                $mail->setFrom($from, $from);
            }
            $mail->addReplyTo($from);
            $mail->addAddress($to);
            $mail->Subject = $subject;
            $mail->Body = $htmlBody;
            $mail->isHTML(true);

            try {
                $mail->send();
            } catch (\Exception $sendEx) {
                log_message('warning', '[REPORT_ERROR] Envio SMTP falhou: ' . $sendEx->getMessage() . '. Tentando fallback...');
                
                $sentViaFallback = false;

                // Fallback 1: Tenta PHPMailer com isMail()
                try {
                    $mailFallback = new PHPMailer(true);
                    $mailFallback->isMail();
                    $mailFallback->CharSet = 'UTF-8';
                    $senderMail = !empty($username) ? $username : 'noreply@estudotabela.com.br';
                    $mailFallback->setFrom($senderMail, 'MyDataFlow Report Error');
                    $mailFallback->addReplyTo($from);
                    $mailFallback->addAddress($to);
                    $mailFallback->Subject = $subject;
                    $mailFallback->Body = $htmlBody;
                    $mailFallback->isHTML(true);
                    $mailFallback->send();
                    $sentViaFallback = true;
                } catch (\Exception $fallbackEx) {
                    log_message('warning', '[REPORT_ERROR] Fallback PHPMailer isMail falhou: ' . $fallbackEx->getMessage() . '. Tentando mail() nativo...');
                }

                // Fallback 2: mail() direto do PHP se isMail falhou
                if (!$sentViaFallback) {
                    $headers  = "MIME-Version: 1.0\r\n";
                    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
                    $headers .= "From: MyDataFlow Erro <noreply@estudotabela.com.br>\r\n";
                    $headers .= "Reply-To: $from\r\n";
                    $headers .= "X-Mailer: PHP/" . phpversion();

                    $sentViaFallback = @mail($to, $subject, $htmlBody, $headers);
                }

                if (!$sentViaFallback) {
                    log_message('critical', "[REPORT_ERROR] Falha de SMTP e mail(). Relatório gravado em log. Remetente: $from | Assunto: $subject | Mensagem: $mensagem | Erro SMTP: " . $sendEx->getMessage());
                    return $this->response->setJSON([
                        'status' => 'success',
                        'mensagem' => 'Seu relatório de erro foi registrado com sucesso em nosso sistema! Nossa equipe técnica analisará o ocorrido.'
                    ]);
                }
            }

            return $this->response->setJSON([
                'status' => 'success',
                'mensagem' => 'Seu relatório de erro foi enviado com sucesso! Nossa equipe analisará o ocorrido.'
            ]);
        } catch (\Exception $e) {
            log_message('error', '[REPORT_ERROR] Falha geral ao processar relatório: ' . $e->getMessage());
            return $this->response->setJSON([
                'status' => 'error',
                'mensagem' => 'Falha ao enviar o relatório de erro: ' . $e->getMessage()
            ]);
        }
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
