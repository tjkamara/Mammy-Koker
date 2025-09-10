<?php
/**
 * admin_refund_escrow.php
 * - Admin-only action: refund escrow to buyer
 * - Sets escrow_transactions.status = 'refunded'
 * - Sets orders.status = 'cancelled'
 * - Notifies buyer via messages
 * - Writes an audit row including before/after states + IP/UA
 */
require_once __DIR__ . '/admin_gate.php'; // session, role=admin, csrf helpers, db.php
require_once __DIR__ . '/audit.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('POST only'); }
require_csrf_or_die($_POST['csrf_token'] ?? '');

$escrow_id = (int)($_POST['escrow_id'] ?? 0);
$note      = trim($_POST['note'] ?? 'Refunded via admin console');
if ($escrow_id <= 0) { http_response_code(400); exit('Invalid escrow'); }

try {
  if (isset($pdo) && $pdo instanceof PDO) {
    $pdo->beginTransaction();

    // Lock the escrow/order rows
    $q = $pdo->prepare("
      SELECT e.order_id, e.status AS e_status,
             o.status AS o_status, o.buyer_id
      FROM escrow_transactions e
      JOIN orders o ON o.id = e.order_id
      WHERE e.id = ? FOR UPDATE
    ");
    $q->execute([$escrow_id]);
    $row = $q->fetch(PDO::FETCH_ASSOC);
    if (!$row) throw new Exception('Escrow not found');

    // No-op if already released/refunded
    if ($row['e_status'] !== 'held') {
      $pdo->commit();
      header("Location: admin_escrow.php"); exit;
    }

    // Mutations
    $pdo->prepare("UPDATE escrow_transactions SET status='refunded' WHERE id=?")->execute([$escrow_id]);
    $pdo->prepare("UPDATE orders SET status='cancelled' WHERE id=?")->execute([(int)$row['order_id']]);

    // Notify buyer
    $msg = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, message, is_read) VALUES (?,?,?,0)");
    $msg->execute([$_SESSION['user_id'], (int)$row['buyer_id'], "Admin refunded escrow for order #{$row['order_id']}"]);

    // Audit
    audit_escrow_change([
      'escrow_id'   => $escrow_id,
      'order_id'    => (int)$row['order_id'],
      'action'      => 'refund',
      'prev_escrow' => $row['e_status'],
      'new_escrow'  => 'refunded',
      'prev_order'  => $row['o_status'],
      'new_order'   => 'cancelled',
      'admin_id'    => (int)$_SESSION['user_id'],
      'note'        => $note,
      'ip'          => $_SERVER['REMOTE_ADDR'] ?? '',
      'ua'          => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 255),
    ]);

    $pdo->commit();
  } else {
    // MySQLi path
    $conn->begin_transaction();

    $q = $conn->prepare("
      SELECT e.order_id, e.status AS e_status,
             o.status AS o_status, o.buyer_id
      FROM escrow_transactions e
      JOIN orders o ON o.id = e.order_id
      WHERE e.id = ? FOR UPDATE
    ");
    $q->bind_param("i", $escrow_id);
    $q->execute();
    $row = $q->get_result()->fetch_assoc();
    if (!$row) throw new Exception('Escrow not found');

    if ($row['e_status'] !== 'held') {
      $conn->commit();
      header("Location: admin_escrow.php"); exit;
    }

    $u1 = $conn->prepare("UPDATE escrow_transactions SET status='refunded' WHERE id=?");
    $u1->bind_param("i", $escrow_id); $u1->execute();

    $u2 = $conn->prepare("UPDATE orders SET status='cancelled' WHERE id=?");
    $u2->bind_param("i", $row['order_id']); $u2->execute();

    $m  = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, message, is_read) VALUES (?,?,?,0)");
    $txt= "Admin refunded escrow for order #{$row['order_id']}";
    $m->bind_param("iis", $_SESSION['user_id'], $row['buyer_id'], $txt); $m->execute();

    audit_escrow_change([
      'escrow_id'   => $escrow_id,
      'order_id'    => (int)$row['order_id'],
      'action'      => 'refund',
      'prev_escrow' => $row['e_status'],
      'new_escrow'  => 'refunded',
      'prev_order'  => $row['o_status'],
      'new_order'   => 'cancelled',
      'admin_id'    => (int)$_SESSION['user_id'],
      'note'        => $note,
      'ip'          => $_SERVER['REMOTE_ADDR'] ?? '',
      'ua'          => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 255),
    ]);

    $conn->commit();
  }

  header("Location: admin_escrow.php"); exit;
} catch (Throwable $e) {
  if (isset($pdo)) { $pdo->rollBack(); } else { $conn->rollback(); }
  error_log("admin_refund_escrow error: ".$e->getMessage());
  http_response_code(500); exit('Refund failed');
}
