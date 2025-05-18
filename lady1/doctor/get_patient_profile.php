<?php
require_once '../config/database.php';
header('Content-Type: application/json');

if (!isset($_GET['user_id'])) {
    echo json_encode(['error' => 'Missing user_id']);
    exit;
}

$user_id = intval($_GET['user_id']);

$stmt = $pdo->prepare('
    SELECT u.name, u.email, p.phone_number, p.address, p.gender, p.date_of_birth, p.medical_history
    FROM users u
    JOIN patients p ON u.id = p.user_id
    WHERE u.id = ?
');
$stmt->execute([$user_id]);
$profile = $stmt->fetch(PDO::FETCH_ASSOC);

if ($profile) {
    echo json_encode($profile);
} else {
    echo json_encode(['error' => 'Patient not found']);
} 