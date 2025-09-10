<?php
session_start();
include "db.php";

if (!isset($_SESSION['user_id']) || !isset($_GET['user_id'])) {
    exit("Unauthorized");
}

$current_user = $_SESSION['user_id'];
$receiver_id = intval($_GET['user_id']);

$stmt = $conn->prepare("SELECT * FROM messages WHERE 
    (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)
    ORDER BY created_at ASC
");
$stmt->bind_param("iiii", $current_user, $receiver_id, $receiver_id, $current_user);
$stmt->execute();
$result = $stmt->get_result();

while ($msg = $result->fetch_assoc()):
    $is_me = $msg['sender_id'] == $current_user;
    ?>
    <div class="message <?= $is_me ? 'me text-end' : 'text-start' ?>">
        <div class="bg-<?= $is_me ? 'success' : 'secondary' ?> text-white d-inline-block p-2 rounded">
            <?= nl2br(htmlspecialchars($msg['message'])) ?>
            <?php if ($msg['attachment']): ?>
                <div class="mt-2">
                    <a href="<?= htmlspecialchars($msg['attachment']) ?>" target="_blank">
                        <img src="<?= htmlspecialchars($msg['attachment']) ?>" class="attachment-preview">
                    </a>
                </div>
            <?php endif; ?>
        </div>
        <div><small class="text-muted"><?= date("M j, H:i", strtotime($msg['created_at'])) ?></small></div>
    </div>
<?php endwhile; ?>
