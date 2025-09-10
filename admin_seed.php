<?php
/**
 * admin_seed.php (adaptive)
 * One-time page to create OR promote an admin account.
 * - Works with db.php exposing $pdo (PDO) or $conn (MySQLi)
 * - Detects users table columns (password column, created_at presence)
 * - Ensures 'admin' is present in role enum (alters only if needed)
 * Delete this file after successful use.
 */
session_start();
require_once __DIR__ . '/db.php';

/* --- TEMP: uncomment while debugging a 500/505 ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
--- TEMP END --- */

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
  } else {
    throw new Exception('Database not initialized in db.php');
  }
}
function qall($sql, $params = []) {
  $drv = db_driver();
  if ($drv === 'pdo') {
    $st = $GLOBALS['pdo']->prepare($sql);
    $st->execute($params);
    return $st->fetchAll(PDO::FETCH_ASSOC);
  } elseif ($drv === 'mysqli') {
    $types = '';
    if ($params) $types = implode('', array_map(fn($p)=> is_int($p)?'i':(is_float($p)?'d':'s'), $params));
    $st = $GLOBALS['conn']->prepare($sql);
    if ($params) $st->bind_param($types, ...$params);
    $st->execute();
    $res = $st->get_result();
    return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
  } else {
    throw new Exception('Database not initialized in db.php');
  }
}
function qexec($sql, $params = []) {
  $drv = db_driver();
  if ($drv === 'pdo') {
    $st = $GLOBALS['pdo']->prepare($sql);
    return $st->execute($params);
  } elseif ($drv === 'mysqli') {
    $types = '';
    if ($params) $types = implode('', array_map(fn($p)=> is_int($p)?'i':(is_float($p)?'d':'s'), $params));
    $st = $GLOBALS['conn']->prepare($sql);
    if ($params) $st->bind_param($types, ...$params);
    return $st->execute();
  } else {
    throw new Exception('Database not initialized in db.php');
  }
}

// Detect users columns
$cols = [];
try {
  $rows = qall("SHOW COLUMNS FROM users");
  foreach ($rows as $r) { $cols[strtolower($r['Field'])] = $r; }
} catch (Throwable $e) {
  http_response_code(500);
  exit("Could not DESCRIBE users table. Error: ".$e->getMessage());
}

// Choose password column
$pwd_col = null;
foreach (['password_hash','password','pass'] as $try) {
  if (isset($cols[$try])) { $pwd_col = $try; break; }
}
if (!$pwd_col) {
  http_response_code(500);
  exit("No password column found in users table (looked for password_hash / password / pass).");
}

// Ensure role column exists
if (!isset($cols['role'])) {
  http_response_code(500);
  exit("No 'role' column in users table.");
}

// Ensure 'admin' is allowed in role enum (if enum)
try {
  $roleType = strtolower($cols['role']['Type'] ?? '');
  if (str_starts_with($roleType, 'enum(') && !str_contains($roleType, "'admin'")) {
    // Build new enum list by adding 'admin'
    $enumDef = $cols['role']['Type']; // e.g., enum('buyer','seller')
    $inside  = trim($enumDef[4] === '(' ? substr($enumDef, 5, -1) : $enumDef, '() ');
    $vals    = array_map(fn($s)=>trim($s," '"), explode(',', $inside));
    $vals[]  = 'admin';
    $vals    = array_values(array_unique($vals));
    $newEnum = "ENUM('".implode("','", $vals)."')";
    qexec("ALTER TABLE users MODIFY role $newEnum NOT NULL DEFAULT 'buyer'");
  }
} catch (Throwable $e) {
  // ignore if cannot alter (host perms); continue — insert may still work if 'admin' already allowed
}

// Will we include created_at?
$has_created_at = isset($cols['created_at']);

// CSRF (basic for this page)
if (empty($_SESSION['csrf_seed'])) $_SESSION['csrf_seed'] = bin2hex(random_bytes(32));
$csrf = $_SESSION['csrf_seed'];

$error = '';
$ok    = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!hash_equals($_SESSION['csrf_seed'] ?? '', $_POST['csrf_seed'] ?? '')) {
    $error = 'Invalid request. Refresh and try again.';
  } else {
    $name  = trim($_POST['name']  ?? '');
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';
    $cpass = $_POST['confirm']  ?? '';

    if ($name==='' || $email==='' || $pass==='') {
      $error = 'All fields are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      $error = 'Invalid email address.';
    } elseif ($pass !== $cpass) {
      $error = 'Passwords do not match.';
    } else {
      $hash = password_hash($pass, PASSWORD_DEFAULT);

      // Does user exist?
      $exists = qrow("SELECT id FROM users WHERE email = ? LIMIT 1", [$email]);

      if ($exists) {
        // promote
        qexec("UPDATE users SET role='admin' WHERE email = ? LIMIT 1", [$email]);
        $ok = "Existing user promoted to admin: {$email}";
      } else {
        // build insert using only available columns
        $fields = ['name','email',$pwd_col,'role'];
        $place  = ['?','?','?','admin']; // role will be literal 'admin' later
        $params = [$name, $email, $hash];

        if ($has_created_at) { $fields[]='created_at'; $place[]='NOW()'; }

        // Compose SQL (role as literal to avoid enum binding snafus)
        $sql = "INSERT INTO users (".implode(',', $fields).") VALUES (?,?,?,"."'admin'".($has_created_at?',NOW()':'').")";

        qexec($sql, $params);
        $ok = "Admin account created for {$email}";
      }
    }
  }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Create Admin • One-time Seed</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
  <style>.card{border-radius:1rem}</style>
</head>
<body class="bg-light">
<div class="container py-5">
  <div class="row justify-content-center">
    <div class="col-12 col-md-6">
      <div class="card shadow-sm">
        <div class="card-body p-4">
          <h1 class="h5 mb-3">Create Admin Account</h1>

          <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
          <?php elseif ($ok): ?>
            <div class="alert alert-success"><?= htmlspecialchars($ok) ?></div>
            <div class="mb-3">
              <a class="btn btn-primary" href="admin_login.php">Go to Admin Login</a>
            </div>
            <div class="alert alert-warning">
              For security, delete <code>admin_seed.php</code> now.
            </div>
          <?php endif; ?>

          <form method="post" autocomplete="off">
            <input type="hidden" name="csrf_seed" value="<?= htmlspecialchars($csrf) ?>">
            <div class="mb-3">
              <label class="form-label">Full Name</label>
              <input class="form-control" type="text" name="name" required placeholder="Admin User" value="<?= isset($_POST['name'])?htmlspecialchars($_POST['name']):'' ?>">
            </div>
            <div class="mb-3">
              <label class="form-label">Email</label>
              <input class="form-control" type="email" name="email" required placeholder="admin@mammycoker.com" value="<?= isset($_POST['email'])?htmlspecialchars($_POST['email']):'' ?>">
            </div>
            <div class="mb-3">
              <label class="form-label">Password</label>
              <input class="form-control" type="password" name="password" required placeholder="Strong password">
            </div>
            <div class="mb-3">
              <label class="form-label">Confirm Password</label>
              <input class="form-control" type="password" name="confirm" required placeholder="Repeat password">
            </div>
            <button class="btn btn-success w-100">Create / Promote Admin</button>
          </form>
        </div>
      </div>
      <p class="small text-muted text-center mt-3">
        This page is for one-time setup. Delete it after use.
      </p>
    </div>
  </div>
</div>
</body>
</html>
