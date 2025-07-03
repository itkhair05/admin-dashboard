<?php
ob_start(); // Bật output buffering
session_start();
require __DIR__ . '/../../config/db_connect.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: /ad/modules/auth/login.php");
    exit;
}

// Chỉ xử lý yêu cầu POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_order'])) {
    $order_id = trim($_POST['order_id'] ?? '');
    error_log("Processing delete request for order ID: $order_id");
    
    if (!is_numeric($order_id)) {
        error_log("Validation error: Invalid order_id ($order_id)");
        header("Location: /ad/modules/orders/manage_orders.php?error=1");
        exit;
    }

    try {
        // Kiểm tra sự tồn tại của đơn hàng
        $stmt = $pdo->prepare("SELECT id FROM orders WHERE id = ?");
        $stmt->execute([$order_id]);
        if (!$stmt->fetch()) {
            error_log("Order not found: ID=$order_id");
            header("Location: /ad/modules/orders/manage_orders.php?error=1");
            exit;
        }

        $stmt = $pdo->prepare("DELETE FROM orders WHERE id = ?");
        $result = $stmt->execute([$order_id]);
        if ($result) {
            error_log("Order deleted successfully: ID=$order_id");
            header("Location: /ad/modules/orders/manage_orders.php?success=delete");
            exit;
        } else {
            error_log("Failed to delete order: ID=$order_id");
            header("Location: /ad/modules/orders/manage_orders.php?error=1");
            exit;
        }
    } catch (PDOException $e) {
        error_log("Database error when deleting order: " . $e->getMessage());
        header("Location: /ad/modules/orders/manage_orders.php?error=1");
        exit;
    }
} else {
    error_log("Invalid request to delete_order.php: Method=" . $_SERVER['REQUEST_METHOD']);
    header("Location: /ad/modules/orders/manage_orders.php");
    exit;
}
?>
<?php ob_end_flush(); ?>