<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_doctor'])) {
    $doctorId = $_POST['doctor_id'];
    $name = $_POST['name'];
    $email = $_POST['email'];
    $specialization = $_POST['specialization'];
    $license_number = $_POST['license_number'];

    try {
        $pdo->beginTransaction();

        // Get the user_id for this doctor
        $stmt = $pdo->prepare("SELECT user_id FROM doctors WHERE id = ?");
        $stmt->execute([$doctorId]);
        $doctor = $stmt->fetch();

        if ($doctor) {
            // Update user information
            $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ? WHERE id = ?");
            $stmt->execute([$name, $email, $doctor['user_id']]);

            // Update doctor information
            $stmt = $pdo->prepare("UPDATE doctors SET specialization = ?, license_number = ? WHERE id = ?");
            $stmt->execute([$specialization, $license_number, $doctorId]);

            $pdo->commit();
            $_SESSION['success'] = "Doctor information updated successfully!";
        } else {
            throw new Exception("Doctor not found");
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Error updating doctor: " . $e->getMessage();
    }
}

header('Location: manage_doctors.php');
exit(); 