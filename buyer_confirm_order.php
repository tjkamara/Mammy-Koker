<?php
// buyer_confirm_order.php
session_start();
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'buyer') {
  http_response_code(403); exit('Buyers only.');
}
$buyer_id = (int)$_SESSION['user_id'];

// CSRF token (for GET render and POST submit)
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token'];

/**
 * ------------------------------------------------------
 * POST: Create order + payment session, then redirect to checkout
 * ------------------------------------------------------
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
    http_response_code(400); exit('Bad token');
  }

  $gig_id   = (int)($_POST['gig_id'] ?? 0);
  $qty_raw  = (int)($_POST['quantity'] ?? 1);
  $qty      = max(1, min(10, $qty_raw)); // clamp 1..10
  // We won't persist buyer_notes here to avoid schema mismatch; feel free to store it if your schema has a column.
  // $buyer_notes = trim((string)($_POST['buyer_notes'] ?? ''));

  if ($gig_id <= 0) { http_response_code(400); exit('Invalid gig'); }

  // Load gig
  $sqlGig = "SELECT g.id, g.title, g.price, g.seller_id FROM gigs g WHERE g.id=? AND g.status='active' LIMIT 1";
  if (isset($pdo) && $pdo instanceof PDO) {
    $st = $pdo->prepare($sqlGig); $st->execute([$gig_id]); $gig = $st->fetch(PDO::FETCH_ASSOC);
  } elseif (isset($conn) && $conn instanceof mysqli) {
    $st = $conn->prepare($sqlGig); $st->bind_param("i", $gig_id); $st->execute();
    $res = $st->get_result(); $gig = $res ? $res->fetch_assoc() : null;
  } else {
    http_response_code(500); exit('Database not initialized.');
  }

  if (!$gig) { http_response_code(404); exit('Gig not found or inactive.'); }
  if ((int)$gig['seller_id'] === $buyer_id) { http_response_code(400); exit('You cannot order your own gig.'); }

  $seller_id = (int)$gig['seller_id'];
  $unit      = (float)$gig['price'];
  $amount    = $unit * $qty; // total to pay

  try {
    if (isset($pdo) && $pdo instanceof PDO) {
      $pdo->beginTransaction();

      // 1) Create order as 'pending' (awaiting payment)
      $o = $pdo->prepare("INSERT INTO orders (buyer_id, seller_id, gig_id, status) VALUES (?,?,?,'pending')");
      $o->execute([$buyer_id, $seller_id, $gig_id]);
      $order_id = (int)$pdo->lastInsertId();

      // 2) Create payment session (simulated checkout)
      $reference = 'MC_' . bin2hex(random_bytes(6));
      $ps = $pdo->prepare("INSERT INTO payment_sessions (order_id, provider, amount, reference, status) VALUES (?,?,?,?, 'created')");
      $ps->execute([$order_id, 'monime', $amount, $reference]);

      $pdo->commit();

    } elseif (isset($conn) && $conn instanceof mysqli) {
      $conn->begin_transaction();

      $o = $conn->prepare("INSERT INTO orders (buyer_id, seller_id, gig_id, status) VALUES (?,?,?,'pending')");
      $o->bind_param("iii", $buyer_id, $seller_id, $gig_id);
      $o->execute();
      $order_id = (int)$conn->insert_id;

      $reference = 'MC_' . bin2hex(random_bytes(6));
      $ps = $conn->prepare("INSERT INTO payment_sessions (order_id, provider, amount, reference, status) VALUES (?,?,?,?, 'created')");
      $provider = 'monime';
      $ps->bind_param("isds", $order_id, $provider, $amount, $reference);
      $ps->execute();

      $conn->commit();
    } else {
      http_response_code(500); exit('Database not initialized.');
    }

    // 3) Send buyer to checkout
    header("Location: checkout.php?ref={$reference}");
    exit;

  } catch (Throwable $e) {
    if (isset($pdo)) $pdo->rollBack(); else if (isset($conn)) $conn->rollback();
    error_log("buyer_confirm_order create+session error: " . $e->getMessage());
    http_response_code(500); exit('Order creation failed');
  }
}

/**
 * ------------------------------------------------------
 * GET: Render confirmation page
 * ------------------------------------------------------
 */
$gig_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($gig_id <= 0) { http_response_code(400); exit('Invalid gig.'); }

$sql = "SELECT g.id, g.title, g.description, g.price, g.delivery_time, g.revisions, g.status,
               COALESCE(g.image_path, g.image, 'uploads/gigs/default.jpg') AS image_path,
               u.id AS seller_id, u.name AS seller_name
        FROM gigs g
        JOIN users u ON u.id = g.seller_id
        WHERE g.id = ? AND g.status = 'active'
        LIMIT 1";

