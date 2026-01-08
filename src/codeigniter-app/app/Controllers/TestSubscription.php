<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\UsuarioModel;
use App\Helpers\SubscriptionHelper;

class TestSubscription extends BaseController
{
    public function index()
    {
        $userId = $_SESSION['id_usuario_logado'] ?? null;
        
        $debug = [
            'usuario_logado' => $_SESSION['usuario_logado'] ?? 'NAO_DEFINIDO',
            'id_usuario' => $userId,
            'sessao_completa' => $_SESSION,
        ];

        if ($userId) {
            $usuarioModel = new UsuarioModel();
            $usuario = $usuarioModel->find($userId);
            
            if ($usuario) {
                $debug['usuario_banco'] = [
                    'id' => $usuario->id,
                    'nome' => $usuario->nome,
                    'email' => $usuario->email,
                    'status_assinatura' => $usuario->status_assinatura ?? 'NAO_DEFINIDO',
                    'data_vencimento' => $usuario->data_vencimento_assinatura ?? 'NAO_DEFINIDO',
                    'data_inicio_trial' => $usuario->data_inicio_trial ?? 'NAO_DEFINIDO',
                ];
                
                $diasRestantes = SubscriptionHelper::calcularDiasRestantes($usuario->data_vencimento_assinatura);
                $debug['calculo_dias'] = $diasRestantes;
                
                $deveMostrar = SubscriptionHelper::deveMostrarAviso(
                    $usuario->data_vencimento_assinatura,
                    $usuario->status_assinatura ?? 'trial'
                );
                $debug['deve_mostrar_aviso'] = $deveMostrar ? 'SIM' : 'NAO';
            } else {
                $debug['erro'] = 'Usuario nao encontrado no banco';
            }
        }
        
        header('Content-Type: application/json');
        echo json_encode($debug, JSON_PRETTY_PRINT);
        exit;
    }
}
