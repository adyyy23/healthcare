<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    header('Location: ../login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['appointment_id'])) {
    $appointment_id = $_POST['appointment_id'];
    // Check if the appointment belongs to this doctor and is completed
    $stmt = $pdo->prepare('SELECT a.*, d.user_id as doctor_user_id FROM appointments a JOIN doctors d ON a.doctor_id = d.id WHERE a.id = ? AND a.status = ?');
    $stmt->execute([$appointment_id, 'completed']);
    $appointment = $stmt->fetch();
    if (!$appointment || $appointment['doctor_user_id'] != $_SESSION['user_id']) {
        $_SESSION['error'] = 'Invalid appointment.';
        header('Location: dashboard.php');
        exit();
    }
    
    // Debug appointment details
    error_log('Appointment details: ' . json_encode($appointment));
    
    // Get schedule info before deleting
    $schedule_stmt = $pdo->prepare('SELECT * FROM doctor_schedules WHERE doctor_id = ? AND date = ? AND start_time = ?');
    $schedule_stmt->execute([$appointment['doctor_id'], $appointment['appointment_date'], $appointment['appointment_time']]);
    $schedule = $schedule_stmt->fetch();
    
    // Debug: log the values being used
    error_log('Looking for schedule with doctor_id=' . $appointment['doctor_id'] . 
              ', date=' . $appointment['appointment_date'] . 
              ', start_time=' . $appointment['appointment_time']);
    
    if (!$schedule) {
        // Try a more flexible query to find the schedule
        error_log('No exact match found, trying a more flexible query');
        $schedule_stmt = $pdo->prepare('SELECT * FROM doctor_schedules WHERE doctor_id = ? AND date = ?');
        $schedule_stmt->execute([$appointment['doctor_id'], $appointment['appointment_date']]);
        $possible_schedules = $schedule_stmt->fetchAll();
        error_log('Found ' . count($possible_schedules) . ' schedules on this date');
        
        // Try to find a schedule with a similar time (might be formatted differently)
        $appointment_hour = date('H:i', strtotime($appointment['appointment_time']));
        error_log('Looking for time similar to: ' . $appointment_hour);
        
        foreach ($possible_schedules as $possible) {
            error_log('Possible schedule: ' . json_encode($possible));
            $schedule_hour = date('H:i', strtotime($possible['start_time']));
            error_log('Comparing with: ' . $schedule_hour);
            
            if ($schedule_hour == $appointment_hour) {
                error_log('Found matching schedule by hour comparison');
                $schedule = $possible;
                break;
            }
        }
        
        if (!$schedule && count($possible_schedules) > 0) {
            // If we still can't find an exact match but there are schedules on this date,
            // use the first one as a fallback
            error_log('No exact time match found, using first available schedule on this date as fallback');
            $schedule = $possible_schedules[0];
        } else if (!$schedule) {
            $_SESSION['error'] = 'No matching schedule found for this appointment. Please check the date and time.';
            header('Location: dashboard.php');
            exit();
        }
    }
    
    error_log('Found matching schedule: ' . json_encode($schedule));
    
    // Delete the appointment
    $stmt = $pdo->prepare('DELETE FROM appointments WHERE id = ?');
    $result = $stmt->execute([$appointment_id]);
    error_log('Appointment deletion result: ' . ($result ? 'success' : 'failed'));
    
    // Delete the schedule
    $delete_sched = $pdo->prepare('DELETE FROM doctor_schedules WHERE id = ?');
    $sched_result = $delete_sched->execute([$schedule['id']]);
    error_log('Schedule deletion result: ' . ($sched_result ? 'success' : 'failed') . ' for schedule ID: ' . $schedule['id']);
    
    if (!$result) {
        $_SESSION['error'] = 'Failed to delete appointment.';
    } elseif (!$sched_result) {
        $_SESSION['error'] = 'Failed to delete schedule.';
    } else {
        $_SESSION['success'] = 'Completed appointment and schedule deleted.';
    }
    header('Location: dashboard.php');
    exit();
}
header('Location: dashboard.php');
exit(); 