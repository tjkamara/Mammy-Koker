<?php
session_start();
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'buyer') { http_response_code(403); exit('Buyers only.'); }

$buyer_id = (int)$_SESSION['user_id'];
$csrf     = $_POST['csrf_token'] ?? '';
if (!$csrf || !hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) { http_response_code(400); exit('Bad token'); }

$order_id = (int)($_POST['order_id'] ?? 0);
$rating   = (int)($_POST['rating'] ?? 0);
$comment  = trim((string)($_POST['comment'] ?? ''));

if ($order_id<=0 || $rating<1 || $rating>5) { http_response_code(400); exit('Invalid input'); }

// verify order belongs to this buyer and is completed
$ordSql = "SELECT id, status FROM orders WHERE id=? AND buyer_id=? LIMIT 1";
if (isset($pdo)) { $st=$pdo->prepare($ordSql); $st->execute([$order_id,$buyer_id]); $order=$st->fetch(PDO::FETCH_ASSOC); }
else { $st=$conn->prepare($ordSql); $st->bind_param("ii",$order_id,$buyer_id); $st->execute(); $order=$st->get_result()->fetch_assoc(); }
if (!$order) { http_response_code(404); exit('Order not found'); }
if ($order['status']!=='completed') { http_response_code(400); exit('Order not completed'); }

// prevent duplicate review (code-level)
$chkSql = "SELECT id FROM reviews WHERE order_id=? LIMIT 1";
if (isset($pdo)) { $st=$pdo->prepare($chkSql); $st->execute([$order_id]); $exists=$st->fetch(PDO::FETCH_ASSOC); }
else { $st=$conn->prepare($chkSql); $st->bind_param("i",$order_id); $st->execute(); $exists=$st->get_result()->fetch_assoc(); }
if ($exists) { header("Location: buyer_order_view.php?order_id={$order_id}"); exit; }

// insert review
$insSql = "INSERT INTO reviews (order_id, rating, comment) VALUES (?,?,?)";
if (isset($pdo)) {
  $st=$pdo->prepare($insSql); $st->execute([$order_id,$rating,$comment]);
} else {
  $st=$conn->prepare($insSql); $st->bind_param("iis",$order_id,$rating,$comment); $st->execute();
}

header("Location: buyer_order_view.php?order_id={$order_id}");
exit;
