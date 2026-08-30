<?php

namespace App\Models;

use CodeIgniter\Model;

class ContaCorrenteModel extends Model
{
    protected $table            = 'conta_corrente';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'object';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'usuario_id',
        'aposta_id',
        'tipo',
        'descricao',
        'valor',
        'saldo_anterior',
        'saldo_posterior',
        'criado_em'
    ];

    protected $useTimestamps = false;
    protected $createdField  = 'criado_em';

    /**
     * Sincroniza apostas históricas se o usuário ainda não possuir lançamentos na conta corrente
     */
    public function syncApostasHistorico(int $usuarioId): void
    {
        $db = \Config\Database::connect();
        $qCc = $db->table($this->table)->where('usuario_id', $usuarioId)->get();
        if ($qCc && count($qCc->getResultObject()) > 0) {
            return;
        }

        $qApostas = $db->table('apostas')
            ->where('usuario_id', $usuarioId)
            ->orderBy('criado_em', 'ASC')
            ->orderBy('id', 'ASC')
            ->get();

        $apostas = $qApostas ? $qApostas->getResultObject() : [];
        if (empty($apostas)) {
            return;
        }

        foreach ($apostas as $ap) {
            $isConfirmada = isset($ap->confirmada) ? (int)$ap->confirmada : 1;
            if ($isConfirmada === 0 || $ap->status === 'Não Confirmada') {
                continue; // Pula apostas em rascunho/não confirmadas
            }

            $apostaId = (int)$ap->id;
            $valorAposta = (float)$ap->valor_aposta;
            $status = $ap->status;
            $timeCasa = $ap->time_casa;
            $timeFora = $ap->time_fora;
            $palpite = $ap->palpite;
            $ganhosPotenciais = (float)$ap->ganhos_potenciais;
            $cashOut = $ap->cash_out !== null ? (float)$ap->cash_out : null;

            $this->debitarAposta($usuarioId, $apostaId, $valorAposta, "Aposta #{$apostaId} ({$timeCasa} x {$timeFora} - {$palpite})");

            if (in_array($status, ['Ganha', 'Meio Ganha', 'ANULADA', 'Meio Perdida', 'Cashout'])) {
                $retorno = ($status === 'Cashout' && $cashOut !== null) ? $cashOut : $ganhosPotenciais;
                $this->creditarRetornoAposta($usuarioId, $apostaId, (float)$retorno, "Retorno Aposta #{$apostaId} ({$status})");
            }
        }
    }

    /**
     * Obter saldo atual da conta corrente do usuário
     */
    public function getSaldo(int $usuarioId): float
    {
        $db = \Config\Database::connect();
        $qUser = $db->table('usuario')
            ->select('saldo_conta_corrente')
            ->where('id', $usuarioId)
            ->get();

        if ($qUser && ($rowUser = $qUser->getRow()) && isset($rowUser->saldo_conta_corrente)) {
            return (float)$rowUser->saldo_conta_corrente;
        }

        // Se não houver no usuário, calcula pelo último lançamento da conta corrente
        $qCc = $db->table($this->table)
            ->select('saldo_posterior')
            ->where('usuario_id', $usuarioId)
            ->orderBy('id', 'DESC')
            ->limit(1)
            ->get();

        $lastCc = $qCc ? $qCc->getRow() : null;

        return $lastCc ? (float)$lastCc->saldo_posterior : 0.00;
    }

    /**
     * Adicionar créditos monetários na conta corrente (Depósito / Aporte)
     */
    public function adicionarCredito(int $usuarioId, float $valor, string $descricao = 'Crédito Adicionado na Conta Corrente'): array
    {
        if ($valor <= 0) {
            return ['success' => false, 'message' => 'O valor do crédito deve ser maior que zero.'];
        }

        $db = \Config\Database::connect();
        $db->transStart();

        $saldoAnterior = $this->getSaldo($usuarioId);
        $saldoPosterior = round($saldoAnterior + $valor, 2);
        $now = (new \DateTime('now', new \DateTimeZone('America/Sao_Paulo')))->format('Y-m-d H:i:s');

        $db->table($this->table)->insert([
            'usuario_id'      => $usuarioId,
            'aposta_id'       => null,
            'tipo'            => 'CREDITO_ADICIONADO',
            'descricao'       => $descricao,
            'valor'           => $valor,
            'saldo_anterior'  => $saldoAnterior,
            'saldo_posterior' => $saldoPosterior,
            'criado_em'       => $now
        ]);

        $db->table('usuario')
            ->where('id', $usuarioId)
            ->update(['saldo_conta_corrente' => $saldoPosterior]);

        $db->transComplete();

        if ($db->transStatus() === false) {
            return ['success' => false, 'message' => 'Erro ao processar transação no banco de dados.'];
        }

        return [
            'success'         => true,
            'message'         => 'Crédito adicionado com sucesso!',
            'saldo_anterior'  => $saldoAnterior,
            'saldo_posterior' => $saldoPosterior,
            'valor'           => $valor
        ];
    }

    /**
     * Resgatar crédito da conta corrente (Saque / Retirada)
     */
    public function resgatarCredito(int $usuarioId, float $valor, string $descricao = 'Resgate de Crédito da Conta Corrente'): array
    {
        if ($valor <= 0) {
            return ['success' => false, 'message' => 'O valor do resgate deve ser maior que zero.'];
        }

        $saldoAnterior = $this->getSaldo($usuarioId);
        if ($valor > $saldoAnterior) {
            return [
                'success' => false,
                'message' => 'Saldo insuficiente para resgate. Saldo atual disponível: R$ ' . number_format($saldoAnterior, 2, ',', '.')
            ];
        }

        $db = \Config\Database::connect();
        $db->transStart();

        $saldoPosterior = round($saldoAnterior - $valor, 2);
        $now = (new \DateTime('now', new \DateTimeZone('America/Sao_Paulo')))->format('Y-m-d H:i:s');

        $db->table($this->table)->insert([
            'usuario_id'      => $usuarioId,
            'aposta_id'       => null,
            'tipo'            => 'RESGATE_CREDITO',
            'descricao'       => $descricao,
            'valor'           => -$valor, // Armazena negativo representando débito/saída
            'saldo_anterior'  => $saldoAnterior,
            'saldo_posterior' => $saldoPosterior,
            'criado_em'       => $now
        ]);

        $db->table('usuario')
            ->where('id', $usuarioId)
            ->update(['saldo_conta_corrente' => $saldoPosterior]);

        $db->transComplete();

        if ($db->transStatus() === false) {
            return ['success' => false, 'message' => 'Erro ao processar resgate no banco de dados.'];
        }

        return [
            'success'         => true,
            'message'         => 'Resgate de crédito realizado com sucesso!',
            'saldo_anterior'  => $saldoAnterior,
            'saldo_posterior' => $saldoPosterior,
            'valor'           => $valor
        ];
    }

    /**
     * Debitar valor de uma aposta criada
     */
    public function debitarAposta(int $usuarioId, int $apostaId, float $valor, string $descricao = ''): array
    {
        if ($valor <= 0) {
            return ['success' => false, 'message' => 'Valor inválido de aposta.'];
        }

        $db = \Config\Database::connect();

        // Anti-duplicidade: verifica se esta aposta já foi debitada
        $qExists = $db->table($this->table)
            ->where('usuario_id', $usuarioId)
            ->where('aposta_id', $apostaId)
            ->where('tipo', 'DEBITO_APOSTA')
            ->get();

        $exists = $qExists ? $qExists->getRow() : null;

        if ($exists) {
            return ['success' => true, 'message' => 'Débito de aposta já registrado anteriormente.'];
        }

        if (empty($descricao)) {
            $descricao = "Débito Aposta #" . $apostaId;
        }

        $db->transStart();

        $saldoAnterior = $this->getSaldo($usuarioId);
        $saldoPosterior = round($saldoAnterior - $valor, 2);
        $now = (new \DateTime('now', new \DateTimeZone('America/Sao_Paulo')))->format('Y-m-d H:i:s');

        $db->table($this->table)->insert([
            'usuario_id'      => $usuarioId,
            'aposta_id'       => $apostaId,
            'tipo'            => 'DEBITO_APOSTA',
            'descricao'       => $descricao,
            'valor'           => -$valor, // Armazena negativo para representar débito
            'saldo_anterior'  => $saldoAnterior,
            'saldo_posterior' => $saldoPosterior,
            'criado_em'       => $now
        ]);

        $db->table('usuario')
            ->where('id', $usuarioId)
            ->update(['saldo_conta_corrente' => $saldoPosterior]);

        $db->transComplete();

        return [
            'success'         => ($db->transStatus() !== false),
            'saldo_posterior' => $saldoPosterior
        ];
    }

    /**
     * Creditar retorno de uma aposta resolvida/ganha/cashout/anulada
     */
    public function creditarRetornoAposta(int $usuarioId, int $apostaId, float $valor, string $descricao = ''): array
    {
        if ($valor <= 0) {
            return ['success' => true, 'message' => 'Retorno nulo ou zero.'];
        }

        $db = \Config\Database::connect();

        // Anti-duplicidade: verifica se o retorno desta aposta já foi creditado
        $qExists = $db->table($this->table)
            ->where('usuario_id', $usuarioId)
            ->where('aposta_id', $apostaId)
            ->where('tipo', 'CREDITO_RETORNO_APOSTA')
            ->get();

        $exists = $qExists ? $qExists->getRow() : null;

        if ($exists) {
            return ['success' => true, 'message' => 'Retorno de aposta já registrado no extrato.'];
        }

        if (empty($descricao)) {
            $descricao = "Retorno de Aposta #" . $apostaId;
        }

        $db->transStart();

        $saldoAnterior = $this->getSaldo($usuarioId);
        $saldoPosterior = round($saldoAnterior + $valor, 2);
        $now = (new \DateTime('now', new \DateTimeZone('America/Sao_Paulo')))->format('Y-m-d H:i:s');

        $db->table($this->table)->insert([
            'usuario_id'      => $usuarioId,
            'aposta_id'       => $apostaId,
            'tipo'            => 'CREDITO_RETORNO_APOSTA',
            'descricao'       => $descricao,
            'valor'           => $valor,
            'saldo_anterior'  => $saldoAnterior,
            'saldo_posterior' => $saldoPosterior,
            'criado_em'       => $now
        ]);

        $db->table('usuario')
            ->where('id', $usuarioId)
            ->update(['saldo_conta_corrente' => $saldoPosterior]);

        $db->transComplete();

        return [
            'success'         => ($db->transStatus() !== false),
            'saldo_posterior' => $saldoPosterior
        ];
    }

    /**
     * Estornar débito de uma aposta que foi excluída/cancelada enquanto pendente
     */
    public function estornarAposta(int $usuarioId, int $apostaId, float $valor, string $descricao = ''): array
    {
        if ($valor <= 0) {
            return ['success' => false, 'message' => 'Valor de estorno inválido.'];
        }

        $db = \Config\Database::connect();

        // Verifica se a aposta realmente teve débito registrado
        $qDebito = $db->table($this->table)
            ->where('usuario_id', $usuarioId)
            ->where('aposta_id', $apostaId)
            ->where('tipo', 'DEBITO_APOSTA')
            ->get();

        $debito = $qDebito ? $qDebito->getRow() : null;

        if (!$debito) {
            return ['success' => false, 'message' => 'Nenhum débito encontrado para estornar.'];
        }

        // Verifica se já não foi estornado
        $qEstorno = $db->table($this->table)
            ->where('usuario_id', $usuarioId)
            ->where('aposta_id', $apostaId)
            ->where('tipo', 'ESTORNO_APOSTA')
            ->get();

        $estornoExistente = $qEstorno ? $qEstorno->getRow() : null;

        if ($estornoExistente) {
            return ['success' => true, 'message' => 'Estorno já realizado anteriormente.'];
        }

        if (empty($descricao)) {
            $descricao = "Estorno Aposta #" . $apostaId;
        }

        $db->transStart();

        $saldoAnterior = $this->getSaldo($usuarioId);
        $saldoPosterior = round($saldoAnterior + $valor, 2);
        $now = (new \DateTime('now', new \DateTimeZone('America/Sao_Paulo')))->format('Y-m-d H:i:s');

        $db->table($this->table)->insert([
            'usuario_id'      => $usuarioId,
            'aposta_id'       => $apostaId,
            'tipo'            => 'ESTORNO_APOSTA',
            'descricao'       => $descricao,
            'valor'           => $valor,
            'saldo_anterior'  => $saldoAnterior,
            'saldo_posterior' => $saldoPosterior,
            'criado_em'       => $now
        ]);

        $db->table('usuario')
            ->where('id', $usuarioId)
            ->update(['saldo_conta_corrente' => $saldoPosterior]);

        $db->transComplete();

        return [
            'success'         => ($db->transStatus() !== false),
            'saldo_posterior' => $saldoPosterior
        ];
    }

    /**
     * Retorna o extrato de movimentações e o resumo financeiro da conta corrente
     */
    public function getExtrato(int $usuarioId, ?string $dataInicio = null, ?string $dataFim = null, ?string $tipo = null): array
    {
        $this->syncApostasHistorico($usuarioId);
        $db = \Config\Database::connect();

        $builder = $db->table($this->table)->where('usuario_id', $usuarioId);

        if (!empty($dataInicio)) {
            $builder->where('criado_em >=', $dataInicio . ' 00:00:00');
        }
        if (!empty($dataFim)) {
            $builder->where('criado_em <=', $dataFim . ' 23:59:59');
        }
        if (!empty($tipo)) {
            $builder->where('tipo', $tipo);
        }

        $qTrans = $builder->orderBy('criado_em', 'DESC')->orderBy('id', 'DESC')->get();
        $transacoes = $qTrans ? $qTrans->getResultObject() : [];

        foreach ($transacoes as &$t) {
            $t->descricao = self::fixUtf8($t->descricao);
        }
        unset($t);

        // Métricas resumidas gerais do usuário (sempre da conta completa)
        $qSummary = $db->table($this->table)
            ->select("
                COALESCE(SUM(CASE WHEN tipo = 'CREDITO_ADICIONADO' THEN valor ELSE 0 END), 0) as total_creditos_adicionados,
                COALESCE(SUM(CASE WHEN tipo = 'DEBITO_APOSTA' THEN ABS(valor) ELSE 0 END), 0) as total_debitado_apostas,
                COALESCE(SUM(CASE WHEN tipo = 'CREDITO_RETORNO_APOSTA' THEN valor ELSE 0 END), 0) as total_retorno_apostas,
                COALESCE(SUM(CASE WHEN tipo = 'ESTORNO_APOSTA' THEN valor ELSE 0 END), 0) as total_estornos,
                COALESCE(SUM(CASE WHEN tipo = 'RESGATE_CREDITO' THEN ABS(valor) ELSE 0 END), 0) as total_resgates,
                COUNT(*) as total_transacoes
            ")
            ->where('usuario_id', $usuarioId)
            ->get();

        $summary = $qSummary ? $qSummary->getRow() : null;

        $saldoAtual = $this->getSaldo($usuarioId);

        $totalCreditos = (float)($summary->total_creditos_adicionados ?? 0);
        $totalDebitos  = (float)($summary->total_debitado_apostas ?? 0);
        $totalRetornos = (float)($summary->total_retorno_apostas ?? 0);
        $totalEstornos = (float)($summary->total_estornos ?? 0);
        $totalResgates = (float)($summary->total_resgates ?? 0);

        // Resultado líquido proveniente das apostas
        $lucroLiquidoApostas = $totalRetornos - ($totalDebitos - $totalEstornos);

        return [
            'transacoes'                 => $transacoes,
            'saldo_atual'                => $saldoAtual,
            'total_creditos_adicionados' => $totalCreditos,
            'total_debitado_apostas'     => $totalDebitos,
            'total_retorno_apostas'      => $totalRetornos,
            'total_estornos'             => $totalEstornos,
            'total_resgates'             => $totalResgates,
            'lucro_liquido_apostas'      => $lucroLiquidoApostas,
            'total_transacoes'           => (int)($summary->total_transacoes ?? 0)
        ];
    }

    /**
     * Dados consolidados para o Gráfico de Evolução Financeira da Conta Corrente
     */
    public function getEvolucaoFinanceira(int $usuarioId): array
    {
        $db = \Config\Database::connect();

        $qRows = $db->table($this->table)
            ->where('usuario_id', $usuarioId)
            ->orderBy('criado_em', 'ASC')
            ->orderBy('id', 'ASC')
            ->get();

        $rows = $qRows ? $qRows->getResultObject() : [];

        $labels = [];
        $saldoEvolucao = [];
        $creditosAdicionadosAcum = [];
        $retornosApostasAcum = [];
        $rawItems = [];

        $acumCreditos = 0.0;
        $acumRetornos = 0.0;

        foreach ($rows as $r) {
            $dt = date('d/m/Y H:i', strtotime($r->criado_em));
            $val = (float)$r->valor;

            if ($r->tipo === 'CREDITO_ADICIONADO') {
                $acumCreditos += $val;
            } elseif ($r->tipo === 'CREDITO_RETORNO_APOSTA') {
                $acumRetornos += $val;
            }

            $labels[] = $dt;
            $saldoEvolucao[] = (float)$r->saldo_posterior;
            $creditosAdicionadosAcum[] = round($acumCreditos, 2);
            $retornosApostasAcum[] = round($acumRetornos, 2);

            $rawItems[] = [
                'criado_em'       => $r->criado_em,
                'tipo'            => $r->tipo,
                'valor'           => $val,
                'saldo_posterior' => (float)$r->saldo_posterior
            ];
        }

        return [
            'labels'                      => $labels,
            'saldo_evolucao'              => $saldoEvolucao,
            'creditos_adicionados_acum'   => $creditosAdicionadosAcum,
            'retornos_apostas_acum'       => $retornosApostasAcum,
            'items'                       => $rawItems
        ];
    }

    /**
     * Corrige strings com codificação UTF-8 dupla ou incorreta
     */
    public static function fixUtf8(?string $str): string
    {
        if (empty($str)) {
            return '';
        }
        if (strpos($str, 'Ã©') !== false || strpos($str, 'Ãµ') !== false || strpos($str, 'Ã¡') !== false || strpos($str, 'Ã§') !== false || strpos($str, 'Ã£') !== false || strpos($str, 'Ãª') !== false || strpos($str, 'DÃ©bito') !== false) {
            $converted = @utf8_decode($str);
            if ($converted !== false && mb_check_encoding($converted, 'UTF-8')) {
                return $converted;
            }
            return mb_convert_encoding($str, 'ISO-8859-1', 'UTF-8');
        }
        return $str;
    }
}
