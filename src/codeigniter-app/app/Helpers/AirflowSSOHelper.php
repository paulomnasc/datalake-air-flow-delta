<?php
namespace App\Helpers;

class AirflowSSOHelper
{
    /**
     * Realiza login no Airflow Webserver e retorna o cookie de sessão.
     * @param string $username
     * @param string $password
     * @param string $airflowUrl
     * @return string|null Cookie de sessão do Airflow ou null em caso de erro
     */
    public static function loginAndGetSessionCookie(string $username, string $password, string $airflowUrl): ?string
    {
        $loginUrl = rtrim($airflowUrl, '/') . '/login/';
        $postFields = http_build_query([
            'username' => $username,
            'password' => $password
        ]);
        $ch = curl_init($loginUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $response = curl_exec($ch);
        if ($response === false) {
            curl_close($ch);
            return null;
        }
        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $header = substr($response, 0, $header_size);
        curl_close($ch);
        // Procura o cookie de sessão
        if (preg_match('/Set-Cookie: ([^;]+);/i', $header, $matches)) {
            return $matches[1]; // Ex: session=abc123
        }
        return null;
    }
}
