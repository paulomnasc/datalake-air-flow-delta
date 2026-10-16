<?php

namespace App\Controllers;

use App\Controllers\BaseController;

class TerminalStakeController extends BaseController
{
    /**
     * Interface do Terminal Remoto Isolado (Stake.com / Casas de Apostas)
     */
    public function index()
    {
        $data = [
            'title'      => 'Terminal Remoto Seguro | Stake.com',
            'browserUrl' => '/remote-browser/',
        ];

        return $this->loadView('terminal/stake', $data);
    }
}
