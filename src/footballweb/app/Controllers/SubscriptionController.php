<?php

namespace App\Controllers;

require_once FCPATH . 'vendor/autoload.php';

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;
use App\Models\UsuarioModel;
use App\Helpers\SubscriptionHelper;
use App\Helpers\AirflowHelper;
use Config\Services;

/**
 * SubscriptionController
 * 
 * Controller para gerenciar assinaturas de usuários
 * Exibe página de renovação, verifica status e processa pagamentos
 */
class SubscriptionController extends BaseController
{
    /**
     * Página principal de renovação de assinatura
     * Exibe informações da assinatura atual e opções de renovação
     */
    public function index()
    {
        // Verifica se usuário está logado
        if (!isset($_SESSION['usuario_logado']) || $_SESSION['usuario_logado'] != 1) {
            return redirect()->to('/loginUsuario')->with('error', 'Você precisa estar logado para acessar esta página.');
        }

        $userId = $_SESSION['id_usuario_logado'] ?? null;
        if (!$userId) {
            return redirect()->to('/loginUsuario');
        }

        // Busca dados do usuário
        $usuarioModel = new UsuarioModel();
        $usuario = $usuarioModel->find($userId);

        if (!$usuario) {
            return redirect()->to('/')->with('error', 'Usuário não encontrado.');
        }

        // Cotação USD -> BRL para exibir valor convertido
        $valorUsd = getenv('INITIAL_PAYMENT_USD') ? (float)getenv('INITIAL_PAYMENT_USD') : 10.00;
        $cotacao = null;
        $valorBrl = null;
        $cotacaoMensagem = null;

        try {
            $client = Services::curlrequest(['timeout' => 5]);
            $response = $client->get('https://api.exchangerate.host/latest?base=USD&symbols=BRL');

            if ($response->getStatusCode() === 200) {
                $body = json_decode($response->getBody(), true);
                if (!empty($body['rates']['BRL'])) {
                    $cotacao = (float) $body['rates']['BRL'];
                    $valorBrl = round($valorUsd * $cotacao, 2);
                }
            }
        } catch (\Throwable $e) {
            log_message('warning', '[SUBSCRIPTION] Falha ao buscar cotação USD->BRL: ' . $e->getMessage());
        }

        if (!$valorBrl) {
            $cotacao = $cotacao ?? 5.38;
            $valorBrl = round($valorUsd * $cotacao, 2);
            $cotacaoMensagem = 'Não foi possível obter a cotação em tempo real. Usando taxa padrão.';
        }

        // Prepara dados para a view
        $data = [
            'usuario_nome' => $usuario->nome ?? 'Usuário',
            'usuario_email' => $usuario->email ?? '',
            'status_assinatura' => $usuario->status_assinatura ?? 'trial',
            'data_vencimento' => $usuario->data_vencimento_assinatura ?? null,
            'data_ultimo_pagamento' => $usuario->data_ultimo_pagamento ?? null,
            'data_inicio_trial' => $usuario->data_inicio_trial ?? null,
            'dias_restantes' => SubscriptionHelper::calcularDiasRestantes($usuario->data_vencimento_assinatura),
            'pode_acessar' => true,
            'mensagem_bloqueio' => $_SESSION['subscription_blocked_message'] ?? null,
            'valor_usd' => $valorUsd,
            'cotacao_usd_brl' => $cotacao,
            'valor_brl' => $valorBrl,
            'cotacao_mensagem' => $cotacaoMensagem
        ];

        // Adiciona variáveis faltantes para a view
        $data['dias_restantes'] = 0;
        $data['data_vencimento_formatada'] = '-';
        $data['proximo_vencimento_formatado'] = '-';
        $data['data_ultimo_pagamento'] = '';
        $data['cotacao_mensagem'] = $cotacaoMensagem;

        // Define texto de periodicidade para assinatura
        $data['texto_periodicidade'] = ' mensais';

        // Gera o payload do PIX no backend para maior confiabilidade
        $data['pix_payload'] = $this->buildPixPayload(
            '03206740703', // Chave CPF de Cristiane (somente números)
            $valorBrl,
            'CRISTIANE B L NASCIMENTO',
            'SAO PAULO',
            'RENOVACAO'
        );

        return view('subscription/renew', $data);
    }

