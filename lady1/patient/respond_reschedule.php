<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is patient
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header('Location: ../login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['appointment_id'], $_POST['action'])) {
    $appointment_id = $_POST['appointment_id'];
    $action = $_POST['action'];
    $user_id = $_SESSION['user_id'];

    try {
        // Start transaction
        $pdo->beginTransaction();

        // Get appointment and patient info
        $stmt = $pdo->prepare("
            SELECT a.*, p.user_id as patient_user_id, d.user_id as doctor_user_id
            FROM appointments a
            JOIN patients p ON a.patient_id = p.id
            JOIN doctors d ON a.doctor_id = d.id
            WHERE a.id = ? AND p.user_id = ?
        ");
        $stmt->execute([$appointment_id, $user_id]);
        $appointment = $stmt->fetch();

        if (!$appointment) {
            throw new Exception('Appointment not found or access denied.');
        }

        if ($action === 'agree') {
            // Patient agrees to reschedule: update appointment status to approved
            $stmt = $pdo->prepare("
                UPDATE appointments
                SET status = 'approved',
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ? AND patient_id = ?
            ");
            $stmt->execute([$appointment_id, $appointment['patient_id']]);

            // Update notification to read and create a new notification for doctor
            $stmt = $pdo->prepare("
                UPDATE notifications
                SET is_read = TRUE
                WHERE user_id = ? AND related_id = ? AND type = 'action'
            ");
            $stmt->execute([$user_id, $appointment_id]);

            $notification_message = 'Patient has agreed to the rescheduled appointment.';
            $stmt = $pdo->prepare("
                INSERT INTO notifications (user_id, type, message, related_id)
                VALUES (?, 'appointment', ?, ?)
            ");
            $stmt->execute([$appointment['doctor_user_id'], $notification_message, $appointment_id]);

        } elseif ($action === 'cancel') {
            // Patient cancels the appointment
            $stmt = $pdo->prepare("
                UPDATE appointments
                SET status = 'cancelled',
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ? AND patient_id = ?
            ");
            $stmt->execute([$appointment_id, $appointment['patient_id']]);

            // Update notification to read and create a new notification for doctor
            $stmt = $pdo->prepare("
                UPDATE notifications
                SET is_read = TRUE
                WHERE user_id = ? AND related_id = ? AND type = 'action'
            ");
            $stmt->execute([$user_id, $appointment_id]);

            $notification_message = 'Patient has cancelled the appointment.';
            $stmt = $pdo->prepare("
                INSERT INTO notifications (user_id, type, message, related_id)
                VALUES (?, 'appointment', ?, ?)
            ");
            $stmt->execute([$appointment['doctor_user_id'], $notification_message, $appointment_id]);

        } else {
            throw new Exception('Invalid action.');
        }

        // Commit transaction
        $pdo->commit();

        // Redirect back to notifications page with success message
        $_SESSION['success'] = 'Your response has been recorded.';
        header('Location: notifications.php');
        exit();

    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = 'Error: ' . $e->getMessage();
        header('Location: notifications.php');
        exit();
    }
} else {
    header('Location: notifications.php');
    exit();
}
?>
