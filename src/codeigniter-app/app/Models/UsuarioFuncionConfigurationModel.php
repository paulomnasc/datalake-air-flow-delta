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
     */
    public function sincronizarComPadrao($usuarioId)
    {
        try {
            $funcionModel = new FuncionConfigurationModel();
            
            // Busca todas as funções ativas
            $funcionsAtivas = $funcionModel->getAllAtivas();
            
            if (empty($funcionsAtivas)) {
                log_message('warning', "Nenhuma função ativa encontrada para sincronizar com usuário {$usuarioId}");
                return false;
            }
            
            // Limpa funções antigas do usuário
            $this->limparFuncoesDoUsuario($usuarioId);
            
            // Insere todas as funções ativas para o usuário
            $db = \Config\Database::connect();
            $db->transStart();
            
            try {
                foreach ($funcionsAtivas as $funcao) {
                    $this->insert([
                        'usuario_id' => $usuarioId,
                        'funcion_configuration_id' => $funcao->id
                    ]);
                }
                
                $db->transCommit();
                log_message('info', "Funções sincronizadas com sucesso para usuário {$usuarioId}. Total: " . count($funcionsAtivas));
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
     * Retorna as funções de um usuário formatadas para o select (agrupadas por grupo)
     */
    public function getFuncoesFormatadas($usuarioId)
    {
        $funcionModel = new FuncionConfigurationModel();
        return $funcionModel->getAgrupadasPorGrupoParaUsuario($usuarioId);
    }
}
