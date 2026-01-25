<?php

namespace App\Controllers;

use CodeIgniter\RESTful\ResourceController;

/**
 * Controller para gerenciar regras de validação customizadas do Medallion.
 * 
 * Permite que usuários criem validações Python que serão injetadas nas DAGs
 * sem expor a implementação interna da dag_factory.
 */
class ValidationRulesController extends ResourceController
{
    protected $format = 'json';
    
    /**
     * Mostra o editor de regras de validação
     */
    public function index()
    {
        $session = session();
        $userBucket = $session->get('user_bucket') ?? 'lab01';
        
        return view('code_editor/validation-rules-editor', [
            'userBucket' => $userBucket
        ]);
    }
    
    /**
     * Lista regras de validação do usuário
     * GET /api/validation-rules
     */
    public function list()
    {
        try {
            $userBucket = $this->request->getGet('userBucket') ?? 'lab01';
            $rulesPath = "s3://{$userBucket}/validation-rules/";
            
            $s3Hook = new \App\Libraries\MinIOHelper();
            $files = $s3Hook->listFiles($rulesPath);
            
            $rules = [];
            foreach ($files as $file) {
                if (str_ends_with($file, '.json')) {
                    $content = $s3Hook->getFileContent($file);
                    $rule = json_decode($content, true);
                    if ($rule) {
                        $rules[] = $rule;
                    }
                }
            }
            
            return $this->respond([
                'success' => true,
                'rules' => $rules,
                'count' => count($rules)
            ]);
            
        } catch (\Exception $e) {
            log_message('error', 'Erro ao listar regras: ' . $e->getMessage());
            return $this->respond([
                'success' => true,
                'rules' => [],
                'count' => 0
            ]);
        }
    }
    
