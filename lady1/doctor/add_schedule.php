<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    header('Location: ../login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Get doctor ID
        $stmt = $pdo->prepare("SELECT id FROM doctors WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $doctor = $stmt->fetch();

        if (!$doctor) {
            throw new Exception('Doctor profile not found');
        }

        $date = $_POST['date'];
        $startTime = $_POST['start_time'];
        $endTime = $_POST['end_time'];

        // Validate date and times
        if (strtotime($date) < strtotime('today')) {
            throw new Exception('Cannot add schedules for past dates');
        }

        if (strtotime($startTime) >= strtotime($endTime)) {
            throw new Exception('End time must be after start time');
        }

        // Check for overlapping schedules
        $stmt = $pdo->prepare("
            SELECT * FROM doctor_schedules 
            WHERE doctor_id = ? 
            AND date = ? 
            AND (
                (start_time <= ? AND end_time > ?) OR
                (start_time < ? AND end_time >= ?) OR
                (start_time >= ? AND end_time <= ?)
            )
        ");
        $stmt->execute([
            $doctor['id'], 
            $date, 
            $startTime, $startTime,
            $endTime, $endTime,
            $startTime, $endTime
        ]);

        if ($stmt->fetch()) {
            throw new Exception('Schedule overlaps with existing schedule');
        }

        // Add new schedule
        $stmt = $pdo->prepare("
            INSERT INTO doctor_schedules (doctor_id, date, start_time, end_time, status) 
            VALUES (?, ?, ?, ?, 'available')
        ");
        $stmt->execute([$doctor['id'], $date, $startTime, $endTime]);

        $_SESSION['success'] = 'Schedule added successfully';
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
}

header('Location: dashboard.php');
exit(); 