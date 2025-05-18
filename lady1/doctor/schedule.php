<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is doctor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    header('Location: ../login.php');
    exit();
}

// Get doctor's information
$stmt = $pdo->prepare("
    SELECT d.*, u.name, u.email 
    FROM doctors d 
    JOIN users u ON d.user_id = u.id 
    WHERE d.user_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$doctor = $stmt->fetch();

// Handle form submission for adding/updating schedule
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['action'])) {
            if ($_POST['action'] === 'add') {
                // Add new schedule (always pending for admin approval)
                $stmt = $pdo->prepare("
                    INSERT INTO doctor_schedules (
                        doctor_id, 
                        date, 
                        start_time, 
                        end_time,
                        status
                    ) VALUES (?, ?, ?, ?, 'pending')
                ");
                $stmt->execute([
                    $doctor['id'],
                    $_POST['date'],
                    $_POST['start_time'],
                    $_POST['end_time']
                ]);
                $_SESSION['success_message'] = "Schedule submitted and is awaiting admin approval.";
            } 
            elseif ($_POST['action'] === 'update') {
                // Update existing schedule
                $stmt = $pdo->prepare("
                    UPDATE doctor_schedules 
                    SET status = ? 
                    WHERE id = ? AND doctor_id = ?
                ");
                $stmt->execute([
                    $_POST['status'],
                    $_POST['schedule_id'],
                    $doctor['id']
                ]);
                $_SESSION['success_message'] = "Schedule updated successfully!";
            }
            elseif ($_POST['action'] === 'delete') {
                // Delete schedule
                $stmt = $pdo->prepare("
                    DELETE FROM doctor_schedules 
                    WHERE id = ? AND doctor_id = ?
                ");
                $stmt->execute([$_POST['schedule_id'], $doctor['id']]);
                $_SESSION['success_message'] = "Schedule deleted successfully!";
            }
        }
    } catch (Exception $e) {
        $_SESSION['error_message'] = $e->getMessage();
    }
    
    // Redirect to prevent form resubmission
    header('Location: schedule.php');
    exit();
}

// First, check if the table exists and has the correct structure
try {
    $pdo->query("SELECT 1 FROM doctor_schedules LIMIT 1");
} catch (PDOException $e) {
    // Table doesn't exist, create it
    $sql = "CREATE TABLE IF NOT EXISTS doctor_schedules (
        id INT PRIMARY KEY AUTO_INCREMENT,
        doctor_id INT NOT NULL,
        date DATE NOT NULL,
        start_time TIME NOT NULL,
        end_time TIME NOT NULL,
        status ENUM('available', 'booked', 'unavailable') DEFAULT 'available',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE
    )";
    $pdo->exec($sql);
}

