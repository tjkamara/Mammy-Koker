<?php
/**
 * order.php
 * Creates an order and its escrow transaction, then redirects
 * to the single-order buyer view: buyer_order_view.php?order_id=...
 * Works with $pdo (PDO) or $conn (MySQLi). Requires db.php and a logged-in buyer.
 */
session_start();
require_once __DIR__ . '/db.php';

// Only buyers can create orders
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'buyer') {
  http_response_code(403); exit('Buyers only.');
}
$buyer_id = (int)$_SESSION['user_id'];

// CSRF must match (if you use the token on the confirmation page)
if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
  http_response_code(400); exit('Invalid request.');
}

// Inputs
$gig_id = (int)($_POST['gig_id'] ?? 0);
if ($gig_id <= 0) { http_response_code(400); exit('Missing gig.'); }

$quantity    = isset($_POST['quantity']) ? max(1, min(10, (int)$_POST['quantity'])) : 1;
$buyer_notes = trim($_POST['buyer_notes'] ?? '');

// Fetch gig + seller
$sql_gig = "SELECT g.id, g.title, g.price, g.seller_id
            FROM gigs g
            WHERE g.id = ? AND g.status = 'active'
            LIMIT 1";
$gig = null;
if (isset($pdo) && $pdo instanceof PDO) {
  $st = $pdo->prepare($sql_gig); $st->execute([$gig_id]); $gig = $st->fetch(PDO::FETCH_ASSOC);
} elseif (isset($conn) && $conn instanceof mysqli) {
  $st = $conn->prepare($sql_gig); $st->bind_param("i", $gig_id); $st->execute();
  $res = $st->get_result(); $gig = $res ? $res->fetch_assoc() : null;
} else {
  http_response_code(500); exit('Database not initialized.');
}
if (!$gig) { http_response_code(404); exit('Gig not found.'); }

$seller_id = (int)$gig['seller_id'];
if ($seller_id === $buyer_id) { http_response_code(400); exit('You cannot order your own gig.'); }

$unit_price = (float)$gig['price'];
$amount     = $unit_price * $quantity;

// Create order + escrow (transaction)
try {
  if (isset($pdo) && $pdo instanceof PDO) {
    $pdo->beginTransaction();

    // Insert order (status pending)
    $o = $pdo->prepare("INSERT INTO orders (buyer_id, seller_id, gig_id, status, created_at)
                        VALUES (?, ?, ?, 'pending', NOW())");
    $o->execute([$buyer_id, $seller_id, $gig_id]);
    $order_id = (int)$pdo->lastInsertId();

    // Insert escrow (held)
    $e = $pdo->prepare("INSERT INTO escrow_transactions (order_id, amount, status, created_at)
                        VALUES (?, ?, 'held', NOW())");
    $e->execute([$order_id, $amount]);

    // Drop buyer requirements as a message to seller (optional)
    if ($buyer_notes !== '') {
      $m = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, message, is_read)
                          VALUES (?, ?, ?, 0)");
      $msg = "REQUIREMENTS for order #{$order_id} (qty {$quantity}):\n".$buyer_notes;
      $m->execute([$buyer_id, $seller_id, $msg]);
    }

    $pdo->commit();
  } else {
    // MySQLi
    $conn->begin_transaction();

    $o = $conn->prepare("INSERT INTO orders (buyer_id, seller_id, gig_id, status, created_at)
                         VALUES (?, ?, ?, 'pending', NOW())");
    $o->bind_param("iii", $buyer_id, $seller_id, $gig_id);
    $o->execute();
    $order_id = $conn->insert_id;

    $e = $conn->prepare("INSERT INTO escrow_transactions (order_id, amount, status, created_at)
                         VALUES (?, ?, 'held', NOW())");
    $e->bind_param("id", $order_id, $amount);
    $e->execute();

    if ($buyer_notes !== '') {
      $m = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, message, is_read)
                           VALUES (?, ?, ?, 0)");
      $msg = "REQUIREMENTS for order #{$order_id} (qty {$quantity}):\n".$buyer_notes;
      $m->bind_param("iis", $buyer_id, $seller_id, $msg);
      $m->execute();
    }

    $conn->commit();
  }

  // Redirect to the single-order page
  header("Location: buyer_order_view.php?order_id=".$order_id);
  exit;

} catch (Throwable $e) {
  if (isset($pdo)) $pdo->rollBack(); else $conn->rollback();
  error_log("order_create error: ".$e->getMessage());
  http_response_code(500); exit('Unable to create order.');
}
