<?php
namespace App\Controllers;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\ResponseInterface;

class ApiController extends Controller
{
    public function getAreasByCatalogo($id_catalogo)
    {
        $db = \Config\Database::connect();
        $areas = $db->table('area_atuacao')->where('id_catalogo_servicos', $id_catalogo)->get()->getResult();
        return $this->response->setJSON($areas);
    }

    public function getAtividadesByArea($id_area)
    {
        $db = \Config\Database::connect();
        $atividades = $db->table('atividade_macro')->where('id_area_atuacao', $id_area)->get()->getResult();
        return $this->response->setJSON($atividades);
    }

    public function getServicosByAtividade($id_atividade)
    {
        $db = \Config\Database::connect();
        
        $dataReferencia = date('Y-m-d');
        
        $builder = $db->table('servico s');
        $builder->select('
            s.*, 
            mc.sigla as sigla_metrica,
            (SELECT valor_item_contrato 
             FROM reajuste_item_contrato 
             WHERE id_item_contrato = ic.id 
             AND data_reajuste_item_contrato <= ' . $db->escape($dataReferencia) . ' 
             ORDER BY data_reajuste_item_contrato DESC LIMIT 1) as valor_item_contrato
        ');
        $builder->join('atividade_macro am', 'am.id = s.id_atividade_macro', 'left');
        $builder->join('area_atuacao aa', 'aa.id = am.id_area_atuacao', 'left');
        $builder->join('catalogo_servicos cs', 'cs.id = aa.id_catalogo_servicos', 'left');
        $builder->join('item_contrato ic', 'ic.id = cs.id_item_contrato', 'left');
        $builder->join('metrica_contrato mc', 'mc.id = ic.id_metrica', 'left');
        $builder->where('s.id_atividade_macro', $id_atividade);
        $servicos = $builder->get()->getResult();
        
        return $this->response->setJSON($servicos);
    }

    public function getItensByOs($id_os)
    {
        $db = \Config\Database::connect();
        
        $os = $db->table('ordem_servico')->where('id', $id_os)->get()->getRow();
        $dataEmissao = $os ? $os->Data_Emissao : date('Y-m-d');

        $builder = $db->table('os_item_os oio');
        $builder->select('
            io.id, 
            io.id_servico,
            io.Quantidade_Horas as quantidade_horas, 
            io.Profissional_Alocado as profissional_alocado, 
            s.numero_item, 
            s.descricao,
            s.remuneracao,
            s.sla_dias,
            mc.sigla as sigla_metrica,
            (SELECT valor_item_contrato 
             FROM reajuste_item_contrato 
             WHERE id_item_contrato = cs.id_item_contrato 
             AND data_reajuste_item_contrato <= ' . $db->escape($dataEmissao) . ' 
             ORDER BY data_reajuste_item_contrato DESC LIMIT 1) as valor_item_contrato
        ');
        $builder->join('item_os io', 'io.id = oio.id_item_os');
        $builder->join('servico s', 's.id = io.id_servico', 'left');
        $builder->join('atividade_macro am', 'am.id = s.id_atividade_macro', 'left');
        $builder->join('area_atuacao aa', 'aa.id = am.id_area_atuacao', 'left');
        $builder->join('catalogo_servicos cs', 'cs.id = aa.id_catalogo_servicos', 'left');
        $builder->join('item_contrato ic', 'ic.id = cs.id_item_contrato', 'left');
        $builder->join('metrica_contrato mc', 'mc.id = ic.id_metrica', 'left');
        $builder->where('oio.id_os', $id_os);
        $itens = $builder->get()->getResult();
        
        foreach($itens as &$item) {
            $valContrato = isset($item->valor_item_contrato) ? (float)$item->valor_item_contrato : 0;
            $remun = isset($item->remuneracao) ? (float)$item->remuneracao : 0;
            $qtd = isset($item->quantidade_horas) ? (float)$item->quantidade_horas : 0;
            
            $sigla = isset($item->sigla_metrica) ? strtoupper($item->sigla_metrica) : 'H';
            if ($sigla === 'PF' || $sigla === 'PROF') {
                $item->valor_remuneracao_item = $qtd * $valContrato;
            } else {
                $item->valor_remuneracao_item = $qtd * $remun * $valContrato;
            }
        }

        return $this->response->setJSON($itens);
    }

    public function getOsDetails($id_os)
    {
        $db = \Config\Database::connect();
        $os = $db->table('ordem_servico')->where('id', $id_os)->get()->getRow();
        return $this->response->setJSON($os);
    }

    public function getDemandasByOs($id_os)
    {
        $db = \Config\Database::connect();
        $demandas = $db->table('agile_demandas')->where('id_ordem_servico', $id_os)->get()->getResult();
        return $this->response->setJSON($demandas);
    }
}
