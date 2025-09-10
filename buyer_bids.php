<?php
// buyer_bids.php
session_start();
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'buyer') {
  header("Location: auth.php");
  exit();
}
$buyer_id = (int)$_SESSION['user_id'];
$name     = $_SESSION['name'] ?? 'Buyer';

// Filters (optional)
$status = $_GET['status'] ?? ''; // '', 'pending', 'in_progress', 'completed', 'cancelled'

// Build query: orders joined to gigs, sellers, escrow
// orders: id, buyer_id, seller_id, gig_id, status, created_at
// escrow_transactions: order_id, amount, status
// gigs: id, title
// users: id, name (seller)
$sql = "
  SELECT
    o.id            AS order_id,
    o.status        AS order_status,
    o.created_at    AS ordered_at,
    g.title         AS gig_title,
    u.name          AS seller_name,
    e.amount        AS escrow_amount,
    e.status        AS escrow_status
  FROM orders o
  JOIN gigs g              ON g.id = o.gig_id
  JOIN users u             ON u.id = o.seller_id
  LEFT JOIN escrow_transactions e ON e.order_id = o.id
  WHERE o.buyer_id = ?
";

$types = "i";
$params = [$buyer_id];

if ($status !== '') {
  $sql .= " AND o.status = ? ";
  $types .= "s";
  $params[] = $status;
}

$sql .= " ORDER BY o.id DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$res = $stmt->get_result();
$rows = [];
while ($r = $res->fetch_assoc()) { $rows[] = $r; }
$stmt->close();

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
  $color = $map[strtolower($text)] ?? 'secondary';
  return '<span class="badge bg-'.$color.'">'.htmlspecialchars($text).'</span>';
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>My Bids / Orders — Mammy Coker</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    .card { border-radius: 1rem; }
    .table thead th { white-space: nowrap; }
  </style>
</head>
<body class="bg-light">
<nav class="navbar navbar-light bg-white shadow-sm">
  <div class="container">
    <a class="navbar-brand fw-bold" href="buyer_dashboard.php">
      <img src="images/logonbg.png" style="width:48px;height:48px;border-radius:50%;margin:-4px;" />
      <span class="ms-2">Mammy Coker</span>
    </a>
    <div class="d-flex gap-2">
      <a class="btn btn-outline-secondary btn-sm" href="explore_gigs.php"><i class="bi bi-search"></i> Explore</a>
      <a class="btn btn-outline-primary btn-sm" href="buyer_dashboard.php"><i class="bi bi-house-door"></i> Dashboard</a>
    </div>
  </div>
</nav>

<div class="container py-4">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h3 class="mb-0">My Bids / Orders</h3>
    <form class="d-flex gap-2" method="get">
      <select class="form-select form-select-sm" name="status" onchange="this.form.submit()">
        <?php
          $opts = ['' => 'All statuses', 'pending'=>'Pending', 'in_progress'=>'In progress', 'completed'=>'Completed', 'cancelled'=>'Cancelled'];
          foreach ($opts as $k=>$label) {
            $sel = ($k===$status)?'selected':'';
            echo "<option value=\"".htmlspecialchars($k)."\" $sel>".htmlspecialchars($label)."</option>";
          }
        ?>
      </select>
      <noscript><button class="btn btn-sm btn-primary">Filter</button></noscript>
    </form>
  </div>

  <div class="card shadow-sm">
    <div class="table-responsive">
      <table class="table align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>#</th>
            <th>Gig</th>
            <th>Seller</th>
            <th>Order Status</th>
            <th>Escrow</th>
            <th>Amount (NLe)</th>
            <th>Ordered</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$rows): ?>
            <tr><td colspan="8" class="text-center text-muted py-4">No orders yet. Explore gigs and place your first bid.</td></tr>
          <?php else: foreach ($rows as $r): ?>
            <tr>
              <td><?= (int)$r['order_id'] ?></td>
              <td class="fw-semibold"><?= htmlspecialchars($r['gig_title']) ?></td>
              <td><?= htmlspecialchars($r['seller_name']) ?></td>
              <td><?= badge($r['order_status']) ?></td>
              <td><?= $r['escrow_status'] ? badge($r['escrow_status']) : '<span class="badge bg-secondary">n/a</span>' ?></td>
              <td><?= number_format((float)($r['escrow_amount'] ?? 0), 2) ?></td>
              <td><small class="text-muted"><?= htmlspecialchars($r['ordered_at']) ?></small></td>
              <td class="text-end">
                <a class="btn btn-sm btn-outline-primary" href="buyer_order_view.php?order_id=<?= (int)$r['order_id'] ?>">
                  View
                </a>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="mt-3">
    <a class="btn btn-link" href="explore_gigs.php">&larr; Back to Explore Gigs</a>
  </div>
</div>
</body>
</html>
