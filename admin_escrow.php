<?php
/**
 * admin_escrow.php
 * - Admin console for reviewing escrow rows and taking actions (Release / Refund)
 * - Uses admin_gate.php for strict role check + CSRF helpers
 * - Works with db.php exposing either $pdo (PDO) or $conn (MySQLi)
 */

require_once __DIR__ . '/admin_gate.php'; // includes session, role check, csrf helpers, and db.php

// ---------- Helpers ----------
function fetchAllMixed($sql, $params = []) {
  if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
    $st = $GLOBALS['pdo']->prepare($sql);
    $st->execute($params);
    return $st->fetchAll(PDO::FETCH_ASSOC);
  }
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

function badge($text, $map) {
  $cls = $map[$text] ?? 'secondary';
  return '<span class="badge bg-'.$cls.'">'.htmlspecialchars($text).'</span>';
}

// ---------- Optional filters (escrow status, keyword) ----------
$allowed_e = ['all','held','released','refunded'];
$e_status  = $_GET['e'] ?? 'all';
if (!in_array($e_status, $allowed_e, true)) $e_status = 'all';

$q = trim($_GET['q'] ?? ''); // keyword on gig title or user names

$where = [];
$params = [];

if ($e_status !== 'all') {
  $where[] = "e.status = ?";
  $params[] = $e_status;
}
if ($q !== '') {
  $where[] = "(g.title LIKE ? OR ub.name LIKE ? OR us.name LIKE ?)";
  $kw = "%{$q}%";
  $params[] = $kw; $params[] = $kw; $params[] = $kw;
}

$sql = "
  SELECT
    e.id AS escrow_id, e.order_id, e.amount, e.status AS escrow_status, e.created_at AS escrow_created,
    o.status AS order_status, o.buyer_id, o.seller_id,
    g.title AS gig_title,
    ub.name AS buyer_name, us.name AS seller_name
  FROM escrow_transactions e
  JOIN orders o  ON o.id = e.order_id
  JOIN gigs   g  ON g.id = o.gig_id
  JOIN users  ub ON ub.id = o.buyer_id
  JOIN users  us ON us.id = o.seller_id
";
if ($where) {
  $sql .= " WHERE " . implode(' AND ', $where);
}
$sql .= " ORDER BY e.created_at DESC LIMIT 300";

$rows = fetchAllMixed($sql, $params);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Admin • Escrow Console</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
  <style>
    .filter-card{border-radius:1rem}
    .table thead th{white-space:nowrap}
  </style>
</head>
<body class="bg-light">
  <nav class="navbar navbar-light bg-white border-bottom sticky-top">
    <div class="container">
    <a class="navbar-brand fw-bold" href="index.php"><img src="images/logonbg.png" style="width:70px; height:70px; border-radius:50%; margin:-5px;"/></a>
      <div class="d-flex gap-2">
        <a class="btn btn-sm btn-outline-secondary" href="sellerdashboard.php">Seller View</a>
        <a class="btn btn-sm btn-outline-secondary" href="buyer_dashboard.php">Buyer View</a>
        <span class="badge bg-dark">Admin</span>
      </div>
    </div>
  </nav>

  <main class="container py-4">
    <div class="d-flex align-items-center justify-content-between mb-3">
      <h1 class="h4 mb-0">Escrow Console</h1>
      <div class="text-muted small">Rows: <?= count($rows) ?></div>
    </div>

    <!-- Filters -->
    <div class="card filter-card shadow-sm mb-3">
      <div class="card-body">
        <form class="row g-3 align-items-end" method="get" action="admin_escrow.php">
          <div class="col-12 col-md-3">
            <label class="form-label">Escrow Status</label>
            <select name="e" class="form-select">
              <?php
                $opts = ['all'=>'All','held'=>'Held','released'=>'Released','refunded'=>'Refunded'];
                foreach ($opts as $k=>$v) {
                  $sel = ($e_status===$k) ? 'selected' : '';
                  echo "<option value=\"{$k}\" {$sel}>{$v}</option>";
                }
              ?>
            </select>
          </div>
          <div class="col-12 col-md-6">
            <label class="form-label">Keyword</label>
            <input type="text" name="q" class="form-control" placeholder="Gig title, buyer or seller name" value="<?= htmlspecialchars($q) ?>">
          </div>
          <div class="col-12 col-md-3 d-grid">
            <button class="btn btn-primary">Apply</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Escrow table -->
    <div class="table-responsive shadow-sm rounded">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>Escrow #</th>
            <th>Order #</th>
            <th>Gig</th>
            <th>Buyer</th>
            <th>Seller</th>
            <th>Amount</th>
            <th>Escrow</th>
            <th>Order</th>
            <th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$rows): ?>
            <tr><td colspan="9" class="text-center text-muted py-4">No escrow records.</td></tr>
          <?php else: foreach ($rows as $r): ?>
            <tr>
              <td><?= (int)$r['escrow_id'] ?></td>
              <td>#<?= (int)$r['order_id'] ?></td>
              <td class="small"><?= htmlspecialchars($r['gig_title']) ?></td>
              <td><?= htmlspecialchars($r['buyer_name']) ?> (#<?= (int)$r['buyer_id'] ?>)</td>
              <td><?= htmlspecialchars($r['seller_name']) ?> (#<?= (int)$r['seller_id'] ?>)</td>
              <td>NLe <?= number_format((float)$r['amount'], 2) ?></td>
              <td>
                <?= badge($r['escrow_status'], ['held'=>'warning','released'=>'success','refunded'=>'danger']) ?>
                <div class="small text-muted"><?= htmlspecialchars($r['escrow_created']) ?></div>
              </td>
              <td><?= badge($r['order_status'], ['pending'=>'secondary','in_progress'=>'info','completed'=>'success','cancelled'=>'danger']) ?></td>
              <td class="text-end">
                <form class="d-inline" method="post" action="admin_release_escrow.php"
                      onsubmit="return confirm('Release funds to the seller?');">
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                  <input type="hidden" name="escrow_id" value="<?= (int)$r['escrow_id'] ?>">
                  <button class="btn btn-sm btn-success" <?= $r['escrow_status']!=='held' ? 'disabled' : '' ?>>Release</button>
                </form>

                <form class="d-inline" method="post" action="admin_refund_escrow.php"
                      onsubmit="return confirm('Refund funds to the buyer?');">
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                  <input type="hidden" name="escrow_id" value="<?= (int)$r['escrow_id'] ?>">
                  <button class="btn btn-sm btn-danger" <?= $r['escrow_status']!=='held' ? 'disabled' : '' ?>>Refund</button>
                </form>

                <a class="btn btn-sm btn-outline-secondary"
                   href="buyer_order_view.php?order_id=<?= (int)$r['order_id'] ?>">
                   Open
                </a>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </main>
</body>
</html>
