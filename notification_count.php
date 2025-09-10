<?php
session_start();
include "db.php";

if (!isset($_SESSION['user_id'])) {
    echo "0";
    exit();
}

$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($unreadCount);
$stmt->fetch();
$stmt->close();

echo $unreadCount;
