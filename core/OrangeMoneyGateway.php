<?php
/*
 * Simple Orange Money API client
 */
class OrangeMoneyGateway {
    private $clientId;
    private $clientSecret;
    private $merchantKey;
    private $baseUrl;
    private $token;
    private $tokenExpire;

    public function __construct($config)
    {
        $this->clientId = $config['client_id'];
        $this->clientSecret = $config['client_secret'];
        $this->merchantKey = $config['merchant_key'];
        $this->baseUrl = rtrim($config['base_url'], '/');
    }

    private function getAccessToken()
    {
        if ($this->token && $this->tokenExpire > time() + 60) {
            return $this->token;
        }

        $url = $this->baseUrl . '/oauth/v2/token';
        logMessage('Requesting new access token');
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['grant_type' => 'client_credentials']));

        // Use cURL's built-in basic authentication.
        // We still specify the content type for clarity.
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_USERPWD, $this->clientId . ':' . $this->clientSecret);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded'
        ]);
        $response = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        logMessage('Token response code: ' . $code);
        logMessage('Token response body: ' . $response);
        curl_close($ch);
        if ($code === 200) {
            $data = json_decode($response, true);
            $this->token = $data['access_token'];
            $this->tokenExpire = time() + (int)$data['expires_in'];
            return $this->token;
        }
        return null;
    }

    public function initTransaction($payload)
    {
        $token = $this->getAccessToken();
        if (!$token) {
            return ['status_code' => 0, 'body' => 'Unable to get access token'];
        }
        // Utiliser le chemin complet de l'API Orange Money Web Payment
        $url = $this->baseUrl . '/orange-money-webpay/dev/v1/webpayment';
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
            'Accept: application/json'
        ]);
        $response = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        logMessage('Init transaction code: ' . $code);
        logMessage('Init transaction body: ' . $response);
        curl_close($ch);
        return ['status_code' => $code, 'body' => $response];
    }

    public function getTransactionStatus($payload)
    {
        $token = $this->getAccessToken();
        if (!$token) {
            return ['status_code' => 0, 'body' => 'Unable to get access token'];
        }
        // Utiliser le chemin complet de l'API Orange Money Web Payment
        $url = $this->baseUrl . '/orange-money-webpay/dev/v1/transactionstatus';
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
            'Accept: application/json'
        ]);
        $response = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        logMessage('Status check code: ' . $code);
        logMessage('Status check body: ' . $response);
        curl_close($ch);
        return ['status_code' => $code, 'body' => $response];
    }

    public function getMerchantKey()
    {
        return $this->merchantKey;
    }
}
