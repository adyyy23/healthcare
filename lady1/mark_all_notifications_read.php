<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

// Mark all notifications as read
$stmt = $pdo->prepare("
    UPDATE notifications 
    SET is_read = TRUE 
    WHERE user_id = ? AND is_read = FALSE
");
$success = $stmt->execute([$_SESSION['user_id']]);

header('Content-Type: application/json');
echo json_encode(['success' => $success]); 