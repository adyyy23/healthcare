<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not logged in']);
    exit();
}

try {
    // Get unread notifications count
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as unread_count 
        FROM notifications 
        WHERE user_id = ? AND is_read = FALSE
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'unread_count' => (int)$result['unread_count']
    ]);
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
?> 