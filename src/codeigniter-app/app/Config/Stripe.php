<?php
// src/codeigniter-app/app/Config/Stripe.php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Stripe extends BaseConfig
{
    public string $publicKey;
    public string $secretKey;

    public function __construct()
    {
        $this->publicKey = getenv('STRIPE_PUBLIC_KEY');
        $this->secretKey = getenv('STRIPE_SECRET_KEY');
    }
}
