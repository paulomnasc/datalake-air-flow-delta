<?php

namespace App\Models;

use CodeIgniter\Model;

class BancaCicloModel extends Model
{
    protected $table            = 'banca_ciclos';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'object';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'usuario_id',
        'nome_ciclo',
        'moeda_codigo',
        'deposito_inicial',
        'data_inicio',
        'data_fim',
        'status',
        'observacoes',
        'criado_em'
    ];

    protected $useTimestamps = false;
    protected $createdField  = 'criado_em';

    /**
     * Retorna o ciclo de banca atualmente ativo para o usuário
     */
    public function getCicloAtivo(int $usuarioId): ?object
    {
        return $this->where('usuario_id', $usuarioId)
            ->where('status', 'ATIVO')
            ->orderBy('data_inicio', 'DESC')
            ->first();
    }

    /**
     * Retorna a lista de todos os ciclos de banca do usuário
     */
    public function getCiclos(int $usuarioId): array
    {
        return $this->where('usuario_id', $usuarioId)
            ->orderBy('data_inicio', 'DESC')
            ->findAll();
    }

    /**
     * Consolida métricas financeiras precisas a partir do ponto de corte do ciclo
     */
    public function getMetricasCiclo(int $usuarioId, ?int $cicloId = null): array
    {
        $ciclo = $cicloId ? $this->find($cicloId) : $this->getCicloAtivo($usuarioId);
        if (!$ciclo) {
            return [
                'has_ciclo' => false,
                'ciclo'     => null,
            ];
        }

        $db = \Config\Database::connect();
        $dataInicio = $ciclo->data_inicio;
        $dataFim    = $ciclo->data_fim ?? date('Y-m-d H:i:s');
        $depositoInicial = (float)$ciclo->deposito_inicial;
        $moeda = $ciclo->moeda_codigo ?? 'USDT';

        // Saldo atual consolidado na tabela de usuário / conta corrente
        $userRow = $db->table('usuario')->select('saldo_conta_corrente')->where('id', $usuarioId)->get()->getRow();
        $saldoDisponivel = $userRow ? (float)$userRow->saldo_conta_corrente : $depositoInicial;

        // Apostas confirmadas neste ciclo
        $apostasCiclo = $db->table('apostas')
            ->where('usuario_id', $usuarioId)
            ->where('criado_em >=', $dataInicio)
            ->where('criado_em <=', $dataFim)
            ->where('confirmada', 1)
            ->get()
            ->getResult();

        $totalApostas = count($apostasCiclo);
        $totalApostado = 0.0;
        $totalRetornado = 0.0;
        $capitalEmCampo = 0.0;
        $greens = 0;
        $reds = 0;
        $pushes = 0;
        $pendentes = 0;

        foreach ($apostasCiclo as $ap) {
            $stake = (float)$ap->valor_aposta;
            $totalApostado += $stake;
            $status = strtoupper(trim($ap->status ?? ''));

            if ($status === 'PENDENTE') {
                $capitalEmCampo += $stake;
                $pendentes++;
            } elseif ($status === 'GANHA') {
                $totalRetornado += (float)$ap->ganhos_potenciais;
                $greens++;
            } elseif ($status === 'PERDIDA') {
                $reds++;
            } elseif (in_array($status, ['ANULADA', 'REEMBOLSADA', 'DEVOLVIDA', 'MEIO GANHA', 'MEIO PERDIDA'])) {
                $ret = (float)$ap->ganhos_potenciais;
                if ($ret <= 0 && isset($ap->cash_out)) {
                    $ret = (float)$ap->cash_out;
                }
                $totalRetornado += $ret;
                $pushes++;
            }
        }

        $volumeLiquidado = $totalApostado - $capitalEmCampo;
        $lucroLiquido = round($totalRetornado - $volumeLiquidado, 2);
        $roiPct = $volumeLiquidado > 0 ? round(($lucroLiquido / $volumeLiquidado) * 100, 2) : 0.0;
        $patrimonioTotal = round($saldoDisponivel + $capitalEmCampo, 2);
        $crescimentoBancaPct = $depositoInicial > 0 ? round((($patrimonioTotal - $depositoInicial) / $depositoInicial) * 100, 2) : 0.0;

        return [
            'has_ciclo'             => true,
            'ciclo_id'              => (int)$ciclo->id,
            'nome_ciclo'            => $ciclo->nome_ciclo,
            'moeda'                 => $moeda,
            'deposito_inicial'      => $depositoInicial,
            'deposito_inicial_fmt'  => number_format($depositoInicial, 2),
            'data_inicio'           => $dataInicio,
            'data_inicio_fmt'       => date('d/m/Y H:i', strtotime($dataInicio)),
            'saldo_disponivel'      => $saldoDisponivel,
            'saldo_disponivel_fmt'  => number_format($saldoDisponivel, 2),
            'capital_em_campo'      => $capitalEmCampo,
            'capital_em_campo_fmt'  => number_format($capitalEmCampo, 2),
            'patrimonio_total'      => $patrimonioTotal,
            'patrimonio_total_fmt'  => number_format($patrimonioTotal, 2),
            'lucro_liquido'         => $lucroLiquido,
            'lucro_liquido_fmt'     => number_format($lucroLiquido, 2),
            'roi_pct'               => $roiPct,
            'crescimento_banca_pct' => $crescimentoBancaPct,
            'total_apostas'         => $totalApostas,
            'greens'                => $greens,
            'reds'                  => $reds,
            'pushes'                => $pushes,
            'pendentes'             => $pendentes,
        ];
    }
}
