<?php
// OrangeMoneyPayment.php (Version 2 - Corrigée)

class OrangeMoneyPayment {
    private $accessToken;

    /**
     * Obtient le token d'accès auprès d'Orange Money.
     * Cette fonction a été corrigée pour construire l'en-tête d'autorisation
     * dynamiquement à partir du Client ID et du Client Secret.
     */
    private function getAccessToken() {
        if ($this->accessToken) return $this->accessToken;

        // Construction de l'en-tête d'autorisation Basic en Base64
        $credentials = 'kirUCvDEFj8ZDDx2W4ZYzf4REaaWRTrI' . ':' . '02vwBAHxto2ZFDrMjIyzjK6q5awk01wTvbjv5jIRgdLf';
        $authHeader = 'Basic ' . base64_encode($credentials);

        $ch = curl_init(ORANGE_MONEY_AUTH_URL);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: ' . $authHeader, // Utilisation de l'en-tête corrigé
            'Content-Type: application/x-www-form-urlencoded',
            'Accept: application/json'
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if (!$response) {
            throw new Exception("Erreur de connexion OAuth2: " . $error);
        }

        $data = json_decode($response, true);

        if (!isset($data['access_token'])) {
            // Log de la réponse d'erreur pour un meilleur débogage
            error_log("Réponse d'erreur d'Orange Money lors de l'authentification : " . $response);
            throw new Exception("Token OAuth2 non reçu: " . $response);
        }

        $this->accessToken = $data['access_token'];
        return $this->accessToken;
    }

    public function createPayment(array $data) {
        $token = $this->getAccessToken();
        $payload = [
            'merchant_key' => ORANGE_MONEY_MERCHANT_KEY,
            'currency' => 'GNF',
            'order_id' => $data['order_id'],
            'amount' => (int)$data['amount'],
            'return_url' => $data['return_url'],
            'cancel_url' => $data['cancel_url'],
            'notif_url' => $data['notif_url'],
            'lang' => 'fr',
            'reference' => 'DhruFusion'
        ];
        
        $url = ORANGE_MONEY_API_BASE . '/webpayment';
        error_log("Payload envoyé à Orange Money: " . json_encode($payload));

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
            'Accept: application/json'
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        error_log("Réponse d'Orange Money (Code $httpCode): " . $response);
        
        if (!$response || $httpCode >= 400) {
            throw new Exception("Erreur API Orange Money ($httpCode): " . $response);
        }

        $responseData = json_decode($response, true);
        if (empty($responseData['payment_url'])) {
            throw new Exception("Réponse invalide d'Orange Money: " . $response);
        }
        return $responseData;
    }
}
