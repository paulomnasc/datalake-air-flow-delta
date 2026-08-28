<?php

namespace App\Models;

use CodeIgniter\Model;

class ApostaModel extends Model
{
    protected $table            = 'apostas';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'object';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'usuario_id',
        'fixture_id',
        'time_casa',
        'time_fora',
        'mercado',
        'palpite',
        'odd',
        'odd_justa',
        'probabilidade_poisson',
        'ev_percentual',
        'status_gatekeeper',
        'data_hora_jogo',
        'valor_aposta',
        'ganhos_potenciais',
        'cash_out',
        'tipo',
        'status',
        'confirmada',
        'resultado_detalhado',
        'analise_ia_perda',
        'analise_ia_data',
        'processado_em',
        'criado_em',
        'updated_at'
    ];

    protected $useTimestamps = false;
    protected $createdField  = 'criado_em';
    protected $updatedField  = 'updated_at';

    /**
     * Retorna estatísticas resumidas das apostas do usuário
     */
    public function getResumoUsuario(int $usuarioId): array
    {
        $db = \Config\Database::connect();
        
        $builder = $db->table($this->table)->where('usuario_id', $usuarioId);
        $totalApostas = $builder->countAllResults(false);

        $builderSelect = $db->table($this->table)
            ->select("
                COALESCE(SUM(valor_aposta), 0) as total_apostado,
                COALESCE(SUM(CASE WHEN status != 'Pendente' THEN valor_aposta ELSE 0 END), 0) as total_apostado_liquidado,
                COALESCE(SUM(CASE 
                    WHEN status = 'Ganha' THEN COALESCE(NULLIF(ganhos_potenciais, 0), (valor_aposta * odd))
                    WHEN status = 'Meio Ganha' THEN (valor_aposta + ((COALESCE(NULLIF(ganhos_potenciais, 0), (valor_aposta * odd)) - valor_aposta) / 2))
                    WHEN status = 'ANULADA' THEN valor_aposta
                    WHEN status = 'Meio Perdida' THEN (valor_aposta * 0.5)
                    WHEN status = 'Cashout' THEN COALESCE(NULLIF(cash_out, 0), COALESCE(NULLIF(ganhos_potenciais, 0), valor_aposta))
                    ELSE 0 
                END), 0) as ganhos_totais,
                COALESCE(SUM(cash_out), 0) as total_cashout,
                SUM(CASE WHEN status = 'Ganha' THEN 1 ELSE 0 END) as ganhas,
                SUM(CASE WHEN status = 'Meio Ganha' THEN 1 ELSE 0 END) as meio_ganhas,
                SUM(CASE WHEN status = 'Perdida' THEN 1 ELSE 0 END) as perdidas,
                SUM(CASE WHEN status = 'Meio Perdida' THEN 1 ELSE 0 END) as meio_perdidas,
                SUM(CASE WHEN status = 'ANULADA' THEN 1 ELSE 0 END) as anuladas,
                SUM(CASE WHEN status = 'Pendente' THEN 1 ELSE 0 END) as pendentes,
                SUM(CASE WHEN status = 'Cashout' THEN 1 ELSE 0 END) as cashouts
            ", false)
            ->where('usuario_id', $usuarioId);

        $query = $builderSelect->get();
        $row = $query->getRowArray();

        $row['total_apostas'] = $totalApostas;
        $row['saldo_liquido'] = (float)($row['ganhos_totais'] ?? 0) - (float)($row['total_apostado_liquidado'] ?? 0);

        return $row ?? [
            'total_apostas' => 0,
            'total_apostado' => 0,
            'total_apostado_liquidado' => 0,
            'ganhos_totais' => 0,
            'total_cashout' => 0,
            'saldo_liquido' => 0,
            'ganhas' => 0,
            'meio_ganhas' => 0,
            'perdidas' => 0,
            'meio_perdidas' => 0,
            'anuladas' => 0,
            'pendentes' => 0,
            'cashouts' => 0
        ];
    }
}
