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
        $servicos = $db->table('servico')->where('id_atividade_macro', $id_atividade)->get()->getResult();
        return $this->response->setJSON($servicos);
    }

    public function getItensByOs($id_os)
    {
        $db = \Config\Database::connect();
        $builder = $db->table('os_item_os oio');
        $builder->select('io.id, io.Quantidade_Horas as quantidade_horas, io.Profissional_Alocado as profissional_alocado, s.numero_item, s.descricao');
        $builder->join('item_os io', 'io.id = oio.id_item_os');
        $builder->join('servico s', 's.id = io.id_servico', 'left');
        $builder->where('oio.id_os', $id_os);
        $itens = $builder->get()->getResult();
        return $this->response->setJSON($itens);
    }
}
