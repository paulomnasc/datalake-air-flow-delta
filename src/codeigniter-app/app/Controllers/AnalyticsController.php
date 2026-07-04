<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Helpers\SessionHelper;
use App\Helpers\MetabaseHelper;

class AnalyticsController extends BaseController
{
    /**
     * Valida os modelos e redireciona o usuário para o Metabase via JWT SSO
     */
    public function access()
    {
        if (!SessionHelper::isLoggedIn()) {
            return redirect()->to(route_to('Usuario.login'));
        }

        $userId = SessionHelper::getUserId();
        $email = SessionHelper::getUserEmail();
        $name = SessionHelper::getUserName();
        $schemaProd = "user_{$userId}_analytics";

        // 1. Validar se os modelos analíticos existem no PostgreSQL (datalake_bi)
        $hasAnalytics = false;
        try {
            $dsn = "pgsql:host=postgres-bi;port=5432;dbname=datalake_bi";
            $pdo = new \PDO($dsn, 'pbi_user', 'pbi_password', [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
            
            // Conta as tabelas/views existentes no schema produtivo do inquilino
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as total 
                FROM information_schema.tables 
                WHERE table_schema = :schema
            ");
            $stmt->execute(['schema' => $schemaProd]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if ($result && (int) $result['total'] > 0) {
                $hasAnalytics = true;
            }
        } catch (\Exception $e) {
            log_message('error', "AnalyticsController: Erro ao validar tabelas no PostgreSQL: " . $e->getMessage());
        }

        // 2. Renderizar a tela de credenciais para login manual no Metabase OSS
        try {
            $metabaseHelper = new MetabaseHelper();
            $password = $metabaseHelper->getTenantPassword($email);
            $siteUrl = $metabaseHelper->getSiteUrl();
            
            log_message('info', "AnalyticsController: Exibindo tela de login manual no Metabase para o usuário {$userId}.");
            return view('analytics/login', [
                'email'         => $email,
                'password'      => $password,
                'siteUrl'       => $siteUrl,
                'noOlapWarning' => !$hasAnalytics
            ]);
        } catch (\Exception $e) {
            log_message('error', "AnalyticsController: Erro ao carregar credenciais do Metabase: " . $e->getMessage());
            return redirect()->to(route_to('dashboard'))->with('error_analytics', '⚠️ Erro interno ao conectar com o serviço de Analytics. Tente novamente mais tarde.');
        }
    }
}
