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
            
            // Caminho do repositório Git e script de sync
            // Dentro do container, o diretório raiz está mapeado como /datalake-root
            $repoPath = '/datalake-root';
            $syncScript = $repoPath . '/sync_validators_to_airflow.sh';
            
            log_message('info', "Script path: $syncScript, exists: " . (file_exists($syncScript) ? 'yes' : 'no'));
            
            if (!file_exists($syncScript)) {
                log_message('error', "Script de sincronização não encontrado: $syncScript");
                return $this->failServerError('Script de sincronização não disponível em: ' . $syncScript);
            }
            
            // Se conteúdo foi fornecido, salvar o arquivo no repositório local primeiro
            if (!empty($content)) {
                $localFilePath = $repoPath . '/' . $filename;
                log_message('info', "Salvando conteúdo em: $localFilePath");
                
                if (file_put_contents($localFilePath, $content) === false) {
                    log_message('error', "Falha ao salvar arquivo: $localFilePath");
                    return $this->failServerError('Não foi possível salvar o arquivo localmente');
                }
                
                log_message('info', "Arquivo salvo com sucesso: $localFilePath");
            }
            
            // Executar script de sincronização
            $command = "cd " . escapeshellarg($repoPath) . " && " .
                       "bash " . escapeshellarg($syncScript) . " " . 
                       escapeshellarg($filename);
            
            log_message('info', "Executando deploy: $command");
            
            $output = [];
            $returnCode = 0;
            exec($command . " 2>&1", $output, $returnCode);
            
            $outputText = implode("\n", $output);
            
            if ($returnCode !== 0) {
                log_message('error', "Deploy falhou com código $returnCode: $outputText");
                return $this->respond([
                    'success' => false,
                    'error' => 'Falha ao sincronizar',
                    'details' => $outputText,
                    'return_code' => $returnCode
                ], 500);
            }
            
            log_message('info', "Deploy concluído com sucesso para $filename");
            
            return $this->respond([
                'success' => true,
                'message' => "✅ $filename sincronizado para Airflow!",
                'output' => $outputText,
                'next_step' => 'Aguarde 30 segundos e procure a DAG no Airflow Web UI'
            ]);
            
        } catch (\Exception $e) {
            log_message('error', 'Erro ao fazer deploy: ' . $e->getMessage());
            return $this->failServerError('Erro ao sincronizar: ' . $e->getMessage());
        }
    }
}
