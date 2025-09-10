<?php
session_start();
include "db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'buyer') {
    header("Location: auth.php");
    exit();
}

// Fetch all distinct categories (exploded by comma)
$rawCategories = [];
$res = $conn->query("SELECT category FROM gigs WHERE status='active'");
while ($row = $res->fetch_assoc()) {
    $rawCategories = array_merge($rawCategories, array_map('trim', explode(',', $row['category'])));
}
$categories = array_unique(array_filter($rawCategories));
sort($categories);

// Category icons
$categoryIcons = [
    'Web Design' => 'bi-globe2',
    'Graphic Design' => 'bi-palette-fill',
    'Logo Design' => 'bi-image',
    'Writing' => 'bi-pencil-square',
    'Translation' => 'bi-translate',
    'Video Editing' => 'bi-film',
    'Animation' => 'bi-easel2',
    'Programming' => 'bi-code-slash',
    'SEO' => 'bi-bar-chart-line-fill',
    'Marketing' => 'bi-megaphone-fill',
    'Voice Over' => 'bi-mic-fill',
    'Music Production' => 'bi-music-note-beamed',
    'UI/UX Design' => 'bi-vector-pen',
    'Data Entry' => 'bi-keyboard',
    'Online Tutoring' => 'bi-mortarboard-fill',
    '3D Modeling' => 'bi-cube',
    'default' => 'bi-tags'
];

// Get filters
$search = $_GET['search'] ?? '';
$currentCategory = $_GET['category'] ?? '';
$gigs = [];

// Prepare SQL
$sql = "SELECT * FROM gigs WHERE status='active'";
$params = [];
$types = "";

if (!empty($currentCategory)) {
    $sql .= " AND category LIKE ?";
    $types .= "s";
    $params[] = "%" . $currentCategory . "%";
}
if (!empty($search)) {
    $sql .= " AND (title LIKE ? OR description LIKE ?)";
    $types .= "ss";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$stmt = $conn->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $gigs[] = $row;
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Explore Gigs - Mammy Coker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .gig-card img {
            height: 180px;
            object-fit: cover;
            border-radius: 8px;
        }
        .category-pill {
            border: 1px solid #dee2e6;
            border-radius: 30px;
            padding: 6px 14px;
            font-size: 14px;
            background: #f8f9fa;
            transition: 0.2s ease;
            display: inline-flex;
            align-items: center;
        }
        .category-pill:hover,
        .category-pill.active {
            background-color: #0d6efd;
            color: white;
        }
        .category-pill i {
            margin-right: 6px;
        }
    </style>
</head>
<body class="bg-light">
<?php
$name = $_SESSION['name'] ?? 'User';
$initials = strtoupper(substr($name, 0, 1));
?>
<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
    <div class="container-fluid">
        <!-- Logo or App name -->
        <!--<a class="navbar-brand fw-bold" href="buyer_dashboard.php">-->
        <!--    <i class="bi bi-shop-window text-primary"></i> Mammy Coker-->
        <!--</a>-->
                <a class="navbar-brand" href="#"><img src="images/logonbg.png" style="width:70px; height:70px; border-radius:50%; margin:-5px;"/></a>


        <!-- Toggle for mobile -->
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent">
            <span class="navbar-toggler-icon"></span>
        </button>

        <!-- Navbar content -->
        <div class="collapse navbar-collapse" id="navbarSupportedContent">
            <div class="d-flex align-items-center gap-2 ms-auto flex-wrap">

                <!-- Back/Forward -->
                <button class="btn btn-outline-secondary btn-sm" onclick="history.back()" title="Go Back">
                    <i class="bi bi-arrow-left"></i>
                </button>
                <button class="btn btn-outline-secondary btn-sm" onclick="history.forward()" title="Go Forward">
                    <i class="bi bi-arrow-right"></i>
                </button>
                <a href="buyer_dashboard.php" class="btn btn-sm btn-outline-primary" title="Home">
                    <i class="bi bi-house-door"></i>
                </a>

                <!-- User dropdown -->
                <div class="dropdown">
                    <button class="btn btn-outline-dark rounded-circle fw-bold" type="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false" style="width: 38px; height: 38px;">
                        <?= $initials ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                        <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person-circle me-1"></i>Profile</a></li>
                        <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right me-1"></i>Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</nav>



<div class="container mt-4">
    <h3 class="mb-4">Explore Gigs</h3>

    <!-- Search -->
    <form method="GET" class="mb-3">
        <div class="input-group">
            <input type="text" name="search" class="form-control" placeholder="Search gigs..." value="<?php echo htmlspecialchars($search); ?>">
            <button class="btn btn-primary" type="submit"><i class="bi bi-search"></i></button>
        </div>
    </form>

    <!-- Category Filters -->
    <div class="d-flex flex-wrap gap-2 mb-4">
        <a href="explore_gigs.php<?php echo $search ? '?search=' . urlencode($search) : ''; ?>"
           class="category-pill <?php echo $currentCategory == '' ? 'active' : ''; ?>">
            <i class="bi bi-ui-checks-grid"></i> All
        </a>
        <?php foreach ($categories as $cat): ?>
            <?php
                $url = "explore_gigs.php?category=" . urlencode($cat);
                if (!empty($search)) $url .= "&search=" . urlencode($search);
                $icon = $categoryIcons[$cat] ?? $categoryIcons['default'];
            ?>
            <a href="<?php echo $url; ?>"
               class="category-pill <?php echo ($currentCategory === $cat) ? 'active' : ''; ?>">
                <i class="bi <?php echo $icon; ?>"></i> <?php echo htmlspecialchars($cat); ?>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- Gig Cards -->
    <div class="row">
        <?php if (empty($gigs)): ?>
            <div class="col-12">
                <div class="alert alert-warning text-center">No gigs found matching your criteria.</div>
            </div>
        <?php else: ?>
            <?php foreach ($gigs as $gig): ?>
                <?php $gigCats = array_map('trim', explode(',', $gig['category'])); ?>
                <div class="col-md-4 mb-4">
                    <div class="card gig-card shadow-sm">
                        <img src="<?= htmlspecialchars($gig['image_path']) ?>" class="card-img-top" alt="Gig Image">
                        <div class="card-body">
                            <h5 class="card-title"><?= htmlspecialchars($gig['title']) ?></h5>
                            <div class="mb-2 small text-muted">
                                <?php foreach ($gigCats as $c): ?>
                                    <span class="badge bg-light border text-dark me-1">
                                        <i class="bi <?= $categoryIcons[$c] ?? $categoryIcons['default']; ?>"></i>
                                        <?= htmlspecialchars($c) ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                            <p class="card-text fw-bold">NLe <?= number_format($gig['price'], 2) ?></p>
                            <a href="gig_details.php?id=<?= $gig['id'] ?>" class="btn btn-sm btn-outline-success w-100">View Gig</a>

                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
