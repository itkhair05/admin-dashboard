<?php
session_start();

// Xóa tất cả session
session_unset();
session_destroy();

// Chuyển hướng về trang đăng nhập
header("Location: ../auth/login.php");
exit;
?>
