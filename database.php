<?php
// database.php

class Database {
    private $conn;

    public function connect() {
        if ($this->conn) {
            return $this->conn;
        }

        try {
            if (DB_TYPE === 'sqlite') {
                $this->conn = new PDO('sqlite:' . DB_SQLITE_PATH);
            } else {
                $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
                $this->conn = new PDO($dsn, DB_USER, DB_PASS);
            }
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $this->initializeTables();
        } catch (PDOException $e) {
            error_log("Erreur de connexion BDD: " . $e->getMessage());
            throw new Exception("Erreur de connexion à la base de données.");
        }
        return $this->conn;
    }

    private function initializeTables() {
        $sql = (DB_TYPE === 'sqlite') ? 
        "CREATE TABLE IF NOT EXISTS orders (
            order_id INTEGER PRIMARY KEY AUTOINCREMENT,
            amount REAL,
            amount_gnf REAL,
            custom_id TEXT,
            description TEXT,
            customer_email TEXT,
            ipn_url TEXT,
            success_url TEXT,
            fail_url TEXT,
            order_date TEXT,
            status TEXT DEFAULT 'pending',
            transaction_id TEXT,
            pay_token TEXT,

            notif_token TEXT,
            payment_url TEXT,
            order_id_om TEXT,
            ipn_response TEXT
        );" :
        "CREATE TABLE IF NOT EXISTS orders (
            order_id INT AUTO_INCREMENT PRIMARY KEY,
            amount DECIMAL(10,2),
            amount_gnf DECIMAL(15,0),
            custom_id VARCHAR(255),
            description TEXT,
            customer_email VARCHAR(255),
            ipn_url TEXT,
            success_url TEXT,
            fail_url TEXT,
            order_date DATETIME,
            status VARCHAR(50) DEFAULT 'pending',
            transaction_id VARCHAR(255),
            pay_token VARCHAR(255),
            notif_token VARCHAR(255),
            payment_url TEXT,
            order_id_om VARCHAR(255),
            ipn_response TEXT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        
        try {
            $this->conn->exec($sql);
            $this->ensurePaymentUrlColumn();
        } catch (PDOException $e) {
            error_log("Erreur création table 'orders': " . $e->getMessage());
            throw new Exception("Erreur lors de l'initialisation de la table des commandes.");
        }
    }

    /**
     * Ajoute la colonne payment_url si elle n'existe pas encore (migration légère).
     */
    private function ensurePaymentUrlColumn() {
        try {
            if (DB_TYPE === 'sqlite') {
                $cols = $this->conn->query("PRAGMA table_info(orders)")->fetchAll(PDO::FETCH_COLUMN, 1);
                if (!in_array('payment_url', $cols, true)) {
                    $this->conn->exec("ALTER TABLE orders ADD COLUMN payment_url TEXT");
                }
            } else {
                $res = $this->conn->query("SHOW COLUMNS FROM orders LIKE 'payment_url'");
                if ($res->rowCount() === 0) {
                    $this->conn->exec("ALTER TABLE orders ADD COLUMN payment_url TEXT NULL");
                }
            }
        } catch (PDOException $e) {
            error_log("Erreur vérification/ajout colonne payment_url: " . $e->getMessage());
            // On continue même en cas d'échec pour ne pas bloquer l'application
        }
    }
}
