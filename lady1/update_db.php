<?php
require_once 'config/database.php';

try {
    // Add is_read column to messages table
    $pdo->exec("ALTER TABLE messages ADD COLUMN is_read BOOLEAN DEFAULT FALSE");
    echo "Successfully added is_read column to messages table.<br>";
    
    // Update existing messages to be marked as read
    $pdo->exec("UPDATE messages SET is_read = TRUE");
    echo "Successfully updated existing messages to be marked as read.<br>";
    
    echo "<br>Database update completed successfully! You can now <a href='patient/dashboard.php'>return to the dashboard</a>.";
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?> 