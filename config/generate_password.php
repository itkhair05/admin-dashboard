
<?php
$password = 'admin123'; // Thay bằng mật khẩu bạn muốn
$hashed_password = password_hash($password, PASSWORD_DEFAULT);
echo "Mật khẩu mã hóa: " . $hashed_password;
?>
