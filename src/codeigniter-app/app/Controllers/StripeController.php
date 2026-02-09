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

    public function success()
    {
        return view('stripe/success');
    }

    public function cancel()
    {
        return view('stripe/cancel');
    }
    }
}
