<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_doctor'])) {
    $doctorId = $_POST['doctor_id'];

    try {
        $pdo->beginTransaction();

        // Get the user_id for this doctor
        $stmt = $pdo->prepare("SELECT user_id FROM doctors WHERE id = ?");
        $stmt->execute([$doctorId]);
        $doctor = $stmt->fetch();

        if ($doctor) {
            // Delete doctor record (this will cascade to related records due to foreign key constraints)
            $stmt = $pdo->prepare("DELETE FROM doctors WHERE id = ?");
            $stmt->execute([$doctorId]);

            // Delete user account
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$doctor['user_id']]);

            $pdo->commit();
            $_SESSION['success'] = "Doctor deleted successfully!";
        } else {
            throw new Exception("Doctor not found");
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Error deleting doctor: " . $e->getMessage();
    }
}

header('Location: manage_doctors.php');
exit(); 