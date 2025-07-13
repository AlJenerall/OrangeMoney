<?php

require_once __DIR__ . '/database.php';

class OrderModel {
    private $conn;

    public function __construct() {
        $db = new Database();
        $this->conn = $db->connect();
    }

    public function createOrder($orderData) {
        $query = "INSERT INTO orders (
            amount, currency_code, description, customer_name, customer_email, custom_id, ipn_url, success_url, fail_url, order_date
        ) VALUES (
            :amount, :currency_code, :description, :customer_name, :customer_email, :custom_id, :ipn_url, :success_url, :fail_url, :order_date
        )";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':amount', $orderData['amount']);
        $stmt->bindParam(':currency_code', $orderData['currency_code']);
        $stmt->bindParam(':description', $orderData['description']);
        $stmt->bindParam(':customer_name', $orderData['customer_name']);
        $stmt->bindParam(':customer_email', $orderData['customer_email']);
        $stmt->bindParam(':custom_id', $orderData['custom_id']);
        $stmt->bindParam(':ipn_url', $orderData['ipn_url']);
        $stmt->bindParam(':success_url', $orderData['success_url']);
        $stmt->bindParam(':fail_url', $orderData['fail_url']);
        $stmt->bindParam(':order_date', $orderData['order_date']);
        if ($stmt->execute()) {
            return $this->conn->lastInsertId();
        }
        return false;
    }

    public function getOrderById($orderId) {
        $stmt = $this->conn->prepare("SELECT * FROM orders WHERE order_id = :order_id");
        $stmt->bindParam(':order_id', $orderId);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function updateOrder($orderId, $orderData) {
        $fields = [];
        $params = [];
        foreach ($orderData as $key => $value) {
            $fields[] = "$key = :$key";
            $params[":$key"] = $value;
        }
        $fields_str = implode(", ", $fields);
        $query = "UPDATE orders SET $fields_str WHERE order_id = :order_id";
        $params[':order_id'] = $orderId;
        $stmt = $this->conn->prepare($query);
        return $stmt->execute($params);
    }

   public function updateStatusByNotifToken($notif_token, $status, $transaction_id = null) { // Renommer le paramètre aussi pour la clarté
        $stmt = $this->conn->prepare("UPDATE orders SET status = ?, transaction_id = ? WHERE notif_token = ?");
        return $stmt->execute([$status, $transaction_id, $notif_token]);
    }
}
