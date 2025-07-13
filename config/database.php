<?php
/*
 * This file is part of the Dhru Fusion Pro Payment Gateway.
 *
 * @license    Proprietary
 * @copyright  2024 Dhru.com
 * @author     Dhru Fusion Team
 * @description Custom Payment Gateway Development Kit for Dhru Fusion Pro.
 * @powered    Powered by Dhru.com
 */

// Charger les variables d'environnement
 $env = include __DIR__ . '/../env.php';

class Database
{
    private $db_type;
    private $host;
    private $db_name;
    private $username;
    private $password;
    private $sqlite_file;

    public $conn;

    public function __construct($config = [])
    {
        global $env; // Accéder aux variables d'environnement globales

        $this->db_type = $config['db_type'] ?? $env['DB_TYPE'];

        if ($this->db_type === 'mysql') {
            $this->host = $config['host'] ?? $env['DB_HOST'];
            $this->db_name = $config['db_name'] ?? $env['DB_NAME'];
            $this->username = $config['username'] ?? $env['DB_USER'];
            $this->password = $config['password'] ?? $env['DB_PASS'];
        } elseif ($this->db_type === 'sqlite') {
            $this->sqlite_file = $config['sqlite_file'] ?? $env['SQLITE_FILE'];
        }
    }

    public function connect()
    {
        $this->conn = null;

        try {
            if ($this->db_type === 'sqlite') {
                $this->conn = new PDO("sqlite:" . $this->sqlite_file);
            } elseif ($this->db_type === 'mysql') {
                $this->conn = new PDO("mysql:host=$this->host;dbname=$this->db_name", $this->username, $this->password);
            } else {
                throw new Exception("Unsupported database type: {$this->db_type}");
            }
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $this->initializeTables();

        } catch (PDOException $e) {
            // Utiliser la fonction output définie dans common.php
            output('error', 'Connection Error: ' . $e->getMessage(), null, 500);
        } catch (Exception $e) {
            output('error', 'Error: ' . $e->getMessage(), null, 500);
        }

        return $this->conn;
    }

    private function initializeTables()
    {
        $tables = [
            "orders" => "
            CREATE TABLE IF NOT EXISTS orders (
                order_id INT AUTO_INCREMENT PRIMARY KEY, -- Correction ici
                amount DECIMAL(10,5),
                currency_code VARCHAR(10),
                description TEXT NOT NULL,
                customer_name VARCHAR(255),
                customer_email VARCHAR(255),
                custom_id VARCHAR(50),
                ipn_url TEXT,
                success_url TEXT,
                fail_url TEXT,
                order_date DATETIME NOT NULL,
                status VARCHAR(50),
                received_amount DECIMAL(10,5),
                transaction_id VARCHAR(255),
                received_info LONGTEXT,
                ipn_response LONGTEXT,
                pay_token TEXT,
                payment_url TEXT,
                notif_token TEXT
            );
            ",
            // Add more tables here
        ];

        foreach ($tables as $tableName => $tableQuery) {
            try {
                $this->conn->exec($tableQuery);
            } catch (PDOException $e) {
                error_log("Error creating table '{$tableName}': " . $e->getMessage());
            }
        }
    }
}
