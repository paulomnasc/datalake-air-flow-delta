<?php

namespace App\Libraries;

class MercadoPagoService
{
    private string $accessToken;
    private string $publicKey;
    private string $baseUrl = 'https://api.mercadopago.com';

    public function __construct()
    {
        $this->accessToken = getenv('MERCADOPAGO_ACCESS_TOKEN') ?: 'TEST-8751326938692679-071918-dc0306ec299c7a8f5baeb68f93bb0481-25228421';
        $this->publicKey   = getenv('MERCADOPAGO_PUBLIC_KEY') ?: 'TEST-32dd5097-2b01-43c6-9062-ad19b5937b6d';
    }

    /**
     * Criar um pagamento via Pix no Mercado Pago
     *
     * @param float  $valor               Valor da cobrança em BRL
     * @param string $descricao           Descrição da transação
     * @param string $email               Email do pagador
     * @param string $nome                Nome completo do pagador
     * @param string $documento           CPF ou CNPJ (opcional)
     * @param string $externalReference   ID de referência externa (ex: user_id)
     * @param string|null $notificationUrl URL de Webhook para receber notificações
     * @return array
     */
    public function criarPagamentoPix(
        float $valor,
        string $descricao,
        string $email,
        string $nome = 'Usuário',
        string $documento = '',
        string $externalReference = '',
        ?string $notificationUrl = null
    ): array {
        $nomePartes = explode(' ', trim($nome), 2);
        $firstName  = $nomePartes[0] ?? 'Usuário';
        $lastName   = $nomePartes[1] ?? 'Cliente';

        $payerData = [
            'email'      => $email ?: 'cliente@mydataflow.com.br',
            'first_name' => $firstName,
            'last_name'  => $lastName
        ];

        $docLimpo = preg_replace('/[^0-9]/', '', $documento);
        if (!empty($docLimpo)) {
            $payerData['identification'] = [
                'type'   => strlen($docLimpo) === 14 ? 'CNPJ' : 'CPF',
                'number' => $docLimpo
            ];
        }

        $payload = [
            'transaction_amount' => round($valor, 2),
            'description'        => $descricao,
            'payment_method_id'  => 'pix',
            'payer'              => $payerData,
            'external_reference' => (string) $externalReference
        ];

        if (!empty($notificationUrl)) {
            $payload['notification_url'] = $notificationUrl;
        }

        $idempotencyKey = uniqid('mp_pix_', true);
        $url = $this->baseUrl . '/v1/payments';

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $this->accessToken,
                'Content-Type: application/json',
                'X-Idempotency-Key: ' . $idempotencyKey
            ],
            CURLOPT_SSL_VERIFYPEER => false // Evita falha de certs em ambientes Docker sem CA bundle local
        ]);

        $responseBody = curl_exec($ch);
        $statusCode   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError    = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            if (function_exists('log_message')) {
                log_message('error', '[MERCADOPAGO] Erro cURL ao criar Pix: ' . $curlError);
            }
            return [
                'success' => false,
                'message' => 'Falha de conexão cURL com Mercado Pago: ' . $curlError
            ];
        }

        $body = json_decode($responseBody, true) ?: [];

        if ($statusCode >= 200 && $statusCode < 300 && !empty($body['id'])) {
            $transactionData = $body['point_of_interaction']['transaction_data'] ?? [];

            return [
                'success'           => true,
                'payment_id'        => (string) $body['id'],
                'status'            => $body['status'] ?? 'pending',
                'status_detail'     => $body['status_detail'] ?? '',
                'qr_code'           => $transactionData['qr_code'] ?? '',
                'qr_code_base64'    => $transactionData['qr_code_base64'] ?? '',
                'ticket_url'        => $transactionData['ticket_url'] ?? '',
                'transaction_amount'=> $body['transaction_amount'] ?? $valor,
                'raw_response'      => $body
            ];
        } else {
            $errorMessage = $body['message'] ?? ($body['cause'][0]['description'] ?? 'Erro ao comunicar com Mercado Pago');
            if (function_exists('log_message')) {
                log_message('error', '[MERCADOPAGO] Erro na criação de Pix (HTTP ' . $statusCode . '): ' . json_encode($body));
            }
            return [
                'success' => false,
                'message' => $errorMessage,
                'raw'     => $body
            ];
        }
    }

    /**
     * Consultar o status de um pagamento pelo ID no Mercado Pago
     *
     * @param string|int $paymentId ID da transação no Mercado Pago
     * @return array
     */
    public function consultarPagamento($paymentId): array
    {
        $url = $this->baseUrl . '/v1/payments/' . $paymentId;

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPGET        => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $this->accessToken,
                'Content-Type: application/json'
            ],
            CURLOPT_SSL_VERIFYPEER => false
        ]);

        $responseBody = curl_exec($ch);
        $statusCode   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError    = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            if (function_exists('log_message')) {
                log_message('error', '[MERCADOPAGO] Erro cURL ao consultar pagamento ' . $paymentId . ': ' . $curlError);
            }
            return [
                'success' => false,
                'message' => 'Erro ao consultar pagamento no Mercado Pago: ' . $curlError
            ];
        }

        $body = json_decode($responseBody, true) ?: [];

        if ($statusCode === 200 && !empty($body['id'])) {
            return [
                'success'       => true,
                'payment_id'    => (string) $body['id'],
                'status'        => $body['status'] ?? 'unknown',
                'status_detail' => $body['status_detail'] ?? '',
                'raw_response'  => $body
            ];
        } else {
            return [
                'success' => false,
                'message' => $body['message'] ?? 'Pagamento não encontrado',
                'raw'     => $body
            ];
        }
    }

    public function getPublicKey(): string
    {
        return $this->publicKey;
    }
}
