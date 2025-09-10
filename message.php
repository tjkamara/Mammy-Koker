<?php
session_start();
include "db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: auth.php");
    exit();
}

$current_user = $_SESSION['user_id'];
$receiver_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

if ($receiver_id === 0) {
    echo "No user selected.";
    exit();
}

// Mark messages as read
$conn->query("UPDATE messages SET is_read = 1 WHERE receiver_id = $current_user AND sender_id = $receiver_id");

// Handle new message
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $msg = trim($_POST['message']);
    $attachment_path = "";

    if (!empty($_FILES['attachment']['name'])) {
        $upload_dir = 'uploads/messages/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        $filename = time() . '_' . basename($_FILES['attachment']['name']);
        $target_file = $upload_dir . $filename;
        if (move_uploaded_file($_FILES['attachment']['tmp_name'], $target_file)) {
            $attachment_path = $target_file;
        }
    }

    if (!empty($msg) || !empty($attachment_path)) {
        $stmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, message, attachment) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iiss", $current_user, $receiver_id, $msg, $attachment_path);
        $stmt->execute();
    }
    exit(); // Prevent reloading the page
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Chat</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/emoji-button@4.6.2/dist/index.min.js"></script>
    <style>
        .chat-box {
            height: 70vh;
            overflow-y: auto;
            border: 1px solid #ddd;
            padding: 15px;
            background-color: #fff;
        }
        .message {
            margin-bottom: 12px;
        }
        .message.me {
            text-align: right;
        }
        .attachment-preview {
            max-height: 150px;
            border-radius: 6px;
        }
        .emoji-btn {
            background: none;
            border: none;
            font-size: 1.3rem;
            cursor: pointer;
        }
        
                /*mobile friendly part*/
            @media screen and (max-width: 768px) {
  .sidebar {
    display: none;
  }

  .content {
    width: 100%;
    padding: 10px;
  }
}
    </style>
</head>
<body class="bg-light">

<div class="container py-4">
    <div class="card shadow">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="bi bi-chat-dots-fill me-2"></i>Chat</h5>
            <a href="chat.php" class="btn btn-sm btn-light">← Back</a>
        </div>
        <div class="card-body chat-box" id="chat-box"></div>

        <form id="messageForm" method="POST" enctype="multipart/form-data" class="p-3 d-flex gap-2 align-items-center">
            <input type="text" name="message" id="messageInput" class="form-control" placeholder="Type a message...">
            <input type="file" name="attachment" class="form-control form-control-sm" style="max-width: 180px;">
            <button type="button" id="emojiTrigger" class="emoji-btn"><i class="bi bi-emoji-smile"></i></button>
            <button type="submit" class="btn btn-success">Send</button>
        </form>
    </div>
</div>

<script>
    const chatBox = document.getElementById('chat-box');
    const form = document.getElementById('messageForm');
    const receiverId = <?= $receiver_id ?>;

    // Load messages
    function loadMessages() {
        fetch('fetch_messages.php?user_id=' + receiverId)
            .then(res => res.text())
            .then(data => {
                chatBox.innerHTML = data;
                chatBox.scrollTop = chatBox.scrollHeight;
            });
    }

    // Submit form via AJAX
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        const formData = new FormData(form);

        fetch('message.php?user_id=' + receiverId, {
            method: 'POST',
            body: formData
        }).then(() => {
            form.reset();
            loadMessages();
        });
    });

    // Poll every 3 seconds
    setInterval(loadMessages, 3000);
    loadMessages();

    // Emoji Picker
    const button = document.querySelector('#emojiTrigger');
    const input = document.querySelector('#messageInput');
    const picker = new EmojiButton();
    picker.on('emoji', emoji => {
        input.value += emoji;
    });
    button.addEventListener('click', () => picker.togglePicker(button));
</script>

</body>
</html>
