<?php

namespace App\Models;

use CodeIgniter\Model;

class UsuarioFuncionConfigurationModel extends Model
{
    protected $table      = 'user_funcion_configuration';
    protected $primaryKey = 'id';

    protected $useAutoIncrement = true;

    protected $returnType       = 'object';
    protected $useSoftDeletes   = false;

    protected $allowedFields = ['usuario_id', 'funcion_configuration_id'];

    protected $useTimestamps = true;
    protected $createdField  = 'criado_em';
    protected $updatedField  = '';
    protected $dateFormat    = 'datetime';

    protected $validationRules    = [];
    protected $validationMessages = [];
    protected $skipValidation     = false;

    /**
     * Verifica se um usuário tem uma função configurada
     */
    public function temFuncao($usuarioId, $funcionConfigurationId)
    {
        return $this->where('usuario_id', $usuarioId)
                    ->where('funcion_configuration_id', $funcionConfigurationId)
                    ->first() !== null;
    }

    /**
     * Conta quantas funções um usuário tem configuradas
     */
    public function contarFuncoesDoUsuario($usuarioId)
    {
        return $this->where('usuario_id', $usuarioId)->countAllResults();
    }

    /**
     * Retorna todas as funções configuradas para um usuário
     */
    public function getFuncoesDoUsuario($usuarioId)
    {
        return $this->where('usuario_id', $usuarioId)->findAll();
    }

    /**
     * Remove todas as funções de um usuário (para sincronização)
     */
    public function limparFuncoesDoUsuario($usuarioId)
    {
        return $this->where('usuario_id', $usuarioId)->delete();
    }

    /**
     * Adiciona uma função para um usuário (ignora se já existe)
     */
    public function adicionarFuncaoAoUsuario($usuarioId, $funcionConfigurationId)
    {
        // Verifica se já existe
        if ($this->temFuncao($usuarioId, $funcionConfigurationId)) {
            return true; // Já existe, consideramos sucesso
        }

        return $this->insert([
            'usuario_id' => $usuarioId,
            'funcion_configuration_id' => $funcionConfigurationId
        ]);
    }

    /**
     * Sincroniza as funções de um usuário com todas as funções padrão ativas
     * Esta função é chamada quando um usuário é criado ou no login
     * 
     * IMPORTANTE: NÃO apaga funções custom (is_custom=1), apenas garante que
     * o usuário tenha todas as funções CORE ativas
     */
    public function sincronizarComPadrao($usuarioId)
    {
        try {
            $funcionModel = new FuncionConfigurationModel();
            
            // Busca todas as funções CORE ativas (is_custom=0)
            $funcionsAtivas = $funcionModel->getAllAtivas();
            
            if (empty($funcionsAtivas)) {
                log_message('warning', "Nenhuma função CORE ativa encontrada para sincronizar com usuário {$usuarioId}");
                return false;
            }
            
            $db = \Config\Database::connect();
            $db->transStart();
            
            try {
                // Para cada função CORE ativa, garantir que usuário tenha acesso
                foreach ($funcionsAtivas as $funcao) {
                    // Verificar se já existe
                    $existe = $this->where('usuario_id', $usuarioId)
                                   ->where('funcion_configuration_id', $funcao->id)
                                   ->first();
                    
                    // Se não existe, inserir
                    if (!$existe) {
                        $this->insert([
                            'usuario_id' => $usuarioId,
                            'funcion_configuration_id' => $funcao->id
                        ]);
                    }
                }
                
                $db->transCommit();
                log_message('info', "Funções CORE sincronizadas com sucesso para usuário {$usuarioId}. Total CORE: " . count($funcionsAtivas));
                return true;
            } catch (\Exception $e) {
                $db->transRollback();
                log_message('error', "Erro ao sincronizar funções para usuário {$usuarioId}: " . $e->getMessage());
                return false;
            }
        } catch (\Exception $e) {
            log_message('error', "Erro ao sincronizar funções para usuário {$usuarioId}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Associa uma função custom ao seu criador
     * Chamado automaticamente quando função custom é criada
     */
    public function associarCustomFunction($usuarioId, $funcionId)
    {
        // Verificar se já existe
        $existe = $this->where('usuario_id', $usuarioId)
                       ->where('funcion_configuration_id', $funcionId)
                       ->first();
        
        if ($existe) {
            return true; // Já associada
        }

        return $this->insert([
            'usuario_id' => $usuarioId,
            'funcion_configuration_id' => $funcionId
        ]);
    }

    /**
     * Retorna as funções de um usuário formatadas para o select (agrupadas por grupo)
     * Inclui CORE ativas + CUSTOM do usuário
     */
    public function getFuncoesFormatadas($usuarioId)
    {
        $funcionModel = new FuncionConfigurationModel();
        $funcoes = $funcionModel->getFuncoesDisponiveisParaUsuario($usuarioId);
        
        $agrupadas = [];
        foreach ($funcoes as $funcao) {
            // Custom sempre vai para grupo 'Custom'
            $grupo = ($funcao->is_custom == 1) ? '⭐ Custom (Minhas Funções)' : ($funcao->grupo ?? 'Sem Grupo');
            
            if (!isset($agrupadas[$grupo])) {
                $agrupadas[$grupo] = [];
            }
            $agrupadas[$grupo][] = $funcao;
        }
        
        return $agrupadas;
    }
}
