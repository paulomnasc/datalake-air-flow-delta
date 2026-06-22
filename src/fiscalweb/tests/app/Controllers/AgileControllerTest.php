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

    protected function setUp(): void
    {
        parent::setUp();
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
            'sistema_critico' => 1
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
            'sistema_critico' => 0
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
            'status' => 'Refinamento Backlog'
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
            'status' => 'Homologação'
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
}
