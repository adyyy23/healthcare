<?php
session_start();
require_once '../config/database.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in and is doctor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    header('Location: ../login.php');
    exit();
}

// Get doctor's ID
$stmt = $pdo->prepare("SELECT id FROM doctors WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$doctor = $stmt->fetch();

if (!$doctor) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Doctor profile not found']);
    exit();
}

$doctor_id = $doctor['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['appointment_id'], $_POST['status'])) {
    $appointment_id = $_POST['appointment_id'];
    $status = $_POST['status'];
    // Only allow approved or disapproved
    if (!in_array($status, ['approved', 'disapproved'])) {
        $_SESSION['error'] = 'Invalid status.';
        header('Location: dashboard.php');
        exit();
    }
    // Check if the appointment belongs to this doctor
    $stmt = $pdo->prepare('SELECT a.*, d.user_id as doctor_user_id FROM appointments a JOIN doctors d ON a.doctor_id = d.id WHERE a.id = ?');
    $stmt->execute([$appointment_id]);
    $appointment = $stmt->fetch();
    if (!$appointment || $appointment['doctor_user_id'] != $_SESSION['user_id']) {
        $_SESSION['error'] = 'Invalid appointment.';
        header('Location: dashboard.php');
        exit();
    }
    $stmt = $pdo->prepare('UPDATE appointments SET status = ? WHERE id = ?');
    $stmt->execute([$status, $appointment_id]);
    $_SESSION['success'] = 'Appointment status updated.';
    header('Location: dashboard.php');
    exit();
}

header('Location: dashboard.php');
exit();

try {
    // Start transaction
    $pdo->beginTransaction();

    // Get appointment details
    $stmt = $pdo->prepare("
        SELECT a.*, p.user_id as patient_user_id, d.user_id as doctor_user_id
        FROM appointments a
        JOIN patients p ON a.patient_id = p.id
        JOIN doctors d ON a.doctor_id = d.id
        WHERE a.id = ? AND a.doctor_id = ?
    ");
    $stmt->execute([$appointment_id, $doctor_id]);
    $appointment = $stmt->fetch();

    if (!$appointment) {
        throw new Exception('Appointment not found');
    }

    // Update appointment status
    $stmt = $pdo->prepare("
        UPDATE appointments 
        SET status = ?, 
            updated_at = CURRENT_TIMESTAMP 
        WHERE id = ? AND doctor_id = ?
    ");
    $stmt->execute([$status, $appointment_id, $doctor_id]);

    // Create notification for patient
    $notification_message = '';
    $notification_type = 'appointment';
    switch ($status) {
        case 'approved':
            $notification_message = 'Your appointment has been approved';
            break;
        case 'disapproved':
            $notification_message = 'Your appointment has been disapproved';
            break;
        case 'pending':
            $notification_message = 'Your appointment status has been updated to pending';
            break;
        case 'rescheduled':
            $notification_message = 'Your appointment has been rescheduled. Please review and respond.';
            $notification_type = 'action';
            break;
    }

    $stmt = $pdo->prepare("
        INSERT INTO notifications (user_id, type, message, related_id) 
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$appointment['patient_user_id'], $notification_type, $notification_message, $appointment_id]);

    // Commit transaction
    $pdo->commit();

    header('Content-Type: application/json');
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    // Rollback transaction on error
    $pdo->rollBack();
    
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?> 