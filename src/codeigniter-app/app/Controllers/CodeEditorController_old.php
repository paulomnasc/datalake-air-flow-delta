<?php

namespace App\Controllers;

use App\Controllers\QueryBuilderController;

/**
 * CodeEditorController
 * 
 * Interface avançada com Monaco Editor para execução de queries SQL
 * Herda toda a lógica funcional do QueryBuilderController (DuckDB, segurança, etc)
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
     * Exibe interface web para Code Editor com Monaco
     * Usa a mesma lógica de preparação de dados do QueryBuilder
     */
    public function index()
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
        
        return view('code_editor/index', [
            'duckdbStatus' => $duckdbStatus,
            'parquetFiles' => $parquetFiles,
            'userBucket' => $userBucket,
            'userS3Path' => $userS3Path
        ]);
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
    
    // ===== GIT INTEGRATION METHODS =====
    
    /**
     * Obter status de conexão Git do usuário
     */
    public function gitStatus()
    {
        $userId = $_SESSION['id_usuario_logado'] ?? null;
        
        if (!$userId) {
            return $this->response->setJSON([
                'connected' => false,
                'error' => 'Usuário não autenticado'
            ]);
        }
        
        $gitInfo = \App\Helpers\GitHelper::getUserGitInfo($userId);
        
        if ($gitInfo) {
            return $this->response->setJSON([
                'connected' => true,
                'repo' => [
                    'owner' => $gitInfo['username'],
                    'name' => 'sql-scripts',
                    'branch' => 'main'
                ]
            ]);
        }
        
        return $this->response->setJSON(['connected' => false]);
    }
    
    /**
     * Callback do GitHub OAuth
     */
    public function githubCallback()
    {
        $code = $this->request->getGet('code');
        $state = $this->request->getGet('state');
        
        if (!$code) {
            return redirect('/code-editor')->with('error', 'Falha na autenticação do GitHub');
        }
        
        $userId = $_SESSION['id_usuario_logado'] ?? null;
        
        if (!$userId) {
            return redirect('/loginUsuario');
        }
        
        // Configurações do GitHub App (obter de .env ou variáveis de ambiente)
        $clientId = getenv('GITHUB_CLIENT_ID', true) ?: 'YOUR_CLIENT_ID';
        $clientSecret = getenv('GITHUB_CLIENT_SECRET', true) ?: 'YOUR_CLIENT_SECRET';
        
        // Trocar código por token
        $tokenData = \App\Helpers\GitHelper::exchangeOAuthCode($code, $clientId, $clientSecret);
        
        if (!$tokenData || !isset($tokenData['access_token'])) {
            return redirect('/code-editor')->with('error', 'Erro ao obter token do GitHub');
        }
        
        $token = $tokenData['access_token'];
        
        // Obter dados do usuário GitHub
        $userData = \App\Helpers\GitHelper::getGithubUserData($token);
        
        if (!$userData) {
            return redirect('/code-editor')->with('error', 'Erro ao obter dados do GitHub');
        }
        
        // Salvar token
        if (\App\Helpers\GitHelper::saveGithubToken($userId, $token, $userData)) {
            return redirect('/code-editor')->with('success', 'GitHub conectado com sucesso!');
        }
        
        return redirect('/code-editor')->with('error', 'Erro ao salvar token do GitHub');
    }
    
    /**
     * Fazer commit de um script SQL para GitHub
     */
    public function gitCommit()
    {
        $userId = $_SESSION['id_usuario_logado'] ?? null;
        
        if (!$userId) {
            return $this->response->setStatusCode(401)
                ->setJSON(['success' => false, 'error' => 'Não autenticado']);
        }
        
        $json = $this->request->getJSON(true);
        $message = $json['message'] ?? '';
        $content = $json['content'] ?? '';
        $filename = $json['filename'] ?? 'script.sql';
        
        if (!$message || !$content) {
            return $this->response->setJSON([
                'success' => false,
                'error' => 'Mensagem e conteúdo são obrigatórios'
            ]);
        }
        
        $token = \App\Helpers\GitHelper::getGithubToken($userId);
        
        if (!$token) {
            return $this->response->setJSON([
                'success' => false,
                'error' => 'GitHub não conectado'
            ]);
        }
        
        $gitInfo = \App\Helpers\GitHelper::getUserGitInfo($userId);
        $owner = $gitInfo['username'];
        $repo = 'sql-scripts';
        
        // Criar caminho do arquivo (scripts/[data].sql)
        $filePath = 'scripts/' . $filename;
        
        $result = \App\Helpers\GitHelper::commitFile(
            $token,
            $owner,
            $repo,
            $filePath,
            $content,
            $message
        );
        
        if ($result && isset($result['commit'])) {
            return $this->response->setJSON([
                'success' => true,
                'message' => 'Script enviado para GitHub!',
                'commit' => $result['commit']['message'],
                'url' => $result['html_url'] ?? ''
            ]);
        }
        
        return $this->response->setJSON([
            'success' => false,
            'error' => 'Erro ao fazer commit'
        ]);
    }
    
    /**
     * Obter histórico de commits
     */
    public function gitHistory()
    {
        $userId = $_SESSION['id_usuario_logado'] ?? null;
        
        if (!$userId) {
            return $this->response->setStatusCode(401)
                ->setJSON(['success' => false, 'error' => 'Não autenticado']);
        }
        
        $token = \App\Helpers\GitHelper::getGithubToken($userId);
        
        if (!$token) {
            return $this->response->setJSON([
                'success' => false,
                'commits' => []
            ]);
        }
        
        $gitInfo = \App\Helpers\GitHelper::getUserGitInfo($userId);
        $owner = $gitInfo['username'];
        $repo = 'sql-scripts';
        
        $commits = \App\Helpers\GitHelper::getCommitHistory($token, $owner, $repo);
        
        if ($commits) {
            $formattedCommits = array_map(function ($commit) {
                return [
                    'message' => $commit['commit']['message'] ?? '',
                    'sha' => $commit['sha'] ?? '',
                    'author' => $commit['commit']['author']['name'] ?? '',
                    'date' => $commit['commit']['author']['date'] ?? ''
                ];
            }, $commits);
            
            return $this->response->setJSON([
                'success' => true,
                'commits' => $formattedCommits
            ]);
        }
        
        return $this->response->setJSON([
            'success' => false,
            'commits' => []
        ]);
    }
    
    /**
     * Desconectar GitHub
     */
    public function gitDisconnect()
    {
        $userId = $_SESSION['id_usuario_logado'] ?? null;
        
        if (!$userId) {
            return $this->response->setStatusCode(401)
                ->setJSON(['success' => false, 'error' => 'Não autenticado']);
        }
        
        if (\App\Helpers\GitHelper::removeGithubToken($userId)) {
            return $this->response->setJSON([
                'success' => true,
                'message' => 'Desconectado com sucesso'
            ]);
        }
        
        return $this->response->setJSON([
            'success' => false,
            'error' => 'Erro ao desconectar'
        ]);
    }
    
    // Todos os outros métodos (execute, listTables, getSchema, etc)
    // são herdados do QueryBuilderController
    // Nenhuma duplicação de código!
}
