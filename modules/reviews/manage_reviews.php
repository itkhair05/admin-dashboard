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
error_log("Loading manage_reviews.php at " . date('Y-m-d H:i:s'));

// Lấy danh sách đánh giá với các cột hiện có
$reviews = [];
try {
    $stmt = $pdo->query("SELECT id, user_id, restaurant_id, rating, comment, created_at FROM reviews ORDER BY created_at DESC");
    $reviews = $stmt->fetchAll();
    if (!$reviews) {
        error_log("No reviews found in database.");
    }
} catch (PDOException $e) {
    $error = "Lỗi cơ sở dữ liệu: " . $e->getMessage();
    error_log("Database error when fetching reviews: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý đánh giá</title>
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
                    <i class="fas fa-star mr-2"></i> Quản lý đánh giá
                </h1>
                <p class="text-sm mt-2">Xem và quản lý đánh giá từ khách hàng về món ăn về nhà hàng</p>
                </div>
                <?php if (isset($error)): ?>
                    <div class="alert alert-error animate__animated animate__fadeIn">
                        <i class="fas fa-exclamation-triangle mr-2"></i> <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                <div class="card table-container animate__animated animate__fadeInUp">
                    <h2 class="text-xl font-semibold mb-4 flex items-center">
                        <i class="fas fa-list mr-2"></i> Danh sách đánh giá
                    </h2>
                    <?php if (empty($reviews)): ?>
                        <p class="text-gray-500">Không có đánh giá nào để hiển thị.</p>
                    <?php else: ?>
                        <table>
                            <thead>
                                <tr>
                                    <th class="w-10">ID</th>
                                    <th>Người dùng</th>
                                    <th>Nhà hàng</th>
                                    <th>Đánh giá</th>
                                    <th>Bình luận</th>
                                    <th>Ngày đánh giá</th>
                                    <th class="w-64">Hành động</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reviews as $review): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($review['id']); ?></td>
                                    <td><?php echo htmlspecialchars($review['user_id']); ?></td>
                                    <td><?php echo htmlspecialchars($review['restaurant_id']); ?></td>
                                    <td><?php echo htmlspecialchars($review['rating']); ?>/5</td>
                                    <td><?php echo htmlspecialchars($review['comment'] ?: 'Không có'); ?></td>
                                    <td><?php echo htmlspecialchars($review['created_at']); ?></td>
                                    <td>
                                        <div class="flex flex-wrap gap-2">
                                            <a href="/ad/modules/reviews/view_review.php?id=<?php echo $review['id']; ?>" class="btn btn-info btn-sm animate__animated animate__fadeIn">
                                                <i class="fas fa-eye mr-1"></i> Xem chi tiết
                                            </a>
                                            <a href="/ad/modules/reviews/delete_review.php?id=<?php echo $review['id']; ?>" class="btn btn-danger btn-sm animate__animated animate__fadeIn" onclick="return confirm('Bạn có chắc chắn muốn xóa?');">
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
