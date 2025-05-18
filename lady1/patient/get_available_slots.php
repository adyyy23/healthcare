<?php
require_once '../config/database.php';

header('Content-Type: application/json');

if (!isset($_GET['doctor_id']) || !isset($_GET['date'])) {
    echo json_encode(['error' => 'Missing required parameters']);
    exit;
}

$doctor_id = $_GET['doctor_id'];
$date = $_GET['date'];

// Validate date format
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    echo json_encode(['error' => 'Invalid date format']);
    exit;
}

try {
    // Get doctor's schedule for the selected date
    $stmt = $pdo->prepare("
        SELECT start_time, end_time 
        FROM doctor_schedules 
        WHERE doctor_id = ? 
        AND date = ?
        AND status = 'available'
        ORDER BY start_time
    ");
    $stmt->execute([$doctor_id, $date]);
    $schedule = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$schedule) {
        echo json_encode(['error' => 'No schedule available for this date']);
        exit;
    }

    // Get all booked appointments for the selected date
    $stmt = $pdo->prepare("
        SELECT appointment_time 
        FROM appointments 
        WHERE doctor_id = ? 
        AND appointment_date = ? 
        AND status != 'cancelled'
    ");
    $stmt->execute([$doctor_id, $date]);
    $bookedSlots = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Generate available time slots based on doctor's schedule
    $timeSlots = [];
    $startTime = strtotime($schedule['start_time']);
    $endTime = strtotime($schedule['end_time']);
    $interval = 30 * 60; // 30 minutes

    for ($time = $startTime; $time < $endTime; $time += $interval) {
        $timeSlot = date('H:i:s', $time);
        
        // Only include slots that aren't booked
        if (!in_array($timeSlot, $bookedSlots)) {
            $timeSlots[] = $timeSlot;
        }
    }

    echo json_encode($timeSlots);

} catch (PDOException $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?> 