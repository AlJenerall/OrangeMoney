<?php
// OrderModel.php : Modèle pour gérer les commandes (version finale et complète)

class OrderModel {
    private $db;

    public function __construct(Database $db) {
        $this->db = $db->getConnection();
    }

    /**
     * Crée une nouvelle commande dans la base de données.
     * C'EST LA REQUÊTE CORRIGÉE.
     */
    public function createOrder(array $data) {
        $sql = "INSERT INTO orders (amount, amount_gnf, custom_id, description, customer_email, ipn_url, success_url, fail_url, order_date, status, payment_url, pay_token, notif_token, order_id_om) 
                VALUES (:amount, :amount_gnf, :custom_id, :description, :customer_email, :ipn_url, :success_url, :fail_url, :order_date, :status, :payment_url, :pay_token, :notif_token, :order_id_om)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($data);
        return $this->db->lastInsertId();
    }

    public function getOrderById(int $orderId) {
        $stmt = $this->db->prepare("SELECT * FROM orders WHERE order_id = ?");
        $stmt->execute([$orderId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function updateOrder(int $orderId, array $data) {
        $fields = [];
        foreach (array_keys($data) as $field) {
            $fields[] = "$field = :$field";
        }
        $sql = "UPDATE orders SET " . implode(', ', $fields) . " WHERE order_id = :order_id";
        
        $stmt = $this->db->prepare($sql);
        $data['order_id'] = $orderId;
        return $stmt->execute($data);
    }

    public function updateOrderStatus(int $orderId, string $status) {
        $sql = "UPDATE orders SET status = :status WHERE order_id = :order_id";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':status', $status, PDO::PARAM_STR);
        $stmt->bindValue(':order_id', $orderId, PDO::PARAM_INT);
        return $stmt->execute();
    }
    
    public function getPendingOrderByCustomId(string $customId) {
        $stmt = $this->db->prepare("SELECT * FROM orders WHERE custom_id = ? AND status = 'pending_payment' ORDER BY order_id DESC LIMIT 1");
        $stmt->execute([$customId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function getOrderByCustomId(string $customId) {
        $stmt = $this->db->prepare("SELECT * FROM orders WHERE custom_id = ? ORDER BY order_id DESC LIMIT 1");
        $stmt->execute([$customId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
