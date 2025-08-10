<?php
// OrderModel.php

class OrderModel {
    private $conn;

    public function __construct(Database $db) {
        $this->conn = $db->connect();
    }

    public function createOrder(array $data): int {
        $sql = "INSERT INTO orders (amount, amount_gnf, custom_id, description, customer_email, ipn_url, success_url, fail_url, order_date, status, payment_url)
                VALUES (:amount, :amount_gnf, :custom_id, :description, :customer_email, :ipn_url, :success_url, :fail_url, :order_date, :status, :payment_url)";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($data);
        return $this->conn->lastInsertId();
    }

    public function updateOrder(int $orderId, array $data): bool {
        $fields = [];
        foreach (array_keys($data) as $key) {
            $fields[] = "$key = :$key";
        }
        $sql = "UPDATE orders SET " . implode(', ', $fields) . " WHERE order_id = :order_id";
        $data['order_id'] = $orderId;
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute($data);
    }
    
    public function getOrderById(int $orderId): ?array {
        $stmt = $this->conn->prepare("SELECT * FROM orders WHERE order_id = :order_id");
        $stmt->execute([':order_id' => $orderId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function getOrderByNotifToken(string $notifToken): ?array {
        $stmt = $this->conn->prepare('SELECT * FROM orders WHERE notif_token = :notif_token LIMIT 1');
        $stmt->execute([':notif_token' => $notifToken]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Retrieve the most recent order matching the custom_id that is still pending.
     * Used to avoid creating duplicate payment sessions when a user retries.
     */
    public function getPendingOrderByCustomId(string $customId): ?array {
        $stmt = $this->conn->prepare(
            "SELECT * FROM orders WHERE custom_id = :custom_id AND status IN ('pending','pending_payment') ORDER BY order_date DESC LIMIT 1"
        );
        $stmt->execute([':custom_id' => $customId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }
}
