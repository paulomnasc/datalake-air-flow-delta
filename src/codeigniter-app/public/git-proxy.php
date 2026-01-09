<?php
// Simple Git CORS proxy (same-origin)
// Security: allows only GitHub URLs, requires Authorization header
// Usage: /git-proxy.php?url=https%3A%2F%2Fgithub.com%2Fowner%2Frepo.git%2Finfo%2Frefs%3Fservice%3Dgit-upload-pack

// Allow same-origin access
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, Content-Type, User-Agent');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$url = $_GET['url'] ?? '';
if (!$url) {
    http_response_code(400);
    echo 'Missing url';
    exit;
}

// Allow only GitHub host
$parsed = parse_url($url);
$host = $parsed['host'] ?? '';
if ($host !== 'github.com') {
    http_response_code(403);
    echo 'Forbidden host';
    exit;
}

// Require Authorization header
$headersIn = function_exists('getallheaders') ? getallheaders() : [];
$auth = $headersIn['Authorization'] ?? $headersIn['authorization'] ?? '';
if (!$auth) {
    http_response_code(401);
    echo 'Missing Authorization';
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$body = file_get_contents('php://input');

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);

$forwardHeaders = [
    'Authorization: ' . $auth,
    'User-Agent: isomorphic-git-proxy/1.0',
];

$contentType = $headersIn['Content-Type'] ?? $headersIn['content-type'] ?? '';
if ($contentType) {
    $forwardHeaders[] = 'Content-Type: ' . $contentType;
}

if ($method !== 'GET' && $method !== 'HEAD' && $body !== '') {
    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
}

curl_setopt($ch, CURLOPT_HTTPHEADER, $forwardHeaders);

$response = curl_exec($ch);
if ($response === false) {
    http_response_code(502);
    echo 'cURL error: ' . curl_error($ch);
    curl_close($ch);
    exit;
}

$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$responseHeaders = substr($response, 0, $headerSize);
$responseBody = substr($response, $headerSize);

http_response_code($status);

$lines = explode("\r\n", $responseHeaders);
foreach ($lines as $line) {
    if (stripos($line, 'transfer-encoding:') === 0) continue;
    if (stripos($line, 'content-length:') === 0) continue;
    if (stripos($line, 'connection:') === 0) continue;
    if (stripos($line, 'access-control-allow-origin:') === 0) continue;
    if (stripos($line, 'access-control-allow-headers:') === 0) continue;
    if (stripos($line, 'access-control-allow-methods:') === 0) continue;
    if (strpos($line, ':') !== false) {
        header($line);
    }
}

echo $responseBody;
