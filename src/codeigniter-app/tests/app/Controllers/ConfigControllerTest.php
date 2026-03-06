<?php

if (!defined('SUPPORTPATH')) {
    define('SUPPORTPATH', __DIR__ . '/../../../../support/');
}

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use App\Models\UsuarioModel;
use App\Models\PastaModel;
use App\Models\SourceTypeModel;
use App\Models\ConfigModel;

class ConfigControllerTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $usuarioId;
    protected $pastaId;
    protected $sourceTypeId;

    protected function setUp(): void
    {
        parent::setUp();

        // Criar usuário de teste com email único
        $usuarioModel = new UsuarioModel();
        $email = 'test_' . uniqid() . '@example.com';
        $this->usuarioId = $usuarioModel->insert([
            'nome'  => 'Test User',
            'email' => $email,
            'senha' => password_hash('123456', PASSWORD_DEFAULT),
            'email_confirmado' => 1
        ]);

        // Criar pasta de teste com descrição única
        $pastaModel = new PastaModel();
        $this->pastaId = $pastaModel->insert([
            'descricao'  => 'Pasta de Teste ' . uniqid(),
            'id_usuario' => $this->usuarioId
        ]);

        // Buscar ou criar tipo de fonte 'api' (simples, evita MinIO/SQL)
        $sourceTypeModel = new SourceTypeModel();
        $sourceType = $sourceTypeModel->where('description', 'api')->first();
        
        if ($sourceType) {
            $this->sourceTypeId = $sourceType['id'];
        } else {
            $this->sourceTypeId = $sourceTypeModel->insert([
                'description' => 'api'
            ]);
        }
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    /**
     * Teste de criação de pipeline
     */
    public function test_criacao_pipeline_api()
    {
        $dagId = 'dag_' . uniqid();
        $postData = [
            'dag_id'             => $dagId,
            'id_pasta'           => $this->pastaId,
            'id_source_type'     => $this->sourceTypeId,
            'owner'              => 'tester',
            'description'        => 'Pipeline created during test',
            'schedule_interval'  => '@daily',
            'python_module_path' => 'spark.medallion_pipeline',
            'source_filename'    => 'http://api.example.com/data'
        ];

        $result = $this->withSession([
            'id_usuario_logado' => $this->usuarioId,
            'usuario_logado'    => 1,
            'perfil_usuario_logado' => 'Admin'
        ])->call('post', 'insertConfig', $postData);

        $result->assertStatus(200);
        
        $json = json_decode(strip_tags($result->getBody()), true);
        
        $this->assertEquals('success', $json['status'] ?? 'null', "Mensagem: " . ($json['mensagem'] ?? 'N/A'));

        // Verificar no banco
        $this->seeInDatabase('dag_configurations', [
            'dag_id'   => $dagId,
            'id_pasta' => $this->pastaId
        ]);
    }

    /**
     * Teste de edição de pipeline
     */
    public function test_update_config_success()
    {
        $initialDagId = 'dag_to_update_' . uniqid();
        $updatedDagId = 'dag_updated_' . uniqid();

        // 1. Criar uma config inicial
        $configModel = new ConfigModel();
        $configId = $configModel->insert([
            'dag_id'             => $initialDagId,
            'id_pasta'           => $this->pastaId,
            'id_source_type'     => $this->sourceTypeId,
            'owner'              => 'tester',
            'python_module_path' => 'old.path'
        ]);

        // 2. Tentar atualizar via POST
        $updateData = [
            'id'                 => $configId,
            'dag_id'             => $updatedDagId,
            'id_pasta'           => $this->pastaId,
            'id_source_type'     => $this->sourceTypeId,
            'python_module_path' => 'new.path'
        ];

        $result = $this->withSession([
            'id_usuario_logado' => $this->usuarioId,
            'usuario_logado'    => 1,
            'perfil_usuario_logado' => 'Admin'
        ])->call('post', 'updateConfig', $updateData);

        $result->assertStatus(200);

        // 3. Verificar se mudou no banco
        $this->seeInDatabase('dag_configurations', [
            'id'                 => $configId,
            'dag_id'             => $updatedDagId,
            'python_module_path' => 'new.path'
        ]);
    }

    /**
     * Teste de falha na criação (dag_id duplicado)
     */
    public function test_insert_duplicate_dag_id()
    {
        $dagId = 'duplicate_dag_' . uniqid();
        $data = [
            'dag_id'             => $dagId,
            'id_pasta'           => $this->pastaId,
            'id_source_type'     => $this->sourceTypeId,
            'python_module_path' => 'test'
        ];

        // Inserir a primeira vez
        $configModel = new ConfigModel();
        $configModel->insert($data);

        // Tentar inserir a segunda vez
        $result = $this->withSession([
            'id_usuario_logado' => $this->usuarioId,
            'usuario_logado'    => 1,
            'perfil_usuario_logado' => 'Admin'
        ])->call('post', 'insertConfig', $data);

        $cleanBody = html_entity_decode(strip_tags($result->getBody()));
        $json = json_decode($cleanBody, true);
        
        $this->assertEquals('error', $json['status'] ?? 'null');
        $this->assertStringContainsString('existe um pipeline com o nome', $json['mensagem'] ?? '');
    }

    /**
     * Teste de remoção
     */
    public function test_delete_config()
    {
        $dagId = 'to_be_deleted_' . uniqid();
        $configModel = new ConfigModel();
        $configId = $configModel->insert([
            'dag_id'         => $dagId,
            'id_pasta'       => $this->pastaId,
            'id_source_type' => $this->sourceTypeId
        ]);

        $result = $this->withSession([
            'id_usuario_logado' => $this->usuarioId,
            'usuario_logado'    => 1,
            'perfil_usuario_logado' => 'Admin'
        ])->call('delete', "deleteConfig/{$configId}");

        $result->assertStatus(200);
        $this->dontSeeInDatabase('dag_configurations', ['id' => $configId]);
    }
}
