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
error_log("Loading manage_orders.php at " . date('Y-m-d H:i:s'));

// Lấy trạng thái lọc từ GET (nếu có)
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : 'all';

// Lấy danh sách đơn hàng
$orders = [];
try {
    if ($status_filter === 'all') {
        $stmt = $pdo->query("SELECT o.*, u.username AS customer, r.name AS restaurant 
                            FROM orders o 
                            LEFT JOIN users u ON o.user_id = u.id 
                            LEFT JOIN restaurants r ON o.restaurant_id = r.id");
    } else {
        $stmt = $pdo->prepare("SELECT o.*, u.username AS customer, r.name AS restaurant 
                             FROM orders o 
                             LEFT JOIN users u ON o.user_id = u.id 
                             LEFT JOIN restaurants r ON o.restaurant_id = r.id 
                             WHERE o.status = ?");
        $stmt->execute([$status_filter]);
    }
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!$orders) {
        error_log("No orders found in database for status: $status_filter");
    }
} catch (PDOException $e) {
    $error = "Lỗi khi lấy dữ liệu: " . $e->getMessage();
    error_log("Database error when fetching orders: " . $e->getMessage());
}

// Xử lý xóa đơn hàng qua POST
$success = null;
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_order'])) {
    $order_id = trim($_POST['order_id'] ?? '');
    error_log("Processing delete request for order ID: $order_id");
    
    if (!is_numeric($order_id)) {
        $error = "ID đơn hàng không hợp lệ!";
        error_log("Validation error: Invalid order_id ($order_id)");
    } else {
        try {
            // Kiểm tra sự tồn tại của đơn hàng
            $stmt = $pdo->prepare("SELECT id FROM orders WHERE id = ?");
            $stmt->execute([$order_id]);
            if (!$stmt->fetch()) {
                $error = "Đơn hàng không tồn tại!";
                error_log("Order not found: ID=$order_id");
            } else {
                $stmt = $pdo->prepare("DELETE FROM orders WHERE id = ?");
                $result = $stmt->execute([$order_id]);
                if ($result) {
                    $success = "Xóa đơn hàng thành công!";
                    error_log("Order deleted successfully: ID=$order_id");
                    header("Location: /ad/modules/orders/manage_orders.php?success=delete&status=$status_filter");
                    exit;
                } else {
                    $error = "Không thể xóa đơn hàng!";
                    error_log("Failed to delete order: ID=$order_id");
                }
            }
        } catch (PDOException $e) {
            $error = "Lỗi khi xóa đơn hàng: " . $e->getMessage();
            error_log("Database error when deleting order: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý đơn hàng</title>
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
        .btn-danger {
            background-color: #dc3545;
            color: white;
        }
        .btn-danger:hover {
            background-color: #c82333;
        }
        .btn-warning {
            background-color: #ffc107;
            color: #212529;
        }
        .btn-warning:hover {
            background-color: #e0a800;
        }
        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
        }
        .table-container table {
            width: 100%;
            border-collapse: collapse;
        }
        .table-container th, .table-container td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }
        .table-container th {
            font-weight: 600;
        }
        /* Style cho form lọc */
        .filter-form {
            margin-bottom: 1rem;
            display: flex;
            gap: 1rem;
            align-items: center;
        }
        .filter-form select {
            padding: 0.5rem;
            border-radius: 0.375rem;
            border: 1px solid #e5e7eb;
            font-size: 0.875rem;
        }
        .filter-form button {
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            background-color: #4a90e2;
            color: white;
            border: none;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }
        .filter-form button:hover {
            background-color: #357abd;
        }
        .header-card {
            background: linear-gradient(135deg, #4a90e2, #50e3c2);
            color: white;
            padding: 1.5rem;
            border-radius: 0.75rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            animation: fadeIn 0.5s ease-out;
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
                    <li><a href="/ad/modules/chat/group_chat.php" class="flex items-center p-4 <?php echo ($current_page == 'group_chat.php') ? 'active' : ''; ?>"><i class="fas fa-comments mr-2"></i> <span>Chat nhóm</span></a></li>
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
                <div class="header-card animate__animated animate__fadeInUp">
                <h1 class="text-3xl font-bold mb-6 flex items-center animate__animated animate__fadeInUp">
                    <i class="fas fa-shopping-cart mr-2"></i> Quản lý đơn hàng
                </h1>
                <p class="text-sm mt-2">Theo dõi và xử lý các đơn hàng từ khách hàng</p>
                </div>
                <!-- Thông báo -->
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
                <?php if (isset($_GET['success']) && $_GET['success'] == 'delete'): ?>
                <div class="alert alert-success animate__animated animate__fadeIn">
                    <i class="fas fa-check-circle mr-2"></i> Xóa đơn hàng thành công!
                </div>
                <?php endif; ?>
                <?php if (isset($_GET['success']) && $_GET['success'] == 'update'): ?>
                <div class="alert alert-success animate__animated animate__fadeIn">
                    <i class="fas fa-check-circle mr-2"></i> Cập nhật đơn hàng thành công!
                </div>
                <?php endif; ?>
                <?php if (isset($_GET['error']) && $_GET['error'] == 1): ?>
                <div class="alert alert-error animate__animated animate__fadeIn">
                    <i class="fas fa-exclamation-triangle mr-2"></i> Đơn hàng không tồn tại hoặc lỗi khi xử lý!
                </div>
                <?php endif; ?>

                <!-- Form lọc trạng thái -->
                <form method="GET" action="manage_orders.php" class="filter-form animate__animated animate__fadeInUp">
                    <label for="status" class="text-sm font-medium text-gray-700">Lọc theo trạng thái:</label>
                    <select name="status" id="status" onchange="this.form.submit()" class="border rounded p-2">
                        <option value="all" <?php echo ($status_filter === 'all') ? 'selected' : ''; ?>>Tất cả</option>
                        <option value="pending" <?php echo ($status_filter === 'pending') ? 'selected' : ''; ?>>Chờ xử lý</option>
                        <option value="processing" <?php echo ($status_filter === 'processing') ? 'selected' : ''; ?>>Đang xử lý</option>
                        <option value="delivered" <?php echo ($status_filter === 'delivered') ? 'selected' : ''; ?>>Đã giao</option>
                        <option value="cancelled" <?php echo ($status_filter === 'cancelled') ? 'selected' : ''; ?>>Đã hủy</option>
                    </select>
                    <?php if ($status_filter !== 'all'): ?>
                        <a href="manage_orders.php" class="btn btn-sm btn-warning">Xóa lọc</a>
                    <?php endif; ?>
                </form>

                <!-- Danh sách đơn hàng -->
                <div class="card table-container bg-white shadow-lg rounded-lg p-6 animate__animated animate__fadeInUp">
                    <h2 class="text-xl font-semibold mb-4 flex items-center text-gray-700">
                        <i class="fas fa-list mr-2"></i> Danh sách đơn hàng
                    </h2>
                    <?php if (empty($orders)): ?>
                        <p class="text-gray-500">Không có đơn hàng nào để hiển thị.</p>
                    <?php else: ?>
                        <table>
                            <thead>
                                <tr>
                                    <th class="w-10">ID</th>
                                    <th>Khách hàng</th>
                                    <th>Nhà hàng</th>
                                    <th>Tổng tiền</th>
                                    <th>Trạng thái</th>
                                    <th>Ngày đặt</th>
                                    <th class="w-32">Hành động</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orders as $order): ?>
                                <?php
                                    // Kiểm tra sự tồn tại của đơn hàng
                                    $stmt = $pdo->prepare("SELECT id FROM orders WHERE id = ?");
                                    $stmt->execute([$order['id']]);
                                    $exists = $stmt->fetch();
                                    if ($exists):
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($order['id']); ?></td>
                                    <td><?php echo htmlspecialchars($order['customer'] ?? 'Không xác định'); ?></td>
                                    <td><?php echo htmlspecialchars($order['restaurant'] ?? 'Không xác định'); ?></td>
                                    <td><?php echo number_format($order['total_amount'], 2, ',', '.') . ' VNĐ'; ?></td>
                                    <td><?php echo htmlspecialchars($order['status']); ?></td>
                                    <td><?php echo htmlspecialchars($order['created_at']); ?></td>
                                    <td>
                                        <div class="flex flex-wrap gap-2">
                                            <a href="edit_order.php?id=<?php echo $order['id']; ?>" class="btn btn-warning btn-sm">
                                                <i class="fas fa-edit mr-1"></i> Sửa
                                            </a>
                                            <button onclick="confirmDelete(<?php echo $order['id']; ?>)" class="btn btn-danger btn-sm">
                                                <i class="fas fa-trash mr-1"></i> Xóa
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endif; ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>

                <!-- Form ẩn để xóa đơn hàng -->
                <form id="deleteOrderForm" method="POST" action="delete_order.php">
                    <input type="hidden" name="delete_order" value="1">
                    <input type="hidden" name="order_id" id="order_id">
                </form>
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

        function confirmDelete(id) {
            console.log('Confirm delete called for order ID: ' + id);
            if (confirm('Bạn có chắc chắn muốn xóa đơn hàng này?')) {
                console.log('Delete confirmed for order ID: ' + id);
                $('#order_id').val(id);
                $('#deleteOrderForm').submit();
            } else {
                console.log('Delete cancelled for order ID: ' + id);
            }
        }
    </script>
</body>
</html>
<?php ob_end_flush(); ?>
