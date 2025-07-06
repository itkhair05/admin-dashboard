<?php
ob_start(); // Bật output buffering
session_start();
require __DIR__ . '/../../config/db_connect.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: /ad/modules/auth/login.php");
    exit;
}

// Định nghĩa $current_page
$current_page = basename($_SERVER['PHP_SELF']);

// Chống cache
header("Cache-Control: no-cache, must-revalidate");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

// Debug: Ghi log khi tải trang
error_log("Loading edit_order.php at " . date('Y-m-d H:i:s'));

$order_id = $_GET['id'] ?? null;
if (!$order_id || !is_numeric($order_id)) {
    error_log("Invalid order ID: " . ($order_id ?? 'null'));
    header("Location: /ad/modules/orders/manage_orders.php?error=1");
    exit;
}

// Lấy thông tin đơn hàng
$stmt = $pdo->prepare("SELECT id, user_id, restaurant_id, total_amount, status, created_at FROM orders WHERE id = ?");
$stmt->execute([$order_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$order) {
    error_log("Order not found: ID=$order_id");
    header("Location: /ad/modules/orders/manage_orders.php?error=1");
    exit;
}

// Lấy danh sách người dùng và nhà hàng
try {
    $stmt = $pdo->query("SELECT id, username FROM users");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt = $pdo->query("SELECT id, name FROM restaurants");
    $restaurants = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Lỗi khi lấy dữ liệu: " . $e->getMessage();
    error_log("Database error when fetching users/restaurants: " . $e->getMessage());
}

// Xử lý cập nhật
$success = null;
$error = null;
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user_id = trim($_POST['user_id'] ?? '');
    $restaurant_id = trim($_POST['restaurant_id'] ?? '');
    $total_amount = trim($_POST['total_amount'] ?? '');
    $status = trim($_POST['status'] ?? '');

    error_log("Received POST data for order $order_id: user_id=$user_id, restaurant_id=$restaurant_id, total_amount=$total_amount, status=$status");

    // Kiểm tra dữ liệu
    if (empty($user_id) || empty($restaurant_id) || empty($total_amount) || empty($status)) {
        $error = "Vui lòng điền đầy đủ thông tin!";
        error_log("Validation error: Missing required fields");
    } elseif (!is_numeric($user_id) || !is_numeric($restaurant_id) || !is_numeric($total_amount)) {
        $error = "ID người dùng, ID nhà hàng hoặc tổng tiền không hợp lệ!";
        error_log("Validation error: Invalid numeric fields");
    } elseif (!in_array($status, ['pending', 'processing', 'delivered', 'cancelled'])) {
        $error = "Trạng thái không hợp lệ!";
        error_log("Validation error: Invalid status ($status)");
    } else {
        try {
            // Kiểm tra user_id và restaurant_id tồn tại
            $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            if (!$stmt->fetch()) {
                $error = "Người dùng không tồn tại!";
                error_log("Validation error: Invalid user_id ($user_id)");
            } else {
                $stmt = $pdo->prepare("SELECT id FROM restaurants WHERE id = ?");
                $stmt->execute([$restaurant_id]);
                if (!$stmt->fetch()) {
                    $error = "Nhà hàng không tồn tại!";
                    error_log("Validation error: Invalid restaurant_id ($restaurant_id)");
                } else {
                    $stmt = $pdo->prepare("UPDATE orders SET user_id = ?, restaurant_id = ?, total_amount = ?, status = ? WHERE id = ?");
                    $result = $stmt->execute([$user_id, $restaurant_id, $total_amount, $status, $order_id]);
                    $row_count = $stmt->rowCount();
                    error_log("Update affected rows for order $order_id: $row_count, new status: $status");
                    if ($row_count > 0) {
                        $success = "Cập nhật đơn hàng thành công!";
                        header("Location: /ad/modules/orders/manage_orders.php?success=update");
                        exit;
                    } else {
                        $error = "Không có thay đổi nào được cập nhật!";
                        error_log("No rows updated for order $order_id");
                    }
                }
            }
        } catch (PDOException $e) {
            $error = "Lỗi khi cập nhật: " . $e->getMessage();
            error_log("Database error when updating order: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chỉnh sửa đơn hàng</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="/ad/assets/css/admin.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        .loading {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100%;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            background: rgba(255, 255, 255, 0.8);
            z-index: 1000;
        }
        .loading-spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #4a90e2;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .content {
            opacity: 0;
            transition: opacity 0.5s ease;
        }
        .content.loaded {
            opacity: 1;
        }
        .alert {
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
        }
        .alert-success {
            background-color: #d4edda;
            color: #155724;
        }
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
        }
        .btn {
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        .btn-primary {
            background-color: #4a90e2;
            color: white;
        }
        .btn-primary:hover {
            background-color: #357abd;
        }
        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }
        .btn-secondary:hover {
            background-color: #5a6268;
        }
        .form-control {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
        }
    </style>
</head>
<body class="flex flex-col h-screen bg-gray-50" data-page="<?php echo $current_page; ?>">
    <div class="flex flex-1 overflow-hidden">
        <!-- Sidebar -->
        <aside class="sidebar w-64 bg-gray-800 text-white transition-all duration-300 ease-in-out">
            <h2 class="text-2xl font-bold mb-4 flex items-center p-4">
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
        <main class="main-content flex-1 overflow-auto p-6 bg-gray-50 animate__animated animate__fadeIn" id="main-content">
            <div class="loading" id="loading">
                <span class="loading-spinner"></span>
            </div>
            <div class="content" id="content" style="display: none;">
                <h1 class="text-3xl font-bold mb-6 flex items-center animate__animated animate__fadeInUp">
                    <i class="fas fa-edit mr-2"></i> Chỉnh sửa đơn hàng
                </h1>
                <?php if (isset($success)): ?>
                <div class="alert alert-success animate__animated animate__fadeIn">
                    <i class="fas fa-check-circle mr-2"></i> <?php echo htmlspecialchars($success); ?>
                </div>
                <?php endif; ?>
                <?php if (isset($error)): ?>
                <div class="alert alert-error animate__animated animate__fadeIn">
                    <i class="fas fa-exclamation-triangle mr-2"></i> <?php echo htmlspecialchars($error); ?>
                </div>
                <?php endif; ?>
                <div class="card mb-6 bg-white shadow-lg rounded-lg p-6 animate__animated animate__fadeInUp">
                    <form method="POST" action="" class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="form-group">
                            <label for="user_id">Người dùng</label>
                            <select name="user_id" id="user_id" class="form-control" required>
                                <option value="">Chọn người dùng</option>
                                <?php foreach ($users as $user): ?>
                                <option value="<?php echo htmlspecialchars($user['id']); ?>" <?php echo ($user['id'] == $order['user_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($user['username']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="restaurant_id">Nhà hàng</label>
                            <select name="restaurant_id" id="restaurant_id" class="form-control" required>
                                <option value="">Chọn nhà hàng</option>
                                <?php foreach ($restaurants as $restaurant): ?>
                                <option value="<?php echo htmlspecialchars($restaurant['id']); ?>" <?php echo ($restaurant['id'] == $order['restaurant_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($restaurant['name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="total_amount">Tổng tiền</label>
                            <input type="number" step="0.01" name="total_amount" id="total_amount" class="form-control" value="<?php echo htmlspecialchars($order['total_amount']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="status">Trạng thái</label>
                            <select name="status" id="status" class="form-control" required>
                                <option value="pending" <?php echo $order['status'] === 'pending' ? 'selected' : ''; ?>>Đang chờ</option>
                                <option value="processing" <?php echo $order['status'] === 'processing' ? 'selected' : ''; ?>>Đang xử lý</option>
                                <option value="delivered" <?php echo $order['status'] === 'delivered' ? 'selected' : ''; ?>>Đã giao</option>
                                <option value="cancelled" <?php echo $order['status'] === 'cancelled' ? 'selected' : ''; ?>>Đã hủy</option>
                            </select>
                        </div>
                        <div class="md:col-span-2">
                            <div class="flex flex-wrap gap-4">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save mr-2"></i> Cập nhật
                                </button>
                                <a href="manage_orders.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left mr-2"></i> Quay lại
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
    <script>
        $(document).ready(function() {
            // Hiệu ứng fade-in cho nội dung khi load
            setTimeout(() => {
                $('#loading').fadeOut(300, function() {
                    $('#content').fadeIn(500).addClass('loaded animate__fadeIn');
                    $('.animate__fadeInUp').addClass('animate__fadeInUp');
                });
            }, 500);

            // Xử lý chuyển trang mượt mà
            $('a').on('click', function(e) {
                if (!$(this).attr('href').includes('#') && !$(this).hasClass('no-transition')) {
                    e.preventDefault();
                    $('#main-content').fadeOut(300, function() {
                        window.location.href = $(e.target).closest('a').attr('href');
                    });
                }
            });
        });
    </script>
</body>
</html>
<?php ob_end_flush(); ?>