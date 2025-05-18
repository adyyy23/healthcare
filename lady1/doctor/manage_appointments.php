<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is doctor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    header('Location: ../login.php');
    exit();
}

// Get doctor information
$stmt = $pdo->prepare("SELECT id FROM doctors WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$doctor = $stmt->fetch();

if (!$doctor) {
    header('Location: ../login.php');
    exit();
}

// Handle appointment status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['appointment_id']) && isset($_POST['action'])) {
    try {
        $pdo->beginTransaction();

        $appointment_id = $_POST['appointment_id'];
        $action = $_POST['action'];
        $notes = $_POST['notes'] ?? '';

        // Get appointment details
        $stmt = $pdo->prepare("
            SELECT a.*, p.user_id as patient_user_id, ds.id as schedule_id
            FROM appointments a
            JOIN patients p ON a.patient_id = p.id
            JOIN doctor_schedules ds ON a.schedule_id = ds.id
            WHERE a.id = ? AND a.doctor_id = ?
        ");
        $stmt->execute([$appointment_id, $doctor['id']]);
        $appointment = $stmt->fetch();

        if (!$appointment) {
            throw new Exception("Appointment not found or unauthorized");
        }

        // Update appointment status
        $new_status = $action === 'approve' ? 'approved' : 'cancelled';
        $stmt = $pdo->prepare("
            UPDATE appointments 
            SET status = ?, 
                notes = ?,
                updated_at = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$new_status, $notes, $appointment_id]);

        // Update schedule status
        $schedule_status = $action === 'approve' ? 'booked' : 'available';
        $stmt = $pdo->prepare("
            UPDATE doctor_schedules 
            SET status = ? 
            WHERE id = ?
        ");
        $stmt->execute([$schedule_status, $appointment['schedule_id']]);

        // Create notification for patient
        $notification_message = $action === 'approve' 
            ? "Your appointment has been approved by Dr. " . $_SESSION['name']
            : "Your appointment has been cancelled by Dr. " . $_SESSION['name'];
        
        if (!empty($notes)) {
            $notification_message .= "\nNotes: " . $notes;
        }

        $stmt = $pdo->prepare("
            INSERT INTO notifications (
                user_id, title, message, type, created_at
            ) VALUES (?, ?, ?, 'appointment_status', NOW())
        ");
        $stmt->execute([
            $appointment['patient_user_id'],
            "Appointment " . ucfirst($new_status),
            $notification_message
        ]);

        $pdo->commit();
        $_SESSION['success'] = "Appointment has been " . $new_status;
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Error: " . $e->getMessage();
    }
}

// Get all appointments for this doctor
$stmt = $pdo->prepare("
    SELECT a.*, 
           p.user_id as patient_user_id,
           u.name as patient_name,
           ds.date,
           ds.start_time,
           ds.end_time,
           ds.room_number
    FROM appointments a
    JOIN patients p ON a.patient_id = p.id
    JOIN users u ON p.user_id = u.id
    JOIN doctor_schedules ds ON a.schedule_id = ds.id
    WHERE a.doctor_id = ?
    ORDER BY ds.date DESC, ds.start_time DESC
");
$stmt->execute([$doctor['id']]);
$appointments = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Appointments - Healthcare System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-10">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h3 class="mb-0">Manage Appointments</h3>
                        <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
                    </div>
                    <div class="card-body">
                        <?php if (isset($_SESSION['success'])): ?>
                            <div class="alert alert-success">
                                <?php 
                                echo $_SESSION['success'];
                                unset($_SESSION['success']);
                                ?>
                            </div>
                        <?php endif; ?>

                        <?php if (isset($_SESSION['error'])): ?>
                            <div class="alert alert-danger">
                                <?php 
                                echo $_SESSION['error'];
                                unset($_SESSION['error']);
                                ?>
                            </div>
                        <?php endif; ?>

                        <?php if (empty($appointments)): ?>
                            <div class="alert alert-info">
                                No appointments found.
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Patient</th>
                                            <th>Date</th>
                                            <th>Time</th>
                                            <th>Room</th>
                                            <th>Reason</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($appointments as $appointment): ?>
                                            <tr>
                                                <td>
                                                    <a href="../patient/profile.php?id=<?php echo $appointment['patient_user_id']; ?>" 
                                                       class="text-decoration-none">
                                                        <?php echo htmlspecialchars($appointment['patient_name']); ?>
                                                    </a>
                                                </td>
                                                <td><?php echo date('F d, Y', strtotime($appointment['date'])); ?></td>
                                                <td>
                                                    <?php echo date('h:i A', strtotime($appointment['start_time'])); ?> - 
                                                    <?php echo date('h:i A', strtotime($appointment['end_time'])); ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($appointment['room_number']); ?></td>
                                                <td><?php echo htmlspecialchars($appointment['reason']); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php 
                                                        echo $appointment['status'] === 'approved' ? 'success' : 
                                                            ($appointment['status'] === 'pending' ? 'warning' : 
                                                            ($appointment['status'] === 'cancelled' ? 'danger' : 'secondary')); 
                                                    ?>">
                                                        <?php echo ucfirst($appointment['status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($appointment['status'] === 'pending'): ?>
                                                        <button type="button" 
                                                                class="btn btn-success btn-sm" 
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#approveModal<?php echo $appointment['id']; ?>">
                                                            <i class="bi bi-check-circle"></i> Approve
                                                        </button>
                                                        <button type="button" 
                                                                class="btn btn-danger btn-sm" 
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#cancelModal<?php echo $appointment['id']; ?>">
                                                            <i class="bi bi-x-circle"></i> Cancel
                                                        </button>

                                                        <!-- Approve Modal -->
                                                        <div class="modal fade" id="approveModal<?php echo $appointment['id']; ?>" tabindex="-1">
                                                            <div class="modal-dialog">
                                                                <div class="modal-content">
                                                                    <form method="POST">
                                                                        <input type="hidden" name="appointment_id" value="<?php echo $appointment['id']; ?>">
                                                                        <input type="hidden" name="action" value="approve">
                                                                        <div class="modal-header">
                                                                            <h5 class="modal-title">Approve Appointment</h5>
                                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                                        </div>
                                                                        <div class="modal-body">
                                                                            <div class="mb-3">
                                                                                <label class="form-label">Notes (Optional)</label>
                                                                                <textarea class="form-control" name="notes" rows="3"></textarea>
                                                                            </div>
                                                                        </div>
                                                                        <div class="modal-footer">
                                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                                            <button type="submit" class="btn btn-success">Approve Appointment</button>
                                                                        </div>
                                                                    </form>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <!-- Cancel Modal -->
                                                        <div class="modal fade" id="cancelModal<?php echo $appointment['id']; ?>" tabindex="-1">
                                                            <div class="modal-dialog">
                                                                <div class="modal-content">
                                                                    <form method="POST">
                                                                        <input type="hidden" name="appointment_id" value="<?php echo $appointment['id']; ?>">
                                                                        <input type="hidden" name="action" value="cancel">
                                                                        <div class="modal-header">
                                                                            <h5 class="modal-title">Cancel Appointment</h5>
                                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                                        </div>
                                                                        <div class="modal-body">
                                                                            <div class="mb-3">
                                                                                <label class="form-label">Reason for Cancellation</label>
                                                                                <textarea class="form-control" name="notes" rows="3" required></textarea>
                                                                            </div>
                                                                        </div>
                                                                        <div class="modal-footer">
                                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                                            <button type="submit" class="btn btn-danger">Cancel Appointment</button>
                                                                        </div>
                                                                    </form>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 