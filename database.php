<?php
// Database.php : Classe de gestion de la connexion à la base de données (version finale)

class Database {
    private $connection = null;

    /**
     * Le constructeur établit la connexion à la base de données
     * en se basant sur la configuration définie dans config.php.
     */
    public function __construct() {
        // Charge la configuration si elle n'est pas déjà chargée
        if (!defined('DB_TYPE')) {
            require_once __DIR__ . '/config.php';
        }

        $dbType = DB_TYPE;

        try {
            if ($dbType === 'sqlite') {
                // Connexion à SQLite
                $this->connection = new PDO('sqlite:' . DB_SQLITE_PATH);
                $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $this->createTablesIfNeeded(); // Crée les tables si elles n'existent pas
            } elseif ($dbType === 'mysql') {
                // Connexion à MySQL
                $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
                $this->connection = new PDO($dsn, DB_USER, DB_PASS, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);
            } else {
                throw new Exception("Type de base de données non supporté: " . $dbType);
            }
        } catch (PDOException $e) {
            // Log l'erreur de connexion et arrête le script
            error_log("Erreur de connexion à la base de données: " . $e->getMessage());
            throw new Exception("Impossible de se connecter à la base de données.");
        }
    }

    /**
     * Renvoie l'objet de connexion PDO actif.
     * C'EST LA FONCTION MANQUANTE.
     * @return PDO
     */
    public function getConnection() {
        return $this->connection;
    }

    /**
     * Crée la table 'orders' pour SQLite si elle n'existe pas.
     */
    private function createTablesIfNeeded() {
        $sql = "
        CREATE TABLE IF NOT EXISTS orders (
            order_id INTEGER PRIMARY KEY AUTOINCREMENT,
            amount REAL NOT NULL,
            amount_gnf INTEGER,
            custom_id TEXT,
            description TEXT,
            customer_email TEXT,
            ipn_url TEXT,
            success_url TEXT,
            fail_url TEXT,
            order_date TEXT,
            status TEXT,
            payment_url TEXT,
            pay_token TEXT,
            notif_token TEXT,
            order_id_om TEXT
        );";
        $this->connection->exec($sql);
    }
}
