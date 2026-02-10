<?php
// src/codeigniter-app/app/Controllers/StripeController.php

namespace App\Controllers;

use App\Helpers\StripeHelper;
use CodeIgniter\Controller;

class StripeController extends Controller
{
    public function createSession()
    {
        $customerEmail = $this->request->getPost('email');
        $priceId = $this->request->getPost('price_id');

        // Ajuste para rotas Stripe
        $successUrl = base_url('stripe/success');
        $cancelUrl = base_url('stripe/cancel');

        $session = StripeHelper::createCheckoutSession($customerEmail, $priceId, $successUrl, $cancelUrl);
        return $this->response->setJSON(['url' => $session->url]);
    }


    public function webhook()
    {
        $payload = file_get_contents('php://input');
        $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
        $secret = getenv('STRIPE_WEBHOOK_SECRET');
        \Stripe\Stripe::setApiKey(getenv('STRIPE_SECRET_KEY'));
        try {
            $event = \Stripe\Webhook::constructEvent($payload, $sig_header, $secret);
        } catch (\Exception $e) {
            http_response_code(400);
            exit();
        }
        if ($event->type === 'checkout.session.completed') {
            $session = $event->data->object;
            // Liberar acesso ao curso para o usuário
        }
        http_response_code(200);
    }


    public function success()
    {
        return view('stripe/success');
    }

    public function cancel()
    {
        return view('stripe/cancel');
    }

        // Método para testar envio de dados fake para Stripe
        public function testStripeCharge()
        {
            \Stripe\Stripe::setApiKey(getenv('STRIPE_SECRET_KEY'));
            try {
                $charge = \Stripe\Charge::create([
                    'amount' => 1000, // valor em centavos (R$10,00)
                    'currency' => 'brl',
                    'source' => 'tok_visa', // cartão de teste
                    'description' => 'Teste de cobrança Stripe',
                ]);
                return $this->response->setJSON(['status' => 'success', 'charge' => $charge]);
            } catch (\Exception $e) {
                return $this->response->setJSON(['status' => 'error', 'message' => $e->getMessage()]);
            }
        }
    }
}
