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

// Lấy thông tin khiếu nại
try {
    $stmt = $pdo->prepare("SELECT id, order_id, user_id, restaurant_id, description, status, resolution, created_at ,image_path FROM complaints WHERE id = ?");
    $stmt->execute([$complaint_id]);
    $complaint = $stmt->fetch();
    if (!$complaint) {
        header("Location: /ad/modules/complaints/manage_complaints.php?error=complaint_not_found");
        exit;
    }
    if ($complaint['status'] !== 'rejected') {
        header("Location: /ad/modules/complaints/manage_complaints.php?error=invalid_status");
        exit;
    }
} catch (PDOException $e) {
    error_log("Database error in view_complaint.php: " . $e->getMessage());
    header("Location: /ad/modules/complaints/manage_complaints.php?error=database_error");
    exit;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Xem chi tiết khiếu nại</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="/ad/assets/css/admin.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" rel="stylesheet">
<style>
    .complaint-image {
            max-width: 500px;
            max-height: 500px;
            object-fit: cover;
            border-radius: 0.375rem;
            margin-top: 10px;
        }
        .no-image {
            color: #718096;
            font-style: italic;
        }
</style>
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
                    <li><a href="/ad/views/dashboard.php" class="flex items-center"><i class="fas fa-home mr-2"></i> <span>Tổng quan</span></a></li>
                    <li><a href="/ad/modules/users/manage_users.php" class="flex items-center"><i class="fas fa-users mr-2"></i> <span>Quản lý người dùng</span></a></li>
                    <li><a href="/ad/modules/restaurants/manage_restaurants.php" class="flex items-center"><i class="fas fa-store mr-2"></i> <span>Quản lý nhà hàng</span></a></li>
                    <li><a href="/ad/modules/orders/manage_orders.php" class="flex items-center"><i class="fas fa-shopping-cart mr-2"></i> <span>Quản lý đơn hàng</span></a></li>
                    <li><a href="/ad/modules/reviews/manage_reviews.php" class="flex items-center"><i class="fas fa-star mr-2"></i> <span>Quản lý đánh giá</span></a></li>
                    <li><a href="/ad/modules/complaints/manage_complaints.php" class="flex items-center active"><i class="fas fa-exclamation-circle mr-2"></i> <span>Quản lý khiếu nại</span></a></li>
                    <li><a href="/ad/modules/promotions/manage_promotions.php" class="flex items-center"><i class="fas fa-tags mr-2"></i> <span>Quản lý khuyến mãi</span></a></li>
                    
                    <li><a href="/ad/modules/auth/logout.php" class="flex items-center"><i class="fas fa-sign-out-alt mr-2"></i> <span>Đăng xuất</span></a></li>
                </ul>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content flex-1 overflow-auto">
            <h1 class="text-3xl font-bold mb-6 flex items-center">
                <i class="fas fa-eye mr-2"></i> Xem chi tiết khiếu nại
            </h1>
            <div class="card">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="form-group">
                        <label>ID Khiếu nại</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($complaint['id']); ?>" disabled>
                    </div>
                    <div class="form-group">
                        <label>ID Đơn hàng</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($complaint['order_id'] ?? 'N/A'); ?>" disabled>
                    </div>
                    <div class="form-group">
                        <label>ID Người dùng</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($complaint['user_id'] ?? 'N/A'); ?>" disabled>
                    </div>
                    <div class="form-group">
                        <label>ID Nhà hàng</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($complaint['restaurant_id'] ?? 'N/A'); ?>" disabled>
                    </div>
                    <div class="form-group">
                        <label>Mô tả</label>
                        <textarea class="form-control" rows="4" disabled><?php echo htmlspecialchars($complaint['description']); ?></textarea>
                    </div>
                    <div class="form-group">
                        <label>Giải pháp</label>
                        <textarea class="form-control" rows="4" disabled><?php echo htmlspecialchars($complaint['resolution'] ?? 'Chưa có'); ?></textarea>
                    </div>
                    <div class="form-group">
                        <label>Trạng thái</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($complaint['status']); ?>" disabled>
                    </div>
                    <div class="form-group">
                        <label>Ngày tạo</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($complaint['created_at']); ?>" disabled>
                    </div>
                    <div class="form-group md:col-span-2">
                        <label>Ảnh khiếu nại</label>
                        <?php if ($complaint['image_path'] && file_exists(__DIR__ . '/../../uploads/complaints/' . $complaint['image_path'])): ?>
                            <img src="/ad/uploads/complaints/<?php echo htmlspecialchars($complaint['image_path']); ?>" alt="Ảnh khiếu nại" class="complaint-image">
                        <?php else: ?>
                            <span class="no-image">Không có ảnh</span>
                        <?php endif; ?>
                    </div>
                    <div class="md:col-span-2">
                        <a href="manage_complaints.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left mr-2"></i> Quay lại
                        </a>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
