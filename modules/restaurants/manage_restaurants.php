<?php
ob_start(); // Bật output buffering
session_start();
require __DIR__ . '/../../config/db_connect.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

// Định nghĩa $current_page
$current_page = basename($_SERVER['PHP_SELF']);

// Debug: Ghi log khi tải trang
error_log("Loading manage_restaurants.php at " . date('Y-m-d H:i:s'));

// Lấy danh sách nhà hàng
$restaurants = [];
try {
    $stmt = $pdo->query("SELECT r.*, u.username AS owner FROM restaurants r LEFT JOIN users u ON r.owner_id = u.id");
    $restaurants = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!$restaurants) {
        error_log("No restaurants found in database.");
    }
} catch (PDOException $e) {
    $error = "Lỗi khi lấy dữ liệu: " . $e->getMessage();
    error_log("Database error when fetching restaurants: " . $e->getMessage());
}

// Lấy danh sách người dùng có vai trò restaurant
try {
    $stmt = $pdo->query("SELECT id, username FROM users WHERE role = 'restaurant'");
    $restaurant_owners = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!$restaurant_owners) {
        error_log("No restaurant owners found in database.");
    }
} catch (PDOException $e) {
    $error = "Lỗi khi lấy danh sách chủ sở hữu: " . $e->getMessage();
    error_log("Database error when fetching restaurant owners: " . $e->getMessage());
}

// Xử lý thêm nhà hàng
$success = null;
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log("Processing POST request for add_restaurant at " . date('Y-m-d H:i:s'));
    $name = trim($_POST['name'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $owner_id = trim($_POST['owner_id'] ?? '');
    $image = null;

    // Debug: Ghi log dữ liệu nhận được
    error_log("POST data: name=$name, address=$address, owner_id=$owner_id");

    if (empty($name) || empty($address) || empty($owner_id)) {
        $error = "Vui lòng điền đầy đủ thông tin!";
        error_log("Validation error: Missing required fields");
    } elseif (!is_numeric($owner_id)) {
        $error = "ID chủ sở hữu không hợp lệ!";
        error_log("Validation error: Invalid owner_id ($owner_id)");
    } else {
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'Uploads/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
                error_log("Created uploads directory: $upload_dir");
            }
            $image_name = time() . '_' . basename($_FILES['image']['name']);
            $image_path = $upload_dir . $image_name;
            if (!move_uploaded_file($_FILES['image']['tmp_name'], $image_path)) {
                $error = "Lỗi khi tải lên ảnh!";
                error_log("Failed to upload image: " . $_FILES['image']['error']);
            } else {
                $image = $image_path;
            }
        }

        if (!isset($error)) {
            try {
                $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND role = 'restaurant'");
                $stmt->execute([$owner_id]);
                $owner = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$owner) {
                    $error = "Chủ sở hữu không tồn tại hoặc không phải vai trò nhà hàng!";
                    error_log("Validation error: Invalid owner_id ($owner_id)");
                } else {
                    $stmt = $pdo->prepare("INSERT INTO restaurants (name, address, owner_id, image, status) VALUES (?, ?, ?, ?, 'pending')");
                    $result = $stmt->execute([$name, $address, $owner_id, $image]);
                    if ($result) {
                        $success = "Thêm nhà hàng thành công!";
                        error_log("Restaurant added successfully");
                        header("Location: manage_restaurants.php?msg=add_success");
                        exit;
                    } else {
                        $error = "Không thể thêm nhà hàng!";
                        error_log("Failed to insert restaurant");
                    }
                }
            } catch (PDOException $e) {
                $error = "Lỗi: " . $e->getMessage();
                error_log("Database error: " . $e->getMessage());
            }
        }
    }
}

