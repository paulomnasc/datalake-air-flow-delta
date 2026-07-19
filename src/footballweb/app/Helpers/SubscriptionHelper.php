<?php

namespace App\Helpers;

use DateTime;

/**
 * SubscriptionHelper
 * 
 * Helper para gerenciar controle de assinaturas de usuários
 * Calcula dias restantes, verifica status e gerencia renovações
 */
class SubscriptionHelper
{
    /**
     * Calcula quantos dias faltam para o vencimento da assinatura
     * 
     * @param string|null $dataVencimento Data de vencimento (formato Y-m-d)
     * @return int Número de dias restantes (negativo se já venceu)
     */
    public static function calcularDiasRestantes(?string $dataVencimento): int
    {
        if (empty($dataVencimento)) {
            return -1;
        }

        try {
            $hoje = new DateTime();
            $vencimento = new DateTime($dataVencimento);
            $diferenca = $hoje->diff($vencimento);
            
            // Se já passou da data, retorna negativo
            if ($hoje > $vencimento) {
                return -$diferenca->days;
            }
            
            return $diferenca->days;
        } catch (\Exception $e) {
            log_message('error', 'Erro ao calcular dias restantes: ' . $e->getMessage());
            return -1;
        }
    }

    /**
     * Verifica se deve mostrar aviso de vencimento próximo (7 dias ou menos)
     * 
     * @param string|null $dataVencimento Data de vencimento
     * @param string $statusAssinatura Status atual da assinatura
     * @return bool True se deve mostrar aviso
     */
    public static function deveMostrarAviso(?string $dataVencimento, string $statusAssinatura): bool
    {
        // Só mostra aviso para assinaturas ativas ou em trial
        if (!in_array($statusAssinatura, ['trial', 'active'])) {
            return false;
        }

        $diasRestantes = self::calcularDiasRestantes($dataVencimento);
        
        // Mostra aviso se faltam 7 dias ou menos (e não venceu ainda)
        return $diasRestantes >= 0 && $diasRestantes <= 7;
    }

    /**
     * Verifica se a assinatura está expirada
     * 
     * @param string|null $dataVencimento Data de vencimento
     * @return bool True se expirou
     */
    public static function estaExpirada(?string $dataVencimento): bool
    {
        if (empty($dataVencimento)) {
            return false;
        }

        $diasRestantes = self::calcularDiasRestantes($dataVencimento);
        return $diasRestantes < 0;
    }

    /**
     * Calcula a próxima data de vencimento (30 dias a partir de hoje)
     * 
     * @return string Data de vencimento no formato Y-m-d
     */
    public static function calcularProximoVencimento(): string
    {
        $hoje = new DateTime();
        $proximoVencimento = $hoje->modify('+30 days');
        return $proximoVencimento->format('Y-m-d');
    }

    /**
     * Calcula data de vencimento com base na última data de vencimento
     * Se a assinatura ainda está ativa, adiciona 30 dias à data atual de vencimento
     * Caso contrário, adiciona 30 dias a partir de hoje
     * 
     * @param string|null $dataVencimentoAtual Data de vencimento atual
     * @return string Nova data de vencimento no formato Y-m-d
     */
    public static function calcularRenovacao(?string $dataVencimentoAtual): string
    {
        try {
            if (!empty($dataVencimentoAtual)) {
                $vencimentoAtual = new DateTime($dataVencimentoAtual);
                $hoje = new DateTime();
                
                // Se ainda não venceu, adiciona 30 dias ao vencimento atual
                if ($vencimentoAtual >= $hoje) {
                    $novoVencimento = $vencimentoAtual->modify('+30 days');
                    return $novoVencimento->format('Y-m-d');
                }
            }
            
            // Se já venceu ou não tem data, calcula a partir de hoje
            return self::calcularProximoVencimento();
        } catch (\Exception $e) {
            log_message('error', 'Erro ao calcular renovação: ' . $e->getMessage());
            return self::calcularProximoVencimento();
        }
    }

    /**
     * Atualiza o status da assinatura baseado na data de vencimento
     * 
     * @param string|null $dataVencimento Data de vencimento
     * @param string $statusAtual Status atual da assinatura
     * @return string Novo status da assinatura
     */
    public static function atualizarStatus(?string $dataVencimento, string $statusAtual): string
    {
        // Se foi cancelada, mantém cancelada
        if ($statusAtual === 'cancelled') {
            return 'cancelled';
        }

        // Verifica se expirou
        if (self::estaExpirada($dataVencimento)) {
            return 'expired';
        }

        // Se está em trial e não expirou, mantém trial
        if ($statusAtual === 'trial') {
            return 'trial';
        }

        // Se está ativa e não expirou, mantém ativa
        return 'active';
    }

    /**
     * Formata a mensagem de aviso para exibição
     * 
     * @param int $diasRestantes Dias restantes
     * @param string $statusAssinatura Status da assinatura
     * @return string Mensagem formatada
     */
    public static function obterMensagemAviso(int $diasRestantes, string $statusAssinatura): string
    {
        if ($diasRestantes < 0) {
            return 'Sua assinatura expirou. Renove agora para continuar usando!';
        }

        if ($diasRestantes == 0) {
            $tipo = ($statusAssinatura === 'trial') ? 'período de teste' : 'assinatura';
            return "Seu {$tipo} expira hoje! Renove agora.";
        }

        if ($diasRestantes == 1) {
            $tipo = ($statusAssinatura === 'trial') ? 'período de teste' : 'assinatura';
            return "Seu {$tipo} expira amanhã! Não perca o acesso.";
        }

        $tipo = ($statusAssinatura === 'trial') ? 'período de teste' : 'assinatura';
        return "Seu {$tipo} expira em {$diasRestantes} dias. Renove para garantir acesso contínuo!";
    }

    /**
     * Obtém a classe CSS para o tipo de alerta baseado nos dias restantes
     * 
     * @param int $diasRestantes Dias restantes
     * @return string Classe CSS (alert-warning, alert-danger, etc)
     */
    public static function obterClasseAlerta(int $diasRestantes): string
    {
        if ($diasRestantes < 0) {
            return 'alert-danger';
        }

        if ($diasRestantes <= 2) {
            return 'alert-danger';
        }

        if ($diasRestantes <= 7) {
            return 'alert-warning';
        }

        return 'alert-info';
    }

    /**
     * Verifica se o usuário pode acessar a plataforma
     * 
     * @param string $statusAssinatura Status da assinatura
     * @param string|null $dataVencimento Data de vencimento
     * @return array ['pode_acessar' => bool, 'mensagem' => string]
     */
    public static function podeAcessarPlataforma(string $statusAssinatura, ?string $dataVencimento): array
    {
        // Cancelada: não pode acessar
        if ($statusAssinatura === 'cancelled') {
            return [
                'pode_acessar' => false,
                'mensagem' => 'Sua assinatura foi cancelada. Entre em contato para reativar.'
            ];
        }

        // Expirada: verifica se já passou da data
        if ($statusAssinatura === 'expired' || self::estaExpirada($dataVencimento)) {
            $diasVencidos = abs(self::calcularDiasRestantes($dataVencimento));
            return [
                'pode_acessar' => false,
                'mensagem' => "Sua assinatura expirou há {$diasVencidos} dia(s). Renove para continuar usando a plataforma."
            ];
        }

        // Trial ou Active: pode acessar
        return [
            'pode_acessar' => true,
            'mensagem' => ''
        ];
    }

    /**
     * Registra um novo pagamento e atualiza a assinatura
     * 
     * @param \App\Models\UsuarioModel $usuarioModel Model do usuário
     * @param int $userId ID do usuário
     * @return array ['success' => bool, 'message' => string, 'novo_vencimento' => string]
     */
    public static function registrarPagamento($usuarioModel, int $userId): array
    {
        try {
            $usuario = $usuarioModel->find($userId);
            
            if (!$usuario) {
                return [
                    'success' => false,
                    'message' => 'Usuário não encontrado'
                ];
            }

            $novoVencimento = self::calcularRenovacao($usuario->data_vencimento_assinatura);
            
            $dados = [
                'data_ultimo_pagamento' => date('Y-m-d'),
                'data_vencimento_assinatura' => $novoVencimento,
                'status_assinatura' => 'active'
            ];

            $atualizado = $usuarioModel->update($userId, $dados);

            if ($atualizado) {
                return [
                    'success' => true,
                    'message' => 'Pagamento registrado com sucesso!',
                    'novo_vencimento' => $novoVencimento
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Erro ao atualizar dados do usuário'
                ];
            }
        } catch (\Exception $e) {
            log_message('error', 'Erro ao registrar pagamento: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erro ao processar pagamento: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Verifica se a liga exige créditos para visualização de estatísticas detalhadas.
     * 
     * @param string $leagueName Nome da liga
     * @return bool True se a liga exige créditos
     */
    public static function leagueRequiresCredits(string $leagueName): bool
    {
        $leagueLower = strtolower($leagueName);
        
        $majorKeywords = [
            'champions league',
            'uefa',
            'premier league',
            'la liga',
            'bundesliga',
            'ligue 1',
            'eredivisie',
            'primeira liga',
            'libertadores',
            'sudamericana',
            'copa do brasil',
            'brasile'
        ];
        
        foreach ($majorKeywords as $keyword) {
            if (strpos($leagueLower, $keyword) !== false) {
                return true;
            }
        }
        
        // Brasileirão e Série A/B/C italianas ou equivalentes
        if ($leagueLower === 'serie a' || $leagueLower === 'serie b' || $leagueLower === 'serie c' ||
            strpos($leagueLower, 'série a') !== false || strpos($leagueLower, 'série b') !== false || strpos($leagueLower, 'série c') !== false) {
            return true;
        }
        
        return false;
    }
}
