<?php
/**
 * Configuration de la base de données MySQL
 * Ce fichier contient la classe Database pour la connexion MySQL
 * Compatible avec Orange Money Payment Gateway
 */

class Database
{
    private $db_type = 'mysql';
    
    // Vos informations MySQL de production
    private $host = 'localhost';
    private $db_name = 'ttyyqpmy_MyOm';        // Votre base de données
    private $username = 'ttyyqpmy_MyOmUser';   // Votre utilisateur MySQL
    private $password = 'MyOmPassword25@';     // Votre mot de passe MySQL
    
    public $conn;

    public function __construct($config = [])
    {
        // Permettre de surcharger la config si nécessaire
        $this->host = $config['host'] ?? $this->host;
        $this->db_name = $config['db_name'] ?? $this->db_name;
        $this->username = $config['username'] ?? $this->username;
        $this->password = $config['password'] ?? $this->password;
    }

    public function connect()
    {
        $this->conn = null;

        try {
            $this->conn = new PDO(
                "mysql:host=$this->host;dbname=$this->db_name;charset=utf8", 
                $this->username, 
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Créer la table orders si elle n'existe pas
            $this->initializeTables();
            
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Database Connection Error: ' . $e->getMessage()]);
            exit;
        }

        return $this->conn;
    }

    private function initializeTables()
    {
        $createQuery = "
            CREATE TABLE IF NOT EXISTS orders (
                order_id INT AUTO_INCREMENT PRIMARY KEY,
                amount DECIMAL(10,5),
                amount_gnf DECIMAL(15,0),
                currency_code VARCHAR(10),
                currency_om VARCHAR(10) DEFAULT 'GNF',
                exchange_rate DECIMAL(10,4),
                description TEXT,
                customer_name VARCHAR(255),
                customer_email VARCHAR(255),
                custom_id VARCHAR(50),
                ipn_url TEXT,
                success_url TEXT,
                fail_url TEXT,
                order_date DATETIME NOT NULL,
                status VARCHAR(50) DEFAULT 'pending',
                received_amount DECIMAL(10,5),
                transaction_id VARCHAR(255),
                pay_token VARCHAR(255),
                notif_token VARCHAR(255),
                order_id_om VARCHAR(255),
                api_response LONGTEXT,
                received_info LONGTEXT,
                ipn_response LONGTEXT
               
            );
        ";

        try {
            $this->conn->exec($createQuery);
            error_log("Table 'orders' créée ou vérifiée avec succès");
        } catch (PDOException $e) {
            error_log("Error creating table 'orders': " . $e->getMessage());
        }
    }

    /**
     * Méthode pour tester la connexion
     */
    public function testConnection()
    {
        try {
            $this->connect();
            return ['status' => 'success', 'message' => 'Connexion à la base de données réussie'];
        } catch (Exception $e) {
            return ['status' => 'error', 'message' => 'Erreur de connexion: ' . $e->getMessage()];
        }
    }

    /**
     * Méthode pour fermer la connexion
     */
    public function close()
    {
        $this->conn = null;
    }
}

// Configuration globale pour compatibilité avec d'autres scripts
$config = [
    'db_type' => 'mysql',
    'host' => 'localhost',
    'db_name' => 'ttyyqpmy_MyOm',
    'username' => 'ttyyqpmy_MyOmUser',
    'password' => 'MyOmPassword25@',
];

// Instance globale pour compatibilité
$database = new Database();
?>
