<?php
/**
 * sellerdashboard.php (with search/filter)
 * - Seller landing page, New Orders alert, stats, recent gigs
 * - Orders table with filters (status, keyword, date range)
 * - Quick Deliver for pending orders
 * - Supports db.php exposing $pdo (PDO) or $conn (mysqli)
 */
session_start();
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'seller') {
  http_response_code(403);
  exit('Sellers only.');
}

$seller_id = (int)$_SESSION['user_id'];

// ---------- Helpers (PDO or MySQLi) ----------
function db_fetch_all_mixed($sql, $params = []) {
  // PDO
  if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
    $st = $GLOBALS['pdo']->prepare($sql);
    $st->execute($params);
    return $st->fetchAll(PDO::FETCH_ASSOC);
  }
  // MySQLi
  if (isset($GLOBALS['conn']) && $GLOBALS['conn'] instanceof mysqli) {
    $types = '';
    if (!empty($params)) {
      $types = implode('', array_map(fn($p)=> is_int($p) ? 'i' : (is_float($p) ? 'd' : 's'), $params));
    }
    $st = $GLOBALS['conn']->prepare($sql);
    if ($params) { $st->bind_param($types, ...$params); }
    $st->execute();
    $res = $st->get_result();
    return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
  }
  http_response_code(500); exit('Database not initialized.');
}

function statusBadge($s) {
  $map = ['pending'=>'secondary','in_progress'=>'info','completed'=>'success','cancelled'=>'danger'];
  $cls = $map[$s] ?? 'secondary';
  return '<span class="badge bg-'.$cls.'">'.htmlspecialchars($s).'</span>';
}

