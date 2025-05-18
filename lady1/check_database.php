<?php
require_once 'config/database.php';

try {
    // Check database connection
    echo "Database connection successful!<br>";
    
    // Check if tables exist
    $tables = ['users', 'doctors', 'doctor_schedules', 'appointments', 'messages', 'notifications'];
    
    foreach ($tables as $table) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() > 0) {
            echo "Table '$table' exists.<br>";
        } else {
            echo "Table '$table' does not exist!<br>";
        }
    }
    
    // Check if admin exists
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'");
    $adminCount = $stmt->fetchColumn();
    echo "Number of admin users: $adminCount<br>";
    
    // Check if any doctors exist
    $stmt = $pdo->query("SELECT COUNT(*) FROM doctors");
    $doctorCount = $stmt->fetchColumn();
    echo "Number of doctors: $doctorCount<br>";
    
    // Check if any schedules exist
    $stmt = $pdo->query("SELECT COUNT(*) FROM doctor_schedules");
    $scheduleCount = $stmt->fetchColumn();
    echo "Number of schedules: $scheduleCount<br>";
    
    // Check if any appointments exist
    $stmt = $pdo->query("SELECT COUNT(*) FROM appointments");
    $appointmentCount = $stmt->fetchColumn();
    echo "Number of appointments: $appointmentCount<br>";
    
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?> 