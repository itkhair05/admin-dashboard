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
error_log("Loading report.php at " . date('Y-m-d H:i:s'));

// Xử lý xuất file Excel
if (isset($_GET['export']) && $_GET['export'] == 'excel') {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="report_' . date('Ymd_His') . '.xls"');
    header('Cache-Control: max-age=0');

    $output = "<table border='1'>
        <tr>
            <th>Thang</th>
            <th>Doanh thu (VND)</th>
            <th>So luong don hang</th>
        </tr>";

    try {
        $stmt = $pdo->query("SELECT DATE_FORMAT(created_at, '%Y-%m') as month, SUM(total_amount) as revenue, COUNT(*) as order_count 
                            FROM orders 
                            WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
                            GROUP BY month 
                            ORDER BY month");
        $report_data = $stmt->fetchAll();
        foreach ($report_data as $row) {
            $output .= "<tr>
                <td>" . htmlspecialchars($row['month']) . "</td>
                <td>" . number_format($row['revenue'], 2) . "</td>
                <td>" . htmlspecialchars($row['order_count']) . "</td>
            </tr>";
        }
    } catch (PDOException $e) {
        $output .= "<tr><td colspan='3'>Lỗi: " . htmlspecialchars($e->getMessage()) . "</td></tr>";
    }

    $output .= "</table>";
    echo $output;
    exit;
}

// Lấy dữ liệu báo cáo
$report_data = [];
try {
    $stmt = $pdo->query("SELECT DATE_FORMAT(created_at, '%Y-%m') as month, SUM(total_amount) as revenue, COUNT(*) as order_count 
                        FROM orders 
                        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
                        GROUP BY month 
                        ORDER BY month");
    $report_data = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Lỗi cơ sở dữ liệu: " . $e->getMessage();
    error_log("Database error in report.php: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Báo cáo</title>
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
            max-height: 300px;
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
            border-radius: 0.75rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .card.chart-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px rgba(0, 0, 0, 0.15);
        }
        canvas {
            width: 100% !important;
            height: 100% !important;
            max-height: 300px;
        }
        .chart-loading {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 300px;
            color: #4a5568;
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
        }
        .export-buttons a {
            margin-right: 1rem;
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
                    <h1 class="text-3xl font-bold flex items-center">
                        <i class="fas fa-chart-bar mr-2"></i> Báo cáo Thống kê
                    </h1>
                    <p class="text-sm mt-2">Tổng hợp dữ liệu doanh thu và đơn hàng trong 12 tháng qua</p>
                </div>
                <?php if (isset($error)): ?>
                <div class="alert alert-error animate__animated animate__fadeIn bg-red-100 text-red-700 p-4 rounded-lg mb-6">
                    <i class="fas fa-exclamation-triangle mr-2"></i> <?php echo htmlspecialchars($error); ?>
                </div>
                <?php endif; ?>
                <div class="card mb-6 bg-white shadow-lg rounded-lg p-6 animate__animated animate__fadeInUp">
                    <h2 class="text-xl font-semibold mb-4 flex items-center text-gray-700">
                        <i class="fas fa-download mr-2"></i> Tùy chọn xuất báo cáo
                    </h2>
                    <div class="export-buttons">
                        <a href="?export=excel" class="bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded transition duration-300 animate__animated animate__fadeIn">
                            <i class="fas fa-file-excel mr-2"></i> Xuất Excel
                        </a>
                    </div>
                </div>
                <div class="card chart-card mb-6 bg-white shadow-lg rounded-lg p-6 animate__animated animate__fadeInUp">
                    <h2 class="text-xl font-semibold mb-4 flex items-center text-gray-700">
                        <i class="fas fa-chart-line mr-2"></i> Doanh thu theo tháng
                    </h2>
                    <div class="chart-container" id="reportRevenueChartContainer">
                        <div class="chart-loading">
                            <span class="loading-spinner"></span>
                        </div>
                        <canvas id="reportRevenueChart" style="display: none;"></canvas>
                    </div>
                </div>
                <div class="card chart-card mb-6 bg-white shadow-lg rounded-lg p-6 animate__animated animate__fadeInUp">
                    <h2 class="text-xl font-semibold mb-4 flex items-center text-gray-700">
                        <i class="fas fa-chart-area mr-2"></i> Số lượng đơn hàng theo tháng
                    </h2>
                    <div class="chart-container" id="reportOrderChartContainer">
                        <div class="chart-loading">
                            <span class="loading-spinner"></span>
                        </div>
                        <canvas id="reportOrderChart" style="display: none;"></canvas>
                    </div>
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

            // Hiển thị biểu đồ sau khi tải
            setTimeout(() => {
                if ($('#reportRevenueChartContainer').length) {
                    $('#reportRevenueChartContainer .chart-loading').hide();
                    $('#reportRevenueChart').show();
                    $('#reportRevenueChartContainer').addClass('loaded');
                }
                if ($('#reportOrderChartContainer').length) {
                    $('#reportOrderChartContainer .chart-loading').hide();
                    $('#reportOrderChart').show();
                    $('#reportOrderChartContainer').addClass('loaded');
                }
            }, 1000);

            // Xử lý chuyển trang mượt mà
            $('a').on('click', function(e) {
                if (!$(this).attr('href').includes('#') && !$(this).attr('href').includes('export=excel')) {
                    e.preventDefault();
                    $('#main-content').fadeOut(300, function() {
                        window.location.href = $(e.target).attr('href');
                    });
                }
            });

            // Biểu đồ doanh thu theo tháng (Báo cáo)
            if (document.getElementById('reportRevenueChart')) {
                const reportRevenueCtx = document.getElementById('reportRevenueChart').getContext('2d');
                const reportRevenueData = {
                    labels: [<?php foreach ($report_data as $row) { echo "'" . $row['month'] . "',"; } ?>],
                    datasets: [{
                        label: 'Doanh thu (VNĐ)',
                        data: [<?php foreach ($report_data as $row) { echo $row['revenue'] . ","; } ?>],
                        backgroundColor: 'rgba(49, 130, 206, 0.5)',
                        borderColor: 'rgba(49, 130, 206, 1)',
                        borderWidth: 1,
                        hoverBackgroundColor: 'rgba(49, 130, 206, 0.8)',
                    }]
                };
                new Chart(reportRevenueCtx, {
                    type: 'bar',
                    data: reportRevenueData,
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

            // Biểu đồ số lượng đơn hàng theo tháng (Báo cáo) - Area Chart
            if (document.getElementById('reportOrderChart')) {
                const reportOrderCtx = document.getElementById('reportOrderChart').getContext('2d');
                const reportOrderData = {
                    labels: [<?php foreach ($report_data as $row) { echo "'" . $row['month'] . "',"; } ?>],
                    datasets: [{
                        label: 'Số lượng đơn hàng',
                        data: [<?php foreach ($report_data as $row) { echo $row['order_count'] . ","; } ?>],
                        backgroundColor: 'rgba(72, 187, 120, 0.4)',
                        borderColor: 'rgba(72, 187, 120, 1)',
                        borderWidth: 2,
                        pointRadius: 6,
                        pointHoverRadius: 8,
                        pointBackgroundColor: 'rgba(72, 187, 120, 1)',
                        pointHoverBackgroundColor: 'rgba(72, 187, 120, 0.8)',
                        fill: true,
                        tension: 0.4
                    }]
                };
                new Chart(reportOrderCtx, {
                    type: 'line',
                    data: reportOrderData,
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        animation: { duration: 1500, easing: 'easeInOutQuart' },
                        scales: {
                            y: {
                                beginAtZero: true,
                                title: { display: true, text: 'Số lượng đơn hàng', font: { size: 14 } },
                                ticks: { callback: value => value.toLocaleString('vi-VN') }
                            },
                            x: { title: { display: true, text: 'Tháng', font: { size: 14 } } }
                        },
                        plugins: {
                            tooltip: { callbacks: { label: context => context.dataset.label + ': ' + context.parsed.y.toLocaleString('vi-VN') } }
                        },
                        elements: {
                            line: { borderWidth: 2 }
                        }
                    }
                });
            }
        });
    </script>
</body>
</html>
