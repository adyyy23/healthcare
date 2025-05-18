<?php
require_once 'config/database.php';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Fixing Appointment Statuses</h1>";

// First, check and display current statuses
$stmt = $pdo->query("SELECT id, status FROM appointments");
echo "<h2>Current Status Values:</h2>";
echo "<ul>";
while ($row = $stmt->fetch()) {
    echo "<li>ID: " . $row['id'] . ", Status: '" . htmlspecialchars($row['status']) . "'</li>";
}
echo "</ul>";

// Update all statuses that are approved or contain approve to exactly 'approved'
$stmt = $pdo->prepare("UPDATE appointments SET status = 'approved' WHERE LOWER(status) LIKE '%approve%'");
$stmt->execute();
echo "<p>" . $stmt->rowCount() . " records updated to 'approved'</p>";

// Update all pending statuses to approved
$stmt = $pdo->prepare("UPDATE appointments SET status = 'approved' WHERE LOWER(status) = 'pending'");
$stmt->execute();
echo "<p>" . $stmt->rowCount() . " records updated from 'pending' to 'approved'</p>";

// Update all completed statuses
$stmt = $pdo->prepare("UPDATE appointments SET status = 'completed' WHERE LOWER(status) LIKE '%complete%'");
$stmt->execute();
echo "<p>" . $stmt->rowCount() . " records updated to 'completed'</p>";

// Update all cancelled/disapproved statuses
$stmt = $pdo->prepare("UPDATE appointments SET status = 'cancelled' WHERE LOWER(status) LIKE '%cancel%' OR LOWER(status) LIKE '%disapprove%'");
$stmt->execute();
echo "<p>" . $stmt->rowCount() . " records updated to 'cancelled'</p>";

// Fix NULL or empty statuses
$stmt = $pdo->prepare("UPDATE appointments SET status = 'pending' WHERE status IS NULL OR TRIM(status) = ''");
$stmt->execute();
echo "<p>" . $stmt->rowCount() . " records updated to 'pending' (was empty or NULL)</p>";

// Show updated statuses
$stmt = $pdo->query("SELECT id, status FROM appointments");
echo "<h2>Updated Status Values:</h2>";
echo "<ul>";
while ($row = $stmt->fetch()) {
    echo "<li>ID: " . $row['id'] . ", Status: '" . htmlspecialchars($row['status']) . "'</li>";
}
echo "</ul>";

echo "<p>Status normalization complete. <a href='doctor/appointments.php'>Return to appointments</a></p>";
?> 