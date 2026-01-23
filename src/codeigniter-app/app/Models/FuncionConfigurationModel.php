<?php

namespace App\Models;

use CodeIgniter\Model;

class FuncionConfigurationModel extends Model
{
    protected $table      = 'funcion_configuration';
    protected $primaryKey = 'id';

    protected $useAutoIncrement = true;

    protected $returnType       = 'object';
    protected $useSoftDeletes   = false;

    protected $allowedFields = ['nome', 'modulo_python', 'descricao', 'grupo', 'ordem', 'ativo'];

    protected $useTimestamps = true;
    protected $createdField  = 'criado_em';
    protected $updatedField  = 'atualizado_em';
    protected $dateFormat    = 'datetime';

    protected $validationRules    = [];
    protected $validationMessages = [];
    protected $skipValidation     = false;

    /**
     * Busca todas as funções ativas, organizadas por grupo
     */
    public function getAllAtivas()
    {
        return $this->where('ativo', 1)
                    ->orderBy('grupo', 'ASC')
                    ->orderBy('ordem', 'ASC')
                    ->findAll();
    }

    /**
     * Busca as funções disponíveis para um usuário
     */
    public function getFuncoesParaUsuario($usuarioId)
    {
        return $this->select('funcion_configuration.*')
                    ->join('user_funcion_configuration', 'funcion_configuration.id = user_funcion_configuration.funcion_configuration_id')
                    ->where('user_funcion_configuration.usuario_id', $usuarioId)
                    ->where('funcion_configuration.ativo', 1)
                    ->orderBy('funcion_configuration.grupo', 'ASC')
                    ->orderBy('funcion_configuration.ordem', 'ASC')
                    ->findAll();
    }

    /**
     * Busca uma função específica pelo módulo Python
     */
    public function getPorModuloPython($moduloPython)
    {
        return $this->where('modulo_python', $moduloPython)
                    ->where('ativo', 1)
                    ->first();
    }

    /**
     * Busca uma função pelo ID
     */
    public function getPorId($id)
    {
        return $this->where('id', $id)
                    ->first();
    }

    /**
     * Agrupa as funções por grupo para exibição em optgroup
     */
    public function getAgrupadasPorGrupo()
    {
        $funcoes = $this->getAllAtivas();
        $agrupadas = [];
        
        foreach ($funcoes as $funcao) {
            $grupo = $funcao->grupo ?? 'Sem Grupo';
            if (!isset($agrupadas[$grupo])) {
                $agrupadas[$grupo] = [];
            }
            $agrupadas[$grupo][] = $funcao;
        }
        
        return $agrupadas;
    }

    /**
     * Agrupa as funções de um usuário por grupo
     */
    public function getAgrupadasPorGrupoParaUsuario($usuarioId)
    {
        $funcoes = $this->getFuncoesParaUsuario($usuarioId);
        $agrupadas = [];
        
        foreach ($funcoes as $funcao) {
            $grupo = $funcao->grupo ?? 'Sem Grupo';
            if (!isset($agrupadas[$grupo])) {
                $agrupadas[$grupo] = [];
            }
            $agrupadas[$grupo][] = $funcao;
        }
        
        return $agrupadas;
    }
}
