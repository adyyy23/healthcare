<?php
require_once 'config/database.php';

try {
    // Create patients table
    $pdo->exec("CREATE TABLE IF NOT EXISTS patients (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NOT NULL,
        date_of_birth DATE,
        gender ENUM('male', 'female', 'other'),
        address TEXT,
        phone_number VARCHAR(20),
        medical_history TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");
    
    echo "Patients table created successfully!";
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?> 