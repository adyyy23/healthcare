<?php
require_once 'config/database.php';

try {
    // Create appointments table
    $sql = "CREATE TABLE IF NOT EXISTS appointments (
        id INT PRIMARY KEY AUTO_INCREMENT,
        patient_id INT NOT NULL,
        doctor_id INT NOT NULL,
        schedule_id INT NOT NULL,
        appointment_date DATE NOT NULL,
        appointment_time TIME NOT NULL,
        reason TEXT,
        symptoms TEXT,
        status ENUM('pending', 'approved', 'disapproved') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
        FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE,
        FOREIGN KEY (schedule_id) REFERENCES doctor_schedules(id) ON DELETE CASCADE
    )";

    $pdo->exec($sql);
    echo "Appointments table created successfully!";
} catch(PDOException $e) {
    echo "Error creating table: " . $e->getMessage();
}
?> 