// Xử lý xóa nhà hàng
if (isset($_GET['delete_id'])) {
    $id = $_GET['delete_id'];
    error_log("Processing delete request for restaurant ID: $id");
    try {
        $stmt = $pdo->prepare("SELECT image FROM restaurants WHERE id = ?");
        $stmt->execute([$id]);
        $restaurant = $stmt->fetch(PDO::FETCH_ASSOC);
        $imagePath = $restaurant['image'];
        if ($imagePath && file_exists(__DIR__ . '/' . $imagePath)) {
            unlink(__DIR__ . '/' . $imagePath);
            error_log("Deleted image: " . $imagePath);
        }
        $stmt = $pdo->prepare("DELETE FROM restaurants WHERE id = ?");
        $stmt->execute([$id]);
        $success = "Xóa nhà hàng thành công!";
        error_log("Restaurant deleted successfully: ID=$id");
        header("Location: /ad/modules/restaurants/manage_restaurants.php?msg=delete_success");
        exit;
    } catch (PDOException $e) {
        $error = "Lỗi: " . $e->getMessage();
        error_log("Database error when deleting restaurant: " . $e->getMessage());
    }
}

// Xử lý cập nhật trạng thái
if (isset($_GET['id']) && isset($_GET['status'])) {
    $id = $_GET['id'];
    $status = $_GET['status'];
    error_log("Processing status update for restaurant ID: $id, status: $status");
    try {
        $stmt = $pdo->prepare("UPDATE restaurants SET status = ? WHERE id = ?");
        $stmt->execute([$status, $id]);
        if ($status === 'approved') {
            $success = "Duyệt nhà hàng thành công!";
            header("Location: /ad/modules/restaurants/manage_restaurants.php?msg=approve_success");
        } elseif ($status === 'rejected') {
            $success = "Từ chối nhà hàng thành công!";
            header("Location: /ad/modules/restaurants/manage_restaurants.php?msg=reject_success");
        } else {
            $success = "Cập nhật trạng thái thành công!";
            header("Location: /ad/modules/restaurants/manage_restaurants.php?msg=status_success");
        }
        error_log("Status updated successfully for restaurant ID: $id");
        exit;
    } catch (PDOException $e) {
        $error = "Lỗi: " . $e->getMessage();
        error_log("Database error when updating status: " . $e->getMessage());
    }
}

