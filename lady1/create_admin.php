<?php
require_once 'config/database.php';

try {
    // Check if admin exists
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'");
    $adminCount = $stmt->fetchColumn();

    if ($adminCount == 0) {
        // Create admin user
        $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'admin')");
        $stmt->execute([
            'Admin User',
            'admin@healthcare.com',
            password_hash('admin123', PASSWORD_DEFAULT)
        ]);
        echo "Admin user created successfully!\n";
        echo "Email: admin@healthcare.com\n";
        echo "Password: admin123\n";
    } else {
        echo "Admin user already exists.\n";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?> 