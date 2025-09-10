<?php
session_start();
include "db.php";

if (!isset($_SESSION['temp_email'])) {
    header("Location: auth.php");
    exit();
}

$email = $_SESSION['temp_email'];
$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $otp_entered = $_POST['otp'];

    // Check OTP
    $stmt = $conn->prepare("SELECT id, name, role, otp FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();
    $stmt->bind_result($user_id, $name, $role, $otp_stored);
    $stmt->fetch();

    if ($stmt->num_rows > 0 && $otp_stored == $otp_entered) {
        // OTP is correct, mark user as verified
        $update = $conn->prepare("UPDATE users SET is_verified = 1 WHERE email = ?");
        $update->bind_param("s", $email);
        $update->execute();

        // Set full session and clean up temp session
        $_SESSION['user_id'] = $user_id;
        $_SESSION['name'] = $name;
        $_SESSION['role'] = $role;
        unset($_SESSION['temp_email']);

        // Redirect based on role
        if ($role === 'seller') {
            header("Location: create_gig.php");
        } else {
            header("Location: buyer_dashboard.php");
        }
        exit();
    } else {
        $message = "Invalid OTP! Please try again.";
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify OTP - Mammy Coker</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        body {
            background-color: #f4f4f4;
        }
        .otp-container {
            max-width: 400px;
            margin: auto;
            margin-top: 50px;
            padding: 30px;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            text-align: center;
        }
        .logo {
            width: 120px;
            margin-bottom: 20px;
            border-radius: 50%;
        }
        .btn-verify {
            background-color: #007bff;
            border: none;
        }
        .btn-verify:hover {
            background-color: #0056b3;
        }
        .resend-link {
            display: block;
            margin-top: 15px;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="otp-container">
        <img src="images/logo.jpeg" alt="Mammy Coker Logo" class="logo"> 
        <h4 class="mb-3">Verify Your Account</h4>
        <p class="text-muted">Enter the 6-digit OTP sent to <strong><?php echo htmlspecialchars($email); ?></strong></p>

        <?php 
        if (isset($_GET['message']) && $_GET['message'] == 'resent') {
            echo "<div class='alert alert-success'>A new OTP has been sent to your email.</div>";
        } elseif (!empty($message)) {
            echo "<div class='alert alert-danger'>$message</div>";
        }
        ?>

        <form action="" method="POST">
            <div class="mb-3">
                <input type="text" name="otp" class="form-control text-center" maxlength="6" required placeholder="Enter OTP">
            </div>
            <button type="submit" class="btn btn-verify w-100 text-white">Verify OTP</button>
        </form>

        <a href="resend_otp.php" class="resend-link">Didn’t receive OTP? Resend</a>
    </div>
</body>
</html>