$gig = null;
if (isset($pdo) && $pdo instanceof PDO) {
  $st = $pdo->prepare($sql); $st->execute([$gig_id]); $gig = $st->fetch(PDO::FETCH_ASSOC);
} elseif (isset($conn) && $conn instanceof mysqli) {
  $st = $conn->prepare($sql); $st->bind_param("i", $gig_id); $st->execute();
  $res = $st->get_result(); $gig = $res ? $res->fetch_assoc() : null;
} else {
  http_response_code(500); exit('Database not initialized.');
}

if (!$gig) { http_response_code(404); exit('Gig not found or inactive.'); }
if ((int)$gig['seller_id'] === $buyer_id) { http_response_code(400); exit('You cannot order your own gig.'); }

$unit = (float)$gig['price'];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Confirm Order — <?= htmlspecialchars($gig['title']) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
  <style>
    .gig-img{width:100%;height:260px;object-fit:cover;border-radius:1rem}
    .card{border-radius:1rem}
  </style>
</head>
<body class="bg-light">
<div class="container py-4">
  <a href="gig_details.php?id=<?= (int)$gig['id'] ?>" class="btn btn-link">&larr; Back to Gig</a>

  <div class="row g-4">
    <div class="col-lg-7">
      <div class="card shadow-sm">
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-5">
              <img class="gig-img shadow-sm" src="<?= htmlspecialchars($gig['image_path']) ?>" alt="">
            </div>
            <div class="col-md-7">
              <h1 class="h5 mb-1"><?= htmlspecialchars($gig['title']) ?></h1>
              <div class="text-muted mb-2">by <?= htmlspecialchars($gig['seller_name']) ?></div>
              <p class="small"><?= nl2br(htmlspecialchars($gig['description'])) ?></p>

              <div class="d-flex flex-wrap gap-4">
                <div><small class="text-muted d-block">Price</small><strong>NLe <?= number_format($unit,2) ?></strong></div>
                <div><small class="text-muted d-block">Delivery</small><strong><?= (int)$gig['delivery_time'] ?> day(s)</strong></div>
                <div><small class="text-muted d-block">Revisions</small><strong><?= (int)$gig['revisions'] ?></strong></div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- NOTE: action now posts to THIS SAME FILE -->
      <form class="card shadow-sm mt-4" method="post" action="buyer_confirm_order.php?id=<?= (int)$gig['id'] ?>">
        <div class="card-body">
          <h2 class="h6 mb-3">Order Details</h2>
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
          <input type="hidden" name="gig_id" value="<?= (int)$gig['id'] ?>">

          <div class="row g-3 align-items-center">
            <div class="col-12 col-sm-4">
              <label class="form-label">Quantity</label>
              <input type="number" class="form-control" name="quantity" id="qty" value="1" min="1" max="10" required>
              <div class="form-text">Max 10 per order.</div>
            </div>
            <div class="col-12 col-sm-8">
              <label class="form-label">Your requirements / brief to seller (optional)</label>
              <textarea class="form-control" name="buyer_notes" rows="4" placeholder="Describe what you need, links, files to expect, etc."></textarea>
            </div>
          </div>

          <hr class="my-4">

          <div class="d-flex justify-content-between align-items-center">
            <div class="text-muted">Unit Price: <strong>NLe <?= number_format($unit,2) ?></strong></div>
            <div class="fs-5">Total: <strong id="total">NLe <?= number_format($unit,2) ?></strong></div>
          </div>

          <div class="d-flex justify-content-end mt-3">
            <button class="btn btn-success">Confirm &amp; Continue to Checkout</button>
          </div>
        </div>
      </form>
    </div>

    <div class="col-lg-5">
      <div class="card shadow-sm">
        <div class="card-body">
          <h2 class="h6 mb-2">What happens next?</h2>
          <ol class="small mb-0">
            <li>We’ll create your order and take you to a simulated checkout.</li>
            <li>On successful payment, escrow is held and the order moves to <strong>In Progress</strong>.</li>
            <li>The seller delivers; you <strong>Accept</strong> to release escrow (or open a dispute).</li>
          </ol>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
(function(){
  const unit = <?= json_encode($unit) ?>;
  const qty  = document.getElementById('qty');
  const out  = document.getElementById('total');
  function fmt(n){ return 'NLe ' + Number(n).toLocaleString(undefined,{minimumFractionDigits:2, maximumFractionDigits:2}); }
  function recalc(){ const q = Math.max(1, Math.min(10, parseInt(qty.value||'1',10))); out.textContent = fmt(unit*q); }
  qty.addEventListener('input', recalc);
  recalc();
})();
</script>
</body>
</html>
