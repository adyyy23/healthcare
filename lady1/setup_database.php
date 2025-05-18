<?php
require_once 'config/database.php';

try {
    $pdo->beginTransaction();

    // Create users table if it doesn't exist
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT PRIMARY KEY AUTO_INCREMENT,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(100) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        role ENUM('admin', 'doctor', 'patient') NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Create doctors table if it doesn't exist
    $pdo->exec("CREATE TABLE IF NOT EXISTS doctors (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NOT NULL,
        specialization VARCHAR(100) NOT NULL,
        license_number VARCHAR(50) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");

    // Create patients table if it doesn't exist
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

    // Create doctor_schedules table if it doesn't exist
    $pdo->exec("CREATE TABLE IF NOT EXISTS doctor_schedules (
        id INT PRIMARY KEY AUTO_INCREMENT,
        doctor_id INT NOT NULL,
        date DATE NOT NULL,
        start_time TIME NOT NULL,
        end_time TIME NOT NULL,
        status ENUM('available', 'booked', 'cancelled') DEFAULT 'available',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE
    )");

    // Create appointments table if it doesn't exist
    $pdo->exec("CREATE TABLE IF NOT EXISTS appointments (
        id INT PRIMARY KEY AUTO_INCREMENT,
        patient_id INT NOT NULL,
        doctor_id INT NOT NULL,
        schedule_id INT NOT NULL,
        reason TEXT NOT NULL,
        status ENUM('pending', 'approved', 'cancelled') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
        FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE,
        FOREIGN KEY (schedule_id) REFERENCES doctor_schedules(id) ON DELETE CASCADE
    )");

    $pdo->commit();
    echo "All tables created successfully!";
} catch (PDOException $e) {
    $pdo->rollBack();
    echo "Error creating tables: " . $e->getMessage();
}
?> 