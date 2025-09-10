<?php
// buyer_dashboard.php — clean buyer-only dashboard (no gig dependency)
ini_set('display_errors',1);
ini_set('display_startup_errors',1);
error_reporting(E_ALL);

session_start();
require_once __DIR__ . '/db.php';

// Must be logged in as buyer
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'buyer') {
  header("Location: auth.php");
  exit;
}

$buyer_id   = (int)$_SESSION['user_id'];
$buyer_name = $_SESSION['name'] ?? 'Buyer';

// Dashboards should ignore stray query params that some pages might carry
unset($_GET['id']);

// Flash message from order.php (optional)
$flash      = $_SESSION['flash_success'] ?? '';
$last_order = $_SESSION['last_order_id'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['last_order_id']);

// Fetch recent orders (supports PDO or MySQLi)
$recent = [];
$sql_recent = "
  SELECT o.id AS order_id,
         o.status AS order_status,
         o.created_at AS ordered_at,
         g.title AS gig_title,
         e.status AS escrow_status,
         e.amount AS escrow_amount
  FROM orders o
  JOIN gigs g ON g.id = o.gig_id
  LEFT JOIN escrow_transactions e ON e.order_id = o.id
  WHERE o.buyer_id = ?
  ORDER BY o.id DESC
  LIMIT 10
";

if (isset($pdo) && $pdo instanceof PDO) {
  $st = $pdo->prepare($sql_recent);
  $st->execute([$buyer_id]);
  $recent = $st->fetchAll(PDO::FETCH_ASSOC);
} elseif (isset($conn) && $conn instanceof mysqli) {
  $st = $conn->prepare($sql_recent);
  $st->bind_param("i", $buyer_id);
  $st->execute();
  $rs = $st->get_result();
  while ($row = $rs->fetch_assoc()) $recent[] = $row;
  $st->close();
} else {
  http_response_code(500);
  exit('Database not initialized.');
}

function badge($text) {
  $map = [
    'pending'     => 'warning',
    'in_progress' => 'info',
    'completed'   => 'success',
    'cancelled'   => 'secondary',
    'held'        => 'warning',
    'released'    => 'success',
    'refunded'    => 'danger',
  ];
  $color = $map[strtolower((string)$text)] ?? 'secondary';
  return '<span class="badge bg-'.$color.'">'.htmlspecialchars((string)$text).'</span>';
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Buyer Dashboard — Mammy Coker</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    .card{border-radius:1rem}
    .table thead th{white-space:nowrap}
  </style>
</head>
<body class="bg-light">
<nav class="navbar navbar-light bg-white shadow-sm">
  <div class="container">
    <a class="navbar-brand fw-bold" href="index.php"><img src="images/logonbg.png" style="width:70px; height:70px; border-radius:50%; margin:-5px;"/></a>
    <div class="d-flex gap-2">
      <a class="btn btn-sm btn-outline-secondary" href="explore_gigs.php">Explore Gigs</a>
      <a class="btn btn-sm btn-outline-secondary" href="buyer_bids.php">My Bids / Orders</a>
      <a class="btn btn-sm btn-outline-secondary" href="logout.php">Logout</a>
    </div>
  </div>
</nav>

<div class="container py-4">
  <h1 class="h4 mb-3">Welcome, <?= htmlspecialchars($buyer_name) ?></h1>

  <?php if ($flash): ?>
    <div class="alert alert-success d-flex justify-content-between align-items-center">
      <div><?= htmlspecialchars($flash) ?></div>
      <?php if ($last_order): ?>
        <a class="btn btn-sm btn-outline-light border"
           href="buyer_order_view.php?order_id=<?= (int)$last_order ?>">
          View order #<?= (int)$last_order ?>
        </a>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <div class="row g-3">
    <div class="col-md-8">
      <div class="card shadow-sm">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <h6 class="mb-0">Recent Orders</h6>
            <a href="buyer_bids.php" class="btn btn-sm btn-outline-primary">See all</a>
          </div>

          <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th>#</th>
                  <th>Gig</th>
                  <th>Order</th>
                  <th>Escrow</th>
                  <th>Amount</th>
                  <th>Created</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <?php if (!$recent): ?>
                  <tr><td colspan="7" class="text-muted text-center">No orders yet — explore gigs to get started.</td></tr>
                <?php else: foreach ($recent as $r): ?>
                  <tr>
                    <td><?= (int)$r['order_id'] ?></td>
                    <td class="text-truncate" style="max-width:260px"><?= htmlspecialchars($r['gig_title']) ?></td>
                    <td><?= badge($r['order_status']) ?></td>
                    <td><?= $r['escrow_status'] ? badge($r['escrow_status']) : '<span class="badge bg-secondary">n/a</span>' ?></td>
                    <td>NLe <?= number_format((float)($r['escrow_amount'] ?? 0), 2) ?></td>
                    <td><small class="text-muted"><?= htmlspecialchars($r['ordered_at']) ?></small></td>
                    <td class="text-end">
                      <a class="btn btn-sm btn-outline-primary" href="buyer_order_view.php?order_id=<?= (int)$r['order_id'] ?>">Open</a>
                    </td>
                  </tr>
                <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>

        </div>
      </div>
    </div>

    <div class="col-md-4">
      <div class="card shadow-sm mb-3">
        <div class="card-body text-center">
          <h6 class="mb-2">Find Your Next Gig</h6>
          <p class="text-muted small">Browse categories and place orders with escrow protection.</p>
          <a class="btn btn-success" href="explore_gigs.php">Explore Gigs</a>
        </div>
      </div>

      <div class="card shadow-sm">
        <div class="card-body">
          <h6 class="mb-2">Shortcuts</h6>
          <div class="d-grid gap-2">
            <a class="btn btn-outline-secondary btn-sm" href="buyer_bids.php">My Bids / Orders</a>
            <a class="btn btn-outline-secondary btn-sm" href="messages.php">Messages</a>
            <a class="btn btn-outline-secondary btn-sm" href="profile.php">Profile</a>
          </div>
        </div>
      </div>
    </div>

  </div>
</div>
</body>
</html>
