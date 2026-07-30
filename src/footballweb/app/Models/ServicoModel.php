<?php

namespace App\Models;

use CodeIgniter\Model;

class ServicoModel extends Model
{
    protected $table            = 'servico';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'object';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = ['numero_item', 'descricao', 'entregaveis', 'remuneracao', 'base_horas_mes', 'base_horas_complexidade', 'sla_dias', 'estim_max_ano', 'saldo_horas', 'id_atividade_macro'];

    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;

    // Validation
    protected $validationRules = [
        'numero_item'             => 'required|max_length[100]|is_unique[servico.numero_item,id,{id}]',
        'descricao'               => 'required|max_length[100]|is_unique[servico.descricao,id,{id}]',
        'entregaveis'             => 'required|max_length[255]',
        'remuneracao'             => 'required|numeric',
        'base_horas_mes'          => 'required|numeric',
        'base_horas_complexidade' => 'required|numeric',
        'sla_dias'                => 'required|integer',
        'estim_max_ano'           => 'required|numeric',
        'saldo_horas'             => 'required|numeric',
        'id_atividade_macro'      => 'required|integer'
    ];

    protected $validationMessages = [
        'numero_item' => [
            'required'  => 'O campo Nº Item é obrigatório.',
            'is_unique' => 'O Nº Item informado já está cadastrado para outro serviço.'
        ],
        'descricao' => [
            'required'  => 'O campo Descrição é obrigatório.',
            'is_unique' => 'A Descrição informada já está cadastrada para outra atividade.'
        ],
        'entregaveis' => [
            'required'  => 'O campo Entregáveis é obrigatório.'
        ]
    ];

    public function listToCombo()
    {
        $data = $this->select('id, descricao')->findAll();
        return $data;
    }
}
