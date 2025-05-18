<?php
require_once 'config/database.php';

try {
    // Create users table if it doesn't exist
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT PRIMARY KEY AUTO_INCREMENT,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(100) NOT NULL UNIQUE,
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
        years_of_experience INT,
        profile_picture VARCHAR(255),
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
        allergies TEXT,
        blood_type VARCHAR(5),
        emergency_contact VARCHAR(100),
        emergency_phone VARCHAR(20),
        profile_picture VARCHAR(255),
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

    // Create messages table if it doesn't exist
    $pdo->exec("CREATE TABLE IF NOT EXISTS messages (
        id INT PRIMARY KEY AUTO_INCREMENT,
        sender_id INT NOT NULL,
        receiver_id INT NOT NULL,
        subject VARCHAR(255),
        message TEXT NOT NULL,
        is_read BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
    )");

    echo "Tables created successfully!";
} catch (PDOException $e) {
    echo "Error creating tables: " . $e->getMessage();
}
?> 