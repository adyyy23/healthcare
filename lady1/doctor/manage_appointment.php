<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is doctor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    header('Location: ../login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();

        $appointment_id = $_POST['appointment_id'];
        $action = $_POST['action'];
        $notes = $_POST['notes'] ?? null;

        // Get appointment details
        $stmt = $pdo->prepare("
            SELECT a.*, p.user_id as patient_user_id, d.user_id as doctor_user_id
            FROM appointments a
            JOIN patients p ON a.patient_id = p.id
            JOIN doctors d ON a.doctor_id = d.id
            WHERE a.id = ?
        ");
        $stmt->execute([$appointment_id]);
        $appointment = $stmt->fetch();

        if (!$appointment) {
            throw new Exception('Appointment not found');
        }

        if ($appointment['doctor_user_id'] !== $_SESSION['user_id']) {
            throw new Exception('Unauthorized access');
        }

        // Update appointment status
        switch ($action) {
            case 'approve':
                $stmt = $pdo->prepare("
                    UPDATE appointments 
                    SET status = 'approved', notes = ? 
                    WHERE id = ?
                ");
                $stmt->execute([$notes, $appointment_id]);

                // Create notification for patient
                $stmt = $pdo->prepare("
                    INSERT INTO notifications (user_id, message, type) 
                    VALUES (?, ?, 'success')
                ");
                $stmt->execute([
                    $appointment['patient_user_id'],
                    'Your appointment has been approved. ' . ($notes ? "Note: $notes" : '')
                ]);
                break;

            case 'disapprove':
                if (empty($notes)) {
                    throw new Exception('Please provide a reason for disapproval');
                }

                $stmt = $pdo->prepare("
                    UPDATE appointments 
                    SET status = 'cancelled', notes = ? 
                    WHERE id = ?
                ");
                $stmt->execute([$notes, $appointment_id]);

                // Create notification for patient
                $stmt = $pdo->prepare("
                    INSERT INTO notifications (user_id, message, type) 
                    VALUES (?, ?, 'danger')
                ");
                $stmt->execute([
                    $appointment['patient_user_id'],
                    'Your appointment has been cancelled. Reason: ' . $notes
                ]);
                break;

            default:
                throw new Exception('Invalid action');
        }

        $pdo->commit();
        $_SESSION['success'] = 'Appointment updated successfully';
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = $e->getMessage();
    }
}

header('Location: dashboard.php');
exit(); 