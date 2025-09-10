<?php
session_start();
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'buyer') { http_response_code(403); exit('Buyers only'); }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('POST only'); }
if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) { http_response_code(400); exit('Bad token'); }

$ref      = $_POST['reference'] ?? '';
$provider = $_POST['provider'] ?? 'monime';
$action   = $_POST['action'] ?? 'fail';
if (!$ref) { http_response_code(400); exit('Missing reference'); }

// Load session + order (and price in case we need it)
$sql = "SELECT ps.*, o.buyer_id, o.id AS order_id, o.status AS order_status, g.price, g.title
        FROM payment_sessions ps
        JOIN orders o ON o.id = ps.order_id
        JOIN gigs g ON g.id = o.gig_id
        WHERE ps.reference = ?
        LIMIT 1";
if (isset($pdo)) { $st=$pdo->prepare($sql); $st->execute([$ref]); $ps=$st->fetch(PDO::FETCH_ASSOC); }
else { $st=$conn->prepare($sql); $st->bind_param("s",$ref); $st->execute(); $ps=$st->get_result()->fetch_assoc(); }

if (!$ps) { http_response_code(404); exit('Session not found'); }
if ((int)$ps['buyer_id'] !== (int)$_SESSION['user_id']) { http_response_code(403); exit('Not your session'); }

$order_id  = (int)$ps['order_id'];
$newStatus = ($action === 'success') ? 'paid' : 'failed';

try {
  if (isset($pdo)) {
    $pdo->beginTransaction();

    // Update payment session
    $up = $pdo->prepare("UPDATE payment_sessions SET provider=?, status=? WHERE id=?");
    $up->execute([$provider, $newStatus, (int)$ps['id']]);

    if ($newStatus === 'paid') {
      // Move order to in_progress (only if still pending)
      $uo = $pdo->prepare("UPDATE orders SET status='in_progress' WHERE id=? AND status IN ('pending')");
      $uo->execute([$order_id]);

      // Create escrow (held) if not exists
      $chk = $pdo->prepare("SELECT id FROM escrow_transactions WHERE order_id=? LIMIT 1");
      $chk->execute([$order_id]);
      if (!$chk->fetchColumn()) {
        $ins = $pdo->prepare("INSERT INTO escrow_transactions (order_id, amount, status) VALUES (?,?, 'held')");
        $ins->execute([$order_id, (float)$ps['amount']]);
      }
    }

    $pdo->commit();

  } else {
    $conn->begin_transaction();

    $up = $conn->prepare("UPDATE payment_sessions SET provider=?, status=? WHERE id=?");
    $id = (int)$ps['id'];
    $up->bind_param("ssi",$provider,$newStatus,$id);
    $up->execute();

    if ($newStatus === 'paid') {
      $uo = $conn->prepare("UPDATE orders SET status='in_progress' WHERE id=? AND status IN ('pending')");
      $uo->bind_param("i",$order_id);
      $uo->execute();

      $chk = $conn->prepare("SELECT id FROM escrow_transactions WHERE order_id=? LIMIT 1");
      $chk->bind_param("i",$order_id);
      $chk->execute();
      $has = $chk->get_result()->fetch_row();

      if (!$has) {
        $ins = $conn->prepare("INSERT INTO escrow_transactions (order_id, amount, status) VALUES (?,?, 'held')");
        $amt = (float)$ps['amount'];
        $ins->bind_param("id",$order_id,$amt);
        $ins->execute();
      }
    }

    $conn->commit();
  }

  // === UI Response ===
  // On success: show animated check + auto-redirect to dashboard
  // On fail: show animated cross + button back to checkout
  $title = htmlspecialchars($ps['title'] ?? 'Order');
  $amount = number_format((float)$ps['amount'], 2);
  $refEsc = htmlspecialchars($ps['reference']);
  $redirectSuccess = 'buyer_dashboard.php';
  $backToCheckout = 'checkout.php?ref=' . urlencode($ps['reference']);

  // Output a minimal HTML page with CSS animation (no external deps)
  ?>
  <!doctype html>
  <html>
  <head>
    <meta charset="utf-8">
    <title><?= ($newStatus==='paid' ? 'Payment Successful' : 'Payment Failed') ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <style>
      :root {
        --ok:#16a34a; /* green-600 */
        --err:#dc2626; /* red-600 */
        --bg:#f8fafc; /* slate-50 */
        --fg:#0f172a; /* slate-900 */
      }
      html,body{height:100%}
      body{margin:0;display:flex;align-items:center;justify-content:center;background:var(--bg);color:var(--fg);font-family:system-ui,-apple-system,Segoe UI,Roboto,Inter,Arial,sans-serif}
      .card{background:#fff;border-radius:1rem;box-shadow:0 10px 30px rgba(0,0,0,.06);padding:2rem;max-width:520px;width:92%}
      .center{text-align:center}
      .circle{width:120px;height:120px;border-radius:50%;margin:0 auto 1rem;display:grid;place-items:center;position:relative}
      .circle.ok{background:rgba(22,163,74,.08);border:2px solid rgba(22,163,74,.3);animation:pop .35s ease-out both}
      .circle.err{background:rgba(220,38,38,.08);border:2px solid rgba(220,38,38,.3);animation:pop .35s ease-out both}
      @keyframes pop{0%{transform:scale(.8);opacity:.5}100%{transform:scale(1);opacity:1}}
      /* Checkmark animation */
      svg{width:70px;height:70px}
      .check path, .cross path {
        stroke-dasharray: 180;
        stroke-dashoffset: 180;
        animation: draw 800ms ease-out forwards 200ms;
      }
      @keyframes draw{to{stroke-dashoffset:0}}
      .hint{color:#475569;margin-top:.25rem}
      .btns{display:flex;gap:.5rem;justify-content:center;margin-top:1rem;flex-wrap:wrap}
      .btn{padding:.6rem 1rem;border-radius:.6rem;border:1px solid #e2e8f0;background:#fff;color:#0f172a;text-decoration:none;font-weight:600}
      .btn.primary{background:var(--ok);border-color:var(--ok);color:#fff}
      .btn.outline{border-color:#94a3b8}
      .muted{color:#6b7280;font-size:.9rem;margin-top:.75rem}
    </style>
  </head>
  <body>
    <div class="card center">
      <div class="circle <?= $newStatus==='paid' ? 'ok' : 'err' ?>">
        <?php if ($newStatus==='paid'): ?>
          <!-- Animated Check -->
          <svg class="check" viewBox="0 0 52 52" fill="none">
            <circle cx="26" cy="26" r="24" stroke="var(--ok)" stroke-opacity=".2" stroke-width="2"/>
            <path d="M16 27 L23 34 L36 18" stroke="var(--ok)" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        <?php else: ?>
          <!-- Animated Cross -->
          <svg class="cross" viewBox="0 0 52 52" fill="none">
            <circle cx="26" cy="26" r="24" stroke="var(--err)" stroke-opacity=".2" stroke-width="2"/>
            <path d="M18 18 L34 34 M34 18 L18 34" stroke="var(--err)" stroke-width="4" stroke-linecap="round"/>
          </svg>
        <?php endif; ?>
      </div>

      <h1><?= $newStatus==='paid' ? 'Payment Successful' : 'Payment Failed' ?></h1>
      <p class="hint">
        <?= $newStatus==='paid'
            ? "Your payment of <strong>NLe {$amount}</strong> for <strong>".htmlspecialchars($ps['title'])."</strong> was received."
            : "We couldn’t complete your payment for <strong>".htmlspecialchars($ps['title'])."</strong>." ?>
      </p>

      <?php if ($newStatus==='paid'): ?>
        <div class="btns">
          <a class="btn primary" href="<?= htmlspecialchars($redirectSuccess) ?>">Go to Dashboard</a>
        </div>
        <div class="muted">Redirecting in 2.5 seconds…</div>
        <script>
          setTimeout(function(){ window.location.href = <?= json_encode($redirectSuccess) ?>; }, 2500);
        </script>
      <?php else: ?>
        <div class="btns">
          <a class="btn outline" href="<?= htmlspecialchars($backToCheckout) ?>">Back to Checkout</a>
        </div>
      <?php endif; ?>

      <div class="muted">Ref: <?= $refEsc ?></div>
    </div>
  </body>
  </html>
  <?php
  exit;

} catch (Throwable $e) {
  if (isset($pdo)) $pdo->rollBack(); else $conn->rollback();
  error_log("simulate_payment error: ".$e->getMessage());
  http_response_code(500); exit('Payment simulation failed');
}
