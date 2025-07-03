<?php
session_start();
require __DIR__ . '/../../config/db_connect.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

// Debug: Ghi log để kiểm tra file được tải
error_log("Loading edit_promotion.php at " . date('Y-m-d H:i:s'));

$promotion_id = $_GET['id'] ?? null;
if (!$promotion_id) {
    header("Location: manage_promotions.php?error=missing_id");
    exit;
}

// Lấy thông tin khuyến mãi
try {
    $stmt = $pdo->prepare("SELECT id, restaurant_id, title, discount_percentage, start_date, end_date FROM promotions WHERE id = ?");
    $stmt->execute([$promotion_id]);
    $promotion = $stmt->fetch();
    if (!$promotion) {
        header("Location: manage_promotions.php?error=promotion_not_found");
        exit;
    }
} catch (PDOException $e) {
    $error = "Lỗi cơ sở dữ liệu: " . $e->getMessage();
    error_log("Database error in edit_promotion.php: " . $e->getMessage());
}

// Lấy danh sách nhà hàng
try {
    $stmt = $pdo->query("SELECT id, name FROM restaurants ORDER BY name");
    $restaurants = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Lỗi khi lấy danh sách nhà hàng: " . $e->getMessage();
    error_log("Database error when fetching restaurants: " . $e->getMessage());
}

// Xử lý cập nhật khuyến mãi
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_promotion'])) {
    $restaurant_id = $_POST['restaurant_id'];
    $title = $_POST['title'];
    $discount_percentage = $_POST['discount_percentage'];
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];

    try {
        $stmt = $pdo->prepare("UPDATE promotions SET restaurant_id = ?, title = ?, discount_percentage = ?, start_date = ?, end_date = ? WHERE id = ?");
        $stmt->execute([$restaurant_id, $title, $discount_percentage, $start_date, $end_date, $promotion_id]);
        header("Location: manage_promotions.php?success=Chỉnh sửa khuyến mãi thành công!");
        exit;
    } catch (PDOException $e) {
        $error = "Lỗi: " . $e->getMessage();
        error_log("Database error when updating promotion: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chỉnh sửa khuyến mãi</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="/ad/assets/css/admin.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" rel="stylesheet">
</head>
<body class="flex flex-col h-screen">
    <div class="flex flex-1 overflow-hidden">
        <!-- Sidebar -->
        <aside class="sidebar transition-all duration-300 ease-in-out">
            <h2 class="text-2xl font-bold mb-6 flex items-center">
                <i class="fas fa-utensils mr-2"></i> <span>Admin Dashboard</span>
            </h2>
            <nav>
                <ul>
                    <li><a href="dashboard.php" class="flex items-center"><i class="fas fa-home mr-2"></i> <span>Tổng quan</span></a></li>
                    <li><a href="manage_users.php" class="flex items-center"><i class="fas fa-users mr-2"></i> <span>Quản lý người dùng</span></a></li>
                    <li><a href="manage_restaurants.php" class="flex items-center"><i class="fas fa-store mr-2"></i> <span>Quản lý nhà hàng</span></a></li>
                    <li><a href="manage_orders.php" class="flex items-center"><i class="fas fa-shopping-cart mr-2"></i> <span>Quản lý đơn hàng</span></a></li>
                    <li><a href="manage_reviews.php" class="flex items-center"><i class="fas fa-star mr-2"></i> <span>Quản lý đánh giá</span></a></li>
                    <li><a href="manage_complaints.php" class="flex items-center"><i class="fas fa-exclamation-circle mr-2"></i> <span>Quản lý khiếu nại</span></a></li>
                    <li><a href="manage_promotions.php" class="flex items-center active"><i class="fas fa-tags mr-2"></i> <span>Quản lý khuyến mãi</span></a></li>
                    <li><a href="group_chat.php" class="flex items-center"><i class="fas fa-comments mr-2"></i> <span>Chat nhóm</span></a></li>
                    <li><a href="logout.php" class="flex items-center"><i class="fas fa-sign-out-alt mr-2"></i> <span>Đăng xuất</span></a></li>
                </ul>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content flex-1 overflow-auto">
            <h1 class="text-3xl font-bold mb-6 flex items-center">
                <i class="fas fa-tags mr-2"></i> Chỉnh sửa khuyến mãi
            </h1>
            <?php if (isset($error)): ?>
            <div class="alert alert-error animate__animated animate__fadeIn">
                <i class="fas fa-exclamation-triangle mr-2"></i> <?php echo htmlspecialchars($error); ?>
            </div>
            <?php endif; ?>
            <div class="card">
                <h2 class="text-xl font-semibold mb-4 flex items-center">
                    <i class="fas fa-edit mr-2"></i> Chỉnh sửa khuyến mãi
                </h2>
                <form method="POST" action="" class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="form-group">
                        <label>Nhà hàng</label>
                        <select name="restaurant_id" class="form-control" required>
                            <option value="">Chọn nhà hàng</option>
                            <?php foreach ($restaurants as $restaurant): ?>
                                <option value="<?php echo htmlspecialchars($restaurant['id']); ?>" <?php echo $restaurant['id'] == $promotion['restaurant_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($restaurant['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Tiêu đề khuyến mãi</label>
                        <input type="text" name="title" class="form-control" value="<?php echo htmlspecialchars($promotion['title']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Giảm giá (%)</label>
                        <input type="number" name="discount_percentage" class="form-control" min="0" max="100" step="0.01" value="<?php echo htmlspecialchars($promotion['discount_percentage']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Ngày bắt đầu</label>
                        <input type="date" name="start_date" class="form-control" value="<?php echo htmlspecialchars($promotion['start_date']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Ngày kết thúc</label>
                        <input type="date" name="end_date" class="form-control" value="<?php echo htmlspecialchars($promotion['end_date']); ?>" required>
                    </div>
                    <div class="md:col-span-2">
                        <div class="flex flex-wrap gap-4">
                            <button type="submit" name="update_promotion" class="btn btn-primary">
                                <i class="fas fa-save mr-2"></i> Lưu thay đổi
                            </button>
                            <a href="manage_promotions.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left mr-2"></i> Quay lại
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </main>
    </div>
</body>
</html>
