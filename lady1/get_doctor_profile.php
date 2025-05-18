<?php
require_once 'config/database.php';
header('Content-Type: application/json');

$doctor_id = isset($_GET['doctor_id']) ? intval($_GET['doctor_id']) : 0;
if (!$doctor_id) {
    echo json_encode(['success' => false, 'error' => 'Invalid doctor ID.']);
    exit();
}

$stmt = $pdo->prepare("
    SELECT d.*, u.name, u.email
    FROM doctors d
    JOIN users u ON d.user_id = u.id
    WHERE d.id = ?
");
$stmt->execute([$doctor_id]);
$doctor = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$doctor) {
    echo json_encode(['success' => false, 'error' => 'Doctor profile not found.']);
    exit();
}

// Return doctor info as JSON
$doctor['success'] = true;
echo json_encode($doctor); 