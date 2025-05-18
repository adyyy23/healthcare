<?php
require_once 'config/database.php';

try {
    // Add missing columns to doctors table
    $pdo->exec("ALTER TABLE doctors 
        ADD COLUMN IF NOT EXISTS address TEXT,
        ADD COLUMN IF NOT EXISTS bio TEXT,
        ADD COLUMN IF NOT EXISTS phone_number VARCHAR(20)");

    echo "Columns added successfully!";
} catch (PDOException $e) {
    echo "Error adding columns: " . $e->getMessage();
}
?> 