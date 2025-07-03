<?php
session_start();
require __DIR__ . '/../config/db_connect.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

// Định nghĩa $current_page
$current_page = basename($_SERVER['PHP_SELF']);

// Debug: Ghi log để kiểm tra file được tải
error_log("Loading dashboard.php at " . date('Y-m-d H:i:s'));

// Lấy dữ liệu thống kê
try {
    // Tổng số nhà hàng
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM restaurants");
    $total_restaurants = $stmt->fetch()['total'];

    // Tổng số người dùng
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users");
    $total_users = $stmt->fetch()['total'];

    // Tổng số đơn hàng
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM orders");
    $total_orders = $stmt->fetch()['total'];

    // Tổng số đánh giá
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM reviews");
    $total_reviews = $stmt->fetch()['total'];

    // Tổng số khiếu nại
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM complaints");
    $total_complaints = $stmt->fetch()['total'];

    // Tổng doanh thu
    $stmt = $pdo->query("SELECT SUM(total_amount) as total FROM orders WHERE status = 'delivered'");
    $total_revenue = $stmt->fetch()['total'] ?? 0;

    // Đơn hàng theo trạng thái
    $stmt = $pdo->query("SELECT status, COUNT(*) as count FROM orders GROUP BY status");
    $orders_by_status = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    // Doanh thu theo tháng (12 tháng gần nhất)
    $stmt = $pdo->query("SELECT DATE_FORMAT(created_at, '%Y-%m') as month, SUM(total_amount) as total 
                        FROM orders 
                        WHERE status = 'delivered' 
                        AND created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                        GROUP BY month 
                        ORDER BY month");
    $revenue_by_month = $stmt->fetchAll();

    // Debug: Ghi log để kiểm tra dữ liệu
    error_log("Revenue by month: " . json_encode($revenue_by_month));
} catch (PDOException $e) {
    $error = "Lỗi cơ sở dữ liệu: " . $e->getMessage();
    error_log("Database error in dashboard.php: " . $e->getMessage());
}

// Lấy dữ liệu phân bố vai trò người dùng
$user_roles = [];
try {
    $stmt = $pdo->query("SELECT role, COUNT(*) as total FROM users GROUP BY role");
    $user_roles = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (PDOException $e) {
    error_log("Database error when fetching user roles: " . $e->getMessage());
}

// Lấy số lượng nhà hàng mới theo tháng (12 tháng gần nhất)
$restaurants_by_month = [];
try {
    $stmt = $pdo->query("SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as total FROM restaurants WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH) GROUP BY month ORDER BY month");
    $restaurants_by_month = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Database error when fetching restaurants by month: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tổng quan</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="/ad/assets/css/admin.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
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
        .chart-container {
            position: relative;
            width: 100%;
            max-width: 100%;
            max-height: 250px;
            opacity: 0;
            transform: translateY(20px);
            transition: opacity 0.5s ease, transform 0.5s ease;
        }
        .chart-container.loaded {
            opacity: 1;
            transform: translateY(0);
        }
        .card.chart-card {
            overflow: hidden;
        }
        canvas {
            width: 100% !important;
            height: 100% !important;
            max-height: 250px;
        }
        .chart-loading {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 250px;
        }
        .stat-card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.15);
        }
        .sidebar nav ul li a.active {
            background-color: #3182ce;
            color: #ffffff;
            transition: background-color 0.3s ease, transform 0.3s ease;
        }
        .sidebar nav ul li a:hover {
            background-color: #4a5568;
            color: #ffffff;
            transform: translateX(5px);
            transition: background-color 0.3s ease, transform 0.3s ease;
        }
        .sidebar h2 {
            margin-bottom: 1rem;
        }
        .sidebar nav ul li {
            margin: 0.25rem 0;
        }
        .header-card {
            background: linear-gradient(135deg, #4a90e2, #50e3c2);
            color: white;
            padding: 1.5rem;
            border-radius: 0.75rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            animation: fadeIn 0.5s ease-out;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .music-toggle {
            font-size: 1.5rem;
            cursor: pointer;
            transition: color 0.3s ease;
        }
        .music-toggle:hover {
            color: #ffffff;
        }
    </style>
</head>
<body class="flex flex-col h-screen bg-gray-100" data-page="<?php echo $current_page; ?>">
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
        <main class="main-content flex-1 overflow-auto p-6 animate__animated animate__fadeIn" id="main-content">
            <div class="loading" id="loading">
                <span class="loading-spinner"></span>
            </div>
            <div class="content" id="content" style="display: none;">
                <div class="header-card animate__animated animate__fadeInUp">
                    <div>
                        <h1 class="text-3xl font-bold mb-2 flex items-center animate__animated animate__fadeInUp">
                            <i class="fas fa-home mr-2"></i> Tổng quan
                        </h1>
                        <p class="text-sm mt-2">Xem nhanh thông tin và số liệu chính của hệ thống</p>
                    </div>
                    <div class="music-toggle" id="musicToggle">
                        <i class="fas fa-volume-up" id="musicIcon"></i>
                    </div>
                </div>
                <?php if (isset($error)): ?>
                <div class="alert alert-error animate__animated animate__fadeIn bg-red-100 text-red-700 p-4 rounded-lg mb-6">
                    <i class="fas fa-exclamation-triangle mr-2"></i> <?php echo htmlspecialchars($error); ?>
                </div>
                <?php endif; ?>
                <!-- Thống kê -->
                <div class="card mb-6 bg-white shadow-lg rounded-lg p-6 animate__animated animate__fadeInUp">
                    <h2 class="text-xl font-semibold mb-4 flex items-center">
                        <i class="fas fa-chart-pie mr-2"></i> Thống kê
                    </h2>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div class="stat-card bg-blue-100 p-4 rounded-lg shadow animate__animated animate__fadeInUp" style="animation-delay: 0.1s;">
                            <h3 class="text-lg font-semibold text-blue-800">Tổng nhà hàng</h3>
                            <p class="text-2xl font-bold"><?php echo htmlspecialchars($total_restaurants); ?></p>
                        </div>
                        <div class="stat-card bg-green-100 p-4 rounded-lg shadow animate__animated animate__fadeInUp" style="animation-delay: 0.2s;">
                            <h3 class="text-lg font-semibold text-green-800">Tổng đơn hàng</h3>
                            <p class="text-2xl font-bold"><?php echo htmlspecialchars($total_orders); ?></p>
                        </div>
                        <div class="stat-card bg-yellow-100 p-4 rounded-lg shadow animate__animated animate__fadeInUp" style="animation-delay: 0.3s;">
                            <h3 class="text-lg font-semibold text-yellow-800">Tổng người dùng</h3>
                            <p class="text-2xl font-bold"><?php echo htmlspecialchars($total_users); ?></p>
                        </div>
                        <div class="stat-card bg-purple-100 p-4 rounded-lg shadow animate__animated animate__fadeInUp" style="animation-delay: 0.4s;">
                            <h3 class="text-lg font-semibold text-purple-800">Tổng đánh giá</h3>
                            <p class="text-2xl font-bold"><?php echo htmlspecialchars($total_reviews); ?></p>
                        </div>
                        <div class="stat-card bg-red-100 p-4 rounded-lg shadow animate__animated animate__fadeInUp" style="animation-delay: 0.5s;">
                            <h3 class="text-lg font-semibold text-red-800">Tổng khiếu nại</h3>
                            <p class="text-2xl font-bold"><?php echo htmlspecialchars($total_complaints); ?></p>
                        </div>
                        <div class="stat-card bg-teal-100 p-4 rounded-lg shadow animate__animated animate__fadeInUp" style="animation-delay: 0.6s;">
                            <h3 class="text-lg font-semibold text-teal-800">Tổng doanh thu</h3>
                            <p class="text-2xl font-bold"><?php echo number_format($total_revenue, 2); ?> VNĐ</p>
                        </div>
                    </div>
                </div>
                <!-- Biểu đồ -->
                <div class="card chart-card mb-6 bg-white shadow-lg rounded-lg p-6 animate__animated animate__fadeInUp" style="animation-delay: 0.7s;">
                    <h2 class="text-xl font-semibold mb-4 flex items-center">
                        <i class="fas fa-chart-bar mr-2"></i> Doanh thu theo tháng
                    </h2>
                    <div class="chart-container" id="dashboardRevenueChartContainer">
                        <div class="chart-loading">
                            <span class="loading-spinner"></span>
                        </div>
                        <canvas id="dashboardRevenueChart" style="display: none;"></canvas>
                    </div>
                </div>
                <div class="card chart-card bg-white shadow-lg rounded-lg p-6 animate__animated animate__fadeInUp" style="animation-delay: 0.8s;">
                    <h2 class="text-xl font-semibold mb-4 flex items-center">
                        <i class="fas fa-chart-pie mr-2"></i> Phân bố trạng thái đơn hàng
                    </h2>
                    <div class="chart-container" id="dashboardStatusChartContainer">
                        <div class="chart-loading">
                            <span class="loading-spinner"></span>
                        </div>
                        <canvas id="dashboardStatusChart" style="display: none;"></canvas>
                    </div>
                </div>
                <!-- Biểu đồ phân bố vai trò người dùng -->
                <div class="card chart-card mb-6 bg-white shadow-lg rounded-lg p-6 animate__animated animate__fadeInUp" style="animation-delay: 0.9s;">
                    <h2 class="text-xl font-semibold mb-4 flex items-center">
                        <i class="fas fa-user-friends mr-2"></i> Phân bố vai trò người dùng
                    </h2>
                    <div class="chart-container" id="userRoleChartContainer">
                        <div class="chart-loading">
                            <span class="loading-spinner"></span>
                        </div>
                        <canvas id="userRoleChart" style="display: none;"></canvas>
                    </div>
                </div>
                <!-- Biểu đồ tổng khiếu nại và đánh giá -->
                <div class="card chart-card mb-6 bg-white shadow-lg rounded-lg p-6 animate__animated animate__fadeInUp" style="animation-delay: 1.0s;">
                    <h2 class="text-xl font-semibold mb-4 flex items-center">
                        <i class="fas fa-balance-scale mr-2"></i> Tổng khiếu nại và đánh giá
                    </h2>
                    <div class="chart-container" id="complaintReviewChartContainer">
                        <div class="chart-loading">
                            <span class="loading-spinner"></span>
                        </div>
                        <canvas id="complaintReviewChart" style="display: none;"></canvas>
                    </div>
                </div>
            </div>
        </main>
    </div>
    <audio id="backgroundMusic" loop>
        <source src="/ad/uploads/truoc.mp3" type="audio/mpeg">
        Your browser does not support the audio element.
    </audio>
    
    <script>
        $(document).ready(function() {
            // Hiệu ứng fade-in cho nội dung khi load
            setTimeout(() => {
                $('#loading').fadeOut(300, function() {
                    $('#content').fadeIn(500).addClass('loaded animate__fadeIn');
                    $('.animate__fadeInUp').addClass('animate__fadeInUp');
                });
            }, 500);

            // Hiển thị biểu đồ sau khi tải
            setTimeout(() => {
                if ($('#dashboardRevenueChartContainer').length) {
                    $('#dashboardRevenueChartContainer .chart-loading').hide();
                    $('#dashboardRevenueChart').show();
                    $('#dashboardRevenueChartContainer').addClass('loaded');
                }
                if ($('#dashboardStatusChartContainer').length) {
                    $('#dashboardStatusChartContainer .chart-loading').hide();
                    $('#dashboardStatusChart').show();
                    $('#dashboardStatusChartContainer').addClass('loaded');
                }
                if ($('#userRoleChartContainer').length) {
                    $('#userRoleChartContainer .chart-loading').hide();
                    $('#userRoleChart').show();
                    $('#userRoleChartContainer').addClass('loaded');
                }
                if ($('#complaintReviewChartContainer').length) {
                    $('#complaintReviewChartContainer .chart-loading').hide();
                    $('#complaintReviewChart').show();
                    $('#complaintReviewChartContainer').addClass('loaded');
                }
            }, 1000);

            // Xử lý chuyển trang mượt mà
            $('a').on('click', function(e) {
                if (!$(this).attr('href').includes('#')) {
                    e.preventDefault();
                    $('#main-content').fadeOut(300, function() {
                        window.location.href = $(e.target).attr('href');
                    });
                }
            });

            // Biểu đồ doanh thu theo tháng (Tổng quan)
            if (document.getElementById('dashboardRevenueChart')) {
                const dashboardRevenueCtx = document.getElementById('dashboardRevenueChart').getContext('2d');
                const dashboardRevenueData = {
                    labels: [<?php foreach ($revenue_by_month as $row) { echo "'" . $row['month'] . "',"; } ?>],
                    datasets: [{
                        label: 'Doanh thu (VNĐ)',
                        data: [<?php foreach ($revenue_by_month as $row) { echo $row['total'] . ","; } ?>],
                        backgroundColor: 'rgba(49, 130, 206, 0.5)',
                        borderColor: 'rgba(49, 130, 206, 1)',
                        borderWidth: 1,
                        hoverBackgroundColor: 'rgba(49, 130, 206, 0.8)',
                    }]
                };
                new Chart(dashboardRevenueCtx, {
                    type: 'bar',
                    data: dashboardRevenueData,
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        animation: { duration: 1500, easing: 'easeInOutQuart' },
                        scales: {
                            y: {
                                beginAtZero: true,
                                title: { display: true, text: 'Doanh thu (VNĐ)', font: { size: 14 } },
                                ticks: { callback: value => value.toLocaleString('vi-VN') + ' VNĐ' }
                            },
                            x: { title: { display: true, text: 'Tháng', font: { size: 14 } } }
                        },
                        plugins: {
                            tooltip: { callbacks: { label: context => context.dataset.label + ': ' + context.parsed.y.toLocaleString('vi-VN') + ' VNĐ' } }
                        }
                    }
                });
            }

            // Biểu đồ phân bố trạng thái đơn hàng (Tổng quan)
            if (document.getElementById('dashboardStatusChart')) {
                const dashboardStatusCtx = document.getElementById('dashboardStatusChart').getContext('2d');
                const dashboardStatusData = {
                    labels: ['Đang chờ', 'Đang xử lý', 'Đã giao', 'Đã hủy'],
                    datasets: [{
                        label: 'Số lượng đơn hàng',
                        data: [
                            <?php echo ($orders_by_status['pending'] ?? 0); ?>,
                            <?php echo ($orders_by_status['processing'] ?? 0); ?>,
                            <?php echo ($orders_by_status['delivered'] ?? 0); ?>,
                            <?php echo ($orders_by_status['cancelled'] ?? 0); ?>
                        ],
                        backgroundColor: [
                            'rgba(229, 62, 62, 0.5)',
                            'rgba(49, 130, 206, 0.5)',
                            'rgba(72, 187, 120, 0.5)',
                            'rgba(236, 201, 75, 0.5)'
                        ],
                        borderColor: [
                            'rgba(229, 62, 62, 1)',
                            'rgba(49, 130, 206, 1)',
                            'rgba(72, 187, 120, 1)',
                            'rgba(236, 201, 75, 1)'
                        ],
                        borderWidth: 1,
                        hoverBackgroundColor: [
                            'rgba(229, 62, 62, 0.8)',
                            'rgba(49, 130, 206, 0.8)',
                            'rgba(72, 187, 120, 0.8)',
                            'rgba(236, 201, 75, 0.8)'
                        ]
                    }]
                };
                new Chart(dashboardStatusCtx, {
                    type: 'pie',
                    data: dashboardStatusData,
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        animation: { duration: 1500, easing: 'easeInOutQuart' },
                        plugins: {
                            legend: { position: 'top', labels: { font: { size: 14 } } },
                            title: { display: true, text: 'Phân bố trạng thái đơn hàng', font: { size: 16 } },
                            tooltip: { callbacks: { label: context => context.label + ': ' + context.parsed + ' đơn' } }
                        }
                    }
                });
            }

            // Biểu đồ phân bố vai trò người dùng
            if (document.getElementById('userRoleChart')) {
                const userRoleCtx = document.getElementById('userRoleChart').getContext('2d');
                const userRoleData = {
                    labels: [
                        'Admin',
                        'Nhà hàng',
                        'Người dùng'
                    ],
                    datasets: [{
                        label: 'Số lượng',
                        data: [
                            <?php echo (int)($user_roles['admin'] ?? 0); ?>,
                            <?php echo (int)($user_roles['restaurant'] ?? 0); ?>,
                            <?php echo (int)($user_roles['user'] ?? 0); ?>
                        ],
                        backgroundColor: [
                            'rgba(49, 130, 206, 0.7)',
                            'rgba(72, 187, 120, 0.7)',
                            'rgba(236, 201, 75, 0.7)'
                        ],
                        borderColor: [
                            'rgba(49, 130, 206, 1)',
                            'rgba(72, 187, 120, 1)',
                            'rgba(236, 201, 75, 1)'
                        ],
                        borderWidth: 1
                    }]
                };
                new Chart(userRoleCtx, {
                    type: 'pie',
                    data: userRoleData,
                    options: {
                        responsive: true,
                        plugins: {
                            legend: { position: 'top', labels: { font: { size: 14 } } },
                            title: { display: true, text: 'Phân bố vai trò người dùng', font: { size: 16 } },
                            tooltip: { callbacks: { label: context => context.label + ': ' + context.parsed + ' người' } }
                        }
                    }
                });
            }

            // Biểu đồ tổng khiếu nại và đánh giá
            if (document.getElementById('complaintReviewChart')) {
                const complaintReviewCtx = document.getElementById('complaintReviewChart').getContext('2d');
                const complaintReviewData = {
                    labels: ['Khiếu nại', 'Đánh giá'],
                    datasets: [{
                        label: 'Số lượng',
                        data: [<?php echo (int)$total_complaints; ?>, <?php echo (int)$total_reviews; ?>],
                        backgroundColor: [
                            'rgba(229, 62, 62, 0.7)',
                            'rgba(49, 130, 206, 0.7)'
                        ],
                        borderColor: [
                            'rgba(229, 62, 62, 1)',
                            'rgba(49, 130, 206, 1)'
                        ],
                        borderWidth: 1
                    }]
                };
                new Chart(complaintReviewCtx, {
                    type: 'bar',
                    data: complaintReviewData,
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        animation: { duration: 1500, easing: 'easeInOutQuart' },
                        scales: {
                            y: {
                                beginAtZero: true,
                                title: { display: true, text: 'Số lượng', font: { size: 14 } },
                                ticks: { stepSize: 1 }
                            },
                            x: { title: { display: true, text: 'Loại', font: { size: 14 } } }
                        },
                        plugins: {
                            tooltip: { callbacks: { label: context => context.dataset.label + ': ' + context.parsed.y } }
                        }
                    }
                });
            }

            const music = document.getElementById('backgroundMusic');
            let isPlaying = localStorage.getItem('musicPlaying') === 'true';
            function updateMusicIcon() {
                document.getElementById('musicIcon').className = 'fas ' + (isPlaying ? 'fa-volume-mute' : 'fa-volume-up');
            }
            function playMusicIfNeeded() {
                if (isPlaying) {
                    music.play().catch(()=>{});
                } else {
                    music.pause();
                }
                updateMusicIcon();
            }
            playMusicIfNeeded();
            document.getElementById('musicToggle').onclick = function() {
                isPlaying = !isPlaying;
                localStorage.setItem('musicPlaying', isPlaying ? 'true' : 'false');
                playMusicIfNeeded();
            };
            updateMusicIcon();
            window.addEventListener('storage', function(e) {
                if (e.key === 'musicPlaying') {
                    isPlaying = e.newValue === 'true';
                    playMusicIfNeeded();
                }
            });
        });
    </script>
</body>
</html>
