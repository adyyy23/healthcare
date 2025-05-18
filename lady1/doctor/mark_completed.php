<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    header('Location: ../login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['appointment_id'])) {
    $appointment_id = $_POST['appointment_id'];
    // Check if the appointment belongs to this doctor
    $stmt = $pdo->prepare('SELECT a.*, d.user_id as doctor_user_id, p.user_id as patient_user_id FROM appointments a JOIN doctors d ON a.doctor_id = d.id JOIN patients p ON a.patient_id = p.id WHERE a.id = ?');
    $stmt->execute([$appointment_id]);
    $appointment = $stmt->fetch();
    if (!$appointment || $appointment['doctor_user_id'] != $_SESSION['user_id']) {
        $_SESSION['error'] = 'Invalid appointment.';
        header('Location: appointments.php');
        exit();
    }
    $stmt = $pdo->prepare('UPDATE appointments SET status = ? WHERE id = ?');
    $stmt->execute(['completed', $appointment_id]);

    // Also update the corresponding doctor schedule to completed
    $stmt = $pdo->prepare('UPDATE doctor_schedules SET status = ? WHERE doctor_id = ? AND date = ? AND start_time = ?');
    $stmt->execute(['completed', $appointment['doctor_id'], $appointment['appointment_date'], $appointment['appointment_time']]);

    // Notify patient
    $notif = "Your appointment has been marked as completed by the doctor.";
    $stmt = $pdo->prepare('INSERT INTO notifications (user_id, message, type, related_id, created_at) VALUES (?, ?, ?, ?, NOW())');
    $stmt->execute([$appointment['patient_user_id'], $notif, 'info', $appointment_id]);
    $_SESSION['success'] = 'Appointment marked as completed.';
    header('Location: appointments.php');
    exit();
}
header('Location: appointments.php');
exit(); 