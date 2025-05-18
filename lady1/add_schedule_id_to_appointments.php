<?php
require_once 'config/database.php';

try {
    // Add schedule_id column to appointments table
    $sql = "ALTER TABLE appointments 
            ADD COLUMN IF NOT EXISTS schedule_id INT NOT NULL AFTER doctor_id,
            ADD FOREIGN KEY (schedule_id) REFERENCES doctor_schedules(id) ON DELETE CASCADE";

    $pdo->exec($sql);
    echo "Schedule ID column added successfully!";
} catch(PDOException $e) {
    echo "Error adding column: " . $e->getMessage();
}
?> 