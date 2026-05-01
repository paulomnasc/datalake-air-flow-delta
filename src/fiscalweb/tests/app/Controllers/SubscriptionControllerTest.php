<?php

namespace App\Controllers;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use App\Models\UsuarioModel;

class SubscriptionControllerTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $usuarioId;

    protected function setUp(): void
    {
        parent::setUp();

        // Criar usuário de teste
        $usuarioModel = new UsuarioModel();
        $this->usuarioId = $usuarioModel->insert([
            'nome'  => 'Test Subscription User',
            'email' => 'test_sub_' . uniqid() . '@example.com',
            'senha' => password_hash('123456', PASSWORD_DEFAULT),
            'email_confirmado' => 1,
            'status_assinatura' => 'trial'
        ]);
    }

    public function test_index_returns_success_and_required_data()
    {
        $result = $this->withSession([
            'id_usuario_logado' => $this->usuarioId,
            'usuario_logado'    => 1
        ])->call('get', 'subscription/renew');

        $result->assertStatus(200);
        $result->assertSee('Renovação de Assinatura');
        
        // Verifica se as variáveis de valor estão presentes (pelo menos no HTML gerado)
        $result->assertSee('R$');
    }

    public function test_pix_payment_returns_payload()
    {
        $result = $this->withSession([
            'id_usuario_logado' => $this->usuarioId,
            'usuario_logado'    => 1
        ])->call('get', 'subscription/pixPayment');

        $result->assertStatus(200);
        // O payload do PIX começa com 000201
        $result->assertSee('000201');
    }
}
