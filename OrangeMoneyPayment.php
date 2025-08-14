<?php
    // OrangeMoneyPayment.php : implémentation simple de l'API Orange Money Web Payment
    // Cette classe utilise directement les constantes définies dans config.php.

    class OrangeMoneyPayment {
        private $accessToken;

        /**
         * Obtient un token d'accès auprès d'Orange Money en utilisant l'en‑tête Basic déjà encodé.
         *
         * @throws Exception si la connexion ou la réponse échoue.
         */
        private function getAccessToken() {
            if ($this->accessToken) {
                return $this->accessToken;
            }

            $authHeader = ORANGE_MONEY_AUTH_HEADER;
            $ch         = curl_init(ORANGE_MONEY_AUTH_URL);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: ' . $authHeader,
                'Content-Type: application/x-www-form-urlencoded',
                'Accept: application/json'
            ]);

            $response = curl_exec($ch);
            $error    = curl_error($ch);
            curl_close($ch);

            if (!$response) {
                throw new Exception('Erreur de connexion OAuth2: ' . $error);
            }

            $data = json_decode($response, true);
            if (!isset($data['access_token'])) {
                error_log('Réponse d\'erreur d\'Orange Money lors de l\'authentification : ' . $response);
                throw new Exception('Token OAuth2 non reçu: ' . $response);
            }
            $this->accessToken = $data['access_token'];
            return $this->accessToken;
        }

        /**
         * Crée un lien de paiement Orange Money à partir des informations passées.
         *
         * @param array $data Données à envoyer à l'API (order_id, amount, return_url…)
         * @return array      Les données renvoyées par l'API Orange Money
         * @throws Exception  En cas d'erreur réseau ou d'erreur dans la réponse
         */
        public function createPayment(array $data) {
            $token   = $this->getAccessToken();

            // Prépare le payload avec vos paramètres et constantes
            $payload = [
                'merchant_key' => ORANGE_MONEY_MERCHANT_KEY,
                'currency'     => 'GNF',
                'order_id'     => $data['order_id'],
                'amount'       => (int)$data['amount'],
                'return_url'   => $data['return_url'],
                'cancel_url'   => $data['cancel_url'],
                'notif_url'    => $data['notif_url'],
                'lang'         => 'fr',
                'reference'    => 'DhruFusion'
            ];

            $url = ORANGE_MONEY_API_BASE . '/webpayment';
            $ch  = curl_init($url);
            curl_setopt($ch, CURLOPT_POST, true);
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

            if (!$response || $httpCode >= 400) {
                throw new Exception('Erreur API Orange Money (' . $httpCode . '): ' . $response);
            }

            $responseData = json_decode($response, true);
            if (empty($responseData['payment_url'])) {
                throw new Exception('Réponse invalide d\'Orange Money: ' . $response);
            }
            return $responseData;
        }
    }