<?php
session_start();
require_once '../config/database.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    error_log("Unauthorized access attempt - no user_id in session");
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

if (!isset($_GET['doctor_id'])) {
    error_log("Missing doctor_id parameter");
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Doctor ID is required']);
    exit();
}

try {
    $doctor_id = $_GET['doctor_id'];
    error_log("Processing request for doctor_id: " . $doctor_id);
    
    if (isset($_GET['date'])) {
        $date = $_GET['date'];
        error_log("Fetching schedules for doctor_id=$doctor_id, date=$date");
        $stmt = $pdo->prepare("
            SELECT ds.id, ds.date, ds.start_time, ds.end_time
            FROM doctor_schedules ds
            WHERE ds.doctor_id = ? 
            AND ds.date = ?
            AND ds.status = 'available'
            AND ds.date >= CURDATE()
            ORDER BY ds.start_time ASC
        ");
        $stmt->execute([$doctor_id, $date]);
    } else {
        error_log("Fetching all schedules for doctor_id=$doctor_id");
        $stmt = $pdo->prepare("
            SELECT ds.id, ds.date, ds.start_time, ds.end_time
            FROM doctor_schedules ds
            WHERE ds.doctor_id = ? 
            AND ds.status = 'available'
            AND ds.date >= CURDATE()
            ORDER BY ds.date ASC, ds.start_time ASC
        ");
        $stmt->execute([$doctor_id]);
    }
    
    $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
    error_log("Found " . count($schedules) . " schedules for doctor_id=$doctor_id");
    
    if (!$schedules) {
        error_log("No available schedules found for doctor_id=$doctor_id" . (isset($date) ? ", date=$date" : ""));
    }
    
    header('Content-Type: application/json');
    echo json_encode($schedules);
    
} catch (Exception $e) {
    error_log('Error in get_doctor_schedules.php: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    header('Content-Type: application/json');
    echo json_encode([
        'error' => 'Error fetching schedules: ' . $e->getMessage()
    ]);
}
?> 