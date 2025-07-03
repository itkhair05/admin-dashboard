
<?php
require 'db_connect.php';

$group_id = isset($_GET['group_id']) ? $_GET['group_id'] : null;
if (!$group_id) {
    echo "Không tìm thấy nhóm chat.";
    exit;
}

$stmt = $pdo->prepare("SELECT m.*, u.username FROM messages m JOIN users u ON m.user_id = u.id 
                       WHERE m.group_chat_id = ? ORDER BY m.created_at ASC");
$stmt->execute([$group_id]);
$messages = $stmt->fetchAll();

foreach ($messages as $message) {
    echo "<div class='mb-2'>";
    echo "<strong>" . htmlspecialchars($message['username']) . ":</strong> ";
    echo "<span>" . htmlspecialchars($message['message']) . "</span> ";
    echo "<span class='text-gray-500 text-sm'>" . $message['created_at'] . "</span>";
    echo "</div>";
}
?>
