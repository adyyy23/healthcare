<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

// Get notification ID from POST data
$notification_id = $_POST['notification_id'] ?? null;

if ($notification_id) {
    try {
        // Mark notification as read
        $stmt = $pdo->prepare("
            UPDATE notifications 
            SET is_read = TRUE 
            WHERE id = ? AND user_id = ?
        ");
        
        if ($stmt->execute([$notification_id, $_SESSION['user_id']])) {
            // Get updated unread count
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as unread_count 
                FROM notifications 
                WHERE user_id = ? AND is_read = FALSE
            ");
            $stmt->execute([$_SESSION['user_id']]);
            $result = $stmt->fetch();
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'unread_count' => $result['unread_count']
            ]);
        } else {
            throw new Exception('Failed to mark notification as read');
        }
    } catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
} else {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'No notification ID provided'
    ]);
} 