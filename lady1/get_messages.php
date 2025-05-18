<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id']) || !isset($_GET['other_user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid request']);
    exit();
}

try {
    // Get messages between the current user and the other user
    $stmt = $pdo->prepare("
        SELECT 
            m.*,
            sender.username as sender_name,
            receiver.username as receiver_name,
            DATE_FORMAT(m.created_at, '%h:%i %p') as formatted_time
        FROM messages m
        JOIN users sender ON m.sender_id = sender.id
        JOIN users receiver ON m.receiver_id = receiver.id
        WHERE (m.sender_id = ? AND m.receiver_id = ?)
           OR (m.sender_id = ? AND m.receiver_id = ?)
        ORDER BY m.created_at ASC
    ");
    
    $stmt->execute([
        $_SESSION['user_id'],
        $_GET['other_user_id'],
        $_GET['other_user_id'],
        $_SESSION['user_id']
    ]);
    
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Mark messages as read
    $updateStmt = $pdo->prepare("
        UPDATE messages 
        SET is_read = TRUE 
        WHERE receiver_id = ? AND sender_id = ? AND is_read = FALSE
    ");
    $updateStmt->execute([$_SESSION['user_id'], $_GET['other_user_id']]);
    
    header('Content-Type: application/json');
    echo json_encode(['messages' => $messages]);
} catch(PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => $e->getMessage()]);
}
?> 