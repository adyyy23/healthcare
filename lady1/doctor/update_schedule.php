<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    header('Location: ../login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $schedule_id = $_POST['schedule_id'];
        $status = $_POST['status'];

        // Validate status
        if (!in_array($status, ['available', 'unavailable'])) {
            throw new Exception('Invalid status');
        }

        // Get doctor ID
        $stmt = $pdo->prepare("SELECT id FROM doctors WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $doctor = $stmt->fetch();

        if (!$doctor) {
            throw new Exception('Doctor profile not found');
        }

        // Update schedule status
        $stmt = $pdo->prepare("
            UPDATE doctor_schedules 
            SET status = ? 
            WHERE id = ? AND doctor_id = ?
        ");
        $stmt->execute([$status, $schedule_id, $doctor['id']]);

        if ($stmt->rowCount() === 0) {
            throw new Exception('Schedule not found or unauthorized');
        }

        $_SESSION['success'] = 'Schedule updated successfully';
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
}

header('Location: dashboard.php');
exit(); 