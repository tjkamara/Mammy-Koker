<?php
session_start();
include "db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: auth.php");
    exit();
}

$current_user_id = $_SESSION['user_id'];

// Fetch conversations with last message and unread count
$sql = "
    SELECT 
        m1.sender_id, m1.receiver_id, m1.message, m1.created_at, m1.is_read,
        u.name AS contact_name, u.id AS contact_id,
        (SELECT COUNT(*) FROM messages 
         WHERE sender_id = u.id AND receiver_id = ? AND is_read = 0) AS unread_count
    FROM messages m1
    INNER JOIN (
        SELECT 
            LEAST(sender_id, receiver_id) AS user1,
            GREATEST(sender_id, receiver_id) AS user2,
            MAX(id) AS max_id
        FROM messages
        WHERE sender_id = ? OR receiver_id = ?
        GROUP BY user1, user2
    ) grouped
    ON m1.id = grouped.max_id
    JOIN users u ON u.id = IF(m1.sender_id = ?, m1.receiver_id, m1.sender_id)
    ORDER BY m1.created_at DESC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("iiii", $current_user_id, $current_user_id, $current_user_id, $current_user_id);
$stmt->execute();
$result = $stmt->get_result();

$conversations = [];
while ($row = $result->fetch_assoc()) {
    $conversations[] = $row;
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Your Messages - Mammy Coker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: #f8f9fa;
        }
        .chat-list {
            max-width: 600px;
            margin: auto;
        }
        .chat-item {
            transition: background 0.3s;
            cursor: pointer;
        }
        .chat-item:hover {
            background-color: #f1f1f1;
        }
        .chat-message-preview {
            font-size: 0.9rem;
            color: #6c757d;
        }
        @media (max-width: 576px) {
            .chat-item .fw-bold {
                font-size: 1rem;
            }
            .chat-message-preview {
                font-size: 0.85rem;
            }
        }
        
                /*mobile friendly part*/
            @media screen and (max-width: 768px) {
  .sidebar {
    display: none;
  }

  .content {
    width: 100%;
    padding: 10px;
  }
}
    </style>
</head>
<body>

<div class="container py-4 chat-list">
    <h4 class="mb-4"><i class="bi bi-chat-dots-fill text-primary"></i> Messages</h4>

    <?php if (empty($conversations)): ?>
        <div class="alert alert-info text-center">You have no conversations yet.</div>
    <?php else: ?>
        <ul class="list-group">
            <?php foreach ($conversations as $chat): ?>
                <a href="message.php?user_id=<?= $chat['contact_id'] ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-start chat-item">
                    <div class="ms-2 me-auto">
                        <div class="fw-bold"><?= htmlspecialchars($chat['contact_name']) ?></div>
                        <div class="chat-message-preview"><?= htmlspecialchars(substr($chat['message'], 0, 50)) ?>...</div>
                    </div>
                    <?php if ($chat['unread_count'] > 0): ?>
                        <span class="badge bg-danger rounded-pill"><?= $chat['unread_count'] ?></span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>

</body>
</html>
