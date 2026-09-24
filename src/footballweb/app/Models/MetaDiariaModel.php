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
                $cacheUpdated = strtotime($cache['updated_at'] ?? '2000-01-01');
                $isHoje = ($dataReferencia === date('Y-m-d'));
                $temPendentes = ((int)($cache['pendentes_count'] ?? 0) > 0);

                // Consulta leve no banco para verificar integridade e frescor das apostas
                $betCheck = $db->query("
                    SELECT 
                        COUNT(*) as total_apostas,
                        MAX(updated_at) as max_updated_at
                    FROM apostas
                    WHERE usuario_id = ?
                      AND status NOT IN ('Cancelada', 'CANCELADA')
                      AND DATE(DATE_SUB(COALESCE(data_hora_jogo, criado_em), INTERVAL 3 HOUR)) = ?
                ", [$usuarioId, $dataReferencia])->getRowArray();

                $totalApostas = (int)($betCheck['total_apostas'] ?? 0);
                $maxUpdated = !empty($betCheck['max_updated_at']) ? strtotime($betCheck['max_updated_at']) : 0;

                // Cache é válido somente se:
                // 1) Total de apostas for idêntico ao cadastrado
                // 2) Nenhuma aposta foi alterada/liquidada após a data do cache (max_updated_at <= cacheUpdated)
                // 3) Se tinha pendentes gravados, não pode ser dia passado (jogos encerraram após o cache) nem passar de 180s em dia de hoje
                $cacheValido = ($totalApostas === (int)($cache['total_apostas_cadastradas'] ?? 0))
                            && ($maxUpdated <= $cacheUpdated);

                if ($cacheValido) {
                    if ($isHoje && $temPendentes && (time() - $cacheUpdated) >= 180) {
                        $cacheValido = false;
                    } elseif (!$isHoje && $temPendentes) {
                        $cacheValido = false;
                    }
                }

                if ($cacheValido) {
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
                SUM(CASE WHEN status = 'Ganha' THEN 1.0 WHEN status = 'Meio Ganha' THEN 0.5 ELSE 0.0 END) as greens,
                SUM(CASE WHEN status IN ('ANULADA', 'Anulada') THEN 1 ELSE 0 END) as pushes,
                SUM(CASE WHEN status = 'Perdida' THEN 1.0 WHEN status = 'Meio Perdida' THEN 0.5 ELSE 0.0 END) as reds,
                COALESCE(AVG(odd), 0) as odd_media,
                COALESCE(SUM(valor_aposta), 0) as total_apostado,
                COALESCE(SUM(CASE WHEN status NOT IN ('Pendente', 'Cancelada', 'CANCELADA') THEN valor_aposta ELSE 0 END), 0) as total_liquidado,
                COALESCE(SUM(CASE 
                    WHEN status = 'Ganha' THEN COALESCE(NULLIF(ganhos_potenciais, 0), (valor_aposta * odd))
                    WHEN status = 'Meio Ganha' THEN (valor_aposta + ((COALESCE(NULLIF(ganhos_potenciais, 0), (valor_aposta * odd)) - valor_aposta) / 2))
                    WHEN status IN ('ANULADA', 'Anulada') THEN valor_aposta
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
        $greens      = round((float)($res['greens'] ?? 0.0), 1);
        $pushes      = (int)($res['pushes'] ?? 0);
        $reds        = round((float)($res['reds'] ?? 0.0), 1);
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

        // A checagem de Stop Loss em tempo real é soberana na meta vigente (Ciclo Sequencial Ativo)
        if ($dataReferencia === date('Y-m-d')) {
            $this->verificarEGerarAlertaStopLoss($usuarioId);
        }

        return $cacheData;
    }

    /**
     * Verifica se a meta vigente (Ciclo Sequencial Ativo) atingiu o Stop Loss e gera notificação no sininho (notificacoes_usuario),
     * evitando duplicidades para o mesmo ciclo. Se o ciclo vigente estiver saudável, despina alertas obsoletos.
     */
    public function verificarEGerarAlertaStopLoss(int $usuarioId, ?array $cicloAtivo = null): bool
    {
        $db = \Config\Database::connect();

        if ($cicloAtivo === null) {
            $cicloAtivo = $this->getCicloSequencial($usuarioId);
        }

        $numCiclo    = (int)($cicloAtivo['numero_ciclo'] ?? 1);
        $statusCiclo = $cicloAtivo['status_ciclo'] ?? 'EM_ANDAMENTO';
        $reds        = round((float)($cicloAtivo['reds'] ?? 0.0), 1);
        $lucro       = (float)($cicloAtivo['lucro_liquido'] ?? 0);

        // Se a meta vigente (ciclo ativo) não atingiu Stop Loss, despina notificações anteriores obsoletas
        if ($statusCiclo !== 'STOP_LOSS_ATINGIDO') {
            try {
                $db->table('notificacoes_usuario')
                    ->where('usuario_id', $usuarioId)
                    ->where('tipo', 'STOP_LOSS_DIARIO')
                    ->where('pinada', 1)
                    ->update(['pinada' => 0]);
            } catch (\Throwable $e) {
                log_message('error', "[StopLoss Alerta] Erro ao despinadar notificacoes antigas para usuario {$usuarioId}: " . $e->getMessage());
            }
            return false;
        }

        // Evita disparos duplicados para o mesmo ciclo sequencial
        $jaNotificado = $db->table('notificacoes_usuario')
            ->where('usuario_id', $usuarioId)
            ->where('tipo', 'STOP_LOSS_DIARIO')
            ->like('mensagem', "Ciclo Sequencial #{$numCiclo}")
            ->countAllResults();

        if ($jaNotificado > 0) {
            return false;
        }

        $redsFmt = (fmod($reds, 1.0) == 0.0) ? (string)(int)$reds : number_format($reds, 1, ',', '.');
        $lucroFmt = ($lucro >= 0 ? '+' : '') . 'R$ ' . number_format($lucro, 2, ',', '.');
        $titulo = "⚠️ Stop Loss Atingido - Ciclo Sequencial #{$numCiclo}";
        $msg = "Atenção: O limite de segurança da meta vigente (Ciclo Sequencial #{$numCiclo}) foi atingido ({$redsFmt} Reds / Saldo: {$lucroFmt}). Recomendado pausar novas apostas neste ciclo para proteger sua banca.";
        $link = "/metas?ciclo={$numCiclo}#cardCiclo";

        try {
            return (bool)$db->table('notificacoes_usuario')->insert([
                'usuario_id'  => $usuarioId,
                'aposta_id'   => null,
                'fixture_id'  => null,
                'tipo'        => 'STOP_LOSS_DIARIO',
                'titulo'      => $titulo,
                'mensagem'    => $msg,
                'link'        => $link,
                'lida'        => 0,
                'pinada'      => 1,
                'criado_em'   => date('Y-m-d H:i:s')
            ]);
        } catch (\Throwable $e) {
            log_message('error', "[StopLoss Alerta] Erro ao gravar notificacao de Stop Loss para usuario {$usuarioId}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Retorna o Ciclo Sequencial Fechado (Bloco Fixo de 10 Apostas).
     * Se $numeroCiclo for null, retorna o ciclo ativo corrente.
     */
    public function getCicloSequencial(int $usuarioId, int $tamanhoCiclo = 10, ?int $numeroCiclo = null): array
    {
        $db = \Config\Database::connect();
        $meta = $this->getMetaAtiva($usuarioId);
        $tamanho = $tamanhoCiclo > 0 ? $tamanhoCiclo : (int)($meta->total_apostas_alvo ?? 10);

        // Busca todas as apostas válidas em ordem cronológica
        $todasApostas = $db->table('apostas')
            ->where('usuario_id', $usuarioId)
            ->whereNotIn('status', ['Cancelada', 'CANCELADA'])
            ->orderBy('COALESCE(data_hora_jogo, criado_em)', 'ASC', false)
            ->orderBy('id', 'ASC')
            ->get()
            ->getResultObject();

        $totalApostas = count($todasApostas);

        if ($totalApostas === 0) {
            return [
                'numero_ciclo'        => 1,
                'total_ciclos'        => 1,
                'ciclo_ativo_num'     => 1,
                'is_ativo'            => true,
                'is_fechado'          => false,
                'status_ciclo'        => 'EM_ANDAMENTO',
                'tamanho_ciclo'       => $tamanho,
                'total_no_ciclo'      => 0,
                'liquidadas'          => 0,
                'pendentes'           => 0,
                'greens'              => 0,
                'pushes'              => 0,
                'reds'                => 0,
                'cashouts'            => 0,
                'odd_media'           => 0.0,
                'total_apostado'      => 0.0,
                'total_liquidado'     => 0.0,
                'ganhos_totais'       => 0.0,
                'lucro_liquido'       => 0.0,
                'roi_pct'             => 0.0,
                'progresso_pct'       => 0.0,
                'lucro_alvo'          => (float)($meta->lucro_alvo ?? 7.50),
                'apostas'             => []
            ];
        }

        $numCompletos = (int)floor($totalApostas / $tamanho);
        $resto = $totalApostas % $tamanho;

        // Se tem sobra, o ciclo ativo é $numCompletos + 1
        // Se resto == 0, checa se a última aposta do último bloco tem pendentes
        if ($resto > 0) {
            $totalCiclosExistentes = $numCompletos + 1;
            $cicloAtivoNum = $totalCiclosExistentes;
        } else {
            $ultimoBloco = array_slice($todasApostas, ($numCompletos - 1) * $tamanho, $tamanho);
            $temPendentes = false;
            foreach ($ultimoBloco as $ub) {
                if ($ub->status === 'Pendente') {
                    $temPendentes = true;
                    break;
                }
            }
            if ($temPendentes) {
                $totalCiclosExistentes = $numCompletos;
                $cicloAtivoNum = $numCompletos;
            } else {
                $totalCiclosExistentes = $numCompletos + 1;
                $cicloAtivoNum = $totalCiclosExistentes;
            }
        }

        // Determina qual ciclo exibir
        $cicloAlvo = ($numeroCiclo !== null && $numeroCiclo >= 1 && $numeroCiclo <= $totalCiclosExistentes)
            ? $numeroCiclo
            : $cicloAtivoNum;

        $offset = ($cicloAlvo - 1) * $tamanho;
        $apostasCiclo = array_slice($todasApostas, $offset, $tamanho);

        $totalCiclo    = count($apostasCiclo);
        $liquidadas    = 0;
        $greens        = 0.0;
        $pushes        = 0;
        $reds          = 0.0;
        $cashouts      = 0;
        $pendentes     = 0;
        $apostado      = 0.0;
        $liquidado     = 0.0;
        $retornos      = 0.0;
        $somaOdds      = 0.0;

        foreach ($apostasCiclo as $ap) {
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
                if ($st === 'Ganha') {
                    $greens += 1.0;
                    $retornos += ($val * $odd);
                } elseif ($st === 'Meio Ganha') {
                    $greens += 0.5;
                    $retornos += ($val + (($val * $odd - $val) / 2));
                } elseif (in_array($st, ['ANULADA', 'Anulada'])) {
                    $pushes++;
                    $retornos += $val;
                } elseif ($st === 'Perdida') {
                    $reds += 1.0;
                } elseif ($st === 'Meio Perdida') {
                    $reds += 0.5;
                    $retornos += ($val * 0.5);
                } elseif ($st === 'Cashout') {
                    $cashouts++;
                    $retornos += (float)($ap->cash_out ?? $val);
                }
            }
        }

        $lucroLiquido = round($retornos - $liquidado, 2);
        $roiPct       = $liquidado > 0 ? round(($lucroLiquido / $liquidado) * 100, 2) : 0.0;
        $oddMedia     = $totalCiclo > 0 ? round($somaOdds / $totalCiclo, 2) : 0.0;
        $progressoPct = min(100.0, round(($totalCiclo / $tamanho) * 100, 1));
        $isFechado    = ($totalCiclo >= $tamanho && $pendentes === 0);
        $isAtivo      = ($cicloAlvo === $cicloAtivoNum);

        $lucroAlvo = (float)($meta->lucro_alvo ?? 7.50);
        $stopLoss  = (float)($meta->stop_loss_diario ?? -30.00);
        $maxReds   = (int)($meta->max_reds ?? 3);

        if ($lucroLiquido <= $stopLoss || ($reds >= $maxReds && $lucroLiquido < 0)) {
            $statusCiclo = 'STOP_LOSS_ATINGIDO';
        } elseif ($lucroLiquido >= $lucroAlvo) {
            $statusCiclo = 'META_BATIDA';
        } elseif ($isFechado) {
            if ($lucroLiquido > 0) {
                $statusCiclo = 'SUPERAVITARIO';
            } elseif ($lucroLiquido == 0) {
                $statusCiclo = 'NEUTRO';
            } else {
                $statusCiclo = 'DEFICITARIO';
            }
        } else {
            $statusCiclo = 'EM_ANDAMENTO';
        }

        return [
            'numero_ciclo'        => $cicloAlvo,
            'total_ciclos'        => $totalCiclosExistentes,
            'ciclo_ativo_num'     => $cicloAtivoNum,
            'is_ativo'            => $isAtivo,
            'is_fechado'          => $isFechado,
            'status_ciclo'        => $statusCiclo,
            'tamanho_ciclo'       => $tamanho,
            'total_no_ciclo'      => $totalCiclo,
            'liquidadas'          => $liquidadas,
            'pendentes'           => $pendentes,
            'greens'              => $greens,
            'pushes'              => $pushes,
            'reds'                => $reds,
            'cashouts'            => $cashouts,
            'odd_media'           => $oddMedia,
            'total_apostado'      => $apostado,
            'total_liquidado'     => $liquidado,
            'ganhos_totais'       => round($retornos, 2),
            'lucro_liquido'       => $lucroLiquido,
            'roi_pct'             => $roiPct,
            'progresso_pct'       => $progressoPct,
            'lucro_alvo'          => $lucroAlvo,
            'apostas'             => $apostasCiclo
        ];
    }

    /**
     * Alias para compatibilidade anterior
     */
    public function getCicloAtivoAcumulado(int $usuarioId, int $tamanhoCiclo = 10): array
    {
        return $this->getCicloSequencial($usuarioId, $tamanhoCiclo, null);
    }

    /**
     * Retorna o resumo dos ciclos fechados anteriores para histórico e auditoria
     */
    public function getHistoricoCiclos(int $usuarioId, int $limite = 15, int $tamanhoCiclo = 10): array
    {
        $db = \Config\Database::connect();
        $meta = $this->getMetaAtiva($usuarioId);
        $tamanho = $tamanhoCiclo > 0 ? $tamanhoCiclo : (int)($meta->total_apostas_alvo ?? 10);

        $todasApostas = $db->table('apostas')
            ->where('usuario_id', $usuarioId)
            ->whereNotIn('status', ['Cancelada', 'CANCELADA'])
            ->orderBy('COALESCE(data_hora_jogo, criado_em)', 'ASC', false)
            ->orderBy('id', 'ASC')
            ->get()
            ->getResultObject();

        $totalApostas = count($todasApostas);
        if ($totalApostas === 0) {
            return [];
        }

        $numCiclos = (int)ceil($totalApostas / $tamanho);
        $historico = [];

        for ($c = $numCiclos; $c >= 1; $c--) {
            $offset = ($c - 1) * $tamanho;
            $bloco = array_slice($todasApostas, $offset, $tamanho);
            if (empty($bloco)) continue;

            $totalB = count($bloco);
            $greens = 0.0; $pushes = 0; $reds = 0.0; $cashouts = 0; $pendentes = 0;
            $liquidado = 0.0; $retornos = 0.0;

            $dtInicio = null;
            $dtFim = null;

            foreach ($bloco as $ap) {
                $rawDt = !empty($ap->data_hora_jogo) ? $ap->data_hora_jogo : $ap->criado_em;
                if ($dtInicio === null) $dtInicio = $rawDt;
                $dtFim = $rawDt;

                $st = $ap->status;
                $val = (float)$ap->valor_aposta;
                $odd = (float)$ap->odd;

                if ($st === 'Pendente') {
                    $pendentes++;
                } else {
                    $liquidado += $val;
                    if ($st === 'Ganha') {
                        $greens += 1.0;
                        $retornos += ($val * $odd);
                    } elseif ($st === 'Meio Ganha') {
                        $greens += 0.5;
                        $retornos += ($val + (($val * $odd - $val) / 2));
                    } elseif (in_array($st, ['ANULADA', 'Anulada'])) {
                        $pushes++;
                        $retornos += $val;
                    } elseif ($st === 'Perdida') {
                        $reds += 1.0;
                    } elseif ($st === 'Meio Perdida') {
                        $reds += 0.5;
                        $retornos += ($val * 0.5);
                    } elseif ($st === 'Cashout') {
                        $cashouts++;
                        $retornos += (float)($ap->cash_out ?? $val);
                    }
                }
            }

            $lucroLiq = round($retornos - $liquidado, 2);
            $roi = $liquidado > 0 ? round(($lucroLiq / $liquidado) * 100, 2) : 0.0;
            $isFechado = ($totalB >= $tamanho && $pendentes === 0);

            if ($lucroLiq <= (float)($meta->stop_loss_diario ?? -30.00)) {
                $status = 'STOP_LOSS_ATINGIDO';
            } elseif ($lucroLiq >= (float)($meta->lucro_alvo ?? 7.50)) {
                $status = 'META_BATIDA';
            } elseif ($isFechado) {
                $status = ($lucroLiq > 0) ? 'SUPERAVITARIO' : (($lucroLiq < 0) ? 'DEFICITARIO' : 'NEUTRO');
            } else {
                $status = 'EM_ANDAMENTO';
            }

            $historico[] = [
                'numero_ciclo'   => $c,
                'total_apostas'  => $totalB,
                'tamanho_ciclo'  => $tamanho,
                'data_inicio'    => $dtInicio,
                'data_fim'       => $dtFim,
                'greens'         => $greens,
                'pushes'         => $pushes,
                'reds'           => $reds,
                'cashouts'       => $cashouts,
                'pendentes'      => $pendentes,
                'lucro_liquido'  => $lucroLiq,
                'roi_pct'        => $roi,
                'is_fechado'     => $isFechado,
                'status'         => $status
            ];

            if (count($historico) >= $limite) {
                break;
            }
        }

        return $historico;
    }

    /**
     * Retorna o histórico de dias cacheados dos últimos N dias.
     */
    public function getHistoricoDias(int $usuarioId, int $limite = 30): array
    {
        $db = \Config\Database::connect();

        // Identifica no cache se existem dias passados gravados com jogos pendentes que já foram finalizados
        $diasPendentesPassados = $db->table('metas_diarias_cache')
            ->select('data_referencia')
            ->where('usuario_id', $usuarioId)
            ->where('data_referencia <', date('Y-m-d'))
            ->where('pendentes_count >', 0)
            ->get()
            ->getResultArray();

        foreach ($diasPendentesPassados as $dp) {
            $this->recalcularCacheDiario($usuarioId, $dp['data_referencia']);
        }

        return $db->table('metas_diarias_cache')
            ->where('usuario_id', $usuarioId)
            ->orderBy('data_referencia', 'DESC')
            ->limit($limite)
            ->get()
            ->getResultArray();
    }
}