    /**
     * Salva regra de validação
     * POST /api/validation-rule-save
     */
    public function save()
    {
        try {
            $data = $this->request->getJSON(true);
            
            $userBucket = $data['userBucket'] ?? 'lab01';
            $name = $data['name'] ?? '';
            $layer = $data['layer'] ?? '';
            $table = $data['table'] ?? null;
            $description = $data['description'] ?? '';
            $code = $data['code'] ?? '';
            
            // Validações
            if (empty($name) || empty($layer) || empty($code)) {
                return $this->failValidationError('Nome, camada e código são obrigatórios');
            }
            
            if (!in_array($layer, ['bronze', 'silver', 'gold'])) {
                return $this->failValidationError('Camada inválida');
            }
            
            // Sanitizar nome (apenas alfanumérico e underscore)
            $safeName = preg_replace('/[^a-zA-Z0-9_]/', '_', $name);
            
            // Salvar regra como JSON no MinIO
            $rule = [
                'name' => $safeName,
                'layer' => $layer,
                'table' => $table,
                'description' => $description,
                'code' => $code,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            $ruleKey = "validation-rules/{$layer}/{$safeName}.json";
            $s3Path = "s3://{$userBucket}/{$ruleKey}";
            
            $s3Hook = new \App\Libraries\MinIOHelper();
            $s3Hook->putFileContent($s3Path, json_encode($rule, JSON_PRETTY_PRINT));
            
            // Também salvar código Python separado para fácil import
            $pyKey = "validation-rules/{$layer}/{$safeName}.py";
            $pyPath = "s3://{$userBucket}/{$pyKey}";
            $s3Hook->putFileContent($pyPath, $code);
            
            return $this->respond([
                'success' => true,
                'message' => 'Regra salva com sucesso',
                'rule' => $rule,
                's3_path' => $s3Path
            ]);
            
        } catch (\Exception $e) {
            log_message('error', 'Erro ao salvar regra: ' . $e->getMessage());
            return $this->failServerError('Erro ao salvar regra: ' . $e->getMessage());
        }
    }
    
    /**
     * Testa sintaxe Python da regra
     * POST /api/validation-rule-test
     */
    public function test()
    {
        try {
            $data = $this->request->getJSON(true);
            $code = $data['code'] ?? '';
            
            if (empty($code)) {
                return $this->failValidationError('Código vazio');
            }
            
            // Criar arquivo temporário
            $tempFile = tempnam(sys_get_temp_dir(), 'validation_') . '.py';
            file_put_contents($tempFile, $code);
            
            // Testar sintaxe Python
            $output = [];
            $returnCode = 0;
            exec("python3 -m py_compile " . escapeshellarg($tempFile) . " 2>&1", $output, $returnCode);
            
            unlink($tempFile);
            
            if ($returnCode !== 0) {
                return $this->respond([
                    'success' => false,
                    'error' => 'Erro de sintaxe Python: ' . implode("\n", $output)
                ]);
            }
            
            // Verificar se função validate existe
            if (!str_contains($code, 'def validate(')) {
                return $this->respond([
                    'success' => false,
                    'error' => 'Código deve conter função validate(df, **context)'
                ]);
            }
            
            return $this->respond([
                'success' => true,
                'message' => 'Sintaxe válida! Função validate() encontrada.'
            ]);
            
        } catch (\Exception $e) {
            return $this->failServerError('Erro ao testar: ' . $e->getMessage());
        }
    }
    
    /**
     * Deleta regra de validação
     * DELETE /api/validation-rule-delete
     */
    public function delete($id = null)
    {
        try {
            $data = $this->request->getJSON(true);
            
            $userBucket = $data['userBucket'] ?? 'lab01';
            $name = $data['name'] ?? $id ?? '';
            
            if (empty($name)) {
                return $this->failValidationError('Nome da regra é obrigatório');
            }
            
            $s3Hook = new \App\Libraries\MinIOHelper();
            
            // Buscar e deletar a regra em todas as camadas
            $layers = ['bronze', 'silver', 'gold'];
            $deleted = false;
            
            foreach ($layers as $layer) {
                $jsonPath = "s3://{$userBucket}/validation-rules/{$layer}/{$name}.json";
                $pyPath = "s3://{$userBucket}/validation-rules/{$layer}/{$name}.py";
                
                try {
                    $s3Hook->deleteFile($jsonPath);
                    $s3Hook->deleteFile($pyPath);
                    $deleted = true;
                } catch (\Exception $e) {
                    // Arquivo pode não existir nesta camada, continuar
                }
            }
            
            if (!$deleted) {
                return $this->failNotFound('Regra não encontrada');
            }
            
            return $this->respond([
                'success' => true,
                'message' => 'Regra deletada com sucesso'
            ]);
            
        } catch (\Exception $e) {
            log_message('error', 'Erro ao deletar regra: ' . $e->getMessage());
            return $this->failServerError('Erro ao deletar: ' . $e->getMessage());
        }
    }
    
    /**
     * Sincroniza validador do Git para o Airflow
     * POST /api/validation-deploy
     */
    public function deploy()
    {
        try {
            $data = $this->request->getJSON(true);
            $filename = $data['filename'] ?? null;
            $content = $data['content'] ?? null;
            
            if (!$filename) {
                return $this->failValidationError('Nome do arquivo é obrigatório');
            }
            
            // Sanitizar nome de arquivo
            $filename = preg_replace('/[^a-zA-Z0-9_.-]/', '', $filename);
            
            if (empty($filename)) {
                return $this->failValidationError('Nome de arquivo inválido');
            }
            
            // Se conteúdo foi fornecido, salvar o arquivo na pasta de validadores do Airflow
            if (empty($content)) {
                return $this->failValidationError('Conteúdo do arquivo é obrigatório');
            }
            
            // Caminho correto para validadores custom (dentro do container Docker)
            // /root/datalake-air-flow-delta está montado como /datalake-root no container
            $validadoresPath = '/datalake-root/src/dags/lib/validadores';

            
            // Verificar se diretório existe
            if (!is_dir($validadoresPath)) {
                log_message('error', "Diretório de validadores não existe: $validadoresPath");
                return $this->failServerError("Diretório de validadores não encontrado: $validadoresPath");
            }
            
            $filePath = $validadoresPath . '/' . $filename;
            log_message('info', "Salvando validador em: $filePath");

            // Se existir e estiver protegido, remover antes de sobrescrever
            if (file_exists($filePath) && !is_writable($filePath)) {
                log_message('warning', "Arquivo existente sem permissão de escrita, removendo: $filePath");
                if (!@unlink($filePath)) {
                    log_message('error', "Não foi possível remover arquivo protegido: $filePath");
                    return $this->failServerError('Não foi possível substituir o arquivo (permissão negada)');
                }
            }
            
            // Salvar arquivo Python
            if (file_put_contents($filePath, $content) === false) {
                log_message('error', "Falha ao salvar arquivo: $filePath");
                return $this->failServerError('Não foi possível salvar o arquivo no servidor');
            }
            
            // Definir permissões
            @chmod($filePath, 0644);
            @chown($filePath, 'www-data');
            @chgrp($filePath, 'www-data');
            
            log_message('info', "✅ Validador salvo com sucesso: $filePath");
            
            return $this->respond([
                'success' => true,
                'message' => "✅ {$filename} salvo com sucesso!",
                'file_path' => $filePath,
                'info' => 'Arquivo pronto para uso no Airflow'
            ]);
            
        } catch (\Exception $e) {
            log_message('error', 'Erro ao fazer deploy: ' . $e->getMessage());
            return $this->failServerError('Erro ao salvar validador: ' . $e->getMessage());
        }
    }
}
