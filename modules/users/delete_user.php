<?php
session_start();
require __DIR__ . '/../../config/db_connect.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_GET['id'] ?? null;
if (!$user_id) {
    header("Location: /ad/modules/users/manage_users.php");
    exit;
}

try {
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    header("Location: /ad/modules/users/manage_users.php?success=1");
    exit;
} catch (PDOException $e) {
    $error = "Lỗi: " . $e->getMessage();
    error_log("Database error when deleting user: " . $e->getMessage());
    header("Location: /ad/modules/users/manage_users.php?error=1");
    exit;
}
?>
