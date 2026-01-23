<?php

namespace App\Controllers;

use App\Models\FuncionConfigurationModel;
use App\Models\UsuarioFuncionConfigurationModel;
use App\Helpers\SessionHelper;

class ValidationController extends BaseController
{
    // Desabilitar CSRF para requisições AJAX JSON (será validado em production se necessário)
    protected $csrfProtection = 'session';
    /**
     * Deploy de uma função custom Python criada pelo usuário
     * 
     * Recebe via POST:
     * - module_path: lib.validadores.meu_validador.MeuValidador
     * - nome (opcional): Nome amigável da função
     * - descricao (opcional): Descrição da função
     * 
     * Retorna JSON:
     * - success: true/false
     * - message: mensagem de sucesso/erro
     * - function_id: ID da função criada (se sucesso)
     */
    public function deployCustom()
    {
        // Validar método
        if (strtoupper($this->request->getMethod()) !== 'POST') {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Método inválido'
            ])->setStatusCode(400);
        }

        // Obter usuário logado
        $usuarioId = SessionHelper::getUserId();
        if (!$usuarioId) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Usuário não autenticado'
            ])->setStatusCode(401);
        }

        // Obter dados do JSON body
        $json = $this->request->getJSON();
        if (!$json) {
            // Fallback para POST form data
            $modulePath = $this->request->getPost('module_path');
            $nome = $this->request->getPost('nome');
            $descricao = $this->request->getPost('descricao');
        } else {
            $modulePath = $json->module_path ?? null;
            $nome = $json->nome ?? null;
            $descricao = $json->descricao ?? null;
        }

        // Validar module_path
        if (empty($modulePath)) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Módulo Python não informado'
            ])->setStatusCode(400);
        }

        // Validar formato do módulo (deve ser lib.validadores.*.Classe)
        if (!preg_match('/^lib\.validadores\.[a-zA-Z0-9_]+\.[A-Z][A-Za-z0-9_]*$/', $modulePath)) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Formato inválido. Use: lib.validadores.nome_arquivo.NomeClasse'
            ])->setStatusCode(400);
        }

        try {
            // Extrair nome da classe se não foi fornecido
            if (empty($nome)) {
                $parts = explode('.', $modulePath);
                $nome = end($parts); // Última parte é o nome da classe
            }

            // Se não tem descrição, gerar uma padrão
            if (empty($descricao)) {
                $descricao = "Função customizada: {$nome}";
            }

            // Criar função custom
            $funcionModel = new FuncionConfigurationModel();
            $result = $funcionModel->criarCustomFunction($usuarioId, $nome, $modulePath, $descricao);

            if (!$result['success']) {
                return $this->response->setJSON($result)->setStatusCode(400);
            }

            // Associar função ao usuário
            $usuarioFuncionModel = new UsuarioFuncionConfigurationModel();
            $usuarioFuncionModel->associarCustomFunction($usuarioId, $result['id']);

            // Log de auditoria
            log_message('info', "Função custom criada: {$modulePath} (ID: {$result['id']}) por usuário {$usuarioId}");

            return $this->response->setJSON([
                'success' => true,
                'message' => 'Função custom criada com sucesso! Recarregue a página para vê-la no select.',
                'function_id' => $result['id'],
                'nome' => $nome,
                'module_path' => $modulePath
            ]);

        } catch (\Exception $e) {
            log_message('error', "Erro ao criar função custom: " . $e->getMessage());
            
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Erro ao criar função: ' . $e->getMessage()
            ])->setStatusCode(500);
        }
    }

    /**
     * Lista funções custom do usuário logado
     */
    public function listCustom()
    {
        $usuarioId = SessionHelper::getUserId();
        if (!$usuarioId) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Usuário não autenticado'
            ])->setStatusCode(401);
        }

        try {
            $funcionModel = new FuncionConfigurationModel();
            $customs = $funcionModel->where('is_custom', 1)
                                    ->where('owner_user_id', $usuarioId)
                                    ->orderBy('criado_em', 'DESC')
                                    ->findAll();

            return $this->response->setJSON([
                'success' => true,
                'customs' => $customs,
                'total' => count($customs)
            ]);

        } catch (\Exception $e) {
            log_message('error', "Erro ao listar customs: " . $e->getMessage());
            
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Erro ao listar funções custom'
            ])->setStatusCode(500);
        }
    }

    /**
     * Desativa uma função custom
     */
    public function deactivateCustom($funcionId)
    {
        $usuarioId = SessionHelper::getUserId();
        if (!$usuarioId) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Usuário não autenticado'
            ])->setStatusCode(401);
        }

        try {
            $funcionModel = new FuncionConfigurationModel();
            
            // Verificar se a função existe e pertence ao usuário
            $funcao = $funcionModel->where('id', $funcionId)
                                   ->where('is_custom', 1)
                                   ->where('owner_user_id', $usuarioId)
                                   ->first();

            if (!$funcao) {
                return $this->response->setJSON([
                    'success' => false,
                    'message' => 'Função não encontrada ou você não tem permissão'
                ])->setStatusCode(404);
            }

            // Desativar
            $funcionModel->update($funcionId, ['ativo' => 0]);

            log_message('info', "Função custom desativada: ID {$funcionId} por usuário {$usuarioId}");

            return $this->response->setJSON([
                'success' => true,
                'message' => 'Função desativada com sucesso'
            ]);

        } catch (\Exception $e) {
            log_message('error', "Erro ao desativar custom: " . $e->getMessage());
            
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Erro ao desativar função'
            ])->setStatusCode(500);
        }
    }

    /**
     * Deleta uma função custom
     */
    public function deleteCustom($funcionId)
    {
        $usuarioId = SessionHelper::getUserId();
        if (!$usuarioId) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Usuário não autenticado'
            ])->setStatusCode(401);
        }

        try {
            $funcionModel = new FuncionConfigurationModel();
            
            // Verificar se a função existe e pertence ao usuário
            $funcao = $funcionModel->where('id', $funcionId)
                                   ->where('is_custom', 1)
                                   ->where('owner_user_id', $usuarioId)
                                   ->first();

            if (!$funcao) {
                return $this->response->setJSON([
                    'success' => false,
                    'message' => 'Função não encontrada ou você não tem permissão'
                ])->setStatusCode(404);
            }

            // Deletar (CASCADE vai remover de user_funcion_configuration)
            $funcionModel->delete($funcionId);

            log_message('info', "Função custom deletada: ID {$funcionId} ({$funcao->modulo_python}) por usuário {$usuarioId}");

            return $this->response->setJSON([
                'success' => true,
                'message' => 'Função deletada com sucesso'
            ]);

        } catch (\Exception $e) {
            log_message('error', "Erro ao deletar custom: " . $e->getMessage());
            
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Erro ao deletar função'
            ])->setStatusCode(500);
        }
    }
}
