<?php
session_start();
require_once 'config/database.php';

$error = '';
$success = '';

// Check if there's already an admin
$stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'");
$adminExists = $stmt->fetchColumn() > 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Validate input
    if (empty($name) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = "All fields are required";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters long";
    } else {
        try {
            // Check if email already exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error = "This email address is already registered. Please use a different email or try logging in.";
            } else {
                // Determine role based on whether admin exists
                $role = $adminExists ? 'patient' : 'admin';

                // Start transaction
                $pdo->beginTransaction();

                try {
                    // Insert new user
                    $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    $stmt->execute([$name, $email, $hashedPassword, $role]);
                    
                    $userId = $pdo->lastInsertId();

                    // If this is a patient, create patient record
                    if ($role === 'patient') {
                        $stmt = $pdo->prepare("INSERT INTO patients (user_id, medical_history) VALUES (?, ?)");
                        $stmt->execute([$userId, '']);
                    }

                    // Commit transaction
                    $pdo->commit();
                    $success = "Registration successful! You can now login.";
                } catch (Exception $e) {
                    // Rollback transaction on error
                    $pdo->rollBack();
                    throw $e;
                }
            }
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $error = "This email address is already registered. Please use a different email or try logging in.";
            } else {
                $error = "Registration failed: " . $e->getMessage();
            }
            error_log("Registration error: " . $e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Healthcare System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .register-container {
            max-width: 500px;
            margin: 50px auto;
            padding: 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="register-container">
            <h2 class="text-center mb-4">Create your account</h2>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <?php echo $success; ?>
                    <br>
                    <a href="login.php" class="alert-link">Click here to login</a>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="mb-3">
                    <label class="form-label">Full Name</label>
                    <input type="text" class="form-control" name="name" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Email address</label>
                    <input type="email" class="form-control" name="email" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Password</label>
                    <input type="password" class="form-control" name="password" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Confirm Password</label>
                    <input type="password" class="form-control" name="confirm_password" required>
                </div>

                <button type="submit" class="btn btn-primary w-100">Register</button>
            </form>
            <div class="text-center mt-3">
                Already have an account? <a href="login.php">Login</a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 