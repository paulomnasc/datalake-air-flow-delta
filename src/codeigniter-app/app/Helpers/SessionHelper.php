<?php

namespace App\Helpers;

class SessionHelper
{
    /**
     * Retorna o ID do usuário logado
     * 
     * @return int|null ID do usuário ou null se não estiver logado
     */
    public static function getUserId(): ?int
    {
        return $_SESSION['id_usuario_logado'] ?? null;
    }

    /**
     * Retorna o nome do bucket do usuário logado
     * Formato: {username-prefix}-{id} (alinhado com AirflowHelper::buildUsernameFromEmail)
     * Exemplo: eng-147, joao-89, etc.
     * 
     * @return string|null Nome do bucket ou null se usuário não estiver logado
     */
    public static function getUserBucket(): ?string
    {
        $userId = self::getUserId();
        $email = self::getUserEmail();
        
        if ($userId === null) {
            return null;
        }
        
        // Usar o mesmo padrão do Airflow username: prefixo-userId
        return \App\Helpers\AirflowHelper::buildUsernameFromEmail($email, $userId);
    }

    /**
     * Retorna o path S3 completo do bucket do usuário
     * Formato: s3://{username} (e.g., s3://kauan-duardo-179)
     * 
     * @param string $suffix Path adicional (ex: '/bronze/files')
     * @return string|null Path S3 completo ou null se usuário não estiver logado
     */
    public static function getUserS3Path(string $suffix = ''): ?string
    {
        $bucket = self::getUserBucket();
        
        if ($bucket === null) {
            return null;
        }
        
        // Remove barra inicial se existir
        $suffix = ltrim($suffix, '/');
        
        if (empty($suffix)) {
            return "s3://{$bucket}";
        }
        
        return "s3://{$bucket}/{$suffix}";
    }

    /**
     * Verifica se o usuário está logado
     * 
     * @return bool
     */
    public static function isLoggedIn(): bool
    {
        return isset($_SESSION['usuario_logado']) && $_SESSION['usuario_logado'] == 1;
    }

    /**
     * Retorna o nome do usuário logado
     * 
     * @return string|null
     */
    public static function getUserName(): ?string
    {
        return $_SESSION['nome_usuario_logado'] ?? null;
    }

    /**
     * Retorna o email do usuário logado
     */
    public static function getUserEmail(): ?string
    {
        return $_SESSION['email_usuario_logado'] ?? null;
    }

    /**
     * Retorna o perfil do usuário logado
     * 
     * @return string|null
     */
    public static function getUserProfile(): ?string
    {
        return $_SESSION['perfil_usuario_logado'] ?? null;
    }

    /**
     * Retorna dados completos da sessão do usuário
     * 
     * @return array
     */
    public static function getUserData(): array
    {
        if (!self::isLoggedIn()) {
            return [];
        }
        
        return [
            'id' => self::getUserId(),
            'nome' => self::getUserName(),
            'perfil' => self::getUserProfile(),
            'bucket' => self::getUserBucket(),
            's3_path' => self::getUserS3Path(),
            'logado' => true
        ];
    }

    /**
     * Valida se um path S3 pertence ao bucket do usuário logado
     * Útil para segurança - evitar acesso a buckets de outros usuários
     * 
     * @param string $s3Path Path S3 a validar
     * @return bool True se pertence ao usuário, False caso contrário
     */
    public static function validateUserS3Path(string $s3Path): bool
    {
        $userBucket = self::getUserBucket();
        
        if ($userBucket === null) {
            return false;
        }
        
        // Verifica se o path começa com s3://{userBucket}
        return str_starts_with($s3Path, "s3://{$userBucket}/") || $s3Path === "s3://{$userBucket}";
    }

    /**
     * Substitui referências ao bucket 'lab01' pelo bucket do usuário
     * Útil para migração de queries antigas
     * 
     * @param string $text Texto com possíveis referências a 'lab01'
     * @return string Texto com 'lab01' substituído pelo bucket do usuário
     */
    public static function replaceLab01WithUserBucket(string $text): string
    {
        $userBucket = self::getUserBucket();
        
        if ($userBucket === null) {
            return $text;
        }
        
        // Substitui s3://lab01 pelo bucket do usuário para compatibilidade com queries antigas
        return str_replace('s3://lab01', "s3://{$userBucket}", $text);
    }
}
