<?php

namespace App\Controllers;

use CodeIgniter\HTTP\RedirectResponse;

class Home extends BaseController
{
    public function index(): string|RedirectResponse
    {
        // Se usuário estiver logado, redireciona para o dashboard
        if (isset($_SESSION['usuario_logado']) && $_SESSION['usuario_logado'] == 1) {
            return redirect()->to(base_url('dashboard'));
        }
        
        // invoke  \Viesw\welcome_message.php
        
        //return view('welcome_message');

        //Temporáriamente colocarei o layout com a toggle bar para testar, depois uma home mais elaborada
        // deve ser criada com base em welcome_message para ficar mais agradável e o layout com menu retrátil
        // será chamado por ela
        //
        
        define('CI_ENVIRONMENT', 'development');

        helper(['array','form','html']);

        $osModel = new \App\Models\OrdemServicoModel();
        $list = $osModel->findAll();
        return $this->loadView('menu_smart', ['list' => $list]);

        

    
    }

    public function debugFunctionalities()
    {
        // Método de debug para verificar funcionalidades do usuário
        $sessionData = [
            'id_usuario_logado' => $_SESSION['id_usuario_logado'] ?? null,
            'nome_usuario_logado' => $_SESSION['nome_usuario_logado'] ?? null,
            'perfil_usuario_logado' => $_SESSION['perfil_usuario_logado'] ?? null,
        ];
        
        $functionalities = $this->getUserFunctionalities();
        
        return $this->response->setJSON([
            'session' => $sessionData,
            'functionalities' => $functionalities
        ]);
    }

}