// ---------- Base (unfiltered) for stats/alert ----------
$all_orders = db_fetch_all_mixed("
  SELECT o.id AS order_id, o.status, o.created_at,
         g.title AS gig_title, g.price AS amount,
         b.id AS buyer_id, b.name AS buyer_name
  FROM orders o
  JOIN gigs g  ON g.id = o.gig_id
  JOIN users b ON b.id = o.buyer_id
  WHERE o.seller_id = ?
  ORDER BY o.created_at DESC
  LIMIT 200
", [$seller_id]);

$pending = array_filter($all_orders, fn($r)=>$r['status']==='pending');
$inprog  = array_filter($all_orders, fn($r)=>$r['status']==='in_progress');
$done    = array_filter($all_orders, fn($r)=>$r['status']==='completed');
$cancel  = array_filter($all_orders, fn($r)=>$r['status']==='cancelled');

$next_pending = null; foreach ($all_orders as $r) { if ($r['status']==='pending') { $next_pending = $r; break; } }

// Quick stats
$my_gigs = db_fetch_all_mixed("SELECT id, title, status FROM gigs WHERE seller_id = ? ORDER BY id DESC LIMIT 5", [$seller_id]);
$total_row = db_fetch_all_mixed("
  SELECT SUM(g.price) AS total_amount
  FROM orders o
  JOIN gigs g ON g.id = o.gig_id
  WHERE o.seller_id = ? AND o.status = 'completed'
", [$seller_id]);
$total_earned = (float)($total_row[0]['total_amount'] ?? 0.0);

// ---------- Filters for table ----------
$allowed_status = ['all','pending','in_progress','completed','cancelled'];
$status = $_GET['status'] ?? 'all';
if (!in_array($status, $allowed_status, true)) $status = 'all';

$q  = trim($_GET['q'] ?? '');            // keyword (gig title / buyer name)
$df = trim($_GET['date_from'] ?? '');    // yyyy-mm-dd
$dt = trim($_GET['date_to'] ?? '');      // yyyy-mm-dd

$where = ["o.seller_id = ?"];
$params = [$seller_id];

if ($status !== 'all') {
  $where[] = "o.status = ?";
  $params[] = $status;
}

if ($q !== '') {
  $where[] = "(g.title LIKE ? OR b.name LIKE ?)";
  $kw = "%{$q}%";
  $params[] = $kw; $params[] = $kw;
}

if ($df !== '') {
  $where[] = "DATE(o.created_at) >= ?";
  $params[] = $df;
}
if ($dt !== '') {
  $where[] = "DATE(o.created_at) <= ?";
  $params[] = $dt;
}

$sql_filtered = "
  SELECT o.id AS order_id, o.status, o.created_at,
         g.title AS gig_title, g.price AS amount,
         b.id AS buyer_id, b.name AS buyer_name
  FROM orders o
  JOIN gigs g  ON g.id = o.gig_id
  JOIN users b ON b.id = o.buyer_id
  WHERE ".implode(' AND ', $where)."
  ORDER BY o.created_at DESC
  LIMIT 200
";
$orders = db_fetch_all_mixed($sql_filtered, $params);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Seller Dashboard • Mammy Coker</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
  <style>
    .stat-card{border-radius:1rem}
    .table thead th{white-space:nowrap}
    .filter-card{border-radius:1rem}
  </style>
</head>
<body class="bg-light">
  <nav class="navbar navbar-light bg-white border-bottom sticky-top">
    <div class="container">
      <!--<a class="navbar-brand fw-bold" href="index.php">Mammy Coker</a>-->
          <a class="navbar-brand fw-bold" href="index.php"><img src="images/logonbg.png" style="width:70px; height:70px; border-radius:50%; margin:-5px;"/></a>

      <div class="d-flex gap-2">
        <a class="btn btn-sm btn-outline-secondary" href="my_gigs.php">My Gigs</a>
        <a class="btn btn-sm btn-primary" href="chat.php">Inbox</a>
      </div>
    </div>
  </nav>

  <main class="container py-4">
    <h1 class="h4 mb-3">Seller Dashboard</h1>

    <?php if (count($pending) > 0): ?>
      <div class="alert alert-primary d-flex justify-content-between align-items-center shadow-sm">
        <div><strong><?= count($pending) ?></strong> new order<?= count($pending)>1 ? 's' : '' ?> awaiting your delivery.</div>
        <div class="d-flex gap-2">
          <?php if ($next_pending): ?>
            <a class="btn btn-sm btn-success"
               href="seller_order_view.php?order_id=<?= (int)$next_pending['order_id'] ?>&quick=1">
               Quick Deliver next (#<?= (int)$next_pending['order_id'] ?>)
            </a>
          <?php endif; ?>
          <a class="btn btn-sm btn-outline-light border" href="#orders-table">View all</a>
        </div>
      </div>
    <?php endif; ?>

    <!-- Quick stats -->
    <div class="row g-3 mb-4">
      <div class="col-6 col-lg-3">
        <div class="card stat-card shadow-sm"><div class="card-body">
          <div class="small text-muted mb-1">Pending</div>
          <div class="h4 mb-0"><?= count($pending) ?></div>
        </div></div>
      </div>
      <div class="col-6 col-lg-3">
        <div class="card stat-card shadow-sm"><div class="card-body">
          <div class="small text-muted mb-1">In Progress</div>
          <div class="h4 mb-0"><?= count($inprog) ?></div>
        </div></div>
      </div>
      <div class="col-6 col-lg-3">
        <div class="card stat-card shadow-sm"><div class="card-body">
          <div class="small text-muted mb-1">Completed</div>
          <div class="h4 mb-0"><?= count($done) ?></div>
        </div></div>
      </div>
      <div class="col-6 col-lg-3">
        <div class="card stat-card shadow-sm"><div class="card-body">
          <div class="small text-muted mb-1">Total Earned</div>
          <div class="h4 mb-0">NLe <?= number_format($total_earned, 2) ?></div>
        </div></div>
      </div>
    </div>

    <!-- Filters -->
    <div class="card filter-card shadow-sm mb-3">
      <div class="card-body">
        <form class="row g-3 align-items-end" method="get" action="sellerdashboard.php">
          <div class="col-12 col-md-3">
            <label class="form-label">Status</label>
            <select name="status" class="form-select">
              <?php
                $opts = ['all'=>'All','pending'=>'Pending','in_progress'=>'In Progress','completed'=>'Completed','cancelled'=>'Cancelled'];
                foreach ($opts as $k=>$v) {
                  $sel = ($status===$k) ? 'selected' : '';
                  echo "<option value=\"{$k}\" {$sel}>{$v}</option>";
                }
              ?>
            </select>
          </div>
          <div class="col-12 col-md-3">
            <label class="form-label">Keyword</label>
            <input type="text" name="q" class="form-control" placeholder="Gig title or buyer name" value="<?= htmlspecialchars($q) ?>">
          </div>
          <div class="col-6 col-md-2">
            <label class="form-label">From</label>
            <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($df) ?>">
          </div>
          <div class="col-6 col-md-2">
            <label class="form-label">To</label>
            <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($dt) ?>">
          </div>
          <div class="col-12 col-md-2 d-grid">
            <button class="btn btn-primary">Apply</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Orders -->
    <section class="my-4">
      <div class="d-flex align-items-center justify-content-between mb-3">
        <h2 class="h5 mb-0">My Orders</h2>
        <div class="text-muted small">
          Showing <?= count($orders) ?> of <?= count($all_orders) ?> recent orders
        </div>
      </div>

      <div class="table-responsive shadow-sm rounded">
        <table id="orders-table" class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>Order #</th>
              <th>Gig</th>
              <th>Buyer</th>
              <th>Amount</th>
              <th>Created</th>
              <th>Status</th>
              <th class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$orders): ?>
              <tr><td colspan="7" class="text-muted text-center py-4">No matching orders.</td></tr>
            <?php else: foreach ($orders as $r): ?>
              <tr>
                <td>#<?= (int)$r['order_id'] ?></td>
                <td><?= htmlspecialchars($r['gig_title']) ?></td>
                <td><?= htmlspecialchars($r['buyer_name']) ?></td>
                <td>NLe <?= number_format((float)$r['amount'],2) ?></td>
                <td><span class="small text-muted"><?= htmlspecialchars($r['created_at']) ?></span></td>
                <td><?= statusBadge($r['status']) ?></td>
                <td class="text-end">
                  <a class="btn btn-sm btn-outline-primary"
                     href="seller_order_view.php?order_id=<?= (int)$r['order_id'] ?>">
                     Open
                  </a>
                  <?php if ($r['status']==='pending'): ?>
                    <a class="btn btn-sm btn-success"
                       href="seller_order_view.php?order_id=<?= (int)$r['order_id'] ?>&quick=1">
                       Quick Deliver
                    </a>
                  <?php endif; ?>
                  <a class="btn btn-sm btn-outline-secondary"
                     href="message.php?user_id=<?= (int)$r['buyer_id'] ?>">
                     Message
                  </a>
                </td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </section>

    <!-- My Gigs -->
    <section class="my-5">
      <div class="d-flex align-items-center justify-content-between mb-2">
        <h2 class="h6 mb-0">My Recent Gigs</h2>
        <a class="btn btn-sm btn-outline-secondary" href="my_gigs.php">Manage Gigs</a>
      </div>
      <?php if (!$my_gigs): ?>
        <p class="text-muted">You haven’t created any gigs yet.</p>
      <?php else: ?>
        <div class="list-group shadow-sm">
          <?php foreach ($my_gigs as $g): ?>
          <div class="list-group-item d-flex justify-content-between align-items-center">
            <div>
              <div class="fw-semibold"><?= htmlspecialchars($g['title']) ?></div>
              <div class="small text-muted">Status: <?= htmlspecialchars($g['status']) ?></div>
            </div>
            <a class="btn btn-sm btn-outline-primary" href="gig_details.php?id=<?= (int)$g['id'] ?>">View</a>
          </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>
  </main>

  <script>
    (function(){
      if (location.hash === '#orders-table') {
        const el = document.getElementById('orders-table');
        if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    })();
  </script>
</body>
</html>
