<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'error' => 'Not authorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $receiver_id = $_POST['receiver_id'] ?? '';
    $message = $_POST['message'] ?? '';

    if (empty($receiver_id) || empty($message)) {
        echo json_encode(['success' => false, 'error' => 'Missing required fields']);
        exit();
    }

    try {
        // Insert message
        $stmt = $pdo->prepare("
            INSERT INTO messages (
                sender_id,
                receiver_id,
                message,
                created_at
            ) VALUES (?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $_SESSION['user_id'],
            $receiver_id,
            $message
        ]);

        // Create notification for the doctor
        $notification_message = "You have a new message from Admin";
        $stmt = $pdo->prepare("
            INSERT INTO notifications (
                user_id,
                type,
                message,
                related_id,
                is_read,
                created_at
            ) VALUES (
                ?,
                'message',
                ?,
                ?,
                FALSE,
                NOW()
            )
        ");
        $stmt->execute([$receiver_id, $notification_message, $_SESSION['user_id']]);

        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
}
?> 