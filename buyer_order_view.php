<?php
session_start();
require_once __DIR__ . '/db.php';
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'buyer') { http_response_code(403); exit('Buyers only.'); }

$order_id = (int)($_GET['order_id'] ?? 0);
if ($order_id <= 0) { http_response_code(400); exit('Invalid order'); }

$sql = "SELECT o.id, o.status, o.buyer_id, o.seller_id, o.gig_id, g.title, g.price
        FROM orders o
        JOIN gigs g ON g.id = o.gig_id
        WHERE o.id = ? AND o.buyer_id = ?
        LIMIT 1";

$order = null;
if (isset($pdo)) { $st=$pdo->prepare($sql); $st->execute([$order_id,$_SESSION['user_id']]); $order=$st->fetch(PDO::FETCH_ASSOC); }
elseif (isset($conn)) { $st=$conn->prepare($sql); $st->bind_param("ii",$order_id,$_SESSION['user_id']); $st->execute(); $order=$st->get_result()->fetch_assoc(); }
else { http_response_code(500); exit('DB not ready'); }

if (!$order) { http_response_code(404); exit('Order not found'); }

// Pull latest messages (optional)
$msgs = [];
$msql = "SELECT m.id, m.sender_id, m.receiver_id, m.message, m.sent_at
         FROM messages m
         WHERE (m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?)
         ORDER BY m.id DESC LIMIT 20";
if (isset($pdo)) { $st=$pdo->prepare($msql); $st->execute([$order['seller_id'], $_SESSION['user_id'], $_SESSION['user_id'], $order['seller_id']]); $msgs=$st->fetchAll(PDO::FETCH_ASSOC); }
else { $st=$conn->prepare($msql); $st->bind_param("iiii",$order['seller_id'],$_SESSION['user_id'],$_SESSION['user_id'],$order['seller_id']); $st->execute(); $msgs=$st->get_result()->fetch_all(MYSQLI_ASSOC); }

// ★ REVIEW: fetch existing review for this order
$review = null;
$rSql = "SELECT id, rating, comment, created_at FROM reviews WHERE order_id = ? LIMIT 1";
if (isset($pdo)) { $st=$pdo->prepare($rSql); $st->execute([$order_id]); $review=$st->fetch(PDO::FETCH_ASSOC); }
else { $st=$conn->prepare($rSql); $st->bind_param("i",$order_id); $st->execute(); $review=$st->get_result()->fetch_assoc(); }

// CSRF
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token']=bin2hex(random_bytes(32));
$csrf=$_SESSION['csrf_token'];

// ★ REVIEW: decide whether to auto-open modal after accept
$show_review_modal = (isset($_GET['show_review']) && $_GET['show_review']=='1' && !$review && $order['status']==='completed');
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8"><title>Order #<?= (int)$order['id'] ?> • Buyer View</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="bg-light">
<div class="container py-4">
  <a class="btn btn-link" href="buyer_dashboard.php">&larr; Back</a>
  <h1 class="h4 mb-2">Order #<?= (int)$order['id'] ?> — “<?= htmlspecialchars($order['title']) ?>”</h1>
  <p class="text-muted">Status: <span class="badge bg-info text-dark"><?= htmlspecialchars($order['status']) ?></span></p>
  <p class="mb-4">Amount: <strong>NLe <?= number_format((float)$order['price'],2) ?></strong> (held in escrow)</p>

  <?php if ($order['status']==='in_progress'): ?>
    <div class="alert alert-success">A delivery has been submitted. Review below, then accept to release escrow.</div>
    <form method="post" action="accept_delivery.php" class="mb-4">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
      <input type="hidden" name="order_id" value="<?= (int)$order['id'] ?>">
      <button class="btn btn-success">Accept Delivery &amp; Release Escrow</button>
    </form>
  <?php endif; ?>

  <!-- ★ REVIEW: prompt to rate after completion (if no review yet) -->
  <?php if ($order['status']==='completed' && !$review): ?>
    <div class="alert alert-primary d-flex justify-content-between align-items-center">
      <div>Thanks for accepting this order. Please rate and leave a short review.</div>
      <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#reviewModal">Rate &amp; Review</button>
    </div>
  <?php endif; ?>

  <!-- ★ REVIEW: show existing review -->
  <?php if ($review): ?>
    <div class="card shadow-sm mb-4">
      <div class="card-body">
        <h5 class="card-title mb-2">Your Review</h5>
        <div class="mb-2">
          <?php
            $r = (int)$review['rating'];
            for ($i=1; $i<=5; $i++) {
              $icon = $i <= $r ? 'bi-star-fill' : 'bi-star';
              echo '<i class="bi '.$icon.'"></i> ';
            }
          ?>
          <span class="ms-1 small text-muted"><?= htmlspecialchars($review['created_at']) ?></span>
        </div>
        <?php if (!empty($review['comment'])): ?>
          <p class="mb-0"><?= nl2br(htmlspecialchars($review['comment'])) ?></p>
        <?php else: ?>
          <p class="text-muted mb-0">No comment left.</p>
        <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>

  <div class="card shadow-sm">
    <div class="card-header">Recent Messages</div>
    <div class="card-body">
      <?php if (!$msgs): ?>
        <p class="text-muted mb-0">No messages yet.</p>
      <?php else: foreach ($msgs as $m): ?>
        <div class="mb-3">
          <div class="small text-muted"><?= htmlspecialchars($m['sent_at']) ?> • From #<?= (int)$m['sender_id'] ?></div>
          <pre class="mb-0" style="white-space:pre-wrap"><?= htmlspecialchars($m['message']) ?></pre>
        </div>
      <?php endforeach; endif; ?>
      <a href="message.php?user_id=<?= (int)$order['seller_id'] ?>" class="btn btn-outline-primary btn-sm">Open full chat</a>
    </div>
  </div>
</div>

<!-- ★ REVIEW: Modal -->
<div class="modal fade" id="reviewModal" tabindex="-1" aria-labelledby="reviewModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form class="modal-content" method="post" action="submit_review.php">
      <div class="modal-header">
        <h5 class="modal-title" id="reviewModalLabel">Rate &amp; Review: “<?= htmlspecialchars($order['title']) ?>”</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
        <input type="hidden" name="order_id"  value="<?= (int)$order_id ?>">

        <div class="mb-3">
          <label class="form-label">Rating</label>
          <select class="form-select" name="rating" required>
            <option value="" selected disabled>Choose…</option>
            <option value="5">5 - Excellent</option>
            <option value="4">4 - Very Good</option>
            <option value="3">3 - Good</option>
            <option value="2">2 - Fair</option>
            <option value="1">1 - Poor</option>
          </select>
          <div class="form-text">1 (Poor) to 5 (Excellent)</div>
        </div>

        <div class="mb-3">
          <label class="form-label">Comment (optional)</label>
          <textarea class="form-control" name="comment" rows="4" placeholder="Share your experience…"></textarea>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary">Submit Review</button>
      </div>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<?php if ($show_review_modal): ?>
<script>
document.addEventListener('DOMContentLoaded', function(){
  var modal = new bootstrap.Modal(document.getElementById('reviewModal'));
  modal.show();
});
</script>
<?php endif; ?>
</body>
</html>
