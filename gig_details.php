<?php
// gig_details.php — safe for direct access AND inclusion
session_start();
require_once __DIR__ . '/db.php'; // your real DB connection

// --- Helper: fetch one gig using PDO or MySQLi ---
function db_select_one_gig($gig_id) {
  $sql = "SELECT g.id, g.title, g.description, g.price, g.category,
                 g.delivery_time, g.revisions, g.status,
                 COALESCE(g.image_path, g.image, 'uploads/gigs/default.jpg') AS image_path,
                 u.id AS seller_id, u.name AS seller_name
          FROM gigs g
          JOIN users u ON u.id = g.seller_id
          WHERE g.id = ? AND g.status = 'active'
          LIMIT 1";

  if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
    $stmt = $GLOBALS['pdo']->prepare($sql);
    $stmt->execute([$gig_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
  }

  if (isset($GLOBALS['conn']) && $GLOBALS['conn'] instanceof mysqli) {
    $stmt = $GLOBALS['conn']->prepare($sql);
    $stmt->bind_param("i", $gig_id);
    $stmt->execute();
    $res = $stmt->get_result();
    return $res ? $res->fetch_assoc() : null;
  }

  // No DB handle defined
  http_response_code(500);
  exit("Database not initialized.");
}

// --- CSRF (kept for consistency; not required for GET links) ---
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token'];

// --- Determine if this script is accessed directly or included ---
$is_direct = realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'] ?? __FILE__);

// --- Read & validate gig id ---
$gig_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($gig_id <= 0) {
  if ($is_direct) {
    http_response_code(400);
    exit('Invalid gig.');
  } else {
    // If included without id, do nothing and let the parent continue
    return;
  }
}

// --- Fetch gig ---
$gig = db_select_one_gig($gig_id);
if (!$gig) {
  if ($is_direct) {
    http_response_code(404);
    exit('Gig not found or inactive.');
  } else {
    return;
  }
}

// --- Viewer context ---
$user_id  = $_SESSION['user_id'] ?? null;
$role     = $_SESSION['role'] ?? null;
$is_owner = $user_id && ((int)$gig['seller_id'] === (int)$user_id);
$is_buyer = $user_id && ($role === 'buyer');

// Preserve an optional source marker for nicer return paths (e.g., from=dashboard)
$from = isset($_GET['from']) ? preg_replace('~[^a-z_]~i', '', $_GET['from']) : '';
$order_href = 'buyer_confirm_order.php?id=' . (int)$gig['id'] . ($from ? '&from='.$from : '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title><?= htmlspecialchars($gig['title']) ?> – Gig Details</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
  <style>
    .gig-img{width:100%;height:320px;object-fit:cover;border-radius:1rem}
  </style>
</head>
<body class="bg-light">
<div class="container py-4">

  <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <a href="explore_gigs.php" class="btn btn-link p-0">&larr; Back to Explore</a>
    <?php if ($is_buyer): ?>
      <span class="text-muted">·</span>
      <a href="buyer_dashboard.php" class="btn btn-link p-0">Back to Buyer Dashboard</a>
    <?php endif; ?>
  </div>

  <div class="row g-4">
    <div class="col-lg-6">
      <img src="<?= htmlspecialchars($gig['image_path'] ?? 'uploads/gigs/default.jpg') ?>" alt="" class="gig-img shadow-sm">
    </div>

    <div class="col-lg-6">
      <h1 class="h3 mb-2"><?= htmlspecialchars($gig['title']) ?></h1>
      <div class="text-muted mb-3">by <?= htmlspecialchars($gig['seller_name']) ?></div>
      <p class="mb-3"><?= nl2br(htmlspecialchars($gig['description'])) ?></p>

      <div class="d-flex gap-4 mb-3">
        <div>
          <small class="text-muted d-block">Price</small>
          <strong>NLe <?= number_format((float)$gig['price'], 2) ?></strong>
        </div>
        <div>
          <small class="text-muted d-block">Delivery</small>
          <strong><?= (int)$gig['delivery_time'] ?> day(s)</strong>
        </div>
        <div>
          <small class="text-muted d-block">Revisions</small>
          <strong><?= (int)$gig['revisions'] ?></strong>
        </div>
      </div>

      <?php if (!$user_id): ?>
        <a href="auth.php" class="btn btn-primary">Log in to Order</a>

      <?php elseif ($is_owner): ?>
        <button class="btn btn-secondary" disabled>You can’t order your own gig</button>

      <?php elseif ($is_buyer): ?>
        <!-- Route buyers to the confirmation step (quantity + requirements) -->
        <a class="btn btn-success" href="<?= htmlspecialchars($order_href) ?>">Order Now</a>

      <?php else: ?>
        <div class="alert alert-info">
          You’re logged in as <strong><?= htmlspecialchars($role ?? 'user') ?></strong>.
          Switch to a buyer account to order.
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
</body>
</html>
