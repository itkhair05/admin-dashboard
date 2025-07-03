<?php
session_start();
require __DIR__ . '/../../config/db_connect.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: /ad/modules/auth/login.php");
    exit;
}

$complaint_id = $_GET['id'] ?? null;
if (!$complaint_id) {
    header("Location: /ad/modules/complaints/manage_complaints.php?error=missing_id");
    exit;
}

// Kiểm tra trạng thái
try {
    $stmt = $pdo->prepare("SELECT status FROM complaints WHERE id = ?");
    $stmt->execute([$complaint_id]);
    $complaint = $stmt->fetch();
    if (!$complaint) {
        header("Location: /ad/modules/complaints/manage_complaints.php?error=complaint_not_found");
        exit;
    }
    if ($complaint['status'] !== 'resolved') {
        header("Location: /ad/modules/complaints/manage_complaints.php?error=invalid_status");
        exit;
    }
} catch (PDOException $e) {
    error_log("Database error in delete_complaint.php: " . $e->getMessage());
    header("Location: /ad/modules/complaints/manage_complaints.php?error=database_error");
    exit;
}

try {
    $stmt = $pdo->prepare("DELETE FROM complaints WHERE id = ?");
    $stmt->execute([$complaint_id]);
    header("Location: /ad/modules/complaints/manage_complaints.php?success=1");
    exit;
} catch (PDOException $e) {
    $error = "Lỗi: " . $e->getMessage();
    error_log("Database error when deleting complaint: " . $e->getMessage());
    header("Location: /ad/modules/complaints/manage_complaints.php?error=database_error");
    exit;
}
?>