// Hiển thị thông báo theo msg
$msg = $_GET['msg'] ?? '';
$success = null;
switch ($msg) {
    case 'add_success':
        $success = 'Thêm nhà hàng thành công!';
        break;
    case 'edit_success':
        $success = 'Sửa nhà hàng thành công!';
        break;
    case 'delete_success':
        $success = 'Xóa nhà hàng thành công!';
        break;
    case 'approve_success':
        $success = 'Duyệt nhà hàng thành công!';
        break;
    case 'reject_success':
        $success = 'Từ chối nhà hàng thành công!';
        break;
    case 'status_success':
        $success = 'Cập nhật trạng thái thành công!';
        break;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý nhà hàng</title>
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
        .btn-success {
            background-color: #28a745;
            color: white;
        }
        .btn-success:hover {
            background-color: #218838;
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
        .form-control {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
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
                    <i class="fas fa-store mr-2"></i> Quản lý nhà hàng
                </h1>
                <p class="text-sm mt-2">Quản lý danh sách và thông tin nhà hàng trên nền tảng</p>
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

                <!-- Form thêm nhà hàng -->
                <div class="card mb-6 bg-white shadow-lg rounded-lg p-6 animate__animated animate__fadeInUp">
                    <h2 class="text-xl font-semibold mb-4 flex items-center text-gray-700">
                        <i class="fas fa-plus-circle mr-2"></i> Thêm nhà hàng mới
                    </h2>
                    <form method="POST" action="" enctype="multipart/form-data" class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="form-group">
                            <label for="name">Tên nhà hàng</label>
                            <input type="text" name="name" id="name" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="address">Địa chỉ</label>
                            <textarea name="address" id="address" class="form-control" rows="4" required></textarea>
                        </div>
                        <div class="form-group">
                            <label for="owner_id">Chủ sở hữu</label>
                            <select name="owner_id" id="owner_id" class="form-control" required>
                                <option value="">Chọn chủ sở hữu</option>
                                <?php foreach ($restaurant_owners as $owner): ?>
                                <option value="<?php echo htmlspecialchars($owner['id']); ?>"><?php echo htmlspecialchars($owner['username']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="image">Ảnh nhà hàng</label>
                            <input type="file" name="image" id="image" class="form-control" accept="image/*">
                        </div>
                        <div class="md:col-span-2">
                            <div class="flex flex-wrap gap-4">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save mr-2"></i> Thêm nhà hàng
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Danh sách nhà hàng -->
                <div class="card table-container bg-white shadow-lg rounded-lg p-6 animate__animated animate__fadeInUp">
                    <h2 class="text-xl font-semibold mb-4 flex items-center text-gray-700">
                        <i class="fas fa-list mr-2"></i> Danh sách nhà hàng
                    </h2>
                    <?php if (empty($restaurants)): ?>
                        <p class="text-gray-500">Không có nhà hàng nào để hiển thị.</p>
                    <?php else: ?>
                        <table>
                            <thead>
                                <tr>
                                    <th class="w-10">ID</th>
                                    <th>Tên nhà hàng</th>
                                    <th>Chủ sở hữu</th>
                                    <th class="w-24">Ảnh</th>
                                    <th>Trạng thái</th>
                                    <th class="w-64">Hành động</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($restaurants as $restaurant): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($restaurant['id']); ?></td>
                                    <td><?php echo htmlspecialchars($restaurant['name']); ?></td>
                                    <td><?php echo htmlspecialchars($restaurant['owner'] ?? 'Không xác định'); ?></td>
                                    <td>
                                        <?php if ($restaurant['image']): ?>
                                        <img src="<?php echo htmlspecialchars($restaurant['image']); ?>" alt="Ảnh nhà hàng" class="w-16 h-16 object-cover rounded">
                                        <?php else: ?>
                                        Không có ảnh
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($restaurant['status']); ?></td>
                                    <td>
                                        <div class="flex flex-wrap gap-2">
                                            <?php if ($restaurant['status'] == 'approved'): ?>
                                                <a href="/ad/modules/restaurants/edit_restaurant.php?id=<?php echo $restaurant['id']; ?>" class="btn btn-warning btn-sm">
                                                    <i class="fas fa-edit mr-1"></i> Sửa
                                                </a>
                                                <a href="javascript:void(0)" onclick="confirmDelete(<?php echo $restaurant['id']; ?>)" class="btn btn-danger btn-sm">
                                                    <i class="fas fa-trash mr-1"></i> Xóa
                                                </a>
                                            <?php elseif ($restaurant['status'] == 'rejected'): ?>
                                                <a href="javascript:void(0)" onclick="confirmDelete(<?php echo $restaurant['id']; ?>)" class="btn btn-danger btn-sm">
                                                    <i class="fas fa-trash mr-1"></i> Xóa
                                                </a>
                                            <?php else: ?>
                                                <a href="/ad/modules/restaurants/manage_restaurants.php?id=<?php echo $restaurant['id']; ?>&status=approved" class="btn btn-success btn-sm">
                                                    <i class="fas fa-check mr-1"></i> Duyệt
                                                </a>
                                                <a href="/ad/modules/restaurants/manage_restaurants.php?id=<?php echo $restaurant['id']; ?>&status=rejected" class="btn btn-danger btn-sm">
                                                    <i class="fas fa-times mr-1"></i> Từ chối
                                                </a>
                                                <a href="javascript:void(0)" onclick="confirmDelete(<?php echo $restaurant['id']; ?>)" class="btn btn-danger btn-sm">
                                                    <i class="fas fa-trash mr-1"></i> Xóa
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
                if (!$(this).attr('href').includes('#') && !$(this).hasClass('no-transition')) {
                    e.preventDefault();
                    $('#main-content').fadeOut(300, function() {
                        window.location.href = $(e.target).closest('a').attr('href');
                    });
                }
            });
        });
        // Đưa hàm confirmDelete ra ngoài để gọi được từ HTML
        function confirmDelete(id) {
            if (confirm('Bạn có chắc chắn muốn xóa nhà hàng này?')) {
                window.location.href = '/ad/modules/restaurants/manage_restaurants.php?delete_id=' + id;
            }
        }
    </script>
</body>
</html>
<?php ob_end_flush(); ?>