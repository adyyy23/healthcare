<?php
require_once 'config/database.php';

try {
    // Remove profile_image column from users table
    $sql = "ALTER TABLE users 
            DROP COLUMN IF EXISTS profile_image";

    $pdo->exec($sql);
    echo "Profile image column removed successfully!";
} catch(PDOException $e) {
    echo "Error removing column: " . $e->getMessage();
}
?> 