<?php
// admin_gate.php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

require_once __DIR__ . '/db.php';

// Require logged-in admin
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
  // you can redirect to a dedicated admin login if you have it:
  // header("Location: auth.php?as=admin"); exit;
  http_response_code(403);
  exit('Admins only.');
}

// Stronger session hygiene (optional but recommended)
if (empty($_SESSION['admin_ses_regen'])) {
  session_regenerate_id(true);
  $_SESSION['admin_ses_regen'] = time();
}

// CSRF helpers
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
function csrf_token() { return $_SESSION['csrf_token'] ?? ''; }
function require_csrf_or_die(string $token): void {
  if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
    http_response_code(400); exit('Invalid CSRF token.');
  }
}
