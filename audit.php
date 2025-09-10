<?php
// audit.php — write one row to escrow_audit for release/refund
function audit_escrow_change(array $data): void {
  // $data: escrow_id, order_id, action, prev_escrow, new_escrow, prev_order, new_order, admin_id, note, ip, ua
  $sql = "INSERT INTO escrow_audit
            (escrow_id, order_id, action, prev_escrow, new_escrow, prev_order, new_order, admin_id, note, ip_address, user_agent)
          VALUES (?,?,?,?,?,?,?,?,?,?,?)";

  if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
    $st = $GLOBALS['pdo']->prepare($sql);
    $st->execute([
      $data['escrow_id'], $data['order_id'], $data['action'],
      $data['prev_escrow'], $data['new_escrow'],
      $data['prev_order'],  $data['new_order'],
      $data['admin_id'],    $data['note'],
      $data['ip'],          $data['ua'],
    ]);
    return;
  }

  if (isset($GLOBALS['conn']) && $GLOBALS['conn'] instanceof mysqli) {
    $st = $GLOBALS['conn']->prepare($sql);
    $st->bind_param(
      "iissssissss",
      $data['escrow_id'], $data['order_id'], $data['action'],
      $data['prev_escrow'], $data['new_escrow'],
      $data['prev_order'],  $data['new_order'],
      $data['admin_id'],    $data['note'],
      $data['ip'],          $data['ua']
    );
    $st->execute();
    return;
  }

  // If no DB handle configured
  error_log('audit_escrow_change: DB not initialized');
}
