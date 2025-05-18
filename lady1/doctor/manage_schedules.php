<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is doctor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    header('Location: ../login.php');
    exit();
}

// Get doctor information
$stmt = $pdo->prepare("SELECT d.*, u.name, u.email FROM doctors d JOIN users u ON d.user_id = u.id WHERE d.user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$doctor = $stmt->fetch();

// Debug: Print doctor information
error_log("Doctor Information: " . print_r($doctor, true));

// Handle schedule submission
if (isset($_POST['add_schedule'])) {
    try {
        $pdo->beginTransaction();
        
        $date = $_POST['date'];
        $startTime = $_POST['start_time'];
        $endTime = $_POST['end_time'];
        
        // Debug: Print schedule data
        error_log("Adding schedule - Date: $date, Start: $startTime, End: $endTime");
        
        // Validate date is not in the past
        if (strtotime($date) < strtotime('today')) {
            throw new Exception("Cannot add schedules for past dates.");
        }
        
        // Check for overlapping schedules
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM doctor_schedules 
            WHERE doctor_id = ? AND date = ? 
            AND ((start_time <= ? AND end_time > ?) OR (start_time < ? AND end_time >= ?))
        ");
        $stmt->execute([$doctor['id'], $date, $endTime, $startTime, $endTime, $startTime]);
        if ($stmt->fetchColumn() > 0) {
            throw new Exception("This schedule overlaps with an existing schedule.");
        }
        
        // Insert new schedule with pending status
        $stmt = $pdo->prepare("
            INSERT INTO doctor_schedules (doctor_id, date, start_time, end_time, status) 
            VALUES (?, ?, ?, ?, 'pending')
        ");
        $stmt->execute([$doctor['id'], $date, $startTime, $endTime]);
        
        $scheduleId = $pdo->lastInsertId();
        $pdo->commit();
        $success = "Schedule submitted and is awaiting admin approval.";
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error adding schedule: " . $e->getMessage();
        error_log("Error adding schedule: " . $e->getMessage());
    }
}

// Handle schedule deletion
if (isset($_POST['delete_schedule'])) {
    try {
        $pdo->beginTransaction();
        
        $scheduleId = $_POST['schedule_id'];
        
        // Check if schedule exists and belongs to this doctor
        $stmt = $pdo->prepare("
            SELECT id FROM doctor_schedules 
            WHERE id = ? AND doctor_id = ? AND status = 'pending'
        ");
        $stmt->execute([$scheduleId, $doctor['id']]);
        
        if ($stmt->fetch()) {
            // Delete the schedule
            $stmt = $pdo->prepare("DELETE FROM doctor_schedules WHERE id = ?");
            $stmt->execute([$scheduleId]);
            $pdo->commit();
            $success = "Schedule deleted successfully!";
        } else {
            throw new Exception("Schedule not found or cannot be deleted.");
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error deleting schedule: " . $e->getMessage();
    }
}

// Get doctor's schedules
$stmt = $pdo->prepare("
    SELECT ds.*, 
           DATE_FORMAT(ds.date, '%M %d, %Y') as formatted_date,
           TIME_FORMAT(ds.start_time, '%h:%i %p') as formatted_start_time,
           TIME_FORMAT(ds.end_time, '%h:%i %p') as formatted_end_time
    FROM doctor_schedules ds 
    WHERE ds.doctor_id = ? 
    ORDER BY ds.date DESC, ds.start_time
");
$stmt->execute([$doctor['id']]);
$schedules = $stmt->fetchAll();

// Debug: Print all schedules
error_log("All schedules for doctor: " . print_r($schedules, true));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Schedules - Healthcare System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .status-badge {
            padding: 0.5em 1em;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 500;
        }
        .status-pending {
            background-color: #ffc107;
            color: #000;
        }
        .status-available {
            background-color: #28a745;
            color: #fff;
        }
        .status-booked {
            background-color: #17a2b8;
            color: #fff;
        }
        .status-unavailable {
            background-color: #dc3545;
            color: #fff;
        }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Manage Schedules</h1>
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

        <div class="row">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Add New Schedule</h5>
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Date</label>
                                <input type="date" class="form-control" name="date" 
                                       min="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Start Time</label>
                                <input type="time" class="form-control" name="start_time" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">End Time</label>
                                <input type="time" class="form-control" name="end_time" required>
                            </div>
                            <button type="submit" name="add_schedule" class="btn btn-primary">
                                Add Schedule
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-8">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Your Schedules</h5>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Time</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($schedules)): ?>
                                        <tr>
                                            <td colspan="4" class="text-center">No schedules found.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($schedules as $schedule): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($schedule['formatted_date']); ?></td>
                                            <td>
                                                <?php echo htmlspecialchars($schedule['formatted_start_time'] . ' - ' . 
                                                                          $schedule['formatted_end_time']); ?>
                                            </td>
                                            <td>
                                                <span class="status-badge status-<?php echo $schedule['status']; ?>">
                                                    <?php echo ucfirst($schedule['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($schedule['status'] === 'pending'): ?>
                                                    <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this schedule?');">
                                                        <input type="hidden" name="schedule_id" value="<?php echo $schedule['id']; ?>">
                                                        <button type="submit" name="delete_schedule" class="btn btn-danger btn-sm">
                                                            <i class="bi bi-trash"></i> Delete
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 