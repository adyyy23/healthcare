<?php
require_once 'config/database.php';

try {
    // Add profile_picture column to doctors table if it doesn't exist
    $pdo->exec("ALTER TABLE doctors ADD COLUMN IF NOT EXISTS profile_picture VARCHAR(255)");

    // Add profile_picture column to patients table if it doesn't exist
    $pdo->exec("ALTER TABLE patients ADD COLUMN IF NOT EXISTS profile_picture VARCHAR(255)");

    echo "Profile picture columns added successfully!";
} catch (PDOException $e) {
    echo "Error adding columns: " . $e->getMessage();
}
?> 