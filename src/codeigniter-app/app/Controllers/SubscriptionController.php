<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;
use App\Models\UsuarioModel;
use App\Helpers\SubscriptionHelper;
use App\Helpers\AirflowHelper;

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
            'mensagem_bloqueio' => $_SESSION['subscription_blocked_message'] ?? null
        ];

        // Formata a data de vencimento para exibição
        if ($data['data_vencimento']) {
            try {
                $dataVenc = new \DateTime($data['data_vencimento']);
                $data['data_vencimento_formatada'] = $dataVenc->format('d/m/Y');
            } catch (\Exception $e) {
                $data['data_vencimento_formatada'] = 'Data inválida';
            }
        } else {
            $data['data_vencimento_formatada'] = 'Não definida';
        }

        // Calcula o próximo vencimento se renovar agora
        $data['proximo_vencimento'] = SubscriptionHelper::calcularRenovacao($usuario->data_vencimento_assinatura);
        try {
            $proximaData = new \DateTime($data['proximo_vencimento']);
            $data['proximo_vencimento_formatado'] = $proximaData->format('d/m/Y');
        } catch (\Exception $e) {
            $data['proximo_vencimento_formatado'] = 'Data inválida';
        }

        // Limpa mensagem de bloqueio da sessão
        unset($_SESSION['subscription_blocked_message']);

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

        $data = [
            'usuario_nome' => $usuario->nome ?? 'Usuário',
            'usuario_email' => $usuario->email ?? '',
            'valor' => 7.00, // USD 7,00 (você pode converter para BRL se necessário)
            'moeda' => 'USD'
        ];

        return view('subscription/pix_payment', $data);
    }
}
