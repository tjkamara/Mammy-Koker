<?php
session_start();
include "db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: auth.php");
    exit();
}

$current_user = $_SESSION['user_id'];
$current_name = $_SESSION['name'];

$stmt = $conn->prepare("
    SELECT u.id, u.name, u.role, MAX(m.sent_at) as last_time, 
           (SELECT message FROM messages WHERE 
                    (sender_id = u.id AND receiver_id = ?) OR 
                    (sender_id = ? AND receiver_id = u.id)
             ORDER BY sent_at DESC LIMIT 1) as last_message
    FROM users u
    JOIN messages m ON (m.sender_id = u.id OR m.receiver_id = u.id)
    WHERE (m.sender_id = ? OR m.receiver_id = ?) AND u.id != ?
    GROUP BY u.id, u.name, u.role
    ORDER BY last_time DESC
");

$stmt->bind_param("iiiii", $current_user, $current_user, $current_user, $current_user, $current_user);
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
    <title>Messages - Mammy Coker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .msg-card {
            transition: 0.3s;
            cursor: pointer;
        }
        .msg-card:hover {
            background: #f8f9fa;
        }
    </style>
</head>
<body class="bg-light">
<div class="container mt-4">
    <h4 class="mb-4"><i class="bi bi-chat-dots"></i> Conversations</h4>

    <?php if (empty($conversations)): ?>
        <div class="alert alert-info text-center">No conversations yet.</div>
    <?php else: ?>
        <div class="list-group">
            <?php foreach ($conversations as $user): ?>
                <a href="chat.php?user_id=<?= $user['id'] ?>" class="list-group-item list-group-item-action msg-card d-flex justify-content-between align-items-start">
                    <div class="ms-2 me-auto">
                        <div class="fw-bold"><?= htmlspecialchars($user['name']) ?> 
                            <span class="badge bg-secondary text-uppercase"><?= $user['role'] ?></span>
                        </div>
                        <small class="text-muted"><?= htmlspecialchars(substr($user['last_message'], 0, 60)) ?>...</small>
                    </div>
                    <small><?= date("M j, H:i", strtotime($user['last_time'])) ?></small>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
</body>
</html>
