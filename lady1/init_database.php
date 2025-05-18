<?php
require_once 'config/database.php';

try {
    // Drop existing tables in reverse order of dependencies
    $pdo->exec("DROP TABLE IF EXISTS notifications");
    $pdo->exec("DROP TABLE IF EXISTS messages");
    $pdo->exec("DROP TABLE IF EXISTS appointments");
    $pdo->exec("DROP TABLE IF EXISTS doctor_schedules");
    $pdo->exec("DROP TABLE IF EXISTS patients");
    $pdo->exec("DROP TABLE IF EXISTS doctors");
    $pdo->exec("DROP TABLE IF EXISTS users");

    // Create users table
    $pdo->exec("CREATE TABLE users (
        id INT PRIMARY KEY AUTO_INCREMENT,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(100) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        role ENUM('admin', 'doctor', 'patient') NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Create doctors table
    $pdo->exec("CREATE TABLE doctors (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT,
        specialization VARCHAR(100),
        license_number VARCHAR(50),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");

    // Create patients table with medical_history
    $pdo->exec("CREATE TABLE patients (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT,
        date_of_birth DATE,
        gender ENUM('male', 'female', 'other'),
        address TEXT,
        phone_number VARCHAR(20),
        medical_history TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");

    // Create doctor_schedules table
    $pdo->exec("CREATE TABLE doctor_schedules (
        id INT PRIMARY KEY AUTO_INCREMENT,
        doctor_id INT,
        date DATE NOT NULL,
        start_time TIME NOT NULL,
        end_time TIME NOT NULL,
        status ENUM('available', 'booked', 'cancelled') DEFAULT 'available',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE
    )");

    // Create appointments table
    $pdo->exec("CREATE TABLE appointments (
        id INT PRIMARY KEY AUTO_INCREMENT,
        patient_id INT,
        doctor_id INT,
        schedule_id INT,
        status ENUM('pending', 'approved', 'cancelled', 'completed') DEFAULT 'pending',
        reason TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
        FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE,
        FOREIGN KEY (schedule_id) REFERENCES doctor_schedules(id) ON DELETE CASCADE
    )");

    // Create messages table
    $pdo->exec("CREATE TABLE messages (
        id INT PRIMARY KEY AUTO_INCREMENT,
        sender_id INT,
        receiver_id INT,
        message TEXT,
        is_read BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
    )");

    // Create notifications table
    $pdo->exec("CREATE TABLE notifications (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT,
        message TEXT,
        is_read BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");

    // Create default admin user
    $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'admin')");
    $stmt->execute(['Admin', 'admin@example.com', password_hash('admin123', PASSWORD_DEFAULT)]);

    echo "Database initialized successfully!";
} catch (PDOException $e) {
    echo "Error initializing database: " . $e->getMessage();
}
?> 