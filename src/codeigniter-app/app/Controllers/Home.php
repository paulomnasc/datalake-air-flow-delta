<?php

namespace App\Controllers;

class Home extends BaseController
{
    public function index(): string
    {
        // invoke  \Viesw\welcome_message.php
        
        //return view('welcome_message');

        //Temporáriamente colocarei o layout com a toggle bar para testar, depois uma home mais elaborada
        // deve ser criada com base em welcome_message para ficar mais agradável e o layout com menu retrátil
        // será chamado por ela
        //
        
        define('CI_ENVIRONMENT', 'development');

        helper(['array','form','html']);

        return $this->loadView('menu_smart',[]);

        

    
    }

}
