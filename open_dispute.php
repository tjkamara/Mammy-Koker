<?php
session_start();
require_once __DIR__ . '/db.php';
if ($_SERVER['REQUEST_METHOD']!=='POST') { http_response_code(405); exit('POST only'); }
if (!isset($_SESSION['user_id'])) { http_response_code(403); exit('Login required'); }

if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
  http_response_code(400); exit('Bad token');
}

$order_id = (int)($_POST['order_id'] ?? 0);
$reason   = trim($_POST['reason'] ?? '');
if ($order_id<=0 || $reason==='') { http_response_code(400); exit('Invalid'); }

// Fetch order to route the message
$sql = "SELECT o.id, o.buyer_id, o.seller_id FROM orders o WHERE o.id=? LIMIT 1";
if (isset($pdo)) { $st=$pdo->prepare($sql); $st->execute([$order_id]); $o=$st->fetch(PDO::FETCH_ASSOC); }
else { $st=$conn->prepare($sql); $st->bind_param("i",$order_id); $st->execute(); $o=$st->get_result()->fetch_assoc(); }
if (!$o) { http_response_code(404); exit('Order missing'); }

$sender   = (int)$_SESSION['user_id'];
$receiver = ($sender===(int)$o['buyer_id']) ? (int)$o['seller_id'] : (int)$o['buyer_id'];
$text = "DISPUTE opened on order #{$order_id}:\n".$reason;

if (isset($pdo)) {
  $m=$pdo->prepare("INSERT INTO messages (sender_id, receiver_id, message, is_read) VALUES (?,?,?,0)");
  $m->execute([$sender, $receiver, $text]);
} else {
  $m=$conn->prepare("INSERT INTO messages (sender_id, receiver_id, message, is_read) VALUES (?,?,?,0)");
  $m->bind_param("iis",$sender,$receiver,$text);
  $m->execute();
}
header("Location: buyer_order_view.php?order_id=".$order_id);
exit;
