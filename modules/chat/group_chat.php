<?php
session_start();
require __DIR__ . '/../../config/db_connect.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

// Định nghĩa $current_page
$current_page = basename($_SERVER['PHP_SELF']);

// Debug: Ghi log để kiểm tra file được tải
error_log("Loading group_chat.php at " . date('Y-m-d H:i:s'));

// Xử lý gửi tin nhắn
$success = null;
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $message = trim($_POST['message'] ?? '');
    $user_id = $_SESSION['admin_id'];
    $order_id = null; // Có thể để null hoặc lấy từ form nếu cần
    $complaint_id = null; // Có thể để null hoặc lấy từ form nếu cần

    if (empty($message)) {
        $error = "Tin nhắn không được để trống!";
        error_log("Validation error: Empty message");
    } else {
        try {
            error_log("Attempting to insert message: user_id=$user_id, message=$message, order_id=$order_id, complaint_id=$complaint_id");
            $stmt = $pdo->prepare("INSERT INTO messages (order_id, user_id, message, created_at, complaint_id) VALUES (?, ?, ?, NOW(), ?)");
            $result = $stmt->execute([$order_id, $user_id, $message, $complaint_id]);
            if ($result) {
                $success = "Gửi tin nhắn thành công!";
                $last_id = $pdo->lastInsertId();
                error_log("Message inserted successfully, last ID: $last_id");
            } else {
                $error = "Không thể gửi tin nhắn! Kiểm tra lại database.";
                error_log("Insert failed, rowCount: " . $stmt->rowCount());
            }
        } catch (PDOException $e) {
            $error = "Lỗi khi gửi tin nhắn: " . $e->getMessage();
            error_log("Database error when sending message: " . $e->getMessage());
        }
    }
}

// Lấy danh sách tin nhắn
$messages = [];
try {
    $stmt = $pdo->query("SELECT m.*, u.username FROM messages m LEFT JOIN users u ON m.user_id = u.id ORDER BY m.created_at DESC LIMIT 50");
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!$messages) {
        error_log("No messages found after JOIN with users.");
        $stmt = $pdo->query("SELECT * FROM messages ORDER BY created_at DESC LIMIT 50");
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    if (!$messages) {
        error_log("No messages found in database.");
    }
} catch (PDOException $e) {
    $error = "Lỗi khi lấy tin nhắn: " . $e->getMessage();
    error_log("Database error when fetching messages: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat nhóm</title>
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
        .message-box {
            max-height: 300px;
            overflow-y: auto;
            margin-bottom: 1rem;
        }
        .message {
            padding: 0.5rem 1rem;
            margin-bottom: 0.5rem;
            background-color: #e5e7eb;
            border-radius: 0.375rem;
            display: flex;
            flex-direction: column;
        }
        .message .username {
            font-weight: 600;
            color: #2d3748;
        }
        .message .timestamp {
            font-size: 0.75rem;
            color: #718096;
        }
        .form-control {
            width: 100%;
            max-width: 100%;
            min-width: 0;
            padding: 0.75rem;
            border: 1px solid #e5e7eb;
            border-radius: 0.375rem;
            resize: vertical;
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
                    <i class="fas fa-comments mr-2"></i> Chat nhóm
                </h1>
                <p class="text-sm mt-2">Giao tiếp và trao đổi thông tin với các nhân viên khác</p>
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

                <div class="card animate__animated animate__fadeInUp">
                    <h2 class="text-xl font-semibold mb-4 flex items-center">
                        <i class="fas fa-users mr-2"></i> Danh sách tin nhắn
                    </h2>
                    <div class="message-box bg-gray-100 p-4 rounded-lg" id="message-box">
                        <?php foreach ($messages as $message): ?>
                            <div class="message">
                                <span class="username"><?php echo htmlspecialchars($message['username'] ?? 'Admin'); ?></span>
                                <span><?php echo htmlspecialchars($message['message']); ?></span>
                                <span class="timestamp"><?php echo htmlspecialchars($message['created_at']); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <form method="POST" action="" class="mt-4" id="chat-form">
                        <div class="form-group">
                            <textarea name="message" class="form-control" rows="4" placeholder="Nhập tin nhắn..." required style="width: 100%; min-width: 300px;"></textarea>
                        </div>
                        <div class="flex flex-wrap gap-4">
                            <button type="submit" name="send_message" class="btn btn-primary animate__animated animate__fadeIn">
                                <i class="fas fa-paper-plane mr-2"></i> Gửi
                            </button>
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
                if (!$(this).attr('href').includes('#')) {
                    e.preventDefault();
                    $('#main-content').fadeOut(300, function() {
                        window.location.href = $(e.target).attr('href');
                    });
                }
            });

            // AJAX để cập nhật tin nhắn tự động (mỗi 2 giây)
            function loadMessages() {
                $.get('group_chat.php', function(data) {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(data, 'text/html');
                    const newMessages = doc.getElementById('message-box').innerHTML;
                    $('#message-box').html(newMessages);
                }).fail(function(jqXHR, textStatus, errorThrown) {
                    console.error('Error loading messages:', textStatus, errorThrown);
                });
            }
            setInterval(loadMessages, 2000); // Cập nhật mỗi 2 giây

            // Xử lý gửi tin nhắn bằng AJAX và cập nhật ngay lập tức
            $('#chat-form').on('submit', function(e) {
                e.preventDefault();
                const message = $('textarea[name="message"]').val();
                if (message.trim()) {
                    $.post('group_chat.php', { send_message: 1, message: message }, function(response) {
                        loadMessages(); // Cập nhật danh sách tin nhắn ngay lập tức
                        $('textarea[name="message"]').val(''); // Xóa textarea
                        $('#content').prepend('<div class="alert alert-success animate__animated animate__fadeIn"><i class="fas fa-check-circle mr-2"></i> Gửi tin nhắn thành công!</div>');
                        setTimeout(() => $('.alert-success').remove(), 3000); // Xóa thông báo sau 3 giây
                    }).fail(function(jqXHR, textStatus, errorThrown) {
                        console.error('Error sending message:', textStatus, errorThrown);
                        $('#content').prepend('<div class="alert alert-error animate__animated animate__fadeIn"><i class="fas fa-exclamation-triangle mr-2"></i> Lỗi khi gửi tin nhắn!</div>');
                        setTimeout(() => $('.alert-error').remove(), 3000);
                    });
                }
            });

            // Tải tin nhắn ban đầu sau khi DOM sẵn sàng
            loadMessages();
        });
    </script>
</body>
</html>
