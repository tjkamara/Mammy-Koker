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

// Fetch gig data
$stmt = $conn->prepare("SELECT title, description, price, delivery_time, category FROM gigs WHERE id = ? AND seller_id = ?");
$stmt->bind_param("ii", $gig_id, $seller_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    header("Location: my_gigs.php");
    exit();
}

$gig = $result->fetch_assoc();
$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $price = floatval($_POST['price']);
    $delivery_time = intval($_POST['delivery_time']);
    $category = trim($_POST['category']);

    $update = $conn->prepare("UPDATE gigs SET title = ?, description = ?, price = ?, delivery_time = ?, category = ? WHERE id = ? AND seller_id = ?");
    $update->bind_param("ssdissi", $title, $description, $price, $delivery_time, $category, $gig_id, $seller_id);

    if ($update->execute()) {
        header("Location: my_gigs.php");
        exit();
    } else {
        $message = "Error updating gig. Please try again.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Gig - Mammy Coker</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    
    <style>
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
<body class="bg-light">
    <div class="container py-5">
        <h2>Edit Gig</h2>

        <?php if (!empty($message)): ?>
            <div class="alert alert-danger"><?php echo $message; ?></div>
        <?php endif; ?>

        <form action="" method="POST" class="bg-white p-4 rounded shadow-sm">
            <div class="mb-3">
                <label for="title" class="form-label">Gig Title</label>
                <input type="text" class="form-control" name="title" required value="<?php echo htmlspecialchars($gig['title']); ?>">
            </div>

            <div class="mb-3">
                <label for="description" class="form-label">Gig Description</label>
                <textarea class="form-control" name="description" rows="4" required><?php echo htmlspecialchars($gig['description']); ?></textarea>
            </div>

            <div class="mb-3">
                <label for="price" class="form-label">Price ($)</label>
                <input type="number" class="form-control" name="price" step="0.01" min="1" required value="<?php echo $gig['price']; ?>">
            </div>

            <div class="mb-3">
                <label for="delivery_time" class="form-label">Delivery Time (days)</label>
                <input type="number" class="form-control" name="delivery_time" min="1" required value="<?php echo $gig['delivery_time']; ?>">
            </div>

            <div class="mb-3">
                <label for="category" class="form-label">Category</label>
                <input type="text" class="form-control" name="category" required value="<?php echo htmlspecialchars($gig['category']); ?>">
            </div>

            <button type="submit" class="btn btn-primary">Update Gig</button>
            <a href="my_gigs.php" class="btn btn-secondary">Cancel</a>
        </form>
    </div>
</body>
</html>
