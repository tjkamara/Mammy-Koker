<?php
/**
 * admin_login.php (adaptive + robust)
 * - Verifies admin credentials against your existing users table
 * - Detects password column and hash type
 * - Works with db.php exposing $pdo (PDO) or $conn (MySQLi)
 */

session_start();
require_once __DIR__ . '/db.php';

/* ---- TEMP DEBUG (remove after it works) ----
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
---- END TEMP DEBUG ---- */

// If already admin, go straight to console
if (isset($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'admin') {
  header("Location: admin_escrow.php");
  exit;
}

// CSRF token
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token'];

// Helpers
function db_driver() {
  if (isset($GLOBALS['pdo'])  && $GLOBALS['pdo']  instanceof PDO)    return 'pdo';
  if (isset($GLOBALS['conn']) && $GLOBALS['conn'] instanceof mysqli) return 'mysqli';
  return null;
}
function qrow($sql, $params = []) {
  $drv = db_driver();
  if ($drv === 'pdo') {
    $st = $GLOBALS['pdo']->prepare($sql);
    $st->execute($params);
    return $st->fetch(PDO::FETCH_ASSOC);
  } elseif ($drv === 'mysqli') {
    $types = '';
    if ($params) $types = implode('', array_map(fn($p)=> is_int($p)?'i':(is_float($p)?'d':'s'), $params));
    $st = $GLOBALS['conn']->prepare($sql);
    if ($params) $st->bind_param($types, ...$params);
    $st->execute();
    $res = $st->get_result();
    return $res ? $res->fetch_assoc() : null;
  }
  throw new Exception('Database not initialized.');
}

// Detect users.password column
$pwd_col = null;
try {
  $cols = [];
  $rows = [];
  $drv = db_driver();
  if ($drv === 'pdo') {
    $rows = $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_ASSOC);
  } elseif ($drv === 'mysqli') {
    $res = $conn->query("SHOW COLUMNS FROM users");
    if ($res) while ($r = $res->fetch_assoc()) $rows[] = $r;
  }
  foreach ($rows as $r) $cols[strtolower($r['Field'])] = $r;

  foreach (['password_hash','password','pass'] as $try) {
    if (isset($cols[$try])) { $pwd_col = $try; break; }
  }
} catch (Throwable $e) {
  // We'll fall back to 'password_hash' by default
}
if (!$pwd_col) $pwd_col = 'password_hash'; // sensible default

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // CSRF check
  if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
    $error = 'Invalid request. Please try again.';
  } else {
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';

    if ($email === '' || $pass === '') {
      $error = 'Email and password are required.';
    } else {
      // Fetch admin by email (role must be admin)
      $sql = "SELECT id, name, email, role, `$pwd_col` AS pwd FROM users WHERE email = ? AND role = 'admin' LIMIT 1";
      $u = qrow($sql, [$email]);

      if (!$u) {
        $error = 'No admin account found for that email.';
      } else {
        $stored = (string)$u['pwd'];
        $ok = false;

        // Prefer modern hashes: bcrypt/argon start with $
        if ($stored !== '' && $stored[0] === '$') {
          $ok = password_verify($pass, $stored);
        } else {
          // Legacy fallbacks — strongly recommend migrating to password_hash()
          $md5  = (strlen($stored) === 32 && ctype_xdigit($stored)) ? md5($pass)  : null;
          $sha1 = (strlen($stored) === 40 && ctype_xdigit($stored)) ? sha1($pass) : null;

          if ($md5 && hash_equals($stored, $md5))      $ok = true;
          elseif ($sha1 && hash_equals($stored, $sha1)) $ok = true;
          elseif (hash_equals($stored, $pass))          $ok = true; // plain text (migrate ASAP)
        }

        if ($ok) {
          // Harden session
          session_regenerate_id(true);
          $_SESSION['user_id'] = (int)$u['id'];
          $_SESSION['role']    = 'admin';
          $_SESSION['name']    = $u['name'] ?? 'Admin';
          $_SESSION['admin_login_at'] = time();

          header("Location: admin_escrow.php");
          exit;
        } else {
          $error = 'Incorrect password.';
        }
      }
    }
  }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Admin Login • Mammy Coker</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
  <style>
    body { background:#f7f7fb; }
    .card { border-radius: 1rem; }
    .logo { font-weight: 800; }
  </style>
</head>
<body>
  <div class="container py-5">
    <div class="row justify-content-center">
      <div class="col-12 col-md-5">
        <div class="text-center mb-4">
          <div class="logo h3">Mammy Coker</div>
          <div class="small text-muted">Admin Console</div>
        </div>

        <div class="card shadow-sm">
          <div class="card-body p-4">
            <h1 class="h5 mb-3">Admin Login</h1>

            <?php if ($error): ?>
              <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="post" action="admin_login.php" novalidate>
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
              <div class="mb-3">
                <label class="form-label">Email</label>
                <input class="form-control" type="email" name="email" required autocomplete="username" placeholder="admin@mammycoker.com" value="<?= isset($_POST['email'])?htmlspecialchars($_POST['email']):'' ?>">
              </div>
              <div class="mb-3">
                <label class="form-label d-flex justify-content-between">
                  <span>Password</span>
                </label>
                <input class="form-control" type="password" name="password" required autocomplete="current-password" placeholder="••••••••">
              </div>
              <button class="btn btn-primary w-100">Sign in</button>
            </form>
          </div>
        </div>

        <div class="text-center small text-muted mt-3">Authorized personnel only</div>
        <div class="text-center mt-2">
          <a class="small" href="index.php">&larr; Back to site</a>
        </div>
      </div>
    </div>
  </div>
</body>
</html>
