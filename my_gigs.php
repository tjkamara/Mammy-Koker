<?php
session_start();
include "db.php";

// Ensure only logged-in sellers can access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'seller') {
    header("Location: auth.php");
    exit();
}

$seller_id = $_SESSION['user_id'];
$name = $_SESSION['name'];

// Fetch seller's gigs
$stmt = $conn->prepare("SELECT id, title, description, price, delivery_time, category, image_path, created_at FROM gigs WHERE seller_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $seller_id);
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Gigs - Mammy Coker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .gig-card {
            border-radius: 12px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .gig-image {
            height: 180px;
            object-fit: cover;
        }
        .gig-title {
            font-weight: bold;
            font-size: 1.1rem;
        }
        .gig-meta {
            font-size: 0.9rem;
            color: #555;
        }
        .gig-category {
            font-size: 0.85rem;
            font-style: italic;
            color: #0066cc;
        }
        .nav-item {
            color: white;
        }
    </style>
</head>
<body class="bg-light">

<!-- ✅ Navigation Bar -->
<nav class="navbar navbar-expand-lg navbar-primary bg-primary">
    <div class="container">
    <a class="navbar-brand fw-bold" href="index.php"><img src="images/logonbg.png" style="width:70px; height:70px; border-radius:50%; margin:-5px;"/></a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarToggler">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarToggler">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item"><a href="sellerdashboard.php" class="nav-link active" style="color:white;">Dashboard</a></li>
                <li class="nav-item"><a href="create_gig.php" class="nav-link" style="color:white;">Create Gig</a></li>
                <li class="nav-item"><a href="orders.php" class="nav-link" style="color:white;">Orders</a></li>
            </ul>
            <ul class="navbar-nav">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" style="color:white;" href="#" role="button" data-bs-toggle="dropdown">
                        <?php echo htmlspecialchars($name); ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="profile.php">👤 Profile</a></li>
                        <li><a class="dropdown-item" href="settings.php">⚙️ Settings</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="logout.php">🚪 Logout</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>

<!-- ✅ Main Dashboard -->
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4>Welcome, <?php echo htmlspecialchars($name); ?> 👋</h4>
        <a href="create_gig.php" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> Post New Gig
        </a>
    </div>

    <h5>Your Posted Gigs</h5>
    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4 mt-2">
        <?php if ($result->num_rows > 0): ?>
            <?php while($gig = $result->fetch_assoc()): ?>
                <div class="col">
                    <div class="card gig-card">
                        <?php
                        $imgPath = !empty($gig['image_path']) && file_exists("uploads/" . $gig['image_path']) 
                            ? "uploads/" . $gig['image_path'] 
                            : "images/default.jpg";
                        ?>
                        <!-- <img src="<?php echo $imgPath; ?>" class="card-img-top gig-image" alt="Gig Image"> -->
                        <img src="<?php echo !empty($gig['image_path']) ? $gig['image_path'] : 'images/default.jpg'; ?>" class="card-img-top" alt="Gig Image">


                        <div class="card-body">
                            <div class="gig-title"><?php echo htmlspecialchars($gig['title']); ?></div>
                            <p class="text-muted gig-meta"><?php echo htmlspecialchars($gig['description']); ?></p>
                            <p class="gig-category">Category: <?php echo htmlspecialchars($gig['category']); ?></p>
                            <p class="gig-meta">💵 NLe <?php echo number_format($gig['price'], 2); ?><br>⏱ <?php echo $gig['delivery_time']; ?> days</p>
                            <p class="text-end text-muted" style="font-size: 0.75rem;">Posted on: <?php echo date("M d, Y", strtotime($gig['created_at'])); ?></p>
                            <div class="d-flex justify-content-between mt-3">
                                <a href="edit_gig.php?id=<?php echo $gig['id']; ?>" class="btn btn-sm btn-warning">✏️ Edit</a>
                                <a href="delete_gig.php?id=<?php echo $gig['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this gig?');">🗑 Delete</a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="col">
                <div class="alert alert-info">
                    You haven't posted any gigs yet. <a href="create_gig.php" class="alert-link">Post one now!</a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
