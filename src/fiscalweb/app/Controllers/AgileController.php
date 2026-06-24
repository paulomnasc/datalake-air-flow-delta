<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;
use App\Models\DemandaModel;
use App\Models\BacklogItemModel;
use App\Models\SprintModel;
use App\Models\CerimoniaModel;
use App\Models\ParecerHomologacaoModel;
use App\Models\ReleaseModel;
use App\Models\UsuarioModel;
use App\Models\SistemaModel;

class AgileController extends BaseController
{
    protected $demandaModel;
    protected $backlogItemModel;
    protected $sprintModel;
    protected $cerimoniaModel;
    protected $parecerHomologacaoModel;
    protected $releaseModel;
    protected $usuarioModel;
    protected $sistemaModel;

    public function __construct()
    {
        $this->demandaModel = new DemandaModel();
        $this->backlogItemModel = new BacklogItemModel();
        $this->sprintModel = new SprintModel();
        $this->cerimoniaModel = new CerimoniaModel();
        $this->parecerHomologacaoModel = new ParecerHomologacaoModel();
        $this->releaseModel = new ReleaseModel();
        $this->usuarioModel = new UsuarioModel();
        $this->sistemaModel = new SistemaModel();
    }

    /**
     * Dashboard do Módulo Ágil
     */
    public function dashboard()
    {
        // 1. Tempo Médio de Tramitação (Lead Time) por raia/status
        $db = \Config\Database::connect();
        $leadTimeQuery = $db->query("
            SELECT status, 
                   AVG(DATEDIFF(IFNULL(atualizado_em, NOW()), criado_em)) as media_dias,
                   COUNT(*) as total_demandas
            FROM agile_demandas
            GROUP BY status
        ");
        $leadTimeData = $leadTimeQuery->getResult();

        // 2. Taxa de Rejeição na Homologação
        $rejeicoesQuery = $db->query("
            SELECT 
                COUNT(CASE WHEN parecer = 'Rejeitado' THEN 1 END) as total_rejeitados,
                COUNT(*) as total_pareceres,
                IF(COUNT(*) > 0, (COUNT(CASE WHEN parecer = 'Rejeitado' THEN 1 END) / COUNT(*)) * 100, 0) as taxa_rejeicao
            FROM agile_pareceres_homologacao
        ");
        $rejeicoesData = $rejeicoesQuery->getRow();

        // 3. Calendário de Cerimônias (Carrega cerimônias futuras e passadas)
        $cerimonias = $this->cerimoniaModel
            ->select('agile_cerimonias.*, agile_demandas.titulo as demanda_titulo')
            ->join('agile_demandas', 'agile_demandas.id = agile_cerimonias.id_demanda')
            ->findAll();

        $eventosCalendario = [];
        foreach ($cerimonias as $c) {
            $eventosCalendario[] = [
                'id' => $c->id,
                'title' => $c->tipo_cerimonia . ' - ' . $c->demanda_titulo,
                'start' => $c->data_hora_agendada,
                'className' => $c->data_hora_realizada ? 'bg-success border-success text-white' : 'bg-warning border-warning text-dark',
                'description' => $c->ata_descritiva ?? 'Sem ata registrada.'
            ];
        }

        return view('agile/dashboard', [
            'leadTimeData' => $leadTimeData,
            'rejeicoesData' => $rejeicoesData,
            'eventosCalendario' => json_encode($eventosCalendario)
        ]);
    }

    /**
     * Listagem de Demandas
     */
    public function index()
    {
        $demandas = $this->demandaModel
            ->select('agile_demandas.*, agile_sistemas.sigla as sistema_sigla, agile_sistemas.nome as sistema_nome, ordem_servico.nup_sei')
            ->join('agile_sistemas', 'agile_sistemas.id = agile_demandas.id_sistema', 'left')
            ->join('ordem_servico', 'ordem_servico.id = agile_demandas.id_ordem_servico', 'left')
            ->orderBy('agile_demandas.criado_em', 'DESC')
            ->findAll();
        return view('agile/list_demandas', ['demandas' => $demandas]);
    }

    /**
     * Tela de Adicionar Demanda
     */
    public function add()
    {
        $sistemas = $this->sistemaModel->orderBy('sigla', 'ASC')->findAll();
        $ordens_servico = (new \App\Models\OrdemServicoModel())->listToCombo();
        return view('agile/demanda_form', [
            'sistemas' => $sistemas,
            'ordens_servico' => $ordens_servico
        ]);
    }

    /**
     * Inserir nova Demanda (Gatelink)
     */
    public function insert()
    {
        $sistemaCritico = $this->request->getPost('sistema_critico') ? 1 : 0;
        
        // Regra de Negócio (Gatelink):
        // Se SIM: fluxo COSIS -> "Preparar Demanda SERPRO"
        // Se NÃO: fluxo -> "Alocar Time Fábricas"
        $statusInicial = $sistemaCritico ? 'Preparar Demanda SERPRO' : 'Alocar Time Fábricas';
        $id_sistema = $this->request->getPost('id_sistema') ?: null;
        $id_ordem_servico = $this->request->getPost('id_ordem_servico') ?: null;

        // Pré-requisito obrigatório
        if (empty($id_ordem_servico)) {
            return redirect()->back()->withInput()->with('error', 'O preenchimento da Ordem de Serviço é obrigatório.');
        }

        $data = [
            'id_sistema' => $id_sistema,
            'id_ordem_servico' => $id_ordem_servico,
            'titulo' => $this->request->getPost('titulo'),
            'descricao' => $this->request->getPost('descricao'),
            'sistema_critico' => $sistemaCritico,
            'status' => $statusInicial
        ];

        if ($this->demandaModel->insert($data)) {
            return redirect()->to(route_to('agile.demandas'))->with('success', 'Demanda cadastrada com sucesso!');
        }

        return redirect()->back()->withInput()->with('error', 'Falha ao cadastrar a demanda.');
    }

    /**
     * Tela de Editar Demanda
     */
    public function upd()
    {
        $id = $this->request->getPost('id');
        $demanda = $this->demandaModel->find($id);

        if (!$demanda) {
            return redirect()->to(route_to('agile.demandas'))->with('error', 'Demanda não encontrada.');
        }

        $sistemas = $this->sistemaModel->orderBy('sigla', 'ASC')->findAll();
        $ordens_servico = (new \App\Models\OrdemServicoModel())->listToCombo();
        return view('agile/demanda_form', [
            'demanda' => $demanda, 
            'sistemas' => $sistemas,
            'ordens_servico' => $ordens_servico
        ]);
    }

    /**
     * Atualizar Demanda
     */
    public function update()
    {
        $id = $this->request->getPost('id');
        $id_sistema = $this->request->getPost('id_sistema') ?: null;
        $id_ordem_servico = $this->request->getPost('id_ordem_servico') ?: null;
        $sistemaCritico = $this->request->getPost('sistema_critico') ? 1 : 0;

        // Pré-requisito obrigatório
        if (empty($id_ordem_servico)) {
            return redirect()->back()->withInput()->with('error', 'O preenchimento da Ordem de Serviço é obrigatório.');
        }

        $data = [
            'id_sistema' => $id_sistema,
            'id_ordem_servico' => $id_ordem_servico,
            'titulo' => $this->request->getPost('titulo'),
            'descricao' => $this->request->getPost('descricao'),
            'sistema_critico' => $sistemaCritico,
            'status' => $this->request->getPost('status')
        ];

        if ($this->demandaModel->update($id, $data)) {
            return redirect()->to(route_to('agile.demandas'))->with('success', 'Demanda atualizada com sucesso!');
        }

        return redirect()->back()->withInput()->with('error', 'Falha ao atualizar a demanda.');
    }

    /**
     * Excluir Demanda
     */
    public function delete($id)
    {
        if ($this->demandaModel->delete($id)) {
            return $this->response->setJSON(['status' => 'success', 'mensagem' => 'Demanda excluída com sucesso!']);
        }
        return $this->response->setJSON(['status' => 'error', 'mensagem' => 'Erro ao excluir a demanda.']);
    }

    /**
     * Refinamento do Backlog do Produto
     */
    public function backlog($id_demanda)
    {
        $demanda = $this->demandaModel->find($id_demanda);
        if (!$demanda) {
            return redirect()->to(route_to('agile.demandas'))->with('error', 'Demanda não encontrada.');
        }

        // Se a demanda ainda está nas raias iniciais, avança ela para "Refinamento Backlog" quando o backlog for aberto
        if (in_array($demanda->status, ['Preparar Demanda SERPRO', 'Alocar Time Fábricas'])) {
            $this->demandaModel->update($id_demanda, ['status' => 'Refinamento Backlog']);
            $demanda->status = 'Refinamento Backlog';
        }

        $items = $this->backlogItemModel
            ->where('id_demanda', $id_demanda)
            ->orderBy('ordem', 'ASC')
            ->findAll();

        $cerimonias = $this->cerimoniaModel->where('id_demanda', $id_demanda)->findAll();
        $usuarios = $this->usuarioModel->findAll();

        return view('agile/backlog', [
            'demanda' => $demanda,
            'items' => $items,
            'cerimonias' => $cerimonias,
            'usuarios' => $usuarios
        ]);
    }

    /**
     * Salvar/Criar Item de Backlog
     */
    public function salvarBacklogItem()
    {
        $id = $this->request->getPost('id');
        $id_demanda = $this->request->getPost('id_demanda');

        $data = [
            'id_demanda' => $id_demanda,
            'titulo' => $this->request->getPost('titulo'),
            'criterios_aceite' => $this->request->getPost('criterios_aceite'),
            'pontuacao' => $this->request->getPost('pontuacao') ?: 0
        ];

        if (empty($id)) {
            // Conta quantos itens existem para definir a ordem no final
            $totalItens = $this->backlogItemModel->where('id_demanda', $id_demanda)->countAllResults();
            $data['ordem'] = $totalItens;
            $this->backlogItemModel->insert($data);
        } else {
            $this->backlogItemModel->update($id, $data);
        }

        return redirect()->to(route_to('agile.backlog', $id_demanda))->with('success', 'Item de backlog salvo!');
    }

    /**
     * Salvar Ordem do Backlog via AJAX (Drag-and-Drop)
     */
    public function salvarBacklogOrdem()
    {
        $ordemIds = $this->request->getPost('ordem'); // Array com os IDs ordenados
        if (!empty($ordemIds) && is_array($ordemIds)) {
            foreach ($ordemIds as $index => $id) {
                $this->backlogItemModel->update($id, ['ordem' => $index]);
            }
            return $this->response->setJSON(['status' => 'success']);
        }
        return $this->response->setJSON(['status' => 'error', 'mensagem' => 'Dados inválidos.']);
    }

    /**
     * Deletar Item de Backlog
     */
    public function deletarBacklogItem($id)
    {
        $item = $this->backlogItemModel->find($id);
        if ($item && $this->backlogItemModel->delete($id)) {
            return $this->response->setJSON(['status' => 'success']);
        }
        return $this->response->setJSON(['status' => 'error']);
    }

    /**
     * Quadro Kanban (Ciclo da Sprint)
     */
    public function kanban($id_demanda)
    {
        $demanda = $this->demandaModel->find($id_demanda);
        if (!$demanda) {
            return redirect()->to(route_to('agile.demandas'))->with('error', 'Demanda não encontrada.');
        }

        $sprintAtiva = $this->sprintModel
            ->where('id_demanda', $id_demanda)
            ->where('status', 'Ativa')
            ->first();

        $items = $this->backlogItemModel
            ->where('id_demanda', $id_demanda)
            ->orderBy('ordem', 'ASC')
            ->findAll();

        $cerimonias = $this->cerimoniaModel->where('id_demanda', $id_demanda)->findAll();
        $usuarios = $this->usuarioModel->findAll();
        $pareceres = $this->parecerHomologacaoModel->where('id_demanda', $id_demanda)->findAll();

        return view('agile/kanban', [
            'demanda' => $demanda,
            'sprintAtiva' => $sprintAtiva,
            'items' => $items,
            'cerimonias' => $cerimonias,
            'usuarios' => $usuarios,
            'pareceres' => $pareceres
        ]);
    }

    /**
     * Salvar/Iniciar Sprint
     * Bloqueio de Planejamento: Só inicia se houver cerimônia de Sprint Planning realizada.
     */
    public function salvarSprint()
    {
        $id_demanda = $this->request->getPost('id_demanda');
        
        // Bloqueio de Planejamento:
        // Verifica se há Sprint Planning concluída para esta demanda
        $planningCerimonia = $this->cerimoniaModel
            ->where('id_demanda', $id_demanda)
            ->where('tipo_cerimonia', 'Sprint Planning')
            ->where('data_hora_realizada IS NOT NULL')
            ->first();

        if (!$planningCerimonia) {
            return redirect()->back()->with('error', 'Bloqueio de Planejamento: Não é possível iniciar a Sprint sem uma cerimônia de Sprint Planning registrada e realizada com a ata descritiva.');
        }

        // Valida se há participantes presentes
        $participantes = json_decode($planningCerimonia->participantes_presentes ?? '[]');
        if (count($participantes) < 2) {
            return redirect()->back()->with('error', 'Bloqueio de Planejamento: A cerimônia de Sprint Planning deve ter pelo menos 2 participantes (PO e Fábrica representados).');
        }

        $data = [
            'id_demanda' => $id_demanda,
            'meta' => $this->request->getPost('meta'),
            'data_inicio' => $this->request->getPost('data_inicio'),
            'data_fim' => $this->request->getPost('data_fim'),
            'status' => 'Ativa'
        ];

        if ($this->sprintModel->insert($data)) {
            // Avança a demanda para 'Em Execução'
            $this->demandaModel->update($id_demanda, ['status' => 'Em Execução']);
            return redirect()->to(route_to('agile.kanban', $id_demanda))->with('success', 'Sprint inicializada e demanda movida para Em Execução!');
        }

        return redirect()->back()->with('error', 'Falha ao iniciar a Sprint.');
    }

    /**
     * Finalizar Sprint (Review)
     */
    public function salvarSprintReview()
    {
        $id_sprint = $this->request->getPost('id_sprint');
        $id_demanda = $this->request->getPost('id_demanda');

        // Atualiza a Sprint para Concluída
        $this->sprintModel->update($id_sprint, ['status' => 'Concluída']);

        // Avança a demanda para "Homologação"
        $this->demandaModel->update($id_demanda, ['status' => 'Homologação']);

        return redirect()->to(route_to('agile.kanban', $id_demanda))->with('success', 'Sprint concluída e demanda enviada para Homologação do PO!');
    }

    /**
     * Atualizar coluna Kanban via AJAX
     */
    public function updateKanbanStatus()
    {
        $id = $this->request->getPost('id');
        $status = $this->request->getPost('status');

        if ($this->backlogItemModel->update($id, ['status_kanban' => $status])) {
            return $this->response->setJSON(['status' => 'success']);
        }
        return $this->response->setJSON(['status' => 'error', 'mensagem' => 'Erro ao atualizar coluna.']);
    }

    /**
     * Agendar / Registrar Cerimônia
     */
    public function salvarCerimonia()
    {
        $id_demanda = $this->request->getPost('id_demanda');
        $id = $this->request->getPost('id');

        $participantes = $this->request->getPost('participantes') ?: [];
        $ata = $this->request->getPost('ata_descritiva');
        $realizada = $this->request->getPost('data_hora_realizada');

        // Se a ata foi preenchida, mas a data_hora_realizada foi deixada em branco,
        // assume que a cerimônia foi realizada no momento atual.
        if (!empty($ata) && empty($realizada)) {
            $realizada = date('Y-m-d H:i:s');
        }

        $data = [
            'id_demanda' => $id_demanda,
            'tipo_cerimonia' => $this->request->getPost('tipo_cerimonia'),
            'data_hora_agendada' => $this->request->getPost('data_hora_agendada'),
            'data_hora_realizada' => !empty($realizada) ? $realizada : null,
            'participantes_presentes' => json_encode($participantes),
            'ata_descritiva' => $ata,
            'link_gravacao' => $this->request->getPost('link_gravacao')
        ];

        if (empty($id)) {
            $this->cerimoniaModel->insert($data);
        } else {
            $this->cerimoniaModel->update($id, $data);
        }

        return redirect()->back()->with('success', 'Cerimônia/Ata registrada com sucesso!');
    }

    /**
     * Salvar Parecer de Homologação (PO)
     */
    public function salvarHomologacao()
    {
        $id_demanda = $this->request->getPost('id_demanda');
        $parecer = $this->request->getPost('parecer'); // 'Favorável' ou 'Rejeitado'
        $id_usuario = $_SESSION['id_usuario_logado'] ?? 1;

        $data = [
            'id_demanda' => $id_demanda,
            'id_usuario_po' => $id_usuario,
            'parecer' => $parecer,
            'observacoes' => $this->request->getPost('observacoes')
        ];

        $this->parecerHomologacaoModel->insert($data);

        if ($parecer === 'Favorável') {
            // Avança para a submissão de release
            $this->demandaModel->update($id_demanda, ['status' => 'Submissão Release']);
            $msg = 'Homologação Aprovada! Demanda movida para Submissão de Release.';
        } else {
            // Retorna ao ciclo da Sprint: redefine status da demanda para "Em Execução"
            // E zera status das tarefas prontas para "A Fazer" para correção
            $this->demandaModel->update($id_demanda, ['status' => 'Em Execução']);
            $this->backlogItemModel->where('id_demanda', $id_demanda)
                                  ->where('status_kanban', 'Pronto')
                                  ->set(['status_kanban' => 'A Fazer'])
                                  ->update();
            $msg = 'Homologação Rejeitada! A demanda retornou para Execução e tarefas Prontas foram resetadas.';
        }

        return redirect()->to(route_to('agile.kanban', $id_demanda))->with('success', $msg);
    }

    /**
     * Salvar Liberação de Release (Servidor)
     */
    public function salvarRelease()
    {
        $id_demanda = $this->request->getPost('id_demanda');
        $demanda = $this->demandaModel->find($id_demanda);

        if (!$demanda) {
            return redirect()->back()->with('error', 'Demanda não encontrada.');
        }

        // Garantia de Homologação:
        // Verifica se há Parecer de Homologação Favorável
        $homologacao = $this->parecerHomologacaoModel
            ->where('id_demanda', $id_demanda)
            ->where('parecer', 'Favorável')
            ->first();

        if (!$homologacao) {
            return redirect()->back()->with('error', 'Garantia de Homologação: O avanço para Submissão de Release é condicionado a um registro de Homologação do Produto com parecer favorável do PO.');
        }

        $data = [
            'id_demanda' => $id_demanda,
            'ticket_rdm' => $this->request->getPost('ticket_rdm'),
            'metadados' => json_encode([
                'servidor' => $this->request->getPost('servidor_deploy'),
                'observacoes' => $this->request->getPost('observacoes_deploy'),
                'janela_homologacao' => $this->request->getPost('janela_homologacao')
            ])
        ];

        $this->releaseModel->insert($data);

        // Dupla Esteira de Aprovação de Produção:
        // Se for Sistema Crítico: vai para raias SERPRO
        // Se não: vai para CCM (Comitê de Mudanças)
        if ($demanda->sistema_critico) {
            // Fluxo SERPRO (Simulado localmente, avança para 'SERPRO' e já pode ser homologado)
            $this->demandaModel->update($id_demanda, ['status' => 'SERPRO']);
            $msg = 'Release submetida com sucesso! Fluxo direcionado para a esteira de homologação/implantação SERPRO.';
        } else {
            // Fluxo comum: CCM
            $this->demandaModel->update($id_demanda, ['status' => 'CCM']);
            $msg = 'Release submetida com sucesso! Fluxo direcionado para o Comitê de Mudanças (CCM).';
        }

        return redirect()->to(route_to('agile.kanban', $id_demanda))->with('success', $msg);
    }

    /**
     * Listagem de Sistemas
     */
    public function sistemas()
    {
        $sistemas = $this->sistemaModel->orderBy('sigla', 'ASC')->findAll();
        return view('agile/sistemas_list', ['sistemas' => $sistemas]);
    }

    /**
     * Salvar/Criar Sistema
     */
    public function salvarSistema()
    {
        $id = $this->request->getPost('id');
        $data = [
            'nome' => $this->request->getPost('nome'),
            'sigla' => $this->request->getPost('sigla'),
            'descricao' => $this->request->getPost('descricao')
        ];

        if (empty($id)) {
            $this->sistemaModel->insert($data);
            $msg = 'Sistema cadastrado com sucesso!';
        } else {
            $this->sistemaModel->update($id, $data);
            $msg = 'Sistema atualizado com sucesso!';
        }

        return redirect()->to(route_to('agile.sistemas'))->with('success', $msg);
    }

    /**
     * Deletar Sistema via AJAX
     */
    public function deletarSistema($id)
    {
        if ($this->sistemaModel->delete($id)) {
            return $this->response->setJSON(['status' => 'success', 'mensagem' => 'Sistema excluído com sucesso!']);
        }
        return $this->response->setJSON(['status' => 'error', 'mensagem' => 'Erro ao excluir o sistema.']);
    }

    /**
     * Deletar Cerimônia via AJAX
     */
    public function deletarCerimonia($id)
    {
        if ($this->cerimoniaModel->delete($id)) {
            return $this->response->setJSON(['status' => 'success', 'mensagem' => 'Cerimônia excluída com sucesso!']);
        }
        return $this->response->setJSON(['status' => 'error', 'mensagem' => 'Erro ao excluir a cerimônia.']);
    }
}
