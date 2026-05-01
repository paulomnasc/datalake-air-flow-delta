<?php

namespace App\Controllers;

use CodeIgniter\HTTP\ResponseInterface;

class GitProxyController extends BaseController
{
    public function index()
    {
        // Disable output buffering for immediate response
        if (ob_get_level()) {
            ob_end_clean();
        }
        
        // Log incoming request for debugging
        error_log('GitProxy: ' . $this->request->getMethod() . ' ' . ($this->request->getGet('url') ?? 'no-url'));
        
        // CORS headers
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Authorization, Content-Type, User-Agent, Accept, Git-Protocol');

        // Handle preflight OPTIONS early
        $method = strtoupper($this->request->getMethod());
        if ($method === 'OPTIONS') {
            return $this->response->setStatusCode(ResponseInterface::HTTP_NO_CONTENT);
        }

        $url = $this->request->getGet('url');
        
        // Se não tiver URL (comum em POST durante clone), tentar extrair do Referer ou usar URL construída
        if (!$url && $method === 'POST') {
            // Tentar construir URL baseado no que foi requisitado antes
            // isomorphic-git envia POST para /<service>, então podemos tentar adivinhar
            error_log('GitProxy POST sem URL, tentando alternativa...');
            
            // Deixar passar e processar como redirecionamento
            // Na verdade, o isomorphic-git DEVE enviar a URL... vamos verificar melhor
        }
        
        if (!$url) {
            http_response_code(400);
            header('Content-Type: text/plain');
            echo 'Missing url parameter';
            exit;
        }

        // Normalizar URLs sem protocolo vindas do isomorphic-git (ex.: /github.com/owner/repo...)
        if (is_string($url) && str_starts_with($url, '/github.com/')) {
            $url = 'https://' . ltrim($url, '/');
        }

        $parsed = parse_url($url);
        $host = $parsed['host'] ?? '';
        if ($host !== 'github.com') {
            return $this->response->setStatusCode(ResponseInterface::HTTP_FORBIDDEN)
                ->setBody('Forbidden host');
        }

        // Authorization may come via different server vars depending on stack
        // Try to obtain Authorization header if provided; allow unauthenticated for public repos
        $auth = $this->request->getHeaderLine('Authorization');
        if (!$auth) {
            $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        }
        // Fallback via query param 'auth' (base64 basic) if header not available
        if (!$auth) {
            $authParam = $this->request->getGet('auth');
            if ($authParam) {
                $auth = 'Basic ' . $authParam;
            }
        }

        // From here, only GET/POST/HEAD must include URL
        $body = $this->request->getBody();

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        // Decode compressed responses automatically
        curl_setopt($ch, CURLOPT_ENCODING, '');
        // Also set libcurl User-Agent for servers that rely on it
        curl_setopt($ch, CURLOPT_USERAGENT, 'isomorphic-git-proxy/1.0');

        $forwardHeaders = [
            'User-Agent: isomorphic-git-proxy/1.0',
        ];
        if ($auth) {
            $forwardHeaders[] = 'Authorization: ' . $auth;
        }

        $contentType = $this->request->getHeaderLine('Content-Type');
        if ($contentType) {
            $forwardHeaders[] = 'Content-Type: ' . $contentType;
        }

        $accept = $this->request->getHeaderLine('Accept');
        if ($accept) {
            $forwardHeaders[] = 'Accept: ' . $accept;
        }
        $gitProtocol = $this->request->getHeaderLine('Git-Protocol');
        if ($gitProtocol) {
            $forwardHeaders[] = 'Git-Protocol: ' . $gitProtocol;
        }

        if ($method !== 'GET' && $method !== 'HEAD' && $body !== '') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $forwardHeaders);

        $response = curl_exec($ch);
        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            return $this->response->setStatusCode(ResponseInterface::HTTP_BAD_GATEWAY)
                ->setBody('cURL error: ' . $error);
        }

        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $respHeadersRaw = substr($response, 0, $headerSize);
        $respBody = substr($response, $headerSize);
        curl_close($ch);

        // Extract headers from upstream response
        $contentTypeUpstream = null;
        $contentEncodingUpstream = null;
        foreach (explode("\r\n", $respHeadersRaw) as $line) {
            if (stripos($line, 'Content-Type:') === 0) {
                $contentTypeUpstream = trim(substr($line, strlen('Content-Type:')));
                break;
            }
        }
        foreach (explode("\r\n", $respHeadersRaw) as $line) {
            if (stripos($line, 'Content-Encoding:') === 0) {
                $contentEncodingUpstream = trim(substr($line, strlen('Content-Encoding:')));
                break;
            }
        }

        // Return response completely raw to avoid CodeIgniter processing
        http_response_code($status);
        
        if ($contentTypeUpstream) {
            header('Content-Type: ' . $contentTypeUpstream);
        }
        if ($contentEncodingUpstream) {
            header('Content-Encoding: ' . $contentEncodingUpstream);
        }
        
        header('Content-Length: ' . strlen($respBody));
        
        // Output raw body and exit to bypass CodeIgniter response processing
        echo $respBody;
        exit;
    }
}
