<?php

namespace App\Models;

use CodeIgniter\Model;

class MoedaConfigModel extends Model
{
    protected $table            = 'moedas_config';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'object';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'codigo',
        'nome',
        'simbolo',
        'tipo',
        'casas_decimais',
        'saque_minimo',
        'taxa_saque_fixa',
        'taxa_rede_estimada',
        'taxa_spot_percentual',
        'cotacao_brl',
        'cotacao_atualizada_em',
        'is_default',
        'ativo'
    ];

    protected $useTimestamps = false;
    protected static ?object $cachedDefault = null;
    protected static array $cachePorCodigo = [];

    /**
     * Retorna a moeda padrão ativa configurada no sistema.
     */
    public function getDefaultMoeda(): object
    {
        if (self::$cachedDefault !== null) {
            return self::$cachedDefault;
        }

        $row = $this->where('is_default', 1)->where('ativo', 1)->first();
        if (!$row) {
            $row = $this->where('codigo', 'BRL')->first();
        }

        if (!$row) {
            $row = (object)[
                'id'                   => 1,
                'codigo'               => 'BRL',
                'nome'                 => 'Real Brasileiro',
                'simbolo'              => 'R$',
                'tipo'                 => 'FIAT',
                'casas_decimais'       => 2,
                'saque_minimo'         => 0.0,
                'taxa_saque_fixa'      => 0.0,
                'taxa_rede_estimada'   => 0.0,
                'taxa_spot_percentual' => 0.0,
                'cotacao_brl'          => 1.0000,
                'is_default'           => 1,
                'ativo'                => 1
            ];
        }

        self::$cachedDefault = $row;
        return $row;
    }

    /**
     * Retorna uma moeda específica pelo código (ex: 'LTC', 'BTC', 'BRL').
     */
    public function getMoedaByCodigo(string $codigo): ?object
    {
        $codeUpper = strtoupper(trim($codigo));
        if (isset(self::$cachePorCodigo[$codeUpper])) {
            return self::$cachePorCodigo[$codeUpper];
        }

        $row = $this->where('codigo', $codeUpper)->first();
        if ($row) {
            self::$cachePorCodigo[$codeUpper] = $row;
        }
        return $row;
    }

    /**
     * Retorna todas as moedas cadastradas.
     */
    public function getAllMoedas(bool $apenasAtivas = true): array
    {
        $builder = $this->orderBy('is_default', 'DESC')->orderBy('tipo', 'ASC')->orderBy('codigo', 'ASC');
        if ($apenasAtivas) {
            $builder->where('ativo', 1);
        }
        return $builder->findAll();
    }

    /**
     * Define uma moeda como padrão (is_default = 1) e desmarca as outras.
     */
    public function setDefaultMoeda(string $codigo): bool
    {
        $codeUpper = strtoupper(trim($codigo));
        $db = \Config\Database::connect();
        $db->transStart();

        $db->table($this->table)->set('is_default', 0)->update();
        $db->table($this->table)->set('is_default', 1)->where('codigo', $codeUpper)->update();

        $db->transComplete();
        self::$cachedDefault = null;
        return $db->transStatus();
    }

    /**
     * Calcula o valor líquido em Reais (BRL) abatendo as taxas de movimentação previstas
     * (Taxa fixa de saque da Stake + Taxa de rede + Taxa spot de conversão).
     *
     * @param float $lucroMoeda Lucro/prejuízo na moeda original
     * @param string $moedaCodigo Código da moeda (ex: 'LTC', 'BRL')
     * @param float|null $cotacaoSnapshot Cotação salva na aposta ou null para usar a cotação atual
     * @return array
     */
    public function calcularValorLiquidoBrl(float $lucroMoeda, string $moedaCodigo = 'BRL', ?float $cotacaoSnapshot = null): array
    {
        $moeda = $this->getMoedaByCodigo($moedaCodigo) ?? $this->getDefaultMoeda();
        $cotacao = ($cotacaoSnapshot !== null && $cotacaoSnapshot > 0) ? (float)$cotacaoSnapshot : (float)$moeda->cotacao_brl;
        if ($cotacao <= 0) {
            $cotacao = 1.0;
        }

        $valorBrutoBrl = $lucroMoeda * $cotacao;
        $taxaDescontadaBrl = 0.0;

        // Se a moeda for fiduciária nacional (BRL), não há taxas de rede nem spread cambial
        if ($moeda->tipo === 'CRYPTO') {
            if ($lucroMoeda > 0) {
                // Para lucros positivos que demandam movimentação financeira (saque Stake -> Carteira -> Binance -> BRL):
                // 1. Taxa fixa de saque da Stake (ex: 0.000138 LTC)
                // 2. Taxa de rede da carteira intermediária para a Binance (ex: 0.00023 LTC)
                $taxaFixaMoeda = (float)$moeda->taxa_saque_fixa + (float)$moeda->taxa_rede_estimada;
                $taxaFixaBrl   = $taxaFixaMoeda * $cotacao;

                // 3. Taxa spot de negociação na corretora (ex: 0,10% sobre o montante convertido)
                $taxaSpotBrl   = $valorBrutoBrl * ((float)$moeda->taxa_spot_percentual / 100.0);

                $taxaDescontadaBrl = $taxaFixaBrl + $taxaSpotBrl;
            }
        }

        $valorLiquidoBrl = $valorBrutoBrl - $taxaDescontadaBrl;

        return [
            'moeda_codigo'        => $moeda->codigo,
            'moeda_simbolo'       => $moeda->simbolo,
            'casas_decimais'      => (int)$moeda->casas_decimais,
            'cotacao_utilizada'   => $cotacao,
            'lucro_moeda'         => $lucroMoeda,
            'lucro_bruto_brl'     => round($valorBrutoBrl, 2),
            'taxa_descontada_brl' => round($taxaDescontadaBrl, 2),
            'lucro_liquido_brl'   => round($valorLiquidoBrl, 2)
        ];
    }
}
