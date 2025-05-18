<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is patient
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header('Location: ../login.php');
    exit();
}

// Get patient's information
$stmt = $pdo->prepare("
    SELECT p.*, u.name, u.email 
    FROM patients p 
    JOIN users u ON p.user_id = u.id 
    WHERE p.user_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$patient = $stmt->fetch();

// Get appointment details from session
if (!isset($_SESSION['temp_appointment'])) {
    header('Location: book-appointment.php');
    exit();
}

$appointment = $_SESSION['temp_appointment'];

// Get doctor information
$stmt = $pdo->prepare("
    SELECT d.*, u.name, u.email
    FROM doctors d
    JOIN users u ON d.user_id = u.id
    WHERE d.id = ?
");
$stmt->execute([$appointment['doctor_id']]);
$doctor = $stmt->fetch();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Start transaction
        $pdo->beginTransaction();

        // Insert the appointment
        $stmt = $pdo->prepare("
            INSERT INTO appointments (
                patient_id, 
                doctor_id, 
                appointment_type,
                appointment_date, 
                appointment_time, 
                reason,
                status
            ) VALUES (?, ?, ?, ?, ?, ?, 'pending')
        ");
        
        $stmt->execute([
            $patient['id'],
            $appointment['doctor_id'],
            $appointment['appointment_type'],
            $appointment['appointment_date'],
            $appointment['appointment_time'],
            $appointment['reason']
        ]);

        // Update doctor schedule status
        $stmt = $pdo->prepare("
            UPDATE doctor_schedules 
            SET status = 'booked' 
            WHERE doctor_id = ? 
            AND date = ? 
            AND start_time = ?
        ");
        $stmt->execute([
            $appointment['doctor_id'],
            $appointment['appointment_date'],
            $appointment['appointment_time']
        ]);

        // Commit transaction
        $pdo->commit();

        // Clear temporary appointment data
        unset($_SESSION['temp_appointment']);

        $_SESSION['success_message'] = "Appointment booked successfully!";
        header('Location: dashboard.php');
        exit();

    } catch (Exception $e) {
        // Rollback transaction on error
        $pdo->rollBack();
        $error_message = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirm Appointment - Healthcare System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2563eb;
            --secondary-color: #64748b;
            --success-color: #10b981;
            --info-color: #0ea5e9;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --background-color: #f1f5f9;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--background-color);
            color: #334155;
        }

        .navbar {
            background: white;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            padding: 1rem 2rem;
        }

        .navbar-brand {
            color: var(--primary-color) !important;
            font-weight: 600;
            font-size: 1.5rem;
        }

        .confirmation-card {
            background: white;
            border-radius: 1rem;
            padding: 2rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-top: 2rem;
        }

        .appointment-details {
            background: var(--background-color);
            border-radius: 0.5rem;
            padding: 1.5rem;
            margin: 1.5rem 0;
        }

        .detail-item {
            display: flex;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #e2e8f0;
        }

        .detail-item:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }

        .detail-label {
            font-weight: 500;
            color: var(--secondary-color);
            width: 150px;
        }

        .detail-value {
            color: #1e293b;
        }

        .btn-primary {
            background: var(--primary-color);
            border: none;
            padding: 0.8rem 1.5rem;
            font-weight: 500;
            border-radius: 0.5rem;
        }

        .btn-primary:hover {
            background: #1d4ed8;
        }

        .btn-outline-secondary {
            border-color: var(--secondary-color);
            color: var(--secondary-color);
            padding: 0.8rem 1.5rem;
            font-weight: 500;
            border-radius: 0.5rem;
        }

        .btn-outline-secondary:hover {
            background: var(--secondary-color);
            color: white;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand" href="#">HealthCare Portal</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="appointments.php">Appointments</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="doctors.php">Doctors</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="messages.php">Messages</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="profile.php">Profile</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../logout.php">Logout</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <div class="confirmation-card">
            <h2 class="mb-4">Confirm Your Appointment</h2>
            
            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger">
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <div class="appointment-details">
                <div class="detail-item">
                    <div class="detail-label">Doctor:</div>
                    <div class="detail-value">Dr. <?php echo htmlspecialchars($doctor['name']); ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Specialization:</div>
                    <div class="detail-value"><?php echo htmlspecialchars($doctor['specialization']); ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Appointment Type:</div>
                    <div class="detail-value">
                        <?php echo ucfirst(str_replace('_', ' ', $appointment['appointment_type'])); ?>
                    </div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Date:</div>
                    <div class="detail-value">
                        <?php echo date('F j, Y', strtotime($appointment['appointment_date'])); ?>
                    </div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Time:</div>
                    <div class="detail-value">
                        <?php echo date('g:i A', strtotime($appointment['appointment_time'])); ?>
                    </div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Reason:</div>
                    <div class="detail-value"><?php echo htmlspecialchars($appointment['reason']); ?></div>
                </div>
            </div>

            <form method="POST" action="">
                <div class="d-flex gap-3">
                    <button type="submit" class="btn btn-primary">Confirm Appointment</button>
                    <a href="book-appointment.php" class="btn btn-outline-secondary">Back to Booking</a>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 