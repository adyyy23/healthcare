<?php
require_once 'config/database.php';

try {
    // Create reviews table
    $sql = "CREATE TABLE IF NOT EXISTS reviews (
        id INT PRIMARY KEY AUTO_INCREMENT,
        patient_id INT NOT NULL,
        doctor_id INT NOT NULL,
        rating INT NOT NULL CHECK (rating >= 1 AND rating <= 5),
        comment TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
        FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE
    )";

    $pdo->exec($sql);
    echo "Reviews table created successfully!";
} catch(PDOException $e) {
    echo "Error creating table: " . $e->getMessage();
}
?> 