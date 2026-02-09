<?php
// src/codeigniter-app/app/Helpers/StripeHelper.php

namespace App\Helpers;

use Stripe\Stripe;
use Stripe\Checkout\Session;

class StripeHelper
{
    public static function createCheckoutSession($customerEmail, $priceId, $successUrl, $cancelUrl)
    {
        Stripe::setApiKey(getenv('STRIPE_SECRET_KEY'));
        return Session::create([
            'customer_email' => $customerEmail,
            'line_items' => [[
                'price' => $priceId,
                'quantity' => 1,
            ]],
            'mode' => 'subscription',
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
        ]);
    }
}
