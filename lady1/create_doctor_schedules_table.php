<?php
require_once 'config/database.php';

try {
    // Drop existing doctor_schedules table if it exists
    $pdo->exec("DROP TABLE IF EXISTS doctor_schedules");

    // Create new doctor_schedules table
    $sql = "CREATE TABLE doctor_schedules (
        id INT PRIMARY KEY AUTO_INCREMENT,
        doctor_id INT NOT NULL,
        date DATE NOT NULL,
        start_time TIME NOT NULL,
        end_time TIME NOT NULL,
        status ENUM('pending', 'available', 'booked', 'unavailable') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE
    )";

    $pdo->exec($sql);
    echo "Doctor schedules table created successfully";
} catch(PDOException $e) {
    echo "Error creating table: " . $e->getMessage();
}
?> 