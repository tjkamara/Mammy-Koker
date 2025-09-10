<?php
session_start();
include "db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'seller') {
    header("Location: auth.php");
    exit();
}

$categories = [
    'Web Design', 'Graphic Design', 'Logo Design', 'Writing', 'Translation', 'Video Editing',
    'Animation', 'Programming', 'SEO', 'Marketing', 'Voice Over', 'Music Production',
    'Mobile App Development', 'UI/UX Design', 'Social Media Marketing', 'Content Writing',
    'Copywriting', 'Data Entry', 'Virtual Assistant', 'Business Consulting', 'Legal Consulting',
    'Financial Consulting', 'Accounting', 'Bookkeeping', 'Proofreading', 'Resume Writing',
    'Career Coaching', 'Online Tutoring', 'Game Development', 'Blockchain', 'AI & Machine Learning',
    'Cybersecurity', 'Cloud Computing', 'DevOps', 'Technical Writing', 'Product Design',
    'Interior Design', 'Architecture', 'Photography', 'Fashion Design', 'Jewelry Design',
    'Health & Fitness Coaching', 'Nutrition Coaching', 'Life Coaching', 'Event Planning',
    'Wedding Planning', '3D Modeling', 'CAD Design'
];

$message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'];
    $description = $_POST['description'];
    $price = $_POST['price'];
    $delivery_time = $_POST['delivery_time'];
    $revisions = $_POST['revisions'];
    $selected_categories = isset($_POST['category']) ? implode(", ", $_POST['category']) : '';

    $image_path = 'uploads/gigs/default.jpg';

    if (!empty($_FILES['image']['name'])) {
        if (!file_exists('uploads/gigs')) {
            mkdir('uploads/gigs', 0777, true);
        }
        $image_name = time() . '_' . basename($_FILES['image']['name']);
        $target_path = 'uploads/gigs/' . $image_name;
        if (move_uploaded_file($_FILES['image']['tmp_name'], $target_path)) {
            $image_path = $target_path;
        }
    }

    $stmt = $conn->prepare("INSERT INTO gigs (seller_id, title, description, price, category, image_path, delivery_time, revisions) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("issdssii", $_SESSION['user_id'], $title, $description, $price, $selected_categories, $image_path, $delivery_time, $revisions);

    if ($stmt->execute()) {
        header("Location: my_gigs.php");
        exit();
    } else {
        $message = "Error: " . $stmt->error;
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Gig</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .form-container {
            max-width: 420px;
            margin: 40px auto;
            padding: 25px;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        .category-pill {
            margin: 4px;
        }
        .category-pill input[type="checkbox"] {
            display: none;
        }
        .category-pill label {
            border: 1px solid #ccc;
            border-radius: 50px;
            padding: 6px 14px;
            cursor: pointer;
            background-color: #f1f1f1;
            transition: 0.3s;
        }
        .category-pill input[type="checkbox"]:checked + label {
            background-color: #0d6efd;
            color: white;
            border-color: #0d6efd;
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
<body class="bg-light">
<div class="container">




    <div class="form-container">
        <h4 class="text-center mb-3">New Gig <i class="bi bi-plus-circle"></i></h4>

        <?php if (!empty($message)) echo "<div class='alert alert-danger'>$message</div>"; ?>

        <div class="d-flex justify-content-between mb-3">
                <a class="navbar-brand fw-bold" href="index.php"><img src="images/logonbg.png" style="width:70px; height:70px; border-radius:50%; margin:-5px;"/></a>

    <a href="index.php" class="btn btn-outline-secondary btn-sm">
        ← Back to Home
    </a>
    <a href="sellerdashboard.php" class="btn btn-outline-primary btn-sm">
        Skip for Now → Go to Dashboard
    </a>
</div>

        <form action="" method="POST" enctype="multipart/form-data">
            <div class="mb-2">
                <!-- <label class="form-label">Title</label> -->
                <label class="form-label" data-bs-toggle="tooltip" title="A short and catchy title for your service">Title</label>

                <input type="text" name="title" class="form-control form-control-sm" required>
            </div>
            <div class="mb-2">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-control form-control-sm" rows="2" required></textarea>
            </div>
            <div class="mb-2">
                <label class="form-label">Price (NLe)</label>
                <input type="number" name="price" class="form-control form-control-sm" step="0.01" required>
            </div>
            <div class="mb-2">
                <label class="form-label">Upload Image</label>
                <input type="file" name="image" class="form-control form-control-sm">
            </div>
            <div class="mb-2">
                <label class="form-label">Delivery Time (days)</label>
                <input type="number" name="delivery_time" class="form-control form-control-sm" required>
            </div>
            <div class="mb-2">
                <label class="form-label">Revisions</label>
                <input type="number" name="revisions" class="form-control form-control-sm" value="1" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Categories</label>
                <div class="d-flex flex-wrap">
                    <?php foreach ($categories as $cat): ?>
                        <div class="category-pill">
                            <input type="checkbox" name="category[]" id="cat_<?php echo $cat; ?>" value="<?php echo $cat; ?>">
                            <label for="cat_<?php echo $cat; ?>"><?php echo $cat; ?></label>
                        </div>
                    <?php endforeach; ?>
                    <div class="mt-2 w-100">
                        <input type="text" name="category[]" class="form-control form-control-sm mt-1" placeholder="Add custom category">
                    </div>
                </div>
            </div>
            <button type="submit" class="btn btn-success w-100">Create <i class="bi bi-upload"></i></button>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    });
</script>

</body>
</html>
