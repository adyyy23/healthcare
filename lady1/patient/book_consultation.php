<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is patient
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header('Location: ../login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();

        // Get patient ID
        $stmt = $pdo->prepare("SELECT id FROM patients WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $patient = $stmt->fetch();

        // Validate schedule availability
        $stmt = $pdo->prepare("
            SELECT ds.*, d.user_id as doctor_user_id
            FROM doctor_schedules ds
            JOIN doctors d ON ds.doctor_id = d.id
            WHERE ds.id = ? 
            AND ds.is_available = 1
            AND NOT EXISTS (
                SELECT 1 FROM appointments a 
                WHERE a.schedule_id = ds.id
            )
        ");
        $stmt->execute([$_POST['schedule_time']]);
        $schedule = $stmt->fetch();

        if (!$schedule) {
            throw new Exception('Selected time slot is no longer available.');
        }

        // Create appointment
        $stmt = $pdo->prepare("
            INSERT INTO appointments (
                patient_id, doctor_id, schedule_id, service_id,
                reason, status, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, 'pending', NOW(), NOW())
        ");
        $stmt->execute([
            $patient['id'],
            $schedule['doctor_id'],
            $schedule['id'],
            $_POST['service'],
            $_POST['message']
        ]);

        $appointment_id = $pdo->lastInsertId();

        // Create notification for doctor
        $stmt = $pdo->prepare("
            INSERT INTO notifications (
                user_id, title, message, type, reference_id, created_at
            ) VALUES (?, ?, ?, 'appointment', ?, NOW())
        ");
        
        $stmt->execute([
            $schedule['doctor_user_id'],
            'New Appointment Request',
            'You have a new appointment request from ' . $_POST['name'],
            $appointment_id
        ]);

        $pdo->commit();
        $_SESSION['success'] = 'Appointment booked successfully!';
        header('Location: appointments.php');
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = $e->getMessage();
        header('Location: dashboard.php');
        exit();
    }
} else {
    header('Location: dashboard.php');
    exit();
} 