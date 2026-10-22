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
        'moeda_codigo',
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

    public const DATA_CORTE_DEBITO = '2026-10-22';

    /**
     * Retorna a cláusula SQL para filtrar apostas válidas nas metas diárias:
     * Para datas anteriores a 2026-10-22, preserva o histórico original.
     * A partir de 2026-10-22, exige aposta confirmada (confirmada = 1) E com registro de débito em conta corrente (DEBITO_APOSTA).
     */
    public function getSqlCondicaoApostaValida(string $aliasAposta = 'a'): string
    {
        $corte = self::DATA_CORTE_DEBITO;
        return "(
            DATE(DATE_SUB(COALESCE({$aliasAposta}.data_hora_jogo, {$aliasAposta}.criado_em), INTERVAL 3 HOUR)) < '{$corte}'
            OR (
                {$aliasAposta}.confirmada = 1
                AND EXISTS (
                    SELECT 1 FROM conta_corrente cc 
                    WHERE cc.aposta_id = {$aliasAposta}.id 
                      AND cc.tipo = 'DEBITO_APOSTA'
                )
            )
        )";
    }

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
            $moedaModel = new \App\Models\MoedaConfigModel();
            $moedaPadrao = $moedaModel->getDefaultMoeda();

            // Cria meta padrão inicial
            $default = [
                'usuario_id'         => $usuarioId,
                'moeda_codigo'       => $moedaPadrao->codigo ?? 'BRL',
                'titulo'             => 'Meta Padrão - Ciclo 10 Apostas',
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
            'moeda_codigo'       => !empty($dados['moeda_codigo']) ? strtoupper(trim($dados['moeda_codigo'])) : ($metaExistente->moeda_codigo ?? 'BRL'),
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
                $condValida = $this->getSqlCondicaoApostaValida('a');
                $betCheck = $db->query("
                    SELECT 
                        COUNT(*) as total_apostas,
                        MAX(a.updated_at) as max_updated_at
                    FROM apostas a
                    WHERE a.usuario_id = ?
                      AND a.status NOT IN ('Cancelada', 'CANCELADA')
                      AND DATE(DATE_SUB(COALESCE(a.data_hora_jogo, a.criado_em), INTERVAL 3 HOUR)) = ?
                      AND {$condValida}
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
        $condValida = $this->getSqlCondicaoApostaValida('a');
        $sql = "
            SELECT 
                COUNT(*) as total_cadastradas,
                SUM(CASE WHEN a.status NOT IN ('Pendente', 'Cancelada', 'CANCELADA') THEN 1 ELSE 0 END) as total_liquidadas,
                SUM(CASE WHEN a.status = 'Pendente' THEN 1 ELSE 0 END) as pendentes,
                SUM(CASE WHEN a.status = 'Ganha' THEN 1.0 WHEN a.status = 'Meio Ganha' THEN 0.5 ELSE 0.0 END) as greens,
                SUM(CASE WHEN a.status IN ('ANULADA', 'Anulada') THEN 1 ELSE 0 END) as pushes,
                SUM(CASE WHEN a.status = 'Perdida' THEN 1.0 WHEN a.status = 'Meio Perdida' THEN 0.5 ELSE 0.0 END) as reds,
                COALESCE(AVG(a.odd), 0) as odd_media,
                COALESCE(SUM(a.valor_aposta), 0) as total_apostado,
                COALESCE(SUM(CASE WHEN a.status NOT IN ('Pendente', 'Cancelada', 'CANCELADA') THEN a.valor_aposta ELSE 0 END), 0) as total_liquidado,
                COALESCE(SUM(CASE 
                    WHEN a.status = 'Ganha' THEN COALESCE(NULLIF(a.ganhos_potenciais, 0), (a.valor_aposta * a.odd))
                    WHEN a.status = 'Meio Ganha' THEN (a.valor_aposta + ((COALESCE(NULLIF(a.ganhos_potenciais, 0), (a.valor_aposta * a.odd)) - a.valor_aposta) / 2))
                    WHEN a.status IN ('ANULADA', 'Anulada') THEN a.valor_aposta
                    WHEN a.status = 'Meio Perdida' THEN (a.valor_aposta * 0.5)
                    WHEN a.status = 'Cashout' THEN COALESCE(NULLIF(a.cash_out, 0), COALESCE(NULLIF(a.ganhos_potenciais, 0), a.valor_aposta))
                    ELSE 0 
                END), 0) as retornos_totais
            FROM apostas a
            WHERE a.usuario_id = ?
              AND a.status NOT IN ('Cancelada', 'CANCELADA')
              AND DATE(DATE_SUB(COALESCE(a.data_hora_jogo, a.criado_em), INTERVAL 3 HOUR)) = ?
              AND {$condValida}
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

        $decRound = (!empty($meta->moeda_codigo) && strtoupper($meta->moeda_codigo) !== 'BRL') ? 8 : 2;
        $lucroLiquido = round($retornos - $liquidado, $decRound);
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
        } elseif ($pendentes > 0) {
            $statusDia = 'EM_ANDAMENTO';
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
    public function verificarEGerarAlertaStopLoss(int $usuarioId, $cicloAtivo = null): bool
    {
        $db = \Config\Database::connect();

        if (!is_array($cicloAtivo)) {
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
     * Se $numeroCiclo for null e $dataReferencia for informada, retorna o ciclo correspondente à data.
     * Se ambos forem null, retorna o ciclo ativo corrente.
     */
    public function getCicloSequencial(int $usuarioId, int $tamanhoCiclo = 10, ?int $numeroCiclo = null, ?string $dataReferencia = null): array
    {
        $db = \Config\Database::connect();
        $meta = $this->getMetaAtiva($usuarioId);
        $tamanho = $tamanhoCiclo > 0 ? $tamanhoCiclo : (int)($meta->total_apostas_alvo ?? 10);

        // Busca todas as apostas válidas em ordem cronológica
        $condValida = $this->getSqlCondicaoApostaValida('a');
        $todasApostas = $db->table('apostas a')
            ->select('a.*')
            ->where('a.usuario_id', $usuarioId)
            ->whereNotIn('a.status', ['Cancelada', 'CANCELADA'])
            ->where($condValida)
            ->orderBy('COALESCE(a.data_hora_jogo, a.criado_em)', 'ASC', false)
            ->orderBy('a.id', 'ASC')
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
                'stake_padrao'        => (float)($meta->stake_padrao ?? 10.00),
                'volume_alvo'         => round($tamanho * (float)($meta->stake_padrao ?? 10.00), 2),
                'apostas'             => []
            ];
        }

        $totalCiclosExistentes = (int)ceil($totalApostas / $tamanho);
        if ($totalCiclosExistentes < 1) {
            $totalCiclosExistentes = 1;
        }

        // Determinação estrutural do Ciclo Ativo Vigente
        $hojeBrasil = date('Y-m-d');
        $ultimoCicloFechado = 0;
        $cicloComJogosHoje = null;

        $moedaModel = new \App\Models\MoedaConfigModel();
        $metaMoeda = strtoupper(trim((string)($meta->moeda_codigo ?? 'BRL')));
        $metaObj = $moedaModel->getMoedaByCodigo($metaMoeda);
        $metaCotacaoBrl = (float)($metaObj->cotacao_brl ?? 1.0);
        $decRound = ($metaMoeda !== 'BRL') ? 8 : 2;

        for ($c = 1; $c <= $totalCiclosExistentes; $c++) {
            $offsetBloco = ($c - 1) * $tamanho;
            $blocoTeste = array_slice($todasApostas, $offsetBloco, $tamanho);
            $totalBloco = count($blocoTeste);

            $pendentesBloco = 0;
            $temJogosHoje = false;
            $redsBloco = 0.0;
            $retornosBloco = 0.0;
            $liquidadoBloco = 0.0;

            foreach ($blocoTeste as $bt) {
                $stBt = (string)($bt->status ?? '');
                $stBtNorm = strtolower(trim($stBt));
                $rawValBt = (float)($bt->valor_aposta ?? 0);
                $oddBt = (float)($bt->odd ?? 0);

                $apMoeda = !empty($bt->moeda_codigo) ? strtoupper(trim((string)$bt->moeda_codigo)) : 'BRL';
                $apCotacao = (float)($bt->cotacao_brl_snapshot ?? 1.0);
                $valBt = $this->normalizarValorParaMoedaMeta($rawValBt, $apMoeda, $apCotacao, $metaMoeda, $metaCotacaoBrl);

                if (in_array($stBtNorm, ['pendente', 'em andamento', 'aberta', 'ns'])) {
                    $pendentesBloco++;
                } else {
                    $liquidadoBloco += $valBt;
                    if ($stBt === 'Ganha') {
                        $retornosBloco += ($valBt * $oddBt);
                    } elseif ($stBt === 'Meio Ganha') {
                        $retornosBloco += ($valBt + (($valBt * $oddBt - $valBt) / 2));
                    } elseif (in_array($stBt, ['ANULADA', 'Anulada'])) {
                        $retornosBloco += $valBt;
                    } elseif ($stBt === 'Perdida') {
                        $redsBloco += 1.0;
                    } elseif ($stBt === 'Meio Perdida') {
                        $redsBloco += 0.5;
                        $retornosBloco += ($valBt * 0.5);
                    } elseif ($stBt === 'Cashout') {
                        $rawCash = ($bt->cash_out !== null) ? (float)$bt->cash_out : $rawValBt;
                        $cashBt = $this->normalizarValorParaMoedaMeta($rawCash, $apMoeda, $apCotacao, $metaMoeda, $metaCotacaoBrl);
                        $retornosBloco += $cashBt;
                    }
                }

                $dtAposta = !empty($bt->data_hora_jogo) ? $bt->data_hora_jogo : ($bt->criado_em ?? null);
                $dtStr = $dtAposta ? date('Y-m-d', strtotime($dtAposta . ' -3 hours')) : $hojeBrasil;
                if ($dtStr === $hojeBrasil) {
                    $temJogosHoje = true;
                }
            }

            $lucroBloco = round($retornosBloco - $liquidadoBloco, $decRound);
            $stopLossConfig  = (float)($meta->stop_loss_diario ?? -30.00);
            $lucroAlvoConfig = (float)($meta->lucro_alvo ?? 7.50);
            $maxRedsConfig   = (int)($meta->max_reds ?? 3);

            $isStopLossBloco   = ($lucroBloco <= $stopLossConfig || ($redsBloco >= $maxRedsConfig && $lucroBloco < 0));
            $isMetaBatidaBloco = ($lucroBloco >= $lucroAlvoConfig && $totalBloco >= $tamanho && $pendentesBloco === 0);

            // Regra Canônica: Bloco fecha e é bloqueado se atingir Stop Loss consolidado, Meta Batida consolidada ou completar 10 apostas sem pendentes
            $isFechado = (($isStopLossBloco && $pendentesBloco === 0) || $isMetaBatidaBloco || ($totalBloco >= $tamanho && $pendentesBloco === 0));
            if ($isFechado) {
                $ultimoCicloFechado = $c;
            }
            // Um bloco que atingiu Stop Loss ou Meta Batida NUNCA participa de novos jogos ativos
            if ($temJogosHoje && !$isFechado && $cicloComJogosHoje === null) {
                $cicloComJogosHoje = $c;
            }
        }

        // Ciclo ativo é prioritariamente o ciclo com jogos de hoje em andamento, ou o primeiro após o último fechado
        if ($cicloComJogosHoje !== null) {
            $cicloAtivoNum = $cicloComJogosHoje;
        } elseif ($ultimoCicloFechado < $totalCiclosExistentes) {
            $cicloAtivoNum = $ultimoCicloFechado + 1;
        } else {
            $cicloAtivoNum = max(1, $totalCiclosExistentes);
        }

        // Seleção do ciclo a ser exibido:
        // 1. Se especificado explicitamente via query string (?ciclo=X)
        // 2. Se informada dataReferencia, busca o ciclo que contém as apostas dessa data
        // 3. Fallback para o ciclo ativo vigente
        $cicloAlvo = null;
        if ($numeroCiclo !== null && $numeroCiclo >= 1 && $numeroCiclo <= $totalCiclosExistentes) {
            $cicloAlvo = $numeroCiclo;
        } elseif (!empty($dataReferencia)) {
            foreach ($todasApostas as $idx => $ap) {
                $dtAp = !empty($ap->data_hora_jogo) ? $ap->data_hora_jogo : ($ap->criado_em ?? null);
                $dtApBr = $dtAp ? date('Y-m-d', strtotime($dtAp . ' -3 hours')) : null;
                if ($dtApBr === $dataReferencia) {
                    $cicloAlvo = (int)floor($idx / $tamanho) + 1;
                }
            }
        }

        if ($cicloAlvo === null || $cicloAlvo < 1 || $cicloAlvo > $totalCiclosExistentes) {
            $cicloAlvo = $cicloAtivoNum;
        }

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
            $rawVal = (float)$ap->valor_aposta;
            $odd = (float)$ap->odd;

            $apMoeda = !empty($ap->moeda_codigo) ? strtoupper(trim((string)$ap->moeda_codigo)) : 'BRL';
            $apCotacao = (float)($ap->cotacao_brl_snapshot ?? 1.0);
            $val = $this->normalizarValorParaMoedaMeta($rawVal, $apMoeda, $apCotacao, $metaMoeda, $metaCotacaoBrl);

            $apostado += $val;
            $somaOdds += $odd;

            $stNorm = strtolower(trim((string)$st));
            if (in_array($stNorm, ['pendente', 'em andamento', 'aberta', 'ns'])) {
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
                    $rawCash = ($ap->cash_out !== null) ? (float)$ap->cash_out : $rawVal;
                    $cashVal = $this->normalizarValorParaMoedaMeta($rawCash, $apMoeda, $apCotacao, $metaMoeda, $metaCotacaoBrl);
                    $retornos += $cashVal;
                }
            }
        }

        $lucroLiquido = round($retornos - $liquidado, $decRound);
        $roiPct       = $liquidado > 0 ? round(($lucroLiquido / $liquidado) * 100, 2) : 0.0;
        $oddMedia     = $totalCiclo > 0 ? round($somaOdds / $totalCiclo, 2) : 0.0;
        $progressoPct = min(100.0, round(($totalCiclo / $tamanho) * 100, 1));
        $lucroAlvo = (float)($meta->lucro_alvo ?? 7.50);
        $stopLoss  = (float)($meta->stop_loss_diario ?? -30.00);
        $maxReds   = (int)($meta->max_reds ?? 3);

        $isStopLoss   = ($lucroLiquido <= $stopLoss || ($reds >= $maxReds && $lucroLiquido < 0));
        $isMetaBatida = ($lucroLiquido >= $lucroAlvo && $totalCiclo >= $tamanho && $pendentes === 0);

        // Bloco é bloqueado e fechado se atingir Stop Loss consolidado, Meta Batida consolidada ou completar 10 apostas sem pendentes
        $isFechado = (($isStopLoss && $pendentes === 0) || $isMetaBatida || ($totalCiclo >= $tamanho && $pendentes === 0));
        $isAtivo   = ($cicloAlvo === $cicloAtivoNum);

        if ($isStopLoss && $pendentes === 0) {
            $statusCiclo = 'STOP_LOSS_ATINGIDO';
        } elseif ($pendentes > 0) {
            $statusCiclo = 'EM_ANDAMENTO';
        } elseif ($isMetaBatida) {
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
            'total_apostado'      => round($apostado, $decRound),
            'total_liquidado'     => round($liquidado, $decRound),
            'ganhos_totais'       => round($retornos, $decRound),
            'lucro_liquido'       => $lucroLiquido,
            'roi_pct'             => $roiPct,
            'progresso_pct'       => $progressoPct,
            'lucro_alvo'          => $lucroAlvo,
            'stake_padrao'        => (float)($meta->stake_padrao ?? 10.00),
            'volume_alvo'         => round($tamanho * (float)($meta->stake_padrao ?? 10.00), $decRound),
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
     * Retorna o resumo dos ciclos fechados anteriores para histórico e auditoria (suporta filtro de datas)
     */
    public function getHistoricoCiclos(int $usuarioId, int $limite = 15, int $tamanhoCiclo = 10, ?string $dataInicio = null, ?string $dataFim = null): array
    {
        $db = \Config\Database::connect();
        $meta = $this->getMetaAtiva($usuarioId);
        $tamanho = $tamanhoCiclo > 0 ? $tamanhoCiclo : (int)($meta->total_apostas_alvo ?? 10);

        $condValida = $this->getSqlCondicaoApostaValida('a');
        $todasApostas = $db->table('apostas a')
            ->select('a.*')
            ->where('a.usuario_id', $usuarioId)
            ->whereNotIn('a.status', ['Cancelada', 'CANCELADA'])
            ->where($condValida)
            ->orderBy('COALESCE(a.data_hora_jogo, a.criado_em)', 'ASC', false)
            ->orderBy('a.id', 'ASC')
            ->get()
            ->getResultObject();

        $totalApostas = count($todasApostas);
        if ($totalApostas === 0) {
            return [];
        }

        $numCiclos = (int)ceil($totalApostas / $tamanho);
        $historico = [];

        $moedaModel = new \App\Models\MoedaConfigModel();
        $metaMoeda = strtoupper(trim((string)($meta->moeda_codigo ?? 'BRL')));
        $metaObj = $moedaModel->getMoedaByCodigo($metaMoeda);
        $metaCotacaoBrl = (float)($metaObj->cotacao_brl ?? 1.0);
        $decRound = ($metaMoeda !== 'BRL') ? 8 : 2;

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
                $rawVal = (float)$ap->valor_aposta;
                $odd = (float)$ap->odd;

                $apMoeda = !empty($ap->moeda_codigo) ? strtoupper(trim((string)$ap->moeda_codigo)) : 'BRL';
                $apCotacao = (float)($ap->cotacao_brl_snapshot ?? 1.0);
                $val = $this->normalizarValorParaMoedaMeta($rawVal, $apMoeda, $apCotacao, $metaMoeda, $metaCotacaoBrl);

                $stNorm = strtolower(trim((string)$st));
                if (in_array($stNorm, ['pendente', 'em andamento', 'aberta', 'ns'])) {
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
                        $rawCash = ($ap->cash_out !== null) ? (float)$ap->cash_out : $rawVal;
                        $cashVal = $this->normalizarValorParaMoedaMeta($rawCash, $apMoeda, $apCotacao, $metaMoeda, $metaCotacaoBrl);
                        $retornos += $cashVal;
                    }
                }
            }

            // Filtro por período de datas (intersecção com o intervalo do bloco)
            $cDataIni = !empty($dtInicio) ? substr($dtInicio, 0, 10) : null;
            $cDataFim = !empty($dtFim) ? substr($dtFim, 0, 10) : null;

            if (!empty($dataInicio) && $cDataFim !== null && $cDataFim < $dataInicio) {
                continue;
            }
            if (!empty($dataFim) && $cDataIni !== null && $cDataIni > $dataFim) {
                continue;
            }

            $lucroLiq = round($retornos - $liquidado, $decRound);
            $roi = $liquidado > 0 ? round(($lucroLiq / $liquidado) * 100, 2) : 0.0;
            $stopLossConfig  = (float)($meta->stop_loss_diario ?? -30.00);
            $lucroAlvoConfig = (float)($meta->lucro_alvo ?? 7.50);
            $maxRedsConfig   = (int)($meta->max_reds ?? 3);

            $isStopLoss = ($lucroLiq <= $stopLossConfig || ($reds >= $maxRedsConfig && $lucroLiq < 0));
            $isMetaBatida = ($lucroLiq >= $lucroAlvoConfig && $totalB >= $tamanho && $pendentes === 0);
            $isFechado = (($isStopLoss && $pendentes === 0) || $isMetaBatida || ($totalB >= $tamanho && $pendentes === 0));

            if ($isStopLoss && $pendentes === 0) {
                $status = 'STOP_LOSS_ATINGIDO';
            } elseif ($pendentes > 0) {
                $status = 'EM_ANDAMENTO';
            } elseif ($isMetaBatida) {
                $status = 'META_BATIDA';
            } elseif ($isFechado) {
                if ($lucroLiq > 0) {
                    $status = 'SUPERAVITARIO';
                } elseif ($lucroLiq == 0) {
                    $status = 'NEUTRO';
                } else {
                    $status = 'DEFICITARIO';
                }
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
                'investido'      => $liquidado,
                'retornos'       => $retornos,
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
     * Retorna o histórico de dias cacheados dos últimos N dias (suporta filtro de datas).
     */
    public function getHistoricoDias(int $usuarioId, int $limite = 30, ?string $dataInicio = null, ?string $dataFim = null): array
    {
        $db = \Config\Database::connect();

        // Se datas forem fornecidas, assegura que dias com apostas tenham cache gerado
        if (!empty($dataInicio) || !empty($dataFim)) {
            $condValida = $this->getSqlCondicaoApostaValida('a');
            $bApostas = $db->table('apostas a')
                ->select("DATE(COALESCE(a.data_hora_jogo, a.criado_em)) as dia_aposta", false)
                ->where('a.usuario_id', $usuarioId)
                ->whereNotIn('a.status', ['Cancelada', 'CANCELADA'])
                ->where($condValida);
            if (!empty($dataInicio)) {
                $bApostas->where("DATE(COALESCE(data_hora_jogo, criado_em)) >=", $dataInicio, false);
            }
            if (!empty($dataFim)) {
                $bApostas->where("DATE(COALESCE(data_hora_jogo, criado_em)) <=", $dataFim, false);
            }
            $bApostas->groupBy("DATE(COALESCE(data_hora_jogo, criado_em))");
            $diasComApostas = $bApostas->get()->getResultArray();

            foreach ($diasComApostas as $dca) {
                $diaStr = $dca['dia_aposta'] ?? null;
                if (!empty($diaStr)) {
                    $existeNoCache = $db->table('metas_diarias_cache')
                        ->where('usuario_id', $usuarioId)
                        ->where('data_referencia', $diaStr)
                        ->countAllResults();
                    if ($existeNoCache === 0) {
                        $this->recalcularCacheDiario($usuarioId, $diaStr);
                    }
                }
            }
        }

        // Identifica no cache se existem dias passados gravados com jogos pendentes que já foram finalizados
        $builderPendentes = $db->table('metas_diarias_cache')
            ->select('data_referencia')
            ->where('usuario_id', $usuarioId)
            ->where('data_referencia <', date('Y-m-d'))
            ->where('pendentes_count >', 0);

        if (!empty($dataInicio)) {
            $builderPendentes->where('data_referencia >=', $dataInicio);
        }
        if (!empty($dataFim)) {
            $builderPendentes->where('data_referencia <=', $dataFim);
        }

        $diasPendentesPassados = $builderPendentes->get()->getResultArray();

        foreach ($diasPendentesPassados as $dp) {
            $this->recalcularCacheDiario($usuarioId, $dp['data_referencia']);
        }

        $builder = $db->table('metas_diarias_cache')
            ->where('usuario_id', $usuarioId);

        if (!empty($dataInicio)) {
            $builder->where('data_referencia >=', $dataInicio);
        }
        if (!empty($dataFim)) {
            $builder->where('data_referencia <=', $dataFim);
        }

        $builder->orderBy('data_referencia', 'DESC');

        if (empty($dataInicio) && empty($dataFim)) {
            $builder->limit($limite);
        } else {
            $builder->limit(max($limite, 365));
        }

        return $builder->get()->getResultArray();
    }

    /**
     * Converte o valor de uma aposta para a moeda da meta caso pertençam a moedas diferentes.
     */
    public function normalizarValorParaMoedaMeta(float $valor, string $moedaOrigem, float $cotacaoOrigemBrl, string $moedaDestino, float $cotacaoDestinoBrl): float
    {
        if ($moedaOrigem === $moedaDestino || $valor == 0.0) {
            return $valor;
        }

        // Converte primeiro para BRL
        $valorBrl = ($moedaOrigem === 'BRL') ? $valor : ($valor * ($cotacaoOrigemBrl > 0 ? $cotacaoOrigemBrl : 1.0));

        // Converte de BRL para a moeda de destino
        if ($moedaDestino === 'BRL') {
            return $valorBrl;
        }

        $cotDestino = ($cotacaoDestinoBrl > 0) ? $cotacaoDestinoBrl : 1.0;
        return $valorBrl / $cotDestino;
    }
}
