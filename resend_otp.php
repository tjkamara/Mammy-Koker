<?php
session_start();
include "db.php";

// Include PHPMailer manually
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';
require 'PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (!isset($_SESSION['temp_email'])) {
    header("Location: auth.php");
    exit();
}

$email = $_SESSION['temp_email'];

// Function to send OTP via email
function sendOTP($email, $otp) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'tejankamara2021@gmail.com'; 
        $mail->Password = 'gaza hgmn tcci bpgo'; 
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->setFrom('tejankamara2021@gmail.com', 'Mammy Coker');
        $mail->addAddress($email);

        $mail->Subject = "Your New OTP for Mammy Coker";
        $mail->Body = "Your new OTP is: $otp. It is valid for 10 minutes.";

        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}

// Generate a new OTP
$new_otp = rand(100000, 999999);

// Update the database with the new OTP
$stmt = $conn->prepare("UPDATE users SET otp = ? WHERE email = ?");
$stmt->bind_param("ss", $new_otp, $email);
if ($stmt->execute()) {
    sendOTP($email, $new_otp);
    header("Location: verify_otp.php?message=resent");
    exit();
} else {
    echo "Error updating OTP!";
}
?>
