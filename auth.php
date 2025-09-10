<?php
session_start();
include "db.php";

// Include PHPMailer manually
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';
require 'PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$message = "";

// Function to send OTP via Email
function sendOTP($email, $otp) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'tejankamara2021@gmail.com'; // your email
        $mail->Password = 'gaza hgmn tcci bpgo';       // your app password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->setFrom('tejankamara2021@gmail.com', 'Mammy Coker App');
        $mail->addAddress($email);

        $mail->Subject = "Your OTP for Mammy Coker App";
        $mail->Body    = "Your OTP for verification is: $otp. It is valid for 10 minutes.";

        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form_type = $_POST['form_type'];

    if ($form_type === 'login' && isset($_POST['login'])) {
        // -------- LOGIN --------
        $email    = $_POST['email'];
        $password = $_POST['password'];

        $stmt = $conn->prepare("SELECT id, name, email, password, role, is_verified, full_name FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();
        $stmt->bind_result($id, $name, $email_db, $hashed_password, $role, $is_verified, $full_name);
        $stmt->fetch();

        if ($stmt->num_rows > 0) {
            if (!$is_verified) {
                $message = "Your account is not verified. Please check your email.";
            } elseif (password_verify($password, $hashed_password)) {
                // Set session
                $_SESSION['user_id'] = (int)$id;
                $_SESSION['name']    = $name;
                $_SESSION['role']    = $role;

                // SELLER flow: profile → first gig → dashboard
                if ($role === 'seller') {
                    // If profile incomplete, finish it first
                    if (empty($full_name)) {
                        header("Location: sellerdashboard.php");
                        exit();
                    }

                    // Check whether seller has at least one gig
                    $gigCount = 0;
                    if ($gc = $conn->prepare("SELECT COUNT(*) FROM gigs WHERE seller_id = ?")) {
                        $gc->bind_param("i", $id);
                        $gc->execute();
                        $gc->bind_result($gigCount);
                        $gc->fetch();
                        $gc->close();
                    }

                    if ($gigCount > 0) {
                        // Seller already has a gig → seller dashboard
                        header("Location: sellerdashboard.php");
                        exit();
                    } else {
                        // No gigs yet → send to create-first-gig page (adjust filename if needed)
                        header("Location: create_gig.php");
                        exit();
                    }
                }

                // BUYER flow: go to buyer dashboard
                // (Clean redirect: NO query params like ?id= that might collide with other pages)
                header("Location: buyer_dashboard.php");
                exit();
            } else {
                $message = "Invalid email or password!";
            }
        } else {
            $message = "Invalid email or password!";
        }
        $stmt->close();
    }

    elseif ($form_type === 'register' && isset($_POST['register'])) {
        // -------- REGISTRATION --------
        $name     = $_POST['name'];
        $email    = $_POST['email'];
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $role     = $_POST['role'];
        $otp      = rand(100000, 999999);

        $checkEmail = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $checkEmail->bind_param("s", $email);
        $checkEmail->execute();
        $checkEmail->store_result();

        if ($checkEmail->num_rows > 0) {
            $message = "Email already exists!";
        } else {
            $stmt = $conn->prepare("INSERT INTO users (name, email, password, role, otp, is_verified) VALUES (?, ?, ?, ?, ?, 0)");
            $stmt->bind_param("sssss", $name, $email, $password, $role, $otp);

            if ($stmt->execute()) {
                sendOTP($email, $otp);
                $_SESSION['temp_email'] = $email;
                header("Location: verify_otp.php");
                exit();
            } else {
                $message = "Error: " . $stmt->error;
            }
            $stmt->close();
        }
        $checkEmail->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Authentication</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        .logo {
            width: 120px;
            margin-bottom: 20px;
            border-radius: 50%;
            object-fit: cover;
        }
    </style>
    <script>
        function toggleForm(form) {
            if (form === 'login') {
                document.getElementById('loginForm').style.display = 'block';
                document.getElementById('registerForm').style.display = 'none';
            } else {
                document.getElementById('loginForm').style.display = 'none';
                document.getElementById('registerForm').style.display = 'block';
            }
        }
        window.onload = function () {
            const urlParams = new URLSearchParams(window.location.search);
            const action = urlParams.get('action');
            if (action === 'register') {
                toggleForm('register');
            } else {
                toggleForm('login');
            }
        }
    </script>
</head>
<body class="container d-flex justify-content-center align-items-center" style="height:100vh;">
    <div class="card p-4 shadow" style="width: 400px;">
        <img src="images/logo.jpeg" alt="Mammy Coker Logo" class="logo mx-auto d-block">
        <h2 class="text-center">Welcome</h2>
        <?php if (!empty($message)) echo "<div class='alert alert-info'>$message</div>"; ?>

        <!-- Login Form -->
        <form id="loginForm" action="auth.php?action=login" method="POST">
            <input type="hidden" name="form_type" value="login">
            <h4 class="text-center">Login</h4>
            <div class="mb-3">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <button type="submit" name="login" class="btn btn-primary w-100">Login</button>
            <p class="mt-3 text-center">
                Don't have an account? <a href="auth.php?action=register">Register</a>
            </p>
        </form>

        <!-- Registration Form -->
        <form id="registerForm" action="auth.php?action=register" method="POST" style="display: none;">
            <input type="hidden" name="form_type" value="register">
            <h4 class="text-center">Register</h4>
            <div class="mb-3">
                <label class="form-label">Name</label>
                <input type="text" name="name" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Role</label>
                <select name="role" class="form-control" required>
                    <option value="buyer">Buyer</option>
                    <option value="seller">Seller</option>
                </select>
            </div>
            <button type="submit" name="register" class="btn btn-success w-100">Register</button>
            <p class="mt-3 text-center">
                Already have an account? <a href="auth.php?action=login">Login</a>
            </p>
        </form>
    </div>
</body>
</html>
