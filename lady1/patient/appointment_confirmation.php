<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is patient
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header('Location: ../login.php');
    exit();
}

// Check if appointment details exist in session
if (!isset($_SESSION['appointment_details'])) {
    $_SESSION['error'] = "No appointment details found.";
    header('Location: dashboard.php');
    exit();
}

$appointment = $_SESSION['appointment_details'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirm Appointment</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h3 class="text-center">Confirm Your Appointment</h3>
                    </div>
                    <div class="card-body">
                        <?php if (isset($_SESSION['error'])): ?>
                            <div class="alert alert-danger">
                                <?php 
                                echo $_SESSION['error'];
                                unset($_SESSION['error']);
                                ?>
                            </div>
                        <?php endif; ?>

                        <div class="mb-4">
                            <h4>Appointment Details</h4>
                            <table class="table">
                                <tr>
                                    <th>Doctor:</th>
                                    <td><?php echo htmlspecialchars($appointment['doctor_name']); ?></td>
                                </tr>
                                <tr>
                                    <th>Date:</th>
                                    <td><?php echo date('F j, Y', strtotime($appointment['date'])); ?></td>
                                </tr>
                                <tr>
                                    <th>Time:</th>
                                    <td><?php echo date('g:i A', strtotime($appointment['time'])); ?></td>
                                </tr>
                                <tr>
                                    <th>Location:</th>
                                    <td><?php echo htmlspecialchars($appointment['location']); ?></td>
                                </tr>
                                <tr>
                                    <th>Room Type:</th>
                                    <td><?php echo htmlspecialchars($appointment['room_type']); ?></td>
                                </tr>
                                <tr>
                                    <th>Reason for Visit:</th>
                                    <td><?php echo htmlspecialchars($appointment['reason']); ?></td>
                                </tr>
                            </table>
                        </div>

                        <div class="text-center">
                            <form action="confirm_appointment.php" method="POST" class="d-inline">
                                <button type="submit" class="btn btn-primary">Confirm Appointment</button>
                            </form>
                            <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 