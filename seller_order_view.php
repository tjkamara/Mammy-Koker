<?php
session_start();
require_once __DIR__ . '/db.php';
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'seller') { http_response_code(403); exit('Sellers only.'); }

$order_id = (int)($_GET['order_id'] ?? 0);
if ($order_id <= 0) { http_response_code(400); exit('Invalid order'); }

// Fetch order (ensure this seller owns it)
$sql = "SELECT o.id, o.status, o.buyer_id, o.seller_id, o.gig_id, g.title, g.price
        FROM orders o
        JOIN gigs g ON g.id = o.gig_id
        WHERE o.id = ? AND o.seller_id = ?
        LIMIT 1";

$order = null;
if (isset($pdo)) { $st=$pdo->prepare($sql); $st->execute([$order_id, $_SESSION['user_id']]); $order=$st->fetch(PDO::FETCH_ASSOC); }
elseif (isset($conn)) { $st=$conn->prepare($sql); $st->bind_param("ii",$order_id,$_SESSION['user_id']); $st->execute(); $order=$st->get_result()->fetch_assoc(); }
else { http_response_code(500); exit('DB not ready'); }

if (!$order) { http_response_code(404); exit('Order not found'); }

// CSRF
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token']=bin2hex(random_bytes(32));
$csrf=$_SESSION['csrf_token'];
?>
<!doctype html>
<html><head>
  <meta charset="utf-8"><title>Order #<?= (int)$order['id'] ?> • Deliver</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
</head>
<body class="bg-light">
<div class="container py-4">
  <a class="btn btn-link" href="seller_dashboard.php">&larr; Back</a>
  <h1 class="h4 mb-3">Deliver Order #<?= (int)$order['id'] ?> — “<?= htmlspecialchars($order['title']) ?>”</h1>
  <p class="text-muted mb-4">Status: <span class="badge bg-info text-dark"><?= htmlspecialchars($order['status']) ?></span></p>

  <div class="card shadow-sm">
    <div class="card-body">
      <form method="post" action="deliver_order.php" enctype="multipart/form-data" class="row g-3">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
        <input type="hidden" name="order_id" value="<?= (int)$order['id'] ?>">

        <div class="col-12">
          <label class="form-label">Delivery message to buyer</label>
          <textarea class="form-control" name="delivery_notes" rows="4" placeholder="Summary, instructions, links..."></textarea>
        </div>
        <div class="col-12">
          <label class="form-label">Attach file (optional)</label>
          <input class="form-control" type="file" name="delivery_file">
          <div class="form-text">You can also paste a link in the message above.</div>
        </div>
        <div class="col-12">
          <button class="btn btn-success">Submit Delivery</button>
        </div>
      </form>
    </div>
  </div>
</div>
</body></html>
