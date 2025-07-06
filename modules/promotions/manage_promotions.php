<?php
session_start();
require __DIR__ . '/../../config/db_connect.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: /ad/modules/auth/login.php");
    exit;
}

// Định nghĩa $current_page
$current_page = basename($_SERVER['PHP_SELF']);

// Debug: Ghi log để kiểm tra file được tải
error_log("Loading manage_promotions.php at " . date('Y-m-d H:i:s'));

// Xử lý thông báo từ URL
if (isset($_GET['success'])) {
    $success = $_GET['success'];
}
if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'missing_id':
            $error = "Không tìm thấy ID khuyến mãi.";
            break;
        case 'promotion_not_found':
            $error = "Không tìm thấy khuyến mãi.";
            break;
        case 'database_error':
            $error = "Lỗi cơ sở dữ liệu. Vui lòng thử lại.";
            break;
        default:
            $error = "Đã xảy ra lỗi không xác định.";
    }
}

// Xử lý thêm khuyến mãi
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_promotion'])) {
    $restaurant_id = $_POST['restaurant_id'];
    $title = $_POST['title'];
    $discount_percentage = $_POST['discount_percentage'];
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];

    try {
        $stmt = $pdo->prepare("INSERT INTO promotions (restaurant_id, title, discount_percentage, start_date, end_date) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$restaurant_id, $title, $discount_percentage, $start_date, $end_date]);
        $success = "Thêm khuyến mãi thành công!";
        header("Location: manage_promotions.php?success=" . urlencode($success));
        exit;
    } catch (PDOException $e) {
        $error = "Lỗi: " . $e->getMessage();
        error_log("Database error: " . $e->getMessage());
    }
}

// Lấy danh sách nhà hàng để hiển thị trong form
$restaurants = [];
try {
    $stmt = $pdo->query("SELECT id, name FROM restaurants ORDER BY name");
    $restaurants = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Lỗi khi lấy danh sách nhà hàng: " . $e->getMessage();
    error_log("Database error when fetching restaurants: " . $e->getMessage());
}

// Lấy danh sách khuyến mãi
$promotions = [];
try {
    $stmt = $pdo->query("SELECT id, restaurant_id, title, discount_percentage, start_date, end_date FROM promotions ORDER BY start_date DESC");
    $promotions = $stmt->fetchAll();
    if (!$promotions) {
        error_log("No promotions found in database.");
    }
} catch (PDOException $e) {
    $error = "Lỗi cơ sở dữ liệu: " . $e->getMessage();
    error_log("Database error when fetching promotions: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý khuyến mãi</title>
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
                    <i class="fas fa-tags mr-2"></i> Quản lý khuyến mãi
                </h1>
                <p class="text-sm mt-2">Tạo và quản lý các chương trình khuyến mãi</p>
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

                <!-- Form thêm khuyến mãi -->
                <div class="card mb-6 animate__animated animate__fadeInUp">
                    <h2 class="text-xl font-semibold mb-4 flex items-center">
                        <i class="fas fa-plus-circle mr-2"></i> Thêm khuyến mãi mới
                    </h2>
                    <form method="POST" action="" class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="form-group">
                            <label>Nhà hàng</label>
                            <select name="restaurant_id" class="form-control" required>
                                <option value="">Chọn nhà hàng</option>
                                <?php foreach ($restaurants as $restaurant): ?>
                                    <option value="<?php echo htmlspecialchars($restaurant['id']); ?>">
                                        <?php echo htmlspecialchars($restaurant['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Tiêu đề khuyến mãi</label>
                            <input type="text" name="title" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>Giảm giá (%)</label>
                            <input type="number" name="discount_percentage" class="form-control" min="0" max="100" step="0.01" required>
                        </div>
                        <div class="form-group">
                            <label>Ngày bắt đầu</label>
                            <input type="date" name="start_date" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>Ngày kết thúc</label>
                            <input type="date" name="end_date" class="form-control" required>
                        </div>
                        <div class="md:col-span-2">
                            <div class="flex flex-wrap gap-4">
                                <button type="submit" name="add_promotion" class="btn btn-primary animate__animated animate__fadeIn">
                                    <i class="fas fa-save mr-2"></i> Thêm khuyến mãi
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="card table-container animate__animated animate__fadeInUp">
                    <h2 class="text-xl font-semibold mb-4 flex items-center">
                        <i class="fas fa-list mr-2"></i> Danh sách khuyến mãi
                    </h2>
                    <?php if (empty($promotions)): ?>
                        <p class="text-gray-500">Không có khuyến mãi nào để hiển thị.</p>
                    <?php else: ?>
                        <table>
                            <thead>
                                <tr>
                                    <th class="w-10">ID</th>
                                    <th>Nhà hàng</th>
                                    <th>Tiêu đề</th>
                                    <th>Giảm giá</th>
                                    <th>Ngày bắt đầu</th>
                                    <th>Ngày kết thúc</th>
                                    <th class="w-64">Hành động</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($promotions as $promotion): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($promotion['id']); ?></td>
                                    <td><?php echo htmlspecialchars($promotion['restaurant_id']); ?></td>
                                    <td><?php echo htmlspecialchars($promotion['title']); ?></td>
                                    <td><?php echo htmlspecialchars($promotion['discount_percentage']); ?>%</td>
                                    <td><?php echo htmlspecialchars($promotion['start_date']); ?></td>
                                    <td><?php echo htmlspecialchars($promotion['end_date']); ?></td>
                                    <td>
                                        <div class="flex flex-wrap gap-2">
                                            <a href="edit_promotion.php?id=<?php echo $promotion['id']; ?>" class="btn btn-warning btn-sm animate__animated animate__fadeIn">
                                                <i class="fas fa-edit mr-1"></i> Sửa
                                            </a>
                                            <a href="delete_promotion.php?id=<?php echo $promotion['id']; ?>" class="btn btn-danger btn-sm animate__animated animate__fadeIn" onclick="return confirm('Bạn có chắc chắn muốn xóa khuyến mãi này?');">
                                                <i class="fas fa-trash mr-1"></i> Xóa
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
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
                if (!$(this).attr('href').includes('#')) {
                    e.preventDefault();
                    $('#main-content').fadeOut(300, function() {
                        window.location.href = $(e.target).attr('href');
                    });
                }
            });
        });
    </script>
</body>
</html>
