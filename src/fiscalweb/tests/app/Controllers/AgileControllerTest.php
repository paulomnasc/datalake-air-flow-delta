<?php

namespace App\Controllers;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use App\Models\DemandaModel;
use App\Models\CerimoniaModel;
use App\Models\ParecerHomologacaoModel;

class AgileControllerTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $ordemServicoId;

    protected function setUp(): void
    {
        parent::setUp();
        
        $osModel = new \App\Models\OrdemServicoModel();
        $os = $osModel->first();
        if (!$os) {
            $this->ordemServicoId = $osModel->insert([
                'horas_alocadas' => 100,
                'nup_sei' => 'OS-SEI-12345',
                'data_emissao' => '2026-06-22',
                'data_aceite' => '2026-06-22'
            ]);
        } else {
            $this->ordemServicoId = $os->id;
        }
    }

    public function test_dashboard_returns_success()
    {
        $result = $this->withSession([
            'id_usuario_logado' => 1,
            'usuario_logado'    => 1,
            'nome_usuario_logado'=> 'Admin Test'
        ])->call('get', 'agile/dashboard');

        $result->assertStatus(200);
        $result->assertSee('Painel Ágil e Métricas');
    }

    public function test_demandas_list_returns_success()
    {
        $result = $this->withSession([
            'id_usuario_logado' => 1,
            'usuario_logado'    => 1,
            'nome_usuario_logado'=> 'Admin Test'
        ])->call('get', 'agile/demandas');

        $result->assertStatus(200);
        $result->assertSee('Gestão de Demandas');
    }

    public function test_gatelink_critical_system_starts_in_serpro_status()
    {
        $result = $this->withSession([
            'id_usuario_logado' => 1,
            'usuario_logado'    => 1,
            'nome_usuario_logado'=> 'Admin Test'
        ])->call('post', 'agile/demanda/insert', [
            'titulo' => 'Demanda Crítica Teste',
            'descricao' => 'Teste de criticidade Gatelink',
            'sistema_critico' => 1,
            'id_ordem_servico' => $this->ordemServicoId
        ]);

        // Verifica redirecionamento
        $result->assertRedirectTo(route_to('agile.demandas'));

        // Verifica no banco de dados
        $demandaModel = new DemandaModel();
        $demanda = $demandaModel->where('titulo', 'Demanda Crítica Teste')->first();
        $this->assertNotNull($demanda);
        $this->assertEquals('Preparar Demanda SERPRO', $demanda->status);
        $this->assertEquals(1, $demanda->sistema_critico);

        // Limpa registro de teste
        $demandaModel->delete($demanda->id);
    }

    public function test_gatelink_non_critical_system_starts_in_fabricas_status()
    {
        $result = $this->withSession([
            'id_usuario_logado' => 1,
            'usuario_logado'    => 1,
            'nome_usuario_logado'=> 'Admin Test'
        ])->call('post', 'agile/demanda/insert', [
            'titulo' => 'Demanda Comum Teste',
            'descricao' => 'Teste de criticidade Gatelink comum',
            'sistema_critico' => 0,
            'id_ordem_servico' => $this->ordemServicoId
        ]);

        $result->assertRedirectTo(route_to('agile.demandas'));

        $demandaModel = new DemandaModel();
        $demanda = $demandaModel->where('titulo', 'Demanda Comum Teste')->first();
        $this->assertNotNull($demanda);
        $this->assertEquals('Alocar Time Fábricas', $demanda->status);
        $this->assertEquals(0, $demanda->sistema_critico);

        $demandaModel->delete($demanda->id);
    }

    public function test_sprint_planning_lock_fails_without_ceremony()
    {
        // Cria demanda
        $demandaModel = new DemandaModel();
        $demandaId = $demandaModel->insert([
            'titulo' => 'Lock Sprint Test',
            'descricao' => 'Teste de trava de planejamento',
            'sistema_critico' => 0,
            'status' => 'Refinamento Backlog',
            'id_ordem_servico' => $this->ordemServicoId
        ]);

        // Tenta iniciar Sprint sem cerimônia de planejamento cadastrada
        $result = $this->withSession([
            'id_usuario_logado' => 1,
            'usuario_logado'    => 1,
            'nome_usuario_logado'=> 'Admin Test'
        ])->call('post', 'agile/sprint/salvar', [
            'id_demanda' => $demandaId,
            'meta' => 'Meta da Sprint sem Planejamento',
            'data_inicio' => '2026-06-22',
            'data_fim' => '2026-07-06'
        ]);

        // Deve redirecionar de volta com mensagem de erro
        $result->assertSessionHas('error');
        $this->assertStringContainsString('Bloqueio de Planejamento', session()->getFlashdata('error'));

        // Limpa
        $demandaModel->delete($demandaId);
    }

    public function test_release_lock_fails_without_homologacao_favoravel()
    {
        // Cria demanda
        $demandaModel = new DemandaModel();
        $demandaId = $demandaModel->insert([
            'titulo' => 'Lock Release Test',
            'descricao' => 'Teste de trava de homologação',
            'sistema_critico' => 0,
            'status' => 'Homologação',
            'id_ordem_servico' => $this->ordemServicoId
        ]);

        // Tenta submeter release sem parecer favorável do PO
        $result = $this->withSession([
            'id_usuario_logado' => 1,
            'usuario_logado'    => 1,
            'nome_usuario_logado'=> 'Admin Test'
        ])->call('post', 'agile/demanda/release', [
            'id_demanda' => $demandaId,
            'ticket_rdm' => 'RDM-2026-ERROR',
            'servidor_deploy' => 'PRD-ERR-01',
            'janela_homologacao' => 'Hoje'
        ]);

        // Deve falhar e redirecionar com erro
        $result->assertSessionHas('error');
        $this->assertStringContainsString('Garantia de Homologação', session()->getFlashdata('error'));

        $demandaModel->delete($demandaId);
    }

    public function test_sistemas_list_returns_success()
    {
        $result = $this->withSession([
            'id_usuario_logado' => 1,
            'usuario_logado'    => 1,
            'nome_usuario_logado'=> 'Admin Test'
        ])->call('get', 'agile/sistemas');

        $result->assertStatus(200);
        $result->assertSee('Cadastro de Sistemas');
    }

    public function test_salvar_sistema_and_bind_to_demanda()
    {
        $session = [
            'id_usuario_logado' => 1,
            'usuario_logado'    => 1,
            'nome_usuario_logado'=> 'Admin Test'
        ];

        // 1. Cadastra o sistema
        $result = $this->withSession($session)->call('post', 'agile/sistemas/salvar', [
            'sigla' => 'TSIS',
            'nome' => 'Sistema de Teste Relacionamento',
            'descricao' => 'Descrição do sistema de teste'
        ]);

        $result->assertRedirectTo(route_to('agile.sistemas'));

        // Verifica no BD se o sistema foi criado
        $sistemaModel = new \App\Models\SistemaModel();
        $sistema = $sistemaModel->where('sigla', 'TSIS')->first();
        $this->assertNotNull($sistema);

        // 2. Cadastra demanda vinculada ao sistema criado
        $resultDemanda = $this->withSession($session)->call('post', 'agile/demanda/insert', [
            'id_sistema' => $sistema->id,
            'titulo' => 'Demanda Vinculada Teste',
            'descricao' => 'Descrição da demanda vinculada',
            'sistema_critico' => 0,
            'id_ordem_servico' => $this->ordemServicoId
        ]);

        $resultDemanda->assertRedirectTo(route_to('agile.demandas'));

        // Verifica no BD se a demanda está associada ao ID do sistema
        $demandaModel = new DemandaModel();
        $demanda = $demandaModel->where('titulo', 'Demanda Vinculada Teste')->first();
        $this->assertNotNull($demanda);
        $this->assertEquals($sistema->id, $demanda->id_sistema);

        // Limpa
        $demandaModel->delete($demanda->id);
        $sistemaModel->delete($sistema->id);
    }

    public function test_deletar_cerimonia_returns_success()
    {
        $demandaModel = new DemandaModel();
        $demandaId = $demandaModel->insert([
            'titulo' => 'Demanda Teste Cerimonia',
            'descricao' => 'Descricao de teste',
            'sistema_critico' => 0,
            'status' => 'Refinamento Backlog',
            'id_ordem_servico' => $this->ordemServicoId
        ]);

        $cerimoniaModel = new CerimoniaModel();
        $cerimoniaId = $cerimoniaModel->insert([
            'id_demanda' => $demandaId,
            'tipo_cerimonia' => 'Daily',
            'data_hora_agendada' => '2026-06-22 10:00:00'
        ]);

        $result = $this->withSession([
            'id_usuario_logado' => 1,
            'usuario_logado'    => 1,
            'nome_usuario_logado'=> 'Admin Test'
        ])->call('delete', 'agile/cerimonia/deletar/' . $cerimoniaId);

        // Assert response status
        $result->assertStatus(200);
        $result->assertJSONFragment(['status' => 'success']);

        // Assert record is deleted from db
        $cerimoniaObj = $cerimoniaModel->find($cerimoniaId);
        $this->assertNull($cerimoniaObj);

        // Clean up
        $demandaModel->delete($demandaId);
    }

    public function test_sprint_review_fails_with_non_pronto_items()
    {
        // Cria demanda
        $demandaModel = new DemandaModel();
        $demandaId = $demandaModel->insert([
            'titulo' => 'Review Fail Test',
            'descricao' => 'Teste de falha no review',
            'sistema_critico' => 0,
            'status' => 'Em Execução',
            'id_ordem_servico' => $this->ordemServicoId
        ]);

        // Cria sprint ativa
        $sprintModel = new \App\Models\SprintModel();
        $sprintId = $sprintModel->insert([
            'id_demanda' => $demandaId,
            'meta' => 'Test Meta',
            'data_inicio' => '2026-06-22',
            'data_fim' => '2026-07-06',
            'status' => 'Ativa'
        ]);

        // Cria item de backlog com status_kanban != 'Pronto' (por exemplo, 'A Fazer')
        $backlogItemModel = new \App\Models\BacklogItemModel();
        $itemId = $backlogItemModel->insert([
            'id_demanda' => $demandaId,
            'titulo' => 'Tarefa Impedida ou A Fazer',
            'status_kanban' => 'A Fazer',
            'pontuacao' => 3
        ]);

        // Tenta encerrar a sprint
        $result = $this->withSession([
            'id_usuario_logado' => 1,
            'usuario_logado'    => 1,
            'nome_usuario_logado'=> 'Admin Test'
        ])->call('post', 'agile/sprint/review', [
            'id_sprint' => $sprintId,
            'id_demanda' => $demandaId
        ]);

        // Deve falhar e redirecionar com erro na sessão
        $result->assertSessionHas('error');
        $this->assertStringContainsString('devem estar na coluna Pronto', session()->getFlashdata('error'));

        // Limpa
        $backlogItemModel->delete($itemId);
        $sprintModel->delete($sprintId);
        $demandaModel->delete($demandaId);
    }

    public function test_sprint_review_success_when_all_items_are_pronto()
    {
        // Cria demanda
        $demandaModel = new DemandaModel();
        $demandaId = $demandaModel->insert([
            'titulo' => 'Review Success Test',
            'descricao' => 'Teste de sucesso no review',
            'sistema_critico' => 0,
            'status' => 'Em Execução',
            'id_ordem_servico' => $this->ordemServicoId
        ]);

        // Cria sprint ativa
        $sprintModel = new \App\Models\SprintModel();
        $sprintId = $sprintModel->insert([
            'id_demanda' => $demandaId,
            'meta' => 'Test Meta',
            'data_inicio' => '2026-06-22',
            'data_fim' => '2026-07-06',
            'status' => 'Ativa'
        ]);

        // Cria item de backlog com status_kanban = 'Pronto'
        $backlogItemModel = new \App\Models\BacklogItemModel();
        $itemId = $backlogItemModel->insert([
            'id_demanda' => $demandaId,
            'titulo' => 'Tarefa Concluida',
            'status_kanban' => 'Pronto',
            'pontuacao' => 3
        ]);

        // Tenta encerrar a sprint
        $result = $this->withSession([
            'id_usuario_logado' => 1,
            'usuario_logado'    => 1,
            'nome_usuario_logado'=> 'Admin Test'
        ])->call('post', 'agile/sprint/review', [
            'id_sprint' => $sprintId,
            'id_demanda' => $demandaId
        ]);

        // Deve redirecionar para o kanban sem erros
        $result->assertRedirectTo(route_to('agile.kanban', $demandaId));

        // Limpa
        $backlogItemModel->delete($itemId);
        $sprintModel->delete($sprintId);
        $demandaModel->delete($demandaId);
    }

    public function test_update_status_only_does_not_require_ordem_servico()
    {
        // Cria demanda vinculada a essa ordem de serviço
        $demandaModel = new DemandaModel();
        $demandaId = $demandaModel->insert([
            'titulo' => 'Status Update Test',
            'descricao' => 'Teste de transição de status via kanban',
            'sistema_critico' => 0,
            'status' => 'CCM',
            'id_ordem_servico' => $this->ordemServicoId
        ]);

        // Simula request do CCM/Kanban enviando apenas id, titulo, descricao, sistema_critico e status (sem id_ordem_servico)
        $result = $this->withSession([
            'id_usuario_logado' => 1,
            'usuario_logado'    => 1,
            'nome_usuario_logado'=> 'Admin Test'
        ])->call('post', 'agile/demanda/update', [
            'id' => $demandaId,
            'titulo' => 'Status Update Test',
            'descricao' => 'Teste de transição de status via kanban',
            'sistema_critico' => 0,
            'status' => 'Atualizado Produção'
        ]);

        // Deve ter sucesso e redirecionar para a lista de demandas
        $result->assertRedirectTo(route_to('agile.demandas'));

        // Verifica se a demanda foi atualizada para "Atualizado Produção" e a ordem de serviço foi preservada
        $demandaObj = $demandaModel->find($demandaId);
        $this->assertNotNull($demandaObj);
        $this->assertEquals('Atualizado Produção', $demandaObj->status);
        $this->assertEquals($this->ordemServicoId, $demandaObj->id_ordem_servico);

        // Limpa
        $demandaModel->delete($demandaId);
    }

    public function test_sprint_planning_card_appears_in_execution_status_without_active_sprint()
    {
        // Cria demanda em execução sem sprint ativa
        $demandaModel = new DemandaModel();
        $demandaId = $demandaModel->insert([
            'titulo' => 'Planning Card Test',
            'descricao' => 'Teste de exibição do card de planejamento no status de execução',
            'sistema_critico' => 0,
            'status' => 'Em Execução',
            'id_ordem_servico' => $this->ordemServicoId
        ]);

        // Acessa o Kanban da demanda
        $result = $this->withSession([
            'id_usuario_logado' => 1,
            'usuario_logado'    => 1,
            'nome_usuario_logado'=> 'Admin Test'
        ])->call('get', 'agile/kanban/' . $demandaId);

        $result->assertStatus(200);
        // Deve ver a caixa de "Iniciar Planejamento da Sprint"
        $result->assertSee('Iniciar Planejamento da Sprint');

        // Limpa
        $demandaModel->delete($demandaId);
    }
}
