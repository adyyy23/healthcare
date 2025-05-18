<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is patient
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header('Location: ../login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['appointment_id'])) {
    $appointment_id = intval($_POST['appointment_id']);
    $user_id = $_SESSION['user_id'];

    // Get the patient's id
    $stmt = $pdo->prepare("SELECT id FROM patients WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $patient = $stmt->fetch();
    if (!$patient) {
        header('Location: appointments.php?error=Patient+not+found');
        exit();
    }

    // Check if the appointment belongs to this patient
    $stmt = $pdo->prepare("SELECT * FROM appointments WHERE id = ? AND patient_id = ?");
    $stmt->execute([$appointment_id, $patient['id']]);
    $appointment = $stmt->fetch();
    if (!$appointment) {
        header('Location: appointments.php?error=Appointment+not+found');
        exit();
    }

    // Cancel the appointment
    $stmt = $pdo->prepare("UPDATE appointments SET status = 'cancelled' WHERE id = ?");
    $stmt->execute([$appointment_id]);

    header('Location: appointments.php?success=Appointment+cancelled');
    exit();
} else {
    header('Location: appointments.php?error=Invalid+request');
    exit();
}
?> 