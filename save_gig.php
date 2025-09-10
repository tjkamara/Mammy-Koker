<?php
session_start();
include "db.php";

// Check if user is logged in and is a seller
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'seller') {
    header("Location: auth.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $seller_id = $_SESSION['user_id'];
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $price = (float) $_POST['price'];
    $delivery_time = (int) $_POST['delivery_time'];

    // Combine multiple selected categories into a comma-separated string
    $categories = isset($_POST['category']) ? implode(", ", $_POST['category']) : "";

    // Handle image upload
    $image = $_FILES['image'];
    $imageName = time() . "_" . basename($image['name']);
    $targetDir = "uploads/";
    $targetFile = $targetDir . $imageName;

    // Create uploads directory if it doesn't exist
    if (!file_exists($targetDir)) {
        mkdir($targetDir, 0755, true);
    }

    if (move_uploaded_file($image['tmp_name'], $targetFile)) {
        // Insert gig into database
        $stmt = $conn->prepare("INSERT INTO gigs (seller_id, title, description, price, delivery_time, category, image_path) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issdiss", $seller_id, $title, $description, $price, $delivery_time, $categories, $imageName);

        if ($stmt->execute()) {
            header("Location: dashboard.php?message=gig_created");
            exit();
        } else {
            echo "Error saving gig: " . $stmt->error;
        }
        $stmt->close();
    } else {
        echo "Image upload failed!";
    }
}
?>
