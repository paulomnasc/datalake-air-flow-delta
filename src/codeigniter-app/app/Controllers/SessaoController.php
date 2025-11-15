<?php

namespace App\Controllers;

use App\Controllers\BaseController;

//use CodeIgniter\HTTP\ResponseInterface;

class Sessao extends BaseController
{
    public function index()
    {
        //
    }

    public function setarSessao($variavel, $valor) {
        
        $_SESSION[$variavel] = $valor;
        
    }
}
