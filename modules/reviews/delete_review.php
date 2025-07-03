<?php
session_start();
require __DIR__ . '/../../config/db_connect.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: /ad/modules/auth/login.php");
    exit;
}

$review_id = $_GET['id'] ?? null;
if (!$review_id) {
    header("Location: /ad/modules/reviews/manage_reviews.php");
    exit;
}

try {
    $stmt = $pdo->prepare("DELETE FROM reviews WHERE id = ?");
    $stmt->execute([$review_id]);
    header("Location: /ad/modules/reviews/manage_reviews.php?success=1");
    exit;
} catch (PDOException $e) {
    $error = "Lỗi: " . $e->getMessage();
    error_log("Database error when deleting review: " . $e->getMessage());
    header("Location: /ad/modules/reviews/manage_reviews.php?error=1");
    exit;
}
