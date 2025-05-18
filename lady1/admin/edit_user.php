<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Get user ID from URL
$userId = isset($_GET['id']) ? $_GET['id'] : null;

if (!$userId) {
    header('Location: dashboard.php');
    exit();
}

// Get user information
$stmt = $pdo->prepare("
    SELECT u.*, 
           CASE 
               WHEN u.role = 'doctor' THEN d.specialization
               WHEN u.role = 'patient' THEN p.phone_number
               ELSE NULL 
           END as additional_info
    FROM users u
    LEFT JOIN doctors d ON u.id = d.user_id
    LEFT JOIN patients p ON u.id = p.user_id
    WHERE u.id = ? AND u.role != 'admin'
");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    header('Location: dashboard.php');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();

        // Update user information
        $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ? WHERE id = ?");
        $stmt->execute([$_POST['name'], $_POST['email'], $userId]);

        // Update role-specific information
        if ($user['role'] === 'doctor') {
            $stmt = $pdo->prepare("UPDATE doctors SET specialization = ? WHERE user_id = ?");
            $stmt->execute([$_POST['specialization'], $userId]);
        } elseif ($user['role'] === 'patient') {
            $stmt = $pdo->prepare("UPDATE patients SET phone_number = ? WHERE user_id = ?");
            $stmt->execute([$_POST['phone_number'], $userId]);
        }

        // Update password if provided
        if (!empty($_POST['password'])) {
            $hashedPassword = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashedPassword, $userId]);
        }

        $pdo->commit();
        $success = "User information updated successfully!";
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error updating user: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User - Healthcare System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
    <div class="container py-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Edit User</h1>
            <a href="dashboard.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Back to Dashboard
            </a>
        </div>

        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="card">
            <div class="card-body">
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Name</label>
                        <input type="text" class="form-control" name="name" 
                               value="<?php echo htmlspecialchars($user['name']); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" name="email" 
                               value="<?php echo htmlspecialchars($user['email']); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">New Password (leave blank to keep current)</label>
                        <input type="password" class="form-control" name="password">
                    </div>

                    <?php if ($user['role'] === 'doctor'): ?>
                        <div class="mb-3">
                            <label class="form-label">Specialization</label>
                            <input type="text" class="form-control" name="specialization" 
                                   value="<?php echo htmlspecialchars($user['additional_info']); ?>" required>
                        </div>
                    <?php elseif ($user['role'] === 'patient'): ?>
                        <div class="mb-3">
                            <label class="form-label">Phone Number</label>
                            <input type="tel" class="form-control" name="phone_number" 
                                   value="<?php echo htmlspecialchars($user['additional_info']); ?>" required>
                        </div>
                    <?php endif; ?>

                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> Save Changes
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 