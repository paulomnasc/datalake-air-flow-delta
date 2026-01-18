<?php

namespace App\Controllers;

use App\Controllers\QueryBuilderController;

/**
 * CodeEditorController
 * 
 * Interface avançada com Monaco Editor para execução de queries SQL
 * Herda toda a lógica funcional do QueryBuilderController (DuckDB, segurança, etc)
 * 
 * Git é gerenciado pelo frontend usando isomorphic-git (sem backend necessário)
 * 
 * Rotas:
 * - GET  /code-editor              → Exibe interface web com Monaco Editor
 * - POST /code-editor/execute      → Executa query (herdado de QueryBuilderController)
 * - POST /code-editor/tables       → Lista tabelas (herdado)
 * - POST /code-editor/schema       → Obtém schema (herdado)
 * - POST /code-editor/files        → Lista arquivos Parquet (herdado)
 */
class CodeEditorController extends QueryBuilderController
{
    /**
     * Exibe interface web UNIFICADA: SQL + Validation Rules em uma única view com abas
     * Consolidação de code-editor e validation-rules-editor em unified-code-editor.php
     */
    public function unified()
    {
        // Verifica saúde da API DuckDB
        $duckdbStatus = \App\Helpers\DuckDBHelper::healthCheck();
        
        // Obtém bucket do usuário logado
        $userBucket = \App\Helpers\SessionHelper::getUserBucket();
        // Aponta para o bucket raiz (sem camada específica)
        $userS3Path = \App\Helpers\SessionHelper::getUserS3Path('');
        
        // Lista arquivos Parquet do bucket do usuário (todas as camadas)
        $parquetFiles = [];
        if ($userBucket) {
            $parquetFiles = \App\Helpers\DuckDBHelper::listParquetFiles($userS3Path);
        }
        
        return view('code_editor/unified-code-editor', [
            'duckdbStatus' => $duckdbStatus,
            'parquetFiles' => $parquetFiles,
            'userBucket' => $userBucket,
            'userS3Path' => $userS3Path
        ]);
    }

    /**
     * Exibe interface web para Code Editor com Monaco (LEGACY - redirecionado para unified)
     * Mantido para compatibilidade com bookmarks antigos
     * Usa a mesma lógica de preparação de dados do QueryBuilder
     */
    public function index()
    {
        // Redireciona para versão unificada
        return redirect()->to('/code-editor');
    }
    
    /**
     * Sobrescreve listParquetFiles para retornar apenas camadas: bronze, silver, gold, delta
     * (não inclui raw como no QueryBuilder padrão)
     */
    public function listParquetFiles()
    {
        $json = $this->request->getJSON(true);
        $path = $json['path'] ?? null;
        
        // Camadas permitidas para Code Editor (não inclui raw)
        $layers = ['bronze', 'silver', 'gold', 'delta'];
        
        $allFiles = [];
        
        // Se path não fornecido, busca de todas as camadas permitidas
        if (empty($path)) {
            foreach ($layers as $layer) {
                $layerPath = \App\Helpers\SessionHelper::getUserS3Path('/' . $layer);
                
                // Validar se path pertence ao usuário
                if (\App\Helpers\SessionHelper::validateUserS3Path($layerPath)) {
                    $files = \App\Helpers\DuckDBHelper::listParquetFiles($layerPath);
                    if (is_array($files)) {
                        $allFiles = array_merge($allFiles, $files);
                    }
                }
            }
        } else {
            // Se path fornecido, validar se é uma das camadas permitidas
            $isAllowed = false;
            foreach ($layers as $layer) {
                if (strpos($path, '/' . $layer) !== false) {
                    $isAllowed = true;
                    break;
                }
            }
            
            if (!$isAllowed) {
                return $this->response
                    ->setStatusCode(403)
                    ->setJSON([
                        'success' => false,
                        'error' => 'Acesso negado: apenas bronze, silver, gold e delta são permitidos',
                        'files' => []
                    ]);
            }
            
            // Validar se path pertence ao usuário
            if (!\App\Helpers\SessionHelper::validateUserS3Path($path)) {
                return $this->response
                    ->setStatusCode(403)
                    ->setJSON([
                        'success' => false,
                        'error' => 'Acesso negado: path inválido para este usuário',
                        'files' => []
                    ]);
            }
            
            $allFiles = \App\Helpers\DuckDBHelper::listParquetFiles($path);
        }
        
        return $this->response->setJSON([
            'success' => true,
            'files' => $allFiles,
            'path' => $path ?? 'merged'
        ]);
    }
    
    /**
     * Página de teste do componente git-sidebar
     */
    public function testGitSidebar()
    {
        $userBucket = \App\Helpers\SessionHelper::getUserBucket();
        
        return view('code_editor/test-git-sidebar', [
            'userBucket' => $userBucket ?? 'lab01'
        ]);
    }

    /**
     * Executa query SQL via API (unificado)
     * Intermediário entre frontend e QueryBuilderController::execute()
     * Compatível com o fluxo do unified-editor.js
     */
    public function querySQL()
    {
        // Delega para o execute() do QueryBuilderController
        // que trata da validação, segurança e execução
        return $this->execute();
    }

    /**
     * Executa código Python no backend com acesso a DuckDB e arquivos do usuário
     * 
     * POST /code-editor/execute-python
     * 
     * Params:
     *   - code (string): Código Python a executar
     *   - userBucket (string): Bucket do usuário (opcional, obtido da sessão)
     * 
     * Response:
     *   - success (bool): True se execução OK
     *   - stdout (string): Saída padrão do script
     *   - stderr (string): Saída de erros
     *   - result (mixed): Resultado retornado pelo script (se houver variável 'result')
     */
    public function executePython()
    {
        $code = trim($this->request->getJSON()->code ?? '');
        
        if (empty($code)) {
            return $this->response
                ->setStatusCode(400)
                ->setJSON([
                    'success' => false,
                    'error' => 'Python code cannot be empty'
                ]);
        }

        // Segurança: obtém bucket do usuário da sessão
        $userBucket = \App\Helpers\SessionHelper::getUserBucket();
        if (!$userBucket) {
            return $this->response
                ->setStatusCode(403)
                ->setJSON([
                    'success' => false,
                    'error' => 'User bucket not found. Please login.'
                ]);
        }

        // Sanitização básica: remover comandos perigosos (sem regex para evitar erros de delimitador)
        $forbiddenTokens = [
            'os.system',
            'subprocess.',
            'exec(',
            'eval(',
            '__import__',
            'open("/etc',
            "open('/etc",
            'open("/root',
            "open('/root"
        ];

        foreach ($forbiddenTokens as $token) {
            if (stripos($code, $token) !== false) {
                return $this->response
                    ->setStatusCode(403)
                    ->setJSON([
                        'success' => false,
                        'error' => 'Operação não permitida por razões de segurança'
                    ]);
            }
        }

        try {
            // Cria wrapper Python que captura stdout/stderr
            $pythonWrapper = sprintf(
                <<<'PYTHON'
import sys
import io
import contextlib
import traceback
import json
import os

# Captura saída padrão
stdout_capture = io.StringIO()
stderr_capture = io.StringIO()

# Injeta variáveis do usuário
user_bucket = %s
duckdb_path = "s3://{}/".format(user_bucket)

# Configure MinIO/S3 credentials for PyArrow
minio_endpoint = os.getenv('MINIO_ENDPOINT', '')
minio_access_key = os.getenv('MINIO_ACCESS_KEY_ID', '')
minio_secret_key = os.getenv('MINIO_SECRET_ACCESS_KEY', '')
minio_use_path_style = os.getenv('MINIO_USE_PATH_STYLE_ENDPOINT', 'true').lower() == 'true'

if minio_endpoint and minio_access_key and minio_secret_key:
    os.environ['AWS_ACCESS_KEY_ID'] = minio_access_key
    os.environ['AWS_SECRET_ACCESS_KEY'] = minio_secret_key
    os.environ['AWS_ENDPOINT_URL_S3'] = minio_endpoint
    os.environ['AWS_S3_ENDPOINT_URL'] = minio_endpoint  # Para pyarrow
    if minio_use_path_style:
        os.environ['AWS_S3_USE_PATH_STYLE'] = 'true'

# Código do usuário
user_code = %s

try:
    with contextlib.redirect_stdout(stdout_capture), contextlib.redirect_stderr(stderr_capture):
        # Preparar DuckDB apenas se o código usar duckdb ou read_parquet
        try:
            if ('read_parquet' in user_code) or ('duckdb' in user_code):
                import duckdb
                try:
                    duckdb.install_extension('httpfs')
                    duckdb.load_extension('httpfs')
                except Exception:
                    # Extensão pode já estar instalada/carregada
                    pass
        except Exception as _duckdb_err:
            print('Aviso: duckdb não disponível:', _duckdb_err, file=sys.stderr)

        # Executa código do usuário
        exec(user_code, globals())
    
    # Tenta extrair variável 'result' se existir
    result = globals().get('result', None)
    
    exit_code = 0
except Exception as e:
    result = None
    traceback.print_exc(file=stderr_capture)
    exit_code = 1

stdout_value = stdout_capture.getvalue()
stderr_value = stderr_capture.getvalue()

# Output JSON
output = {
    'success': exit_code == 0,
    'stdout': stdout_value,
    'stderr': stderr_value,
    'result': result
}
print(json.dumps(output))
PYTHON
,
                json_encode($userBucket, JSON_UNESCAPED_SLASHES),
                json_encode($code, JSON_UNESCAPED_SLASHES)
            );

            // Executa Python via shell (deve estar instalado no servidor)
            $descriptorspec = [
                0 => ['pipe', 'r'],   // stdin
                1 => ['pipe', 'w'],   // stdout
                2 => ['pipe', 'w']    // stderr
            ];

            // Descobrir binário Python
            $pythonBinary = getenv('PYTHON_BIN') ?: '';
            if (empty($pythonBinary)) {
                $candidates = ['python3', 'python'];
                foreach ($candidates as $cmd) {
                    $version = @shell_exec($cmd . ' --version 2>/dev/null');
                    if (!empty($version)) {
                        $pythonBinary = $cmd;
                        break;
                    }
                }
            }

            if (empty($pythonBinary)) {
                return $this->response
                    ->setStatusCode(500)
                    ->setJSON([
                        'success' => false,
                        'error' => 'Python não encontrado no servidor. Instale python3 ou configure PYTHON_BIN.'
                    ]);
            }

            // Propagar variáveis de ambiente relevantes para S3/MinIO
            $env = [
                'AWS_ACCESS_KEY_ID' => getenv('AWS_ACCESS_KEY_ID') ?: '',
                'AWS_SECRET_ACCESS_KEY' => getenv('AWS_SECRET_ACCESS_KEY') ?: '',
                'AWS_REGION' => getenv('AWS_REGION') ?: '',
                'S3_ENDPOINT' => getenv('S3_ENDPOINT') ?: '',
                'S3_URL_STYLE' => getenv('S3_URL_STYLE') ?: '',
                'AWS_S3_ALLOW_UNVERIFIED_SSL' => getenv('AWS_S3_ALLOW_UNVERIFIED_SSL') ?: '',
                // MinIO variables
                'MINIO_ENDPOINT' => getenv('MINIO_ENDPOINT') ?: '',
                'MINIO_REGION' => getenv('MINIO_REGION') ?: '',
                'MINIO_ACCESS_KEY_ID' => getenv('MINIO_ACCESS_KEY_ID') ?: '',
                'MINIO_SECRET_ACCESS_KEY' => getenv('MINIO_SECRET_ACCESS_KEY') ?: '',
                'MINIO_USE_PATH_STYLE_ENDPOINT' => getenv('MINIO_USE_PATH_STYLE_ENDPOINT') ?: ''
            ];

            $process = proc_open($pythonBinary . ' -', $descriptorspec, $pipes, null, $env);

            if (is_resource($process)) {
                fwrite($pipes[0], $pythonWrapper);
                fclose($pipes[0]);

                $stdout = stream_get_contents($pipes[1]);
                $stderr = stream_get_contents($pipes[2]);
                fclose($pipes[1]);
                fclose($pipes[2]);

                $returnCode = proc_close($process);

                // Tenta decodificar JSON retornado
                $result = json_decode($stdout, true);
                
                if (is_array($result)) {
                    return $this->response->setJSON($result);
                } else {
                    // Fallback se saída não for JSON válido
                    return $this->response->setJSON([
                        'success' => false,
                        'stdout' => $stdout,
                        'stderr' => $stderr,
                        'result' => null,
                        'error' => !empty($stderr) ? $stderr : 'Falha na execução Python'
                    ]);
                }
            } else {
                return $this->response
                    ->setStatusCode(500)
                    ->setJSON([
                        'success' => false,
                        'error' => 'Falha ao executar Python no servidor'
                    ]);
            }
        } catch (\Exception $e) {
            return $this->response
                ->setStatusCode(500)
                ->setJSON([
                    'success' => false,
                    'error' => 'Erro ao processar requisição: ' . $e->getMessage()
                ]);
        }
    }
    
    // Todos os outros métodos (execute, listTables, getSchema, etc)
    // são herdados do QueryBuilderController
    // Nenhuma duplicação de código!
}

