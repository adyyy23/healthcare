<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    header('Location: ../login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['appointment_id'], $_POST['date'], $_POST['time'])) {
    $appointment_id = $_POST['appointment_id'];
    $new_date = $_POST['date'];
    $new_time = $_POST['time'];

    // Get patient user_id
    $stmt = $pdo->prepare('SELECT a.*, p.user_id as patient_user_id FROM appointments a JOIN patients p ON a.patient_id = p.id WHERE a.id = ?');
    $stmt->execute([$appointment_id]);
    $appointment = $stmt->fetch();
    if (!$appointment) {
        $_SESSION['error'] = 'Appointment not found.';
        header('Location: appointments.php');
        exit();
    }
    $patient_user_id = $appointment['patient_user_id'];

    try {
        $pdo->beginTransaction();
        // Update appointment
        $stmt = $pdo->prepare('UPDATE appointments SET appointment_date = ?, appointment_time = ?, status = ? WHERE id = ?');
        $stmt->execute([$new_date, $new_time, 'pending_patient', $appointment_id]);
        // Insert notification for patient
        $notif = "Your appointment has been rescheduled by the doctor. Please agree or cancel.";
        $stmt = $pdo->prepare('INSERT INTO notifications (user_id, message, type, related_id, created_at) VALUES (?, ?, ?, ?, NOW())');
        $stmt->execute([$patient_user_id, $notif, 'action', $appointment_id]);
        $pdo->commit();
        $_SESSION['success'] = 'Appointment rescheduled and patient notified.';
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = 'Error: ' . $e->getMessage();
    }
    header('Location: appointments.php');
    exit();
}
header('Location: appointments.php');
exit(); 