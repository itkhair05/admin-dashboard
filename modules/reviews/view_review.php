<?php
session_start();
require __DIR__ . '/../../config/db_connect.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: /ad/modules/auth/login.php");
    exit;
}

$review_id = $_GET['id'] ?? null;
if (!$review_id) {
    header("Location: manage_reviews.php?error=missing_id");
    exit;
}

// Lấy thông tin đánh giá
try {
    $stmt = $pdo->prepare("SELECT id, user_id, restaurant_id, rating, comment, created_at FROM reviews WHERE id = ?");
    $stmt->execute([$review_id]);
    $review = $stmt->fetch();
    if (!$review) {
        header("Location: manage_reviews.php?error=review_not_found");
        exit;
    }
} catch (PDOException $e) {
    $error = "Lỗi cơ sở dữ liệu: " . $e->getMessage();
    error_log("Database error in view_review.php: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Xem chi tiết đánh giá</title>
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
                    <li><a href="/ad/views/dashboard.php" class="flex items-center p-4 <?php echo ($current_page == 'dashboard.php' && !isset($_GET['page'])) ? 'active' : ''; ?>"><i class="fas fa-home mr-2"></i> <span>Tổng quan</span></a></li>
                    <li><a href="/ad/modules/users/manage_users.php" class="flex items-center p-4 <?php echo ($current_page == 'manage_users.php') ? 'active' : ''; ?>"><i class="fas fa-users mr-2"></i> <span>Quản lý người dùng</span></a></li>
                    <li><a href="/ad/modules/restaurants/manage_restaurants.php" class="flex items-center p-4 <?php echo ($current_page == 'manage_restaurants.php') ? 'active' : ''; ?>"><i class="fas fa-store mr-2"></i> <span>Quản lý nhà hàng</span></a></li>
                    <li><a href="/ad/modules/orders/manage_orders.php" class="flex items-center p-4 <?php echo ($current_page == 'manage_orders.php') ? 'active' : ''; ?>"><i class="fas fa-shopping-cart mr-2"></i> <span>Quản lý đơn hàng</span></a></li>
                    <li><a href="/ad/modules/reviews/manage_reviews.php" class="flex items-center p-4 <?php echo ($current_page == 'manage_reviews.php') ? 'active' : ''; ?>"><i class="fas fa-star mr-2"></i> <span>Quản lý đánh giá</span></a></li>
                    <li><a href="/ad/modules/complaints/manage_complaints.php" class="flex items-center p-4 <?php echo ($current_page == 'manage_complaints.php') ? 'active' : ''; ?>"><i class="fas fa-exclamation-triangle mr-2"></i> <span>Quản lý khiếu nại</span></a></li>
                    <li><a href="/ad/modules/promotions/manage_promotions.php" class="flex items-center p-4 <?php echo ($current_page == 'manage_promotions.php') ? 'active' : ''; ?>"><i class="fas fa-tags mr-2"></i> <span>Quản lý khuyến mãi</span></a></li>
                  
                    <li><a href="/ad/views/report.php" class="flex items-center p-4 <?php echo ($current_page == 'report.php') ? 'active' : ''; ?>"><i class="fas fa-chart-bar mr-2"></i> <span>Báo cáo</span></a></li>
                    <li><a href="/ad/modules/auth/logout.php" class="flex items-center p-4 <?php echo ($current_page == 'logout.php') ? 'active' : ''; ?>"><i class="fas fa-sign-out-alt mr-2"></i> <span>Đăng xuất</span></a></li>
                </ul>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content flex-1 overflow-auto">
            <h1 class="text-3xl font-bold mb-6 flex items-center">
                <i class="fas fa-star mr-2"></i> Xem chi tiết đánh giá
            </h1>
            <?php if (isset($error)): ?>
                <div class="alert alert-error animate__animated animate__fadeIn">
                    <i class="fas fa-exclamation-triangle mr-2"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            <div class="card">
                <h2 class="text-xl font-semibold mb-4 flex items-center">
                    <i class="fas fa-info-circle mr-2"></i> Thông tin đánh giá
                </h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="form-group">
                        <label>ID</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($review['id']); ?>" disabled>
                    </div>
                    <div class="form-group">
                        <label>Người dùng</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($review['user_id']); ?>" disabled>
                    </div>
                    <div class="form-group">
                        <label>Nhà hàng</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($review['restaurant_id']); ?>" disabled>
                    </div>
                    <div class="form-group">
                        <label>Đánh giá</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($review['rating']); ?>/5" disabled>
                    </div>
                    <div class="form-group">
                        <label>Bình luận</label>
                        <textarea class="form-control" rows="4" disabled><?php echo htmlspecialchars($review['comment'] ?: 'Không có'); ?></textarea>
                    </div>
                    <div class="form-group">
                        <label>Ngày đánh giá</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($review['created_at']); ?>" disabled>
                    </div>
                    <div class="md:col-span-2">
                        <a href="manage_reviews.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left mr-2"></i> Quay lại
                        </a>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
