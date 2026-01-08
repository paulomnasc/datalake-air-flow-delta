<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use App\Models\UsuarioModel;
use App\Helpers\SubscriptionHelper;
use App\Helpers\AirflowHelper;

/**
 * SubscriptionFilter
 * 
 * Filtro para verificar o status da assinatura do usuário em cada requisição
 * Carrega informações de assinatura na sessão e redireciona se necessário
 */
class SubscriptionFilter implements FilterInterface
{
    /**
     * Executa antes da requisição
     * Verifica status da assinatura e carrega dados na sessão
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        // Tenta usar a sessão do CodeIgniter primeiro
        $session = session();
        $usuarioLogado = $session->get('usuario_logado');
        $userId = $session->get('id_usuario_logado');
        
        // Fallback para $_SESSION direta
        if (!$usuarioLogado) {
            $usuarioLogado = $_SESSION['usuario_logado'] ?? null;
            $userId = $_SESSION['id_usuario_logado'] ?? null;
        }
        
        // Verifica se o usuário está logado
        if (!$usuarioLogado || $usuarioLogado != 1) {
            return;
        }

        // Obtém ID do usuário
        if (!$userId) {
            return;
        }

        // Busca dados do usuário no banco
        $usuarioModel = new UsuarioModel();
        $usuario = $usuarioModel->find($userId);

        if (!$usuario) {
            return;
        }

        // Atualiza o status da assinatura se necessário
        $statusAtual = $usuario->status_assinatura ?? 'trial';
        $dataVencimento = $usuario->data_vencimento_assinatura ?? null;
        $novoStatus = SubscriptionHelper::atualizarStatus($dataVencimento, $statusAtual);

        // Se o status mudou, atualiza no banco
        if ($novoStatus !== $statusAtual) {
            $usuarioModel->update($userId, ['status_assinatura' => $novoStatus]);
            $usuario->status_assinatura = $novoStatus;
            
            // Gerenciar status do usuário no Airflow baseado na assinatura
            if ($novoStatus === 'expired' || $novoStatus === 'cancelled') {
                // Desativar usuário no Airflow se assinatura expirou ou foi cancelada
                AirflowHelper::setUserActiveStatus($userId, $usuario->email ?? '', false);
                log_message('info', "[SUBSCRIPTION] Usuário {$userId} desativado no Airflow - assinatura {$novoStatus}");
            } elseif (($statusAtual === 'expired' || $statusAtual === 'cancelled') && 
                      ($novoStatus === 'active' || $novoStatus === 'trial')) {
                // Reativar usuário no Airflow se assinatura foi renovada
                AirflowHelper::setUserActiveStatus($userId, $usuario->email ?? '', true);
                log_message('info', "[SUBSCRIPTION] Usuário {$userId} reativado no Airflow - assinatura {$novoStatus}");
            }
        }

        // Carrega informações de assinatura na sessão
        $_SESSION['subscription_status'] = $usuario->status_assinatura ?? 'trial';
        $_SESSION['subscription_expiry_date'] = $usuario->data_vencimento_assinatura ?? null;
        $_SESSION['subscription_last_payment'] = $usuario->data_ultimo_pagamento ?? null;
        $_SESSION['subscription_trial_start'] = $usuario->data_inicio_trial ?? null;

        // Calcula dias restantes
        $diasRestantes = SubscriptionHelper::calcularDiasRestantes($dataVencimento);
        $_SESSION['subscription_days_remaining'] = $diasRestantes;

        // Verifica se deve mostrar aviso
        $mostrarAviso = SubscriptionHelper::deveMostrarAviso($dataVencimento, $usuario->status_assinatura ?? 'trial');
        $_SESSION['subscription_show_warning'] = $mostrarAviso;
        
        // Define se os serviços (menu SERVIÇOS) devem estar bloqueados
        $statusBloqueado = in_array($usuario->status_assinatura ?? 'trial', ['expired', 'cancelled']);
        $_SESSION['subscription_services_blocked'] = $statusBloqueado;

        // Verifica se pode acessar
        $acessoInfo = SubscriptionHelper::podeAcessarPlataforma(
            $usuario->status_assinatura ?? 'trial',
            $dataVencimento
        );

        // Se não pode acessar, redireciona para página de renovação
        // Exceto se já estiver na página de renovação ou logout
        $uri = $request->getUri()->getPath();
        $rotasPermitidas = [
            '/subscription/renew',
            '/subscription/status',
            '/logout',
            '/Usuario/logOut',
            '/loginUsuario',
            '/sigInUsuario'
        ];

        if (!$acessoInfo['pode_acessar'] && !in_array($uri, $rotasPermitidas)) {
            // Define mensagem de sessão
            $_SESSION['subscription_blocked_message'] = $acessoInfo['mensagem'];
            
            // Redireciona para página de renovação
            return redirect()->to('/subscription/renew');
        }
    }

    /**
     * Executa depois da requisição
     */
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Não precisa fazer nada depois
    }
}
