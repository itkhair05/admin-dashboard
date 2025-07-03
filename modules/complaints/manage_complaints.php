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
error_log("Loading manage_complaints.php at " . date('Y-m-d H:i:s'));

// Xử lý duyệt/từ chối khiếu nại
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $complaint_id = $_POST['complaint_id'] ?? null;
    $action = $_POST['action'] ?? null;

    if ($complaint_id && in_array($action, ['approve', 'reject'])) {
        try {
            $status = $action === 'approve' ? 'resolved' : 'rejected';
            $stmt = $pdo->prepare("UPDATE complaints SET status = ? WHERE id = ?");
            $stmt->execute([$status, $complaint_id]);
            header("Location: manage_complaints.php?success=1");
            exit;
        } catch (PDOException $e) {
            $error = "Lỗi: " . $e->getMessage();
            error_log("Database error when updating complaint status: " . $e->getMessage());
        }
    }
}

// Xử lý thông báo lỗi từ URL
if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'missing_id':
            $error = "Không tìm thấy ID khiếu nại.";
            break;
        case 'complaint_not_found':
            $error = "Không tìm thấy khiếu nại.";
            break;
        case 'invalid_status':
            $error = "Trạng thái khiếu nại không hợp lệ.";
            break;
        case 'database_error':
            $error = "Lỗi cơ sở dữ liệu. Vui lòng thử lại.";
            break;
        default:
            $error = "Đã xảy ra lỗi không xác định.";
    }
}

// Lấy trạng thái lọc từ query string
$filter_status = $_GET['status'] ?? 'all';
$valid_statuses = ['all', 'pending', 'resolved', 'rejected'];
if (!in_array($filter_status, $valid_statuses)) {
    $filter_status = 'all';
}

// Lấy danh sách khiếu nại
$complaints = [];
try {
    $query = "SELECT id, order_id, user_id, restaurant_id, description, status, resolution, created_at FROM complaints";
    if ($filter_status !== 'all') {
        $query .= " WHERE status = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$filter_status]);
    } else {
        $query .= " ORDER BY created_at DESC";
        $stmt = $pdo->query($query);
    }
    $complaints = $stmt->fetchAll();
    if (!$complaints) {
        error_log("No complaints found in database.");
    }
} catch (PDOException $e) {
    $error = "Lỗi cơ sở dữ liệu: " . $e->getMessage();
    error_log("Database error when fetching complaints: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý khiếu nại</title>
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
                    <i class="fas fa-exclamation-circle mr-2"></i> Quản lý khiếu nại
                </h1>
                <p class="text-sm mt-2">Xử lý các khiếu nại và phản hồi từ khách hàng</p>
                </div>
                <?php if (isset($_GET['success']) && $_GET['success'] == 1): ?>
                    <div class="alert alert-success animate__animated animate__fadeIn">
                        <i class="fas fa-check-circle mr-2"></i> Thao tác thành công!
                    </div>
                <?php endif; ?>
                <?php if (isset($error)): ?>
                    <div class="alert alert-error animate__animated animate__fadeIn">
                        <i class="fas fa-exclamation-triangle mr-2"></i> <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                <div class="card mb-6 animate__animated animate__fadeInUp">
                    <h2 class="text-xl font-semibold mb-4 flex items-center">
                        <i class="fas fa-filter mr-2"></i> Lọc khiếu nại
                    </h2>
                    <form method="GET" action="" class="flex items-center gap-4">
                        <div class="form-group">
                            <label for="status">Trạng thái</label>
                            <select name="status" id="status" class="form-control">
                                <option value="all" <?php echo $filter_status === 'all' ? 'selected' : ''; ?>>Tất cả</option>
                                <option value="pending" <?php echo $filter_status === 'pending' ? 'selected' : ''; ?>>Đang chờ</option>
                                <option value="resolved" <?php echo $filter_status === 'resolved' ? 'selected' : ''; ?>>Đã duyệt</option>
                                <option value="rejected" <?php echo $filter_status === 'rejected' ? 'selected' : ''; ?>>Đã từ chối</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary animate__animated animate__fadeIn">
                            <i class="fas fa-filter mr-2"></i> Lọc
                        </button>
                    </form>
                </div>
                <div class="card table-container animate__animated animate__fadeInUp">
                    <h2 class="text-xl font-semibold mb-4 flex items-center">
                        <i class="fas fa-list mr-2"></i> Danh sách khiếu nại
                    </h2>
                    <?php if (empty($complaints)): ?>
                        <p class="text-gray-500">Không có khiếu nại nào để hiển thị.</p>
                    <?php else: ?>
                        <table>
                            <thead>
                                <tr>
                                    <th class="w-10">ID</th>
                                    <th>ID Đơn hàng</th>
                                    <th>ID Người dùng</th>
                                    <th>ID Nhà hàng</th>
                                    <th>Mô tả</th>
                                    <th>Trạng thái</th>
                                    <th>Giải pháp</th>
                                    <th>Ngày tạo</th>
                                    <th class="w-64">Hành động</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($complaints as $complaint): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($complaint['id']); ?></td>
                                    <td><?php echo htmlspecialchars($complaint['order_id'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($complaint['user_id'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($complaint['restaurant_id'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($complaint['description']); ?></td>
                                    <td><?php echo htmlspecialchars($complaint['status']); ?></td>
                                    <td><?php echo htmlspecialchars($complaint['resolution'] ?? 'Chưa có'); ?></td>
                                    <td><?php echo htmlspecialchars($complaint['created_at']); ?></td>
                                    <td>
                                        <div class="flex flex-wrap gap-2">
                                            <?php if ($complaint['status'] === 'pending'): ?>
                                                <form method="POST" action="" class="inline-block">
                                                    <input type="hidden" name="complaint_id" value="<?php echo $complaint['id']; ?>">
                                                    <input type="hidden" name="action" value="approve">
                                                    <button type="submit" class="btn btn-success btn-sm animate__animated animate__fadeIn">
                                                        <i class="fas fa-check mr-1"></i> Duyệt
                                                    </button>
                                                </form>
                                                <form method="POST" action="" class="inline-block">
                                                    <input type="hidden" name="complaint_id" value="<?php echo $complaint['id']; ?>">
                                                    <input type="hidden" name="action" value="reject">
                                                    <button type="submit" class="btn btn-danger btn-sm animate__animated animate__fadeIn">
                                                        <i class="fas fa-times mr-1"></i> Từ chối
                                                    </button>
                                                </form>
                                            <?php elseif ($complaint['status'] === 'resolved'): ?>
                                                <a href="/ad/modules/complaints/edit_complaint.php?id=<?php echo $complaint['id']; ?>" class="btn btn-warning btn-sm animate__animated animate__fadeIn">
                                                    <i class="fas fa-edit mr-1"></i> Sửa
                                                </a>
                                                <a href="/ad/modules/complaints/delete_complaint.php?id=<?php echo $complaint['id']; ?>" class="btn btn-danger btn-sm animate__animated animate__fadeIn" onclick="return confirm('Bạn có chắc chắn muốn xóa?');">
                                                    <i class="fas fa-trash mr-1"></i> Xóa
                                                </a>
                                            <?php elseif ($complaint['status'] === 'rejected'): ?>
                                                <a href="/ad/modules/complaints/view_complaint.php?id=<?php echo $complaint['id']; ?>" class="btn btn-info btn-sm animate__animated animate__fadeIn">
                                                    <i class="fas fa-eye mr-1"></i> Xem
                                                </a>
                                            <?php endif; ?>
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
