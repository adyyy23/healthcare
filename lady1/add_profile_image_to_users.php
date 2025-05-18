<?php
require_once 'config/database.php';

try {
    // Add profile_image column to users table
    $sql = "ALTER TABLE users 
            ADD COLUMN IF NOT EXISTS profile_image VARCHAR(255) DEFAULT NULL";

    $pdo->exec($sql);
    echo "Profile image column added successfully!";
} catch(PDOException $e) {
    echo "Error adding column: " . $e->getMessage();
}
?> 