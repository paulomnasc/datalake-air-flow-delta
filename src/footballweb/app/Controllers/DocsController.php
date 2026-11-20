<?php

namespace App\Controllers;

class DocsController extends BaseController
{
    /**
     * Página de Documentação da Arquitetura do Pipeline Cloudbet & DAGs Airflow
     */
    public function index()
    {
        $data = [
            'title' => 'Documentação do Pipeline & DAGs Cloudbet | FootballWeb',
            'currentRoute' => 'docs'
        ];

        return view('header', $data)
             . view('docs/cloudbet_dags_structure', $data)
             . view('footer');
    }
}
