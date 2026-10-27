<?php
session_start();
include 'db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$current_user = $_SESSION['username'];
$message = htmlspecialchars($_POST['message'] ?? '');
$image_path = null;

if (empty($message)) {
    echo json_encode(['success' => false, 'message' => 'Message cannot be empty']);
    exit();
}

// Handle Image Upload
if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
    if (!file_exists('uploads')) {
        mkdir('uploads', 0777, true);
    }
    $fileName = time() . '_' . basename($_FILES['image']['name']);
    $targetPath = 'uploads/' . $fileName;
    $fileType = strtolower(pathinfo($targetPath, PATHINFO_EXTENSION));

    $allowedTypes = array('jpg', 'jpeg', 'png', 'gif', 'webp');
    if (in_array($fileType, $allowedTypes)) {
        if (move_uploaded_file($_FILES['image']['tmp_name'], $targetPath)) {
            $image_path = $targetPath;
        }
    }
}

// Insert into DB
$stmt = $conn->prepare("INSERT INTO messages (name, message, image_path) VALUES (?, ?, ?)");
$stmt->bind_param("sss", $current_user, $message, $image_path);

if ($stmt->execute()) {
    $new_id = $stmt->insert_id;
    $stmt->close();

    // Send the new post details back as JSON
    echo json_encode([
        'success' => true,
        'post' => [
            'id' => $new_id,
            'name' => $current_user,
            'message' => $message,
            'image_path' => $image_path,
            'created_at' => date('M d, Y • h:i A')
        ]
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>