    /**
     * Retorna o status da assinatura em formato JSON
     * Útil para verificações via AJAX
     */
    public function checkStatus()
    {
        // Verifica se usuário está logado
        if (!isset($_SESSION['usuario_logado']) || $_SESSION['usuario_logado'] != 1) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'Usuário não autenticado'
            ]);
        }

        $userId = $_SESSION['id_usuario_logado'] ?? null;
        if (!$userId) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'ID de usuário não encontrado'
            ]);
        }

        // Busca dados do usuário
        $usuarioModel = new UsuarioModel();
        $usuario = $usuarioModel->find($userId);

        if (!$usuario) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'Usuário não encontrado'
            ]);
        }

        // Calcula informações da assinatura
        $diasRestantes = SubscriptionHelper::calcularDiasRestantes($usuario->data_vencimento_assinatura);
        $acessoInfo = SubscriptionHelper::podeAcessarPlataforma(
            $usuario->status_assinatura ?? 'trial',
            $usuario->data_vencimento_assinatura
        );

        return $this->response->setJSON([
            'status' => 'success',
            'data' => [
                'status_assinatura' => $usuario->status_assinatura ?? 'trial',
                'data_vencimento' => $usuario->data_vencimento_assinatura ?? null,
                'dias_restantes' => $diasRestantes,
                'pode_acessar' => $acessoInfo['pode_acessar'],
                'mensagem' => $acessoInfo['mensagem'],
                'deve_mostrar_aviso' => SubscriptionHelper::deveMostrarAviso(
                    $usuario->data_vencimento_assinatura,
                    $usuario->status_assinatura ?? 'trial'
                )
            ]
        ]);
    }

    /**
     * Processa a confirmação de pagamento
     * Este método deve ser chamado DEPOIS que você confirmar o pagamento manualmente
     * 
     * @return ResponseInterface
     */
    public function confirmPayment()
    {
        // Verifica se usuário está logado
        if (!isset($_SESSION['usuario_logado']) || $_SESSION['usuario_logado'] != 1) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'Usuário não autenticado'
            ]);
        }

        $userId = $_SESSION['id_usuario_logado'] ?? null;
        if (!$userId) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'ID de usuário não encontrado'
            ]);
        }

        // Registra o pagamento
        $usuarioModel = new UsuarioModel();
        $resultado = SubscriptionHelper::registrarPagamento($usuarioModel, $userId);

        if ($resultado['success']) {
            // Atualiza dados na sessão
            $_SESSION['subscription_status'] = 'active';
            $_SESSION['subscription_expiry_date'] = $resultado['novo_vencimento'];
            $_SESSION['subscription_last_payment'] = date('Y-m-d');
            $_SESSION['subscription_services_blocked'] = false; // Desbloqueia serviços

            // Reativar usuário no Airflow
            $usuario = $usuarioModel->find($userId);
            if ($usuario) {
                $airflowResult = AirflowHelper::setUserActiveStatus(
                    $userId,
                    $usuario->email ?? '',
                    true
                );

                if ($airflowResult['success']) {
                    log_message('info', "[SUBSCRIPTION] Usuário {$userId} reativado no Airflow após renovação");
                } else {
                    log_message('warning', "[SUBSCRIPTION] Falha ao reativar usuário {$userId} no Airflow: {$airflowResult['message']}");
                }
            }

            // Envia email usando o método que já funciona: MarketPlaceController::sendMailNoSecurity
            try {
                $marketplace = new \App\Controllers\MarketPlaceController();
                $email = $usuario->email ?? 'no-reply@mydataflow.com';
                $assunto = 'Confirmação de pagamento MyDataFlow - Cursos';
                $mensagem = "Boa tarde, sou o aluno {$usuario->nome} e informo que já realizei o PIX para liberação do meu curso.\n\nDados do aluno:\nNome: {$usuario->nome}\nE-mail: {$usuario->email}";
                $marketplace->sendMailCustom($email, $assunto, $mensagem);
            } catch (\Exception $e) {
                log_message('error', '[SUBSCRIPTION] Falha ao enviar email para admin: ' . $e->getMessage());
            }

            return $this->response->setJSON([
                'status' => 'success',
                'message' => $resultado['message'],
                'novo_vencimento' => $resultado['novo_vencimento']
            ]);
        } else {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => $resultado['message']
            ]);
        }
    }

    /**
     * Página de instrução para pagamento via PIX
     * Aqui você pode adicionar lógica para gerar QR Code dinâmico
     */
    public function pixPayment()
    {
        // Verifica se usuário está logado
        if (!isset($_SESSION['usuario_logado']) || $_SESSION['usuario_logado'] != 1) {
            return redirect()->to('/loginUsuario')->with('error', 'Você precisa estar logado.');
        }

        $userId = $_SESSION['id_usuario_logado'] ?? null;
        $usuarioModel = new UsuarioModel();
        $usuario = $usuarioModel->find($userId);

        $valorUsd = getenv('INITIAL_PAYMENT_USD') ? (float)getenv('INITIAL_PAYMENT_USD') : 10.00;
        $pixKey = '024253748';
        $cotacao = null;
        $valorBrl = null;
        $cotacaoMensagem = null;

        // Busca cotação USD -> BRL
        try {
            $client = Services::curlrequest(['timeout' => 5]);
            $response = $client->get('https://api.exchangerate.host/latest?base=USD&symbols=BRL');

            if ($response->getStatusCode() === 200) {
                $body = json_decode($response->getBody(), true);
                if (!empty($body['rates']['BRL'])) {
                    $cotacao = (float) $body['rates']['BRL'];
                    $valorBrl = round($valorUsd * $cotacao, 2);
                }
            }
        } catch (\Throwable $e) {
            log_message('warning', '[PIX] Falha ao buscar cotação USD->BRL: ' . $e->getMessage());
        }

        // Fallback simples se API falhar
        if (!$valorBrl) {
            $cotacao = $cotacao ?? 5.38;
            $valorBrl = round($valorUsd * $cotacao, 2);
            $cotacaoMensagem = 'Não foi possível obter a cotação em tempo real. Usando taxa padrão.';
        }

        // Monta payload BR Code do PIX e URL do QR Code
        $pixPayload = $this->buildPixPayload(
            $pixKey,
            $valorBrl,
            'DATALAKE',
            'SAMAMBAIA',
            'SUBSCRIP'
        );

        $qrCodeUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=320x320&data=' . urlencode($pixPayload);

        $data = [
            'usuario_nome' => $usuario->nome ?? 'Usuário',
            'usuario_email' => $usuario->email ?? '',
            'valor_usd' => $valorUsd,
            'cotacao_usd_brl' => $cotacao,
            'valor_brl' => $valorBrl,
            'pix_key' => $pixKey,
            'pix_payload' => $pixPayload,
            'qr_code_url' => $qrCodeUrl,
            'cotacao_mensagem' => $cotacaoMensagem
        ];

        return view('subscription/pix_payment', $data);
    }

    /**
     * Gera payload EMV do PIX (BR Code) com chave estática
     */
    private function buildPixPayload(string $pixKey, float $amountBrl, string $merchantName, string $merchantCity, string $txid): string
    {
        // Limpar a chave PIX (remover pontos, traços, espaços, etc)
        $pixKey = preg_replace('/[^a-zA-Z0-9]/', '', $pixKey);

        $merchantAccountInfo = $this->emvField('00', 'BR.GOV.BCB.PIX') .
            $this->emvField('01', $pixKey);

        // TXID deve ser limitado a 25 caracteres para PIX Estático (ou usar ***)
        $txidClean = preg_replace('/[^a-zA-Z0-9]/', '', $txid);
        $additionalDataField = $this->emvField('05', substr($txidClean ?: '***', 0, 25));

        $payload = '000201';
        $payload .= '26' . $this->emvLength($merchantAccountInfo) . $merchantAccountInfo;
        $payload .= '52040000';
        $payload .= '5303986';
        $payload .= $this->emvField('54', number_format($amountBrl, 2, '.', ''));
        $payload .= '5802BR';
        $payload .= $this->emvField('59', substr($merchantName, 0, 25));
        $payload .= $this->emvField('60', substr($merchantCity, 0, 15));
        $payload .= '62' . $this->emvLength($additionalDataField) . $additionalDataField;
        $payload .= '6304';

        return $payload . $this->crc16($payload);
    }

    private function emvField(string $id, string $value): string
    {
        return $id . $this->emvLength($value) . $value;
    }

    private function emvLength(string $value): string
    {
        return str_pad(strlen($value), 2, '0', STR_PAD_LEFT);
    }

    private function crc16(string $payload): string
    {
        $polynomial = 0x1021;
        $result = 0xFFFF;

        for ($offset = 0; $offset < strlen($payload); $offset++) {
            $result ^= ord($payload[$offset]) << 8;

            for ($bitwise = 0; $bitwise < 8; $bitwise++) {
                if ($result & 0x8000) {
                    $result = ($result << 1) ^ $polynomial;
                } else {
                    $result <<= 1;
                }

                $result &= 0xFFFF;
            }
        }

        return strtoupper(str_pad(dechex($result), 4, '0', STR_PAD_LEFT));
    }

    public function initialPayment()
    {
        // Reaproveita lógica do pixPayment para cotação e QR Code
        $userId = $_SESSION['id_usuario_logado'] ?? null;
        if (!$userId) {
            return redirect()->to('/loginUsuario');
        }
        $usuarioModel = new UsuarioModel();
        $usuario = $usuarioModel->find($userId);
        // Valor inicial
        $valorUsd = getenv('INITIAL_PAYMENT_USD') ? (float)getenv('INITIAL_PAYMENT_USD') : 10.00;
        $pixKey = '032.067.407-03'; // Chave PIX
        $cotacao = null;
        $valorBrl = null;
        $cotacaoMensagem = null;
        // Busca cotação USD -> BRL
        try {
            $client = \Config\Services::curlrequest(['timeout' => 5]);
            $response = $client->get('https://api.exchangerate.host/latest?base=USD&symbols=BRL');
            if ($response->getStatusCode() === 200) {
                $body = json_decode($response->getBody(), true);
                if (!empty($body['rates']['BRL'])) {
                    $cotacao = (float) $body['rates']['BRL'];
                    $valorBrl = round($valorUsd * $cotacao, 2);
                }
            }
        } catch (\Throwable $e) {
            log_message('warning', '[PIX] Falha ao buscar cotação USD->BRL: ' . $e->getMessage());
        }
        if (!$valorBrl) {
            $cotacao = $cotacao ?? 5.38;
            $valorBrl = round($valorUsd * $cotacao, 2);
            $cotacaoMensagem = 'Não foi possível obter a cotação em tempo real. Usando taxa padrão.';
        }
        // Monta payload BR Code do PIX e URL do QR Code
        $pixPayload = $this->buildPixPayload(
            '03206740703', 
            $valorBrl,
            'CRISTIANE B L NASCIMENTO',
            'SAMAMBAIA',
            'SUBSCRIP'
        );
        $qrCodeUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=320x320&data=' . urlencode($pixPayload);
        // Prepara dados para a view
        $data = [
            'usuario_nome' => $usuario->nome ?? 'Usuário',
            'usuario_email' => $usuario->email ?? '',
            'status_assinatura' => $usuario->status_assinatura ?? 'trial',
            'mensagem_bloqueio' => 'Para acessar o curso completo, realize o pagamento de BRL: R$ ' . $valorBrl . '.',
            'valor_usd' => $valorUsd,
            'cotacao_usd_brl' => $cotacao,
            'valor_brl' => $valorBrl,
            'cotacao_mensagem' => $cotacaoMensagem,
            'qr_code_url' => $qrCodeUrl,
            'pix_key' => $pixKey,
            'pix_payload' => $pixPayload
        ];
        // Adiciona variáveis faltantes para a view
        $data['dias_restantes'] = 0;
        $data['data_vencimento_formatada'] = '-';
        $data['proximo_vencimento_formatado'] = '-';
        $data['data_ultimo_pagamento'] = '';
        // Define texto de periodicidade para pagamento único de curso
        $data['texto_periodicidade'] = ' (cota única)';
        return view('subscription/renew', $data);
    }

    /**
     * Página para comprar créditos do Grok AI via PIX
     */
    public function buyGrokCredits()
    {
        if (!isset($_SESSION['usuario_logado']) || $_SESSION['usuario_logado'] != 1) {
            return redirect()->to('/loginUsuario')->with('error', 'Você precisa estar logado para adquirir créditos.');
        }

        $userId = $_SESSION['id_usuario_logado'] ?? null;
        if (!$userId) {
            return redirect()->to('/loginUsuario');
        }

        $usuarioModel = new UsuarioModel();
        $usuario = $usuarioModel->find($userId);

        if (!$usuario) {
            return redirect()->to('/')->with('error', 'Usuário não encontrado.');
        }

        // Valor fixo de R$ 10,00 para a recarga de créditos
        $valorBrl = 10.00;

        // Gera o payload do PIX no backend para maior confiabilidade
        $pixPayload = $this->buildPixPayload(
            '03206740703', // Chave CPF de Cristiane (somente números)
            $valorBrl,
            'CRISTIANE B L NASCIMENTO',
            'SAO PAULO',
            'GROKCREDITS'
        );

        // Verifica se o cadastro foi realizado com login social Google
        $needsGoogleLogin = empty($usuario->google_id);

        $data = [
            'usuario_nome' => $usuario->nome ?? 'Usuário',
            'usuario_email' => $usuario->email ?? '',
            'valor_brl' => $valorBrl,
            'pix_payload' => $pixPayload,
            'grok_credits' => $usuario->grok_credits ?? 0,
            'needs_google_login' => $needsGoogleLogin
        ];

        return view('subscription/buy_grok_credits', $data);
    }

    /**
     * Confirma o recebimento de pagamento de créditos Grok AI (Pix R$ 10,00)
     */
    public function confirmGrokPayment()
    {
        if (!isset($_SESSION['usuario_logado']) || $_SESSION['usuario_logado'] != 1) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'Usuário não autenticado'
            ]);
        }

        $userId = $_SESSION['id_usuario_logado'] ?? null;
        if (!$userId) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'ID de usuário não encontrado'
            ]);
        }

        $usuarioModel = new UsuarioModel();
        $usuario = $usuarioModel->find($userId);
        if (!$usuario) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'Usuário não encontrado'
            ]);
        }

        // Bloqueia se o cadastro não foi realizado com login social do Google
        if (empty($usuario->google_id)) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'Para comprar créditos ou usar o sistema de cotas, você deve se cadastrar com seu login social do Google.'
            ]);
        }

        // Adiciona 20 créditos
        $currentCredits = (int)($usuario->grok_credits ?? 0);
        $newCredits = $currentCredits + 20;

        $usuarioModel->update($userId, [
            'grok_credits' => $newCredits
        ]);

        // Envia email usando o método que já funciona: MarketPlaceController::sendMailCustom
        try {
            $marketplace = new \App\Controllers\MarketPlaceController();
            $email = $usuario->email ?? 'no-reply@mydataflow.com';
            $assunto = 'Recarga de Créditos Grok AI - R$ 10,00';
            $mensagem = "O aluno {$usuario->nome} informou que realizou o PIX de R$ 10,00 para recarga de 20 créditos no Grok AI.\n\nDados do aluno:\nNome: {$usuario->nome}\nE-mail: {$usuario->email}\nNovo Saldo: {$newCredits} créditos.";
            $marketplace->sendMailCustom($email, $assunto, $mensagem);
        } catch (\Exception $e) {
            log_message('error', '[GROK-CREDITS] Falha ao enviar email para admin: ' . $e->getMessage());
        }

        return $this->response->setJSON([
            'status' => 'success',
            'message' => 'Créditos recarregados com sucesso!',
            'novo_saldo' => $newCredits
        ]);
    }
}
