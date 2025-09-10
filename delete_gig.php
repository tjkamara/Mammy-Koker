<?php
session_start();
include "db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'seller') {
    header("Location: auth.php");
    exit();
}

$seller_id = $_SESSION['user_id'];
$gig_id = $_GET['id'] ?? null;

if (!$gig_id) {
    header("Location: my_gigs.php");
    exit();
}

// Delete gig
$stmt = $conn->prepare("DELETE FROM gigs WHERE id = ? AND seller_id = ?");
$stmt->bind_param("ii", $gig_id, $seller_id);
$stmt->execute();

header("Location: my_gigs.php");
exit();
?>
