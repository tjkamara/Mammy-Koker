<?php
session_start();
require_once 'db.php'; // <- your DB connection file

// Check if seller is logged in
if (!isset($_SESSION['user_id'])) {
    die("Unauthorized. Please login.");
}

$seller_id = $_SESSION['user_id'];

// Form inputs
$title = trim($_POST['title']);
$category = trim($_POST['category']);
$price = floatval($_POST['price']);
$delivery_time = intval($_POST['delivery_time']);
$description = trim($_POST['description']);

// Image handling
$target_dir = "uploads/gigs/";
if (!is_dir($target_dir)) mkdir($target_dir, 0755, true);

$image_name = basename($_FILES["gig_image"]["name"]);
$image_tmp = $_FILES["gig_image"]["tmp_name"];
$image_ext = strtolower(pathinfo($image_name, PATHINFO_EXTENSION));
$allowed_exts = ['jpg', 'jpeg', 'png', 'gif'];
$new_filename = uniqid("gig_", true) . "." . $image_ext;
$target_file = $target_dir . $new_filename;

if (!in_array($image_ext, $allowed_exts)) {
    die("Only JPG, JPEG, PNG & GIF files are allowed.");
}

if (move_uploaded_file($image_tmp, $target_file)) {
    // Insert gig into DB
    $stmt = $conn->prepare("INSERT INTO gigs (seller_id, title, description, price, category, delivery_time, image_path) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("issdsss", $seller_id, $title, $description, $price, $category, $delivery_time, $new_filename);

    if ($stmt->execute()) {
        header("Location: seller_dashboard.php?msg=Gig created successfully");
        exit();
    } else {
        echo "Error: " . $stmt->error;
    }

    $stmt->close();
} else {
    echo "Failed to upload gig image.";
}

$conn->close();
?>