// Get all schedules for the doctor
try {
    $stmt = $pdo->prepare("
        SELECT 
            ds.*,
            CASE 
                WHEN a.id IS NOT NULL THEN 'booked'
                ELSE ds.status
            END as current_status,
            a.id as appointment_id,
            p.id as patient_id,
            u.name as patient_name
        FROM doctor_schedules ds
        LEFT JOIN appointments a ON 
            a.doctor_id = ds.doctor_id AND 
            a.appointment_date = ds.date AND 
            a.appointment_time = ds.start_time AND
            a.status != 'cancelled'
        LEFT JOIN patients p ON a.patient_id = p.id
        LEFT JOIN users u ON p.user_id = u.id
        WHERE ds.doctor_id = ?
        ORDER BY ds.date, ds.start_time
    ");
    $stmt->execute([$doctor['id']]);
    $schedules = $stmt->fetchAll();
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Error fetching schedules: " . $e->getMessage();
    $schedules = [];
}

// Get today's date for the date input
$today = date('Y-m-d');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Schedule Management - HealthCare Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; background: #f8fafc; color: #0f172a; }
        
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: 250px;
            background: #002366;
            color: white;
            padding: 1.5rem;
            z-index: 1000;
            display: flex;
            flex-direction: column;
        }

        .sidebar h5 {
            color: #fff;
            font-weight: 700;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
        }

        .sidebar h5 i {
            margin-right: 0.5rem;
            color: #4e73df;
        }

        .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 0.8rem 1rem;
            border-radius: 0.5rem;
            margin-bottom: 0.5rem;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
        }

        .nav-link:hover, .nav-link.active {
            color: white;
            background: #4e73df;
        }

        .nav-link i {
            margin-right: 0.5rem;
            width: 20px;
            text-align: center;
        }

        .main-content {
            margin-left: 250px;
            padding: 2rem;
        }

        .card {
            border: none;
            border-radius: 1rem;
            box-shadow: 0 2px 12px rgba(14,165,233,0.07);
            margin-bottom: 1.5rem;
            background: #fff;
        }

        .card-header {
            background: #fff;
            border-bottom: 1px solid #e3e6f0;
            padding: 1.5rem;
            border-radius: 1rem 1rem 0 0;
        }

        .card-header h5 {
            margin: 0;
            font-weight: 600;
            color: #4e73df;
        }

        .table {
            background: white;
            border-radius: 0 0 1rem 1rem;
            border: none;
            overflow: hidden;
        }

        .table thead th {
            background: linear-gradient(120deg, #4e73df 0%, #224abe 100%);
            color: #fff;
            border: none;
            padding: 1rem;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.1rem;
        }

        .table tbody tr {
            border-bottom: 1px solid #f1f3f8;
            transition: background-color 0.2s;
        }

        .table tbody tr:hover {
            background-color: #f8fafc;
        }

        .table tbody td {
            padding: 1rem;
            vertical-align: middle;
            border: none;
        }

        .table tbody tr:last-child {
            border-bottom: none;
        }

        .btn-primary {
            background: linear-gradient(90deg, #4e73df 0%, #1d4ed8 100%);
            border: none;
            color: #fff;
            border-radius: 0.5rem;
            padding: 0.5rem 1rem;
            font-weight: 600;
            transition: all 0.2s;
        }

        .btn-primary:hover {
            background: linear-gradient(90deg, #1d4ed8 0%, #4e73df 100%);
            color: #fff;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(78, 115, 223, 0.3);
        }

        .btn-outline-secondary {
            color: #858796;
            border: 1px solid #858796;
            border-radius: 0.5rem;
            font-weight: 600;
            transition: all 0.2s;
        }

        .btn-outline-secondary:hover {
            background: #858796;
            color: #fff;
            transform: translateY(-1px);
        }

        .btn-outline-danger {
            color: #e74a3b;
            border: 1px solid #e74a3b;
            border-radius: 0.5rem;
            font-weight: 600;
            transition: all 0.2s;
        }

        .btn-outline-danger:hover {
            background: #e74a3b;
            color: #fff;
            transform: translateY(-1px);
        }

        .status-badge {
            display: inline-block;
            min-width: 90px;
            text-align: center;
            padding: 0.4em 1.2em;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9em;
            letter-spacing: 0.02em;
            text-transform: capitalize;
            transition: background 0.2s, color 0.2s;
        }
        
        .status-available { background: #e6f4ea; color: #219150; }
        .status-booked { background: #e6f0fa; color: #2563eb; }
        .status-unavailable { background: #fdeaea; color: #d32f2f; }
        .status-pending { background: #fff7e6; color: #b78103; }

        .page-title {
            color: #4e73df;
            font-weight: 700;
            margin-bottom: 2rem;
        }

        .sidebar-footer {
            margin-top: auto;
            border-top: 1px solid rgba(255,255,255,0.1);
            padding-top: 1rem;
        }

        .profile-img {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid rgba(255,255,255,0.2);
            margin-bottom: 1rem;
        }

        .card-body {
            padding: 0;
        }

        .card-body.p-4 {
            padding: 1.5rem !important;
        }

        .table-responsive {
            border-radius: 0 0 1rem 1rem;
            overflow: hidden;
        }

        .modal-content {
            border: none;
            border-radius: 1rem;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }

        .modal-header {
            border-bottom: 1px solid #e3e6f0;
            padding: 1.5rem;
            background: #fff;
            border-radius: 1rem 1rem 0 0;
        }

        .modal-header h5 {
            color: #4e73df;
            font-weight: 700;
        }

        .modal-footer {
            border-top: 1px solid #e3e6f0;
            padding: 1.5rem;
            background: #fff;
            border-radius: 0 0 1rem 1rem;
        }

        .form-label {
            color: #5a5c69;
            font-weight: 600;
        }

        .form-control, .form-select {
            border-radius: 0.5rem;
            border: 1px solid #d1d3e2;
            padding: 0.75rem 1rem;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .form-control:focus, .form-select:focus {
            border-color: #4e73df;
            box-shadow: 0 0 0 0.25rem rgba(78, 115, 223, 0.25);
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            .main-content {
                margin-left: 0;
            }
            .sidebar.show {
                transform: translateX(0);
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <h5><i class="bi bi-heart-pulse"></i> Doctor Portal</h5>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link" href="dashboard.php">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="appointments.php">
                    <i class="bi bi-calendar-check"></i> Appointments
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link active" href="schedule.php">
                    <i class="bi bi-clock"></i> Schedule
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="messages.php">
                    <i class="bi bi-chat-dots"></i> Messages
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="profile.php">
                    <i class="bi bi-person"></i> Profile
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="../logout.php">
                    <i class="bi bi-box-arrow-right"></i> Logout
                </a>
            </li>
        </ul>
        <div class="sidebar-footer">
            <div class="text-center text-white">
                <img src="<?php echo $doctor['profile_picture'] ? '../' . htmlspecialchars($doctor['profile_picture']) : '../assets/images/default-user.jpg'; ?>" 
                     alt="Profile" class="profile-img">
                <h6 class="mb-1"><?php echo htmlspecialchars($doctor['name']); ?></h6>
                <small><?php echo isset($doctor['specialization']) ? htmlspecialchars($doctor['specialization']) : 'Doctor'; ?></small>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="page-title">Schedule Management</h1>
            <div>
                <button class="btn btn-primary d-md-none me-2" id="sidebarToggle">
                    <i class="bi bi-list"></i>
                </button>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addScheduleModal">
                    <i class="bi bi-plus-lg"></i> Add Schedule
                </button>
            </div>
        </div>

        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php 
                echo $_SESSION['success_message'];
                unset($_SESSION['success_message']);
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php 
                echo $_SESSION['error_message'];
                unset($_SESSION['error_message']);
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <h5>My Schedules</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Status</th>
                                <th>Patient</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($schedules as $schedule): ?>
                                <tr>
                                    <td><?php echo date('F j, Y', strtotime($schedule['date'])); ?></td>
                                    <td>
                                        <?php 
                                        echo date('g:i A', strtotime($schedule['start_time']));
                                        echo ' - ';
                                        echo date('g:i A', strtotime($schedule['end_time']));
                                        ?>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo $schedule['current_status']; ?>">
                                            <?php echo ucfirst($schedule['current_status']); ?>
                                        </span>
                                        <!-- Status change icon -->
                                        <button type="button" class="btn btn-sm btn-outline-primary ms-2" title="Change Status" data-bs-toggle="modal" data-bs-target="#changeStatusModal<?php echo $schedule['id']; ?>">
                                            <i class="bi bi-pencil-square"></i>
                                        </button>
                                        <!-- Change Status Modal -->
                                        <div class="modal fade" id="changeStatusModal<?php echo $schedule['id']; ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Change Schedule Status</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <form method="POST" action="">
                                                        <div class="modal-body">
                                                            <input type="hidden" name="action" value="update">
                                                            <input type="hidden" name="schedule_id" value="<?php echo $schedule['id']; ?>">
                                                            <div class="mb-3">
                                                                <label for="status<?php echo $schedule['id']; ?>" class="form-label">Status</label>
                                                                <select class="form-select" id="status<?php echo $schedule['id']; ?>" name="status" required>
                                                                    <option value="available" <?php if($schedule['current_status']==='available') echo 'selected'; ?>>Available</option>
                                                                    <option value="unavailable" <?php if($schedule['current_status']==='unavailable') echo 'selected'; ?>>Unavailable</option>
                                                                    <option value="pending" <?php if($schedule['current_status']==='pending') echo 'selected'; ?>>Pending</option>
                                                                    <option value="completed" <?php if($schedule['current_status']==='completed') echo 'selected'; ?>>Completed</option>
                                                                </select>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                                                            <button type="submit" class="btn btn-primary">Update Status</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($schedule['patient_name']): ?>
                                            <?php echo htmlspecialchars($schedule['patient_name']); ?>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <!-- Delete icon -->
                                        <?php if ($schedule['current_status'] !== 'booked'): ?>
                                            <form method="POST" action="" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this schedule?');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="schedule_id" value="<?php echo $schedule['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <button class="btn btn-sm btn-outline-secondary" title="Cannot delete booked schedule" disabled>
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Schedule Modal -->
    <div class="modal fade" id="addScheduleModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Schedule</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add">
                        <div class="mb-3">
                            <label for="date" class="form-label">Date</label>
                            <input type="date" class="form-control" id="date" name="date" 
                                   min="<?php echo $today; ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="start_time" class="form-label">Start Time</label>
                            <input type="time" class="form-control" id="start_time" name="start_time" required>
                        </div>
                        <div class="mb-3">
                            <label for="end_time" class="form-label">End Time</label>
                            <input type="time" class="form-control" id="end_time" name="end_time" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Schedule</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle sidebar on mobile
        document.getElementById('sidebarToggle')?.addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('show');
        });
    </script>
</body>
</html> 