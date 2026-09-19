<?php

namespace App\Models;

use CodeIgniter\Model;

class MetaDiariaModel extends Model
{
    protected $table            = 'metas_diarias_config';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'object';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'usuario_id',
        'titulo',
        'stake_padrao',
        'total_apostas_alvo',
        'odd_media_alvo',
        'target_greens',
        'target_pushes',
        'max_reds',
        'lucro_alvo',
        'stop_loss_diario',
        'is_ativa',
        'created_at',
        'updated_at'
    ];

    protected $useTimestamps = false;

    /**
     * Retorna a meta ativa do usuário (ou gera a padrão se não existir).
     */
    public function getMetaAtiva(int $usuarioId): object
    {
        $db = \Config\Database::connect();
        $meta = $db->table('metas_diarias_config')
            ->where('usuario_id', $usuarioId)
            ->where('is_ativa', 1)
            ->orderBy('id', 'DESC')
            ->get()
            ->getRow();

        if (!$meta) {
            // Cria meta padrão inicial
            $default = [
                'usuario_id'         => $usuarioId,
                'titulo'             => 'Meta Padrão - Ciclo 10 Apostas R$ 10',
                'stake_padrao'       => 10.00,
                'total_apostas_alvo' => 10,
                'odd_media_alvo'     => 1.75,
                'target_greens'      => 5,
                'target_pushes'      => 2,
                'max_reds'           => 3,
                'lucro_alvo'         => 7.50,
                'stop_loss_diario'   => -30.00,
                'is_ativa'           => 1,
                'created_at'         => date('Y-m-d H:i:s'),
                'updated_at'         => date('Y-m-d H:i:s')
            ];
            $db->table('metas_diarias_config')->insert($default);
            return (object)$default;
        }

        return $meta;
    }

    /**
     * Salva ou atualiza a meta ativa do usuário.
     */
    public function salvarConfig(int $usuarioId, array $dados): bool
    {
        $db = \Config\Database::connect();
        $metaExistente = $this->getMetaAtiva($usuarioId);

        $payload = [
            'titulo'             => !empty($dados['titulo']) ? trim($dados['titulo']) : 'Meta Diária',
            'stake_padrao'       => isset($dados['stake_padrao']) ? (float)$dados['stake_padrao'] : 10.00,
            'total_apostas_alvo' => isset($dados['total_apostas_alvo']) ? (int)$dados['total_apostas_alvo'] : 10,
            'odd_media_alvo'     => isset($dados['odd_media_alvo']) ? (float)$dados['odd_media_alvo'] : 1.75,
            'target_greens'      => isset($dados['target_greens']) ? (int)$dados['target_greens'] : 5,
            'target_pushes'      => isset($dados['target_pushes']) ? (int)$dados['target_pushes'] : 2,
            'max_reds'           => isset($dados['max_reds']) ? (int)$dados['max_reds'] : 3,
            'lucro_alvo'         => isset($dados['lucro_alvo']) ? (float)$dados['lucro_alvo'] : 7.50,
            'stop_loss_diario'   => isset($dados['stop_loss_diario']) ? (float)$dados['stop_loss_diario'] : -30.00,
            'is_ativa'           => 1,
            'updated_at'         => date('Y-m-d H:i:s')
        ];

        if (isset($metaExistente->id)) {
            return $db->table('metas_diarias_config')
                ->where('id', $metaExistente->id)
                ->where('usuario_id', $usuarioId)
                ->update($payload);
        } else {
            $payload['usuario_id'] = $usuarioId;
            $payload['created_at'] = date('Y-m-d H:i:s');
            return $db->table('metas_diarias_config')->insert($payload);
        }
    }

    /**
     * Obtém o progresso do dia usando Cache-First no MySQL.
     */
    public function getProgressoDiario(int $usuarioId, string $dataReferencia, bool $forceRecalculate = false): array
    {
        $db = \Config\Database::connect();

        if (!$forceRecalculate) {
            $cache = $db->table('metas_diarias_cache')
                ->where('usuario_id', $usuarioId)
                ->where('data_referencia', $dataReferencia)
                ->get()
                ->getRowArray();

            if ($cache) {
                // Se o cache tem menos de 3 minutos ou a data for passada (concluída), entrega instantaneamente
                $updatedTimestamp = strtotime($cache['updated_at'] ?? '2000-01-01');
                $isHoje = ($dataReferencia === date('Y-m-d'));
                if (!$isHoje || (time() - $updatedTimestamp) < 180) {
                    return $cache;
                }
            }
        }

        return $this->recalcularCacheDiario($usuarioId, $dataReferencia);
    }

    /**
     * Recalcula a consolidação estatística das apostas do dia e atualiza a tabela de cache no MySQL.
     */
    public function recalcularCacheDiario(int $usuarioId, string $dataReferencia): array
    {
        $db = \Config\Database::connect();
        $meta = $this->getMetaAtiva($usuarioId);

        // Agregação direta das apostas confirmadas para a data informada
        $sql = "
            SELECT 
                COUNT(*) as total_cadastradas,
                SUM(CASE WHEN status NOT IN ('Pendente', 'Cancelada', 'CANCELADA') THEN 1 ELSE 0 END) as total_liquidadas,
                SUM(CASE WHEN status = 'Pendente' THEN 1 ELSE 0 END) as pendentes,
                SUM(CASE WHEN status IN ('Ganha', 'Meio Ganha') THEN 1 ELSE 0 END) as greens,
                SUM(CASE WHEN status = 'ANULADA' THEN 1 ELSE 0 END) as pushes,
                SUM(CASE WHEN status IN ('Perdida', 'Meio Perdida') THEN 1 ELSE 0 END) as reds,
                COALESCE(AVG(odd), 0) as odd_media,
                COALESCE(SUM(valor_aposta), 0) as total_apostado,
                COALESCE(SUM(CASE WHEN status NOT IN ('Pendente', 'Cancelada', 'CANCELADA') THEN valor_aposta ELSE 0 END), 0) as total_liquidado,
                COALESCE(SUM(CASE 
                    WHEN status = 'Ganha' THEN COALESCE(NULLIF(ganhos_potenciais, 0), (valor_aposta * odd))
                    WHEN status = 'Meio Ganha' THEN (valor_aposta + ((COALESCE(NULLIF(ganhos_potenciais, 0), (valor_aposta * odd)) - valor_aposta) / 2))
                    WHEN status = 'ANULADA' THEN valor_aposta
                    WHEN status = 'Meio Perdida' THEN (valor_aposta * 0.5)
                    WHEN status = 'Cashout' THEN COALESCE(NULLIF(cash_out, 0), COALESCE(NULLIF(ganhos_potenciais, 0), valor_aposta))
                    ELSE 0 
                END), 0) as retornos_totais
            FROM apostas
            WHERE usuario_id = ?
              AND status NOT IN ('Cancelada', 'CANCELADA')
              AND DATE(DATE_SUB(COALESCE(data_hora_jogo, criado_em), INTERVAL 3 HOUR)) = ?
        ";

        $res = $db->query($sql, [$usuarioId, $dataReferencia])->getRowArray();

        $cadastradas = (int)($res['total_cadastradas'] ?? 0);
        $liquidadas  = (int)($res['total_liquidadas'] ?? 0);
        $pendentes   = (int)($res['pendentes'] ?? 0);
        $greens      = (int)($res['greens'] ?? 0);
        $pushes      = (int)($res['pushes'] ?? 0);
        $reds        = (int)($res['reds'] ?? 0);
        $oddMedia    = round((float)($res['odd_media'] ?? 0), 2);
        $apostado    = (float)($res['total_apostado'] ?? 0);
        $liquidado   = (float)($res['total_liquidado'] ?? 0);
        $retornos    = (float)($res['retornos_totais'] ?? 0);

        $lucroLiquido = round($retornos - $liquidado, 2);
        $roiPct       = $liquidado > 0 ? round(($lucroLiquido / $liquidado) * 100, 2) : 0.0;

        $alvoApostas = (int)($meta->total_apostas_alvo ?? 10);
        $lucroAlvo   = (float)($meta->lucro_alvo ?? 7.50);
        $stopLoss    = (float)($meta->stop_loss_diario ?? -30.00);
        $maxReds     = (int)($meta->max_reds ?? 3);

        $progressoApostasPct = $alvoApostas > 0 ? min(100.0, round(($cadastradas / $alvoApostas) * 100, 1)) : 0.0;
        $progressoLucroPct   = $lucroAlvo > 0 ? max(0.0, min(100.0, round(($lucroLiquido / $lucroAlvo) * 100, 1))) : 0.0;

        // Determinação do status do dia
        if ($lucroLiquido <= $stopLoss || ($reds >= $maxReds && $lucroLiquido < 0)) {
            $statusDia = 'STOP_LOSS_ATINGIDO';
        } elseif ($lucroLiquido >= $lucroAlvo) {
            $statusDia = 'META_BATIDA';
        } elseif ($pendentes === 0 && $liquidadas > 0) {
            if ($lucroLiquido > 0) {
                $statusDia = 'SUPERAVITARIO';
            } elseif ($lucroLiquido == 0) {
                $statusDia = 'NEUTRO';
            } else {
                $statusDia = 'DEFICITARIO';
            }
        } elseif ($cadastradas === 0) {
            $statusDia = 'SEM_APOSTAS';
        } else {
            $statusDia = 'EM_ANDAMENTO';
        }

        $now = date('Y-m-d H:i:s');
        $cacheData = [
            'usuario_id'                 => $usuarioId,
            'meta_config_id'             => $meta->id ?? null,
            'data_referencia'            => $dataReferencia,
            'total_apostas_cadastradas'  => $cadastradas,
            'total_apostas_liquidadas'   => $liquidadas,
            'total_apostado'             => $apostado,
            'odd_media_real'             => $oddMedia,
            'greens_count'               => $greens,
            'pushes_count'               => $pushes,
            'reds_count'                 => $reds,
            'pendentes_count'            => $pendentes,
            'ganhos_totais'              => $retornos,
            'lucro_liquido'              => $lucroLiquido,
            'roi_pct'                    => $roiPct,
            'progresso_apostas_pct'      => $progressoApostasPct,
            'progresso_lucro_pct'        => $progressoLucroPct,
            'status_dia'                 => $statusDia,
            'updated_at'                 => $now
        ];

        // Gravação idempotente com INSERT ... ON DUPLICATE KEY UPDATE no MySQL
        $db->table('metas_diarias_cache')->upsert($cacheData);

        return $cacheData;
    }

    /**
     * Retorna o Ciclo Rotativo Ativo de 10 Apostas (Rolling Cycle contínuo entre dias).
     */
    public function getCicloAtivoAcumulado(int $usuarioId, int $tamanhoCiclo = 10): array
    {
        $db = \Config\Database::connect();
        $meta = $this->getMetaAtiva($usuarioId);
        $tamanho = $tamanhoCiclo > 0 ? $tamanhoCiclo : (int)($meta->total_apostas_alvo ?? 10);

        // Busca as últimas N apostas ativas para compor o ciclo corrente
        $q = $db->table('apostas')
            ->where('usuario_id', $usuarioId)
            ->whereNotIn('status', ['Cancelada', 'CANCELADA'])
            ->orderBy('COALESCE(data_hora_jogo, criado_em)', 'DESC')
            ->orderBy('id', 'DESC')
            ->limit($tamanho)
            ->get();

        $apostas = $q ? $q->getResultObject() : [];

        $totalCiclo    = count($apostas);
        $liquidadas    = 0;
        $greens        = 0;
        $pushes        = 0;
        $reds          = 0;
        $pendentes     = 0;
        $apostado      = 0.0;
        $liquidado     = 0.0;
        $retornos      = 0.0;
        $somaOdds      = 0.0;

        foreach ($apostas as $ap) {
            $st = $ap->status;
            $val = (float)$ap->valor_aposta;
            $odd = (float)$ap->odd;
            $apostado += $val;
            $somaOdds += $odd;

            if ($st === 'Pendente') {
                $pendentes++;
            } else {
                $liquidadas++;
                $liquidado += $val;
                if (in_array($st, ['Ganha', 'Meio Ganha'])) {
                    $greens++;
                    $retornos += ($st === 'Ganha') ? ($val * $odd) : ($val + (($val * $odd - $val) / 2));
                } elseif ($st === 'ANULADA') {
                    $pushes++;
                    $retornos += $val;
                } elseif (in_array($st, ['Perdida', 'Meio Perdida'])) {
                    $reds++;
                    if ($st === 'Meio Perdida') {
                        $retornos += ($val * 0.5);
                    }
                } elseif ($st === 'Cashout') {
                    $retornos += (float)($ap->cash_out ?? $val);
                }
            }
        }

        $lucroLiquido = round($retornos - $liquidado, 2);
        $roiPct       = $liquidado > 0 ? round(($lucroLiquido / $liquidado) * 100, 2) : 0.0;
        $oddMedia     = $totalCiclo > 0 ? round($somaOdds / $totalCiclo, 2) : 0.0;
        $progressoPct = min(100.0, round(($totalCiclo / $tamanho) * 100, 1));

        return [
            'tamanho_ciclo'       => $tamanho,
            'total_no_ciclo'      => $totalCiclo,
            'liquidadas'          => $liquidadas,
            'pendentes'           => $pendentes,
            'greens'              => $greens,
            'pushes'              => $pushes,
            'reds'                => $reds,
            'odd_media'           => $oddMedia,
            'total_apostado'      => $apostado,
            'total_liquidado'     => $liquidado,
            'ganhos_totais'       => round($retornos, 2),
            'lucro_liquido'       => $lucroLiquido,
            'roi_pct'             => $roiPct,
            'progresso_pct'       => $progressoPct,
            'lucro_alvo'          => (float)($meta->lucro_alvo ?? 7.50),
            'is_completo'         => ($totalCiclo >= $tamanho && $pendentes === 0),
            'apostas'             => $apostas
        ];
    }

    /**
     * Retorna o histórico de dias cacheados dos últimos N dias.
     */
    public function getHistoricoDias(int $usuarioId, int $limite = 30): array
    {
        $db = \Config\Database::connect();
        return $db->table('metas_diarias_cache')
            ->where('usuario_id', $usuarioId)
            ->orderBy('data_referencia', 'DESC')
            ->limit($limite)
            ->get()
            ->getResultArray();
    }
}
