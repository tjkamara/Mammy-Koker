<?php
session_start();
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'buyer') { http_response_code(403); exit('Buyers only'); }

$ref = $_GET['ref'] ?? '';
if (!$ref) { http_response_code(400); exit('Missing ref'); }

// Load payment session & ensure ownership
$sql = "SELECT ps.*, o.buyer_id, g.title
        FROM payment_sessions ps
        JOIN orders o ON o.id = ps.order_id
        JOIN gigs g ON g.id = o.gig_id
        WHERE ps.reference = ?
        LIMIT 1";
if (isset($pdo)) { $st=$pdo->prepare($sql); $st->execute([$ref]); $ps=$st->fetch(PDO::FETCH_ASSOC); }
else { $st=$conn->prepare($sql); $st->bind_param("s",$ref); $st->execute(); $ps=$st->get_result()->fetch_assoc(); }

if (!$ps) { http_response_code(404); exit('Session not found'); }
if ((int)$ps['buyer_id'] !== (int)$_SESSION['user_id']) { http_response_code(403); exit('Not your session'); }

if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$csrf = $_SESSION['csrf_token'];
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Checkout • <?= htmlspecialchars($ps['reference']) ?></title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
</head>
<body class="bg-light">
<div class="container py-5" style="max-width:680px">
  <h1 class="h4 mb-3">Checkout</h1>
  <div class="card shadow-sm">
    <div class="card-body">
      <div class="mb-2">Order Ref: <strong><?= htmlspecialchars($ps['reference']) ?></strong></div>
      <div class="mb-2">Gig: <strong><?= htmlspecialchars($ps['title']) ?></strong></div>
      <div class="mb-3">Amount: <strong>NLe <?= number_format((float)$ps['amount'],2) ?></strong></div>

      <form method="post" action="simulate_payment.php" class="vstack gap-3">
  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
  <input type="hidden" name="reference"  value="<?= htmlspecialchars($ps['reference']) ?>">

  <div class="row row-cols-1 row-cols-md-2 g-3">
    <!-- Orange Money -->
    <div class="col">
      <label class="card h-100 shadow-sm hover-shadow cursor-pointer">
        <input type="radio" class="form-check-input position-absolute top-0 end-0 m-2"
               name="provider" value="orange" required>
        <div class="card-body text-center">
          <img src="images/Orange.png" alt="Orange Money" class="img-fluid mb-2" style="max-height:60px">
          <h6 class="card-title mb-0">Orange Money</h6>
        </div>
      </label>
    </div>

    <!-- Afrimoney -->
    <div class="col">
      <label class="card h-100 shadow-sm hover-shadow cursor-pointer">
        <input type="radio" class="form-check-input position-absolute top-0 end-0 m-2"
               name="provider" value="afrimoney">
        <div class="card-body text-center">
          <img src="images/afrimoney.png" alt="Afrimoney" class="img-fluid mb-2" style="max-height:60px">
          <h6 class="card-title mb-0">Afrimoney</h6>
        </div>
      </label>
    </div>

    <!-- Monime -->
    <div class="col">
      <label class="card h-100 shadow-sm hover-shadow cursor-pointer">
        <input type="radio" class="form-check-input position-absolute top-0 end-0 m-2"
               name="provider" value="monime" checked>
        <div class="card-body text-center">
          <img src="images/monime.jpeg" alt="Monime" class="img-fluid mb-2" style="max-height:60px">
          <h6 class="card-title mb-0">Monime</h6>
        </div>
      </label>
    </div>

    
  </div>

  <div class="d-flex gap-2 mt-3">
    <button name="action" value="success" class="btn btn-success">Simulate Successful Payment</button>
    <button name="action" value="fail" class="btn btn-outline-danger" type="submit">Simulate Failed Payment</button>
  </div>
</form>



      <?php if ($ps['status'] === 'paid'): ?>
        <div class="alert alert-success mt-3 mb-0">This session is already PAID.</div>
      <?php elseif ($ps['status'] === 'failed'): ?>
        <div class="alert alert-danger mt-3 mb-0">Last attempt failed. You can retry.</div>
      <?php endif; ?>
    </div>
  </div>
</div>
</body>
</html>
