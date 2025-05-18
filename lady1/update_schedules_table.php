<?php
require_once 'config/database.php';

try {
    // Remove status column from doctor_schedules table
    $pdo->exec("ALTER TABLE doctor_schedules DROP COLUMN status");
    echo "Successfully removed status column from doctor_schedules table.";
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?> 