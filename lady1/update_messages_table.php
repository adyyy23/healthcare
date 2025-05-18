<?php
require_once 'config/database.php';

try {
    // Add twilio_message_sid column if it doesn't exist
    $stmt = $pdo->query("SHOW COLUMNS FROM messages LIKE 'twilio_message_sid'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE messages ADD COLUMN twilio_message_sid VARCHAR(255) NULL");
        echo "Added twilio_message_sid column to messages table\n";
    } else {
        echo "twilio_message_sid column already exists\n";
    }

    // Add is_read column if it doesn't exist
    $stmt = $pdo->query("SHOW COLUMNS FROM messages LIKE 'is_read'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE messages ADD COLUMN is_read BOOLEAN DEFAULT FALSE");
        echo "Added is_read column to messages table\n";
    } else {
        echo "is_read column already exists\n";
    }

    echo "Messages table update completed successfully\n";
} catch(PDOException $e) {
    echo "Error updating messages table: " . $e->getMessage() . "\n";
}
?> 