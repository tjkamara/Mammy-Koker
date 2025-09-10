<?php
session_start();
require_once __DIR__ . '/db.php';
if ($_SERVER['REQUEST_METHOD']!=='POST') { http_response_code(405); exit('POST only'); }
if (!isset($_SESSION['user_id']) || ($_SESSION['role']??'')!=='seller') { http_response_code(403); exit('Sellers only'); }
if (!hash_equals($_SESSION['csrf_token']??'', $_POST['csrf_token']??'')) { http_response_code(400); exit('Bad token'); }

$order_id = (int)($_POST['order_id'] ?? 0);
$notes    = trim($_POST['delivery_notes'] ?? '');
if ($order_id<=0) { http_response_code(400); exit('Invalid order'); }

// Read essential order info
$sql = "SELECT o.id, o.buyer_id, o.seller_id FROM orders o WHERE o.id=? AND o.seller_id=? LIMIT 1";
if (isset($pdo)) { $st=$pdo->prepare($sql); $st->execute([$order_id, $_SESSION['user_id']]); $o=$st->fetch(PDO::FETCH_ASSOC); }
elseif (isset($conn)) { $st=$conn->prepare($sql); $st->bind_param("ii",$order_id,$_SESSION['user_id']); $st->execute(); $o=$st->get_result()->fetch_assoc(); }
else { http_response_code(500); exit('DB not ready'); }

if (!$o) { http_response_code(404); exit('Order not found'); }

// Handle upload (optional, store publicly and put link in message)
$link = '';
if (!empty($_FILES['delivery_file']['name'])) {
  $base = time().'_'.preg_replace('/\s+/', '_', basename($_FILES['delivery_file']['name']));
  $dest = __DIR__.'/uploads/deliveries/'.$base;
  if (!is_dir(dirname($dest))) mkdir(dirname($dest),0777,true);
  if (move_uploaded_file($_FILES['delivery_file']['tmp_name'], $dest)) {
    $link = 'uploads/deliveries/'.$base;
  }
}

// Compose delivery message
$body = "DELIVERY:\n";
if ($notes!=='') $body .= $notes."\n";
if ($link!=='')  $body .= "File: ".$link;

try {
  if (isset($pdo)) {
    $pdo->beginTransaction();
    // message to buyer
    $m=$pdo->prepare("INSERT INTO messages (sender_id, receiver_id, message, is_read) VALUES (?,?,?,0)");
    $m->execute([$_SESSION['user_id'], (int)$o['buyer_id'], $body]);
    // mark order as awaiting acceptance
    $u=$pdo->prepare("UPDATE orders SET status='in_progress' WHERE id=?");
    $u->execute([$order_id]);
    $pdo->commit();
  } else {
    $conn->begin_transaction();
    $m=$conn->prepare("INSERT INTO messages (sender_id, receiver_id, message, is_read) VALUES (?,?,?,0)");
    $m->bind_param("iis", $_SESSION['user_id'], $o['buyer_id'], $body);
    $m->execute();
    $u=$conn->prepare("UPDATE orders SET status='in_progress' WHERE id=?");
    $u->bind_param("i", $order_id); $u->execute();
    $conn->commit();
  }
  header("Location: seller_order_view.php?order_id=".$order_id);
  exit;
} catch (Throwable $e) {
  if (isset($pdo)) $pdo->rollBack(); else $conn->rollback();
  error_log("deliver_order error: ".$e->getMessage());
  http_response_code(500); exit('Delivery failed');
}
