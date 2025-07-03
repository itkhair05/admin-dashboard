<?php
session_start();
require __DIR__ . '/../../config/db_connect.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

// Debug: Ghi log để kiểm tra file được tải
error_log("Loading delete_promotion.php at " . date('Y-m-d H:i:s'));

$promotion_id = $_GET['id'] ?? null;
if (!$promotion_id) {
    header("Location: manage_promotions.php?error=missing_id");
    exit;
}

// Xóa khuyến mãi
try {
    $stmt = $pdo->prepare("DELETE FROM promotions WHERE id = ?");
    $stmt->execute([$promotion_id]);
    if ($stmt->rowCount() === 0) {
        header("Location: manage_promotions.php?error=promotion_not_found");
        exit;
    }
    header("Location: manage_promotions.php?success=Xóa khuyến mãi thành công!");
    exit;
} catch (PDOException $e) {
    $error = "Lỗi cơ sở dữ liệu: " . $e->getMessage();
    error_log("Database error in delete_promotion.php: " . $e->getMessage());
    header("Location: manage_promotions.php?error=database_error");
    exit;
}
?>
