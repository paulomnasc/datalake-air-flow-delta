<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\UsuarioModel;
use App\Helpers\SubscriptionHelper;
use CodeIgniter\API\ResponseTrait;

class HotmartWebhookController extends BaseController
{
    use ResponseTrait;

    public function index()
    {
        // 1. Validar Token (Hottok)
        $hottokEnv = getenv('HOTMART_HOTTOK');
        $headerHottok = $this->request->getHeaderLine('X-Hotmart-Hottok');

        // Se o Hottok estiver configurado no .env, validamos
        if (!empty($hottokEnv) && $headerHottok !== $hottokEnv) {
            log_message('error', 'Webhook Hotmart rejeitado. Hottok inválido. Esperado: ' . $hottokEnv . ' Recebido: ' . $headerHottok);
            return $this->failUnauthorized('Hottok inválido');
        }

        // 2. Obter payload JSON
        $json = $this->request->getJSON();
        if (!$json) {
            log_message('error', 'Webhook Hotmart: Payload vazio ou inválido.');
            return $this->failValidationErrors('Payload inválido');
        }

        $event = $json->event ?? '';
        log_message('info', 'Webhook Hotmart recebido: ' . $event);

        if ($event === 'PURCHASE_APPROVED' || $event === 'PURCHASE_COMPLETE') {
            return $this->handlePurchaseApproved($json);
        } else if (in_array($event, ['PURCHASE_CANCELED', 'PURCHASE_REFUNDED', 'PURCHASE_CHARGEBACK'])) {
            return $this->handlePurchaseCanceled($json);
        }

        // Retorna 200 OK para outros eventos para a Hotmart não ficar re-tentando
        return $this->respond(['status' => 'success', 'message' => 'Evento ignorado'], 200);
    }

    private function handlePurchaseApproved($json)
    {
        $email = $json->data->buyer->email ?? '';
        $name = $json->data->buyer->name ?? '';

        if (empty($email)) {
            return $this->failValidationErrors('E-mail do comprador não encontrado no payload');
        }

        $usuarioModel = new UsuarioModel();
        
        // Buscar usuário pelo e-mail
        $usuario = $usuarioModel->where('email', $email)->first();

        $userId = null;

        if (!$usuario) {
            // Criar novo usuário
            $dadosNovoUsuario = [
                'nome' => $name,
                'email' => $email,
                'senha' => password_hash('mudar123', PASSWORD_DEFAULT), // Senha padrão
                'email_confirmado' => 1,
                'status_assinatura' => 'active'
            ];
            
            $userId = $usuarioModel->insert($dadosNovoUsuario);
            if (!$userId) {
                log_message('error', 'Webhook Hotmart: Falha ao criar usuário para o email ' . $email);
                return $this->failServerError('Falha ao criar usuário');
            }
            log_message('info', 'Webhook Hotmart: Usuário criado com sucesso. ID: ' . $userId);
        } else {
            $userId = $usuario->id;
        }

        // Renovar/Ativar Assinatura
        $resultadoSub = SubscriptionHelper::registrarPagamento($usuarioModel, $userId);
        if (!$resultadoSub['success']) {
            log_message('error', 'Webhook Hotmart: Falha ao registrar pagamento. ' . $resultadoSub['message']);
        }

        // Atualiza pagamento_inicial
        $usuarioModel->update($userId, ['pagamento_inicial' => 1]);
        $usuarioAtualizado = $usuarioModel->find($userId);

        // Dispara o email de agradecimento usando a controller existente
        try {
            $pagamentoController = new \App\Controllers\PagamentoInicialAdminController();
            // Inicializa a request/response fake caso a controller precise (BaseController do CI4)
            $pagamentoController->initController($this->request, $this->response, \Config\Services::logger());
            
            $emailEnviado = $pagamentoController->enviarEmailAgradecimento($usuarioAtualizado);
            if ($emailEnviado) {
                log_message('info', 'Webhook Hotmart: E-mail de agradecimento enviado para ' . $email);
            } else {
                log_message('error', 'Webhook Hotmart: Falha ao enviar e-mail de agradecimento para ' . $email);
            }
        } catch (\Exception $e) {
            log_message('error', 'Webhook Hotmart: Erro ao instanciar PagamentoInicialAdminController ou enviar e-mail: ' . $e->getMessage());
        }

        return $this->respond(['status' => 'success', 'message' => 'Compra aprovada processada com sucesso'], 200);
    }

    private function handlePurchaseCanceled($json)
    {
        $email = $json->data->buyer->email ?? '';
        if (empty($email)) {
            return $this->failValidationErrors('E-mail do comprador não encontrado');
        }

        $usuarioModel = new UsuarioModel();
        $usuario = $usuarioModel->where('email', $email)->first();

        if ($usuario) {
            $usuarioModel->update($usuario->id, [
                'status_assinatura' => 'cancelled'
            ]);
            log_message('info', 'Webhook Hotmart: Assinatura cancelada/estornada para ' . $email);
        }

        return $this->respond(['status' => 'success', 'message' => 'Cancelamento processado'], 200);
    }
}
