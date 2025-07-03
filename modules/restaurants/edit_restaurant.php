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
error_log("Loading edit_restaurant.php at " . date('Y-m-d H:i:s'));

// Lấy thông tin nhà hàng
$restaurant = null;
$error = null;
$success = null;
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $error = "ID nhà hàng không hợp lệ!";
    error_log("Invalid restaurant ID: " . ($_GET['id'] ?? 'null'));
    header("Location: manage_restaurants.php");
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT r.*, u.username AS owner FROM restaurants r LEFT JOIN users u ON r.owner_id = u.id WHERE r.id = ?");
    $stmt->execute([$_GET['id']]);
    $restaurant = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$restaurant) {
        $error = "Nhà hàng không tồn tại!";
        error_log("Restaurant not found for ID: " . $_GET['id']);
        header("Location: manage_restaurants.php");
        exit;
    }
} catch (PDOException $e) {
    $error = "Lỗi khi lấy dữ liệu: " . $e->getMessage();
    error_log("Database error when fetching restaurant: " . $e->getMessage());
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

// Xử lý cập nhật nhà hàng
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log("Processing POST request for edit_restaurant at " . date('Y-m-d H:i:s'));
    $id = trim($_POST['id'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $owner_id = trim($_POST['owner_id'] ?? '');
    $image = trim($_POST['current_image'] ?? '');

    // Debug: Ghi log dữ liệu nhận được
    error_log("POST data: id=$id, name=$name, address=$address, owner_id=$owner_id, current_image=$image");

    // Kiểm tra dữ liệu đầu vào
    if (empty($name) || empty($address) || empty($owner_id)) {
        $error = "Vui lòng điền đầy đủ thông tin!";
        error_log("Validation error: Missing required fields");
    } elseif (!is_numeric($id) || $id != $_GET['id']) {
        $error = "ID nhà hàng không hợp lệ!";
        error_log("Validation error: Invalid ID ($id)");
    } elseif (!is_numeric($owner_id)) {
        $error = "ID chủ sở hữu không hợp lệ!";
        error_log("Validation error: Invalid owner_id ($owner_id)");
    } else {
        // Xử lý upload ảnh
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
                // Xóa ảnh cũ nếu có
                if ($image && file_exists($image)) {
                    unlink($image);
                    error_log("Deleted old image: $image");
                }
                $image = $image_path;
            }
        }

        // Cập nhật database
        if (!isset($error)) {
            try {
                $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND role = 'restaurant'");
                $stmt->execute([$owner_id]);
                $owner = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$owner) {
                    $error = "Chủ sở hữu không tồn tại hoặc không phải vai trò nhà hàng!";
                    error_log("Validation error: Invalid owner_id ($owner_id)");
                } else {
                    $stmt = $pdo->prepare("UPDATE restaurants SET name = ?, address = ?, owner_id = ?, image = ? WHERE id = ?");
                    $result = $stmt->execute([$name, $address, $owner_id, $image, $id]);
                    if ($result) {
                        $success = "Cập nhật nhà hàng thành công!";
                        error_log("Restaurant updated successfully: ID=$id");
                        header("Location: manage_restaurants.php?msg=edit_success");
                        exit;
                    } else {
                        $error = "Không thể cập nhật nhà hàng!";
                        error_log("Failed to update restaurant: ID=$id");
                    }
                }
            } catch (PDOException $e) {
                $error = "Lỗi khi cập nhật: " . $e->getMessage();
                error_log("Database error when updating restaurant: " . $e->getMessage());
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chỉnh sửa nhà hàng</title>
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
                <h1 class="text-3xl font-bold mb-6 flex items-center animate__animated animate__fadeInUp">
                    <i class="fas fa-edit mr-2"></i> Chỉnh sửa nhà hàng
                </h1>

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

                <!-- Form chỉnh sửa nhà hàng -->
                <div class="card mb-6 bg-white shadow-lg rounded-lg p-6 animate__animated animate__fadeInUp">
                    <h2 class="text-xl font-semibold mb-4 flex items-center text-gray-700">
                        <i class="fas fa-edit mr-2"></i> Thông tin nhà hàng
                    </h2>
                    <form method="POST" action="" enctype="multipart/form-data" class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <input type="hidden" name="id" value="<?php echo htmlspecialchars($restaurant['id']); ?>">
                        <input type="hidden" name="current_image" value="<?php echo htmlspecialchars($restaurant['image'] ?? ''); ?>">
                        <div class="form-group">
                            <label for="name">Tên nhà hàng</label>
                            <input type="text" name="name" id="name" class="form-control" value="<?php echo htmlspecialchars($restaurant['name']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="address">Địa chỉ</label>
                            <textarea name="address" id="address" class="form-control" rows="4" required><?php echo htmlspecialchars($restaurant['address']); ?></textarea>
                        </div>
                        <div class="form-group">
                            <label for="owner_id">Chủ sở hữu</label>
                            <select name="owner_id" id="owner_id" class="form-control" required>
                                <option value="">Chọn chủ sở hữu</option>
                                <?php foreach ($restaurant_owners as $owner): ?>
                                <option value="<?php echo htmlspecialchars($owner['id']); ?>" <?php echo ($owner['id'] == $restaurant['owner_id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($owner['username']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="image">Ảnh nhà hàng</label>
                            <input type="file" name="image" id="image" class="form-control" accept="image/*">
                            <?php if ($restaurant['image']): ?>
                            <img src="<?php echo htmlspecialchars($restaurant['image']); ?>" alt="Ảnh hiện tại" class="w-32 h-32 object-cover rounded mt-2">
                            <?php endif; ?>
                        </div>
                        <div class="md:col-span-2">
                            <div class="flex flex-wrap gap-4">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save mr-2"></i> Lưu thay đổi
                                </button>
                                <a href="manage_restaurants.php" class="btn btn-danger">
                                    <i class="fas fa-times mr-2"></i> Hủy
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