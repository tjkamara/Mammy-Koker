<?php
session_start();
require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('POST only'); }
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'buyer') { http_response_code(403); exit('Buyers only'); }
if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) { http_response_code(400); exit('Bad token'); }

$order_id = (int)($_POST['order_id'] ?? 0);
if ($order_id <= 0) { http_response_code(400); exit('Invalid order'); }

// Verify buyer owns the order and it is in the right state
$ownSql = "SELECT id FROM orders WHERE id = ? AND buyer_id = ? AND status = 'in_progress' LIMIT 1";
$ok = false;
if (isset($pdo)) {
  $st = $pdo->prepare($ownSql);
  $st->execute([$order_id, $_SESSION['user_id']]);
  $ok = (bool)$st->fetchColumn();
} else {
  $st = $conn->prepare($ownSql);
  $st->bind_param("ii", $order_id, $_SESSION['user_id']);
  $st->execute();
  $res = $st->get_result();
  $ok = (bool)$res->fetch_row();
}
if (!$ok) { http_response_code(400); exit('Order not ready for acceptance'); }

try {
  if (isset($pdo)) {
    $pdo->beginTransaction();

    // Mark the order completed (idempotent guard via WHERE status='in_progress')
    $u = $pdo->prepare("UPDATE orders SET status = 'completed' WHERE id = ? AND status = 'in_progress'");
    $u->execute([$order_id]);
    if ($u->rowCount() === 0) { // nothing changed -> concurrent update or wrong state
      throw new RuntimeException('Order already completed or not in correct state.');
    }

    // Release escrow only if currently held (safer)
    $e = $pdo->prepare("UPDATE escrow_transactions SET status = 'released' WHERE order_id = ? AND status = 'held'");
    $e->execute([$order_id]);

    $pdo->commit();

  } else {
    $conn->begin_transaction();

    $u = $conn->prepare("UPDATE orders SET status = 'completed' WHERE id = ? AND status = 'in_progress'");
    $u->bind_param("i", $order_id);
    $u->execute();
    if ($u->affected_rows === 0) { // nothing changed -> concurrent update or wrong state
      throw new RuntimeException('Order already completed or not in correct state.');
    }

    $e = $conn->prepare("UPDATE escrow_transactions SET status = 'released' WHERE order_id = ? AND status = 'held'");
    $e->bind_param("i", $order_id);
    $e->execute();

    $conn->commit();
  }

  // ✅ Redirect back with a one-time flag that tells the buyer page to auto-open the review modal
  header("Location: buyer_order_view.php?order_id={$order_id}&show_review=1");
  exit;

} catch (Throwable $e) {
  if (isset($pdo)) $pdo->rollBack(); else $conn->rollback();
  error_log("accept_delivery error: " . $e->getMessage());
  http_response_code(500); exit('Accept failed');
}
