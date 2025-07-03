<?php
session_start();
require __DIR__ . '/../../config/db_connect.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: /ad/modules/auth/login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $email = $_POST['email'];
    $role = $_POST['role'];

    try {
        $stmt = $pdo->prepare("INSERT INTO users (username, password, role, email) VALUES (?, ?, ?, ?)");
        $stmt->execute([$username, $password, $role, $email]);
        $success = "Thêm người dùng thành công!";
    } catch (PDOException $e) {
        $error = "Lỗi: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thêm người dùng</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <div class="flex">
        <aside class="w-64 bg-gray-800 text-white h-screen p-4">
            <h2 class="text-2xl font-bold mb-6">Bảng điều khiển</h2>
            <nav>
                <ul>
                    <li><a href="dashboard.php" class="block p-2 hover:bg-gray-700">Tổng quan</a></li>
                    <li><a href="manage_users.php" class="block p-2 hover:bg-gray-700">Quản lý người dùng</a></li>
                    <li><a href="manage_restaurants.php" class="block p-2 hover:bg-gray-700">Quản lý nhà hàng</a></li>
                    <li><a href="manage_orders.php" class="block p-2 hover:bg-gray-700">Quản lý đơn hàng</a></li>
                    <li><a href="manage_reviews.php" class="block p-2 hover:bg-gray-700">Quản lý đánh giá</a></li>
                    <li><a href="manage_promotions.php" class="block p-2 hover:bg-gray-700">Quản lý khuyến mãi</a></li>
                    <li><a href="logout.php" class="block p-2 hover:bg-gray-700">Đăng xuất</a></li>
                </ul>
            </nav>
        </aside>
        <main class="flex-1 p-8">
            <h1 class="text-3xl font-bold mb-6">Thêm người dùng</h1>
            <?php if (isset($success)) echo "<p class='text-green-500'>$success</p>"; ?>
            <?php if (isset($error)) echo "<p class='text-red-500'>$error</p>"; ?>
            <form method="POST" action="" class="max-w-lg">
                <div class="mb-4">
                    <label class="block text-gray-700">Tên người dùng</label>
                    <input type="text" name="username" class="w-full p-2 border rounded" required>
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700">Mật khẩu</label>
                    <input type="password" name="password" class="w-full p-2 border rounded" required>
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700">Email</label>
                    <input type="email" name="email" class="w-full p-2 border rounded" required>
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700">Vai trò</label>
                    <select name="role" class="w-full p-2 border rounded" required>
                        <option value="admin">Quản trị viên</option>
                        <option value="user">Người dùng</option>
                        <option value="restaurant">Nhà hàng</option>
                    </select>
                </div>
                <button type="submit" class="bg-blue-500 text-white p-2 rounded">Thêm người dùng</button>
            </form>
        </main>
    </div>
</body>
</html>
