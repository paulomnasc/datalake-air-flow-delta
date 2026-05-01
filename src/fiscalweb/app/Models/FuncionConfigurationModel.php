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

    protected $allowedFields = ['nome', 'modulo_python', 'descricao', 'grupo', 'ordem', 'ativo', 'is_custom', 'owner_user_id'];

    protected $useTimestamps = true;
    protected $createdField  = 'criado_em';
    protected $updatedField  = 'atualizado_em';
    protected $dateFormat    = 'datetime';

    protected $validationRules    = [];
    protected $validationMessages = [];
    protected $skipValidation     = false;

    /**
     * Busca todas as funções ativas, organizadas por grupo
     * NOTA: Retorna apenas funções CORE (is_custom=0)
     */
    public function getAllAtivas()
    {
        return $this->where('ativo', 1)
                    ->where('is_custom', 0)
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

    /**
     * Busca funções disponíveis para um usuário (CORE ativas + CUSTOM do usuário)
     * Usado para popular o select de funções Python
     */
    public function getFuncoesDisponiveisParaUsuario($usuarioId)
    {
        return $this->where('(is_custom = 0 AND ativo = 1) OR (is_custom = 1 AND owner_user_id = ' . (int)$usuarioId . ' AND ativo = 1)', null, false)
                    ->orderBy('is_custom', 'ASC')  // Core primeiro
                    ->orderBy('grupo', 'ASC')
                    ->orderBy('ordem', 'ASC')
                    ->findAll();
    }

    /**
     * Cria uma função custom para um usuário
     */
    public function criarCustomFunction($usuarioId, $nome, $moduloPython, $descricao = null)
    {
        // Garantir unicidade de nome dentro do usuário (evita erro de UNIQUE no deploy)
        $existeNome = $this->where('owner_user_id', $usuarioId)
                           ->where('nome', $nome)
                           ->first();
        if ($existeNome) {
            return ['success' => false, 'message' => 'Você já possui uma função com este nome', 'id' => $existeNome->id];
        }

        // Verificar se já existe custom com mesmo módulo para este usuário
        $existeModulo = $this->where('owner_user_id', $usuarioId)
                             ->where('modulo_python', $moduloPython)
                             ->first();
        
        if ($existeModulo) {
            return ['success' => false, 'message' => 'Você já possui uma função com este módulo Python', 'id' => $existeModulo->id];
        }

        // Inserir nova função custom
        $data = [
            'nome' => $nome,
            'modulo_python' => $moduloPython,
            'descricao' => $descricao,
            'grupo' => 'Custom',
            'ordem' => 999,  // Custom sempre no final
            'ativo' => 1,
            'is_custom' => 1,
            'owner_user_id' => $usuarioId
        ];

        $id = $this->insert($data);
        
        if ($id) {
            log_message('info', "Função custom criada: {$moduloPython} para usuário {$usuarioId}");
            return ['success' => true, 'message' => 'Função criada com sucesso', 'id' => $id];
        }

        return ['success' => false, 'message' => 'Erro ao criar função custom'];
    }
}
