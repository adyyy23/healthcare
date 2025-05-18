<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['sender_id']) || !isset($data['receiver_id']) || !isset($data['message'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields']);
    exit();
}

try {
    // Save message to database
    $stmt = $pdo->prepare("
        INSERT INTO messages (sender_id, receiver_id, message, created_at) 
        VALUES (?, ?, ?, NOW())
    ");
    
    if ($stmt->execute([$data['sender_id'], $data['receiver_id'], $data['message']])) {
        // Create notification for the receiver
        $stmt = $pdo->prepare("
            INSERT INTO notifications (user_id, type, message, related_id) 
            VALUES (?, 'message', 'You have a new message', ?)
        ");
        $stmt->execute([$data['receiver_id'], $pdo->lastInsertId()]);
        
        echo json_encode(['success' => true]);
    } else {
        throw new Exception('Failed to save message');
    }
} catch (Exception $e) {
    error_log("Error saving message: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save message']);
} 