<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'seller') {
    header("Location: auth.php");
    exit();
}

$message = "";

// Fetch current profile info
$stmt = $conn->prepare("SELECT name, email, bio, skills, profile_image FROM users WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$stmt->bind_result($name, $email, $bio, $skills, $profile_image);
$stmt->fetch();
$stmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $bio = $_POST['bio'];
    $skills = $_POST['skills'];

    if (!empty($_FILES['profile_image']['name'])) {
        $target_dir = "uploads/profiles/";
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        $image_name = time() . '_' . basename($_FILES['profile_image']['name']);
        $target_path = $target_dir . $image_name;
        if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $target_path)) {
            $profile_image = $target_path;
        }
    }

    $stmt = $conn->prepare("UPDATE users SET name = ?, bio = ?, skills = ?, profile_image = ? WHERE id = ?");
    $stmt->bind_param("ssssi", $name, $bio, $skills, $profile_image, $_SESSION['user_id']);
    if ($stmt->execute()) {
        $message = "Profile updated successfully.";
    } else {
        $message = "Error: " . $stmt->error;
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Profile</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .profile-form {
            max-width: 500px;
            margin: 50px auto;
            padding: 20px;
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }
        .profile-pic {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: 50%;
            margin-bottom: 10px;
        }
    </style>
</head>
<body class="bg-light">
<div class="container">
    <div class="profile-form">
        <h4 class="text-center">Edit Profile</h4>
        <?php if ($message) echo "<div class='alert alert-info'>$message</div>"; ?>
        <form action="" method="POST" enctype="multipart/form-data">
            <div class="text-center mb-3">
                <?php if ($profile_image): ?>
                    <img src="<?php echo $profile_image; ?>" class="profile-pic" alt="Profile">
                <?php endif; ?>
            </div>
            <div class="mb-3">
                <label class="form-label">Name</label>
                <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($name); ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Email (readonly)</label>
                <input type="email" class="form-control" value="<?php echo htmlspecialchars($email); ?>" readonly>
            </div>
            <div class="mb-3">
                <label class="form-label">Bio</label>
                <textarea name="bio" class="form-control" rows="3"><?php echo htmlspecialchars($bio); ?></textarea>
            </div>
            <div class="mb-3">
                <label class="form-label">Skills (comma separated)</label>
                <input type="text" name="skills" class="form-control" value="<?php echo htmlspecialchars($skills); ?>">
            </div>
            <div class="mb-3">
                <label class="form-label">Profile Image</label>
                <input type="file" name="profile_image" class="form-control">
            </div>
            <button type="submit" class="btn btn-primary w-100">Save Changes</button>
        </form>
    </div>
</div>
</body>
</html>
