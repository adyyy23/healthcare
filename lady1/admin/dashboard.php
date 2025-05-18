<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Handle adding new doctor
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_doctor'])) {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $license_number = $_POST['license_number'];
    $password = 'doctor123'; // Default password for new doctors

    try {
        $pdo->beginTransaction();

        // Create user account
        $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'doctor')");
        $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
        $userId = $pdo->lastInsertId();

        // Create doctor record
        $stmt = $pdo->prepare("INSERT INTO doctors (user_id, license_number) VALUES (?, ?)");
        $stmt->execute([$userId, $license_number]);

        $pdo->commit();
        $success = "Doctor added successfully! Default password: " . $password;
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error adding doctor: " . $e->getMessage();
    }
}

// Handle user deletion
if (isset($_POST['delete_user'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role != 'admin'");
        $stmt->execute([$_POST['user_id']]);
        $success = "User deleted successfully!";
    } catch (Exception $e) {
        $error = "Error deleting user: " . $e->getMessage();
    }
}

// Handle schedule approval/rejection
if (isset($_POST['schedule_action']) && isset($_POST['schedule_id'])) {
    $schedule_id = $_POST['schedule_id'];
    $action = $_POST['schedule_action'];
    
    try {
        $pdo->beginTransaction();
        
        // Get doctor user_id for notification
        $stmt = $pdo->prepare("
            SELECT d.user_id, u.name as doctor_name 
            FROM doctor_schedules ds 
            JOIN doctors d ON ds.doctor_id = d.id 
            JOIN users u ON d.user_id = u.id 
            WHERE ds.id = ?
        ");
        $stmt->execute([$schedule_id]);
        $doctor_info = $stmt->fetch();
        
        if ($action === 'approve') {
            $stmt = $pdo->prepare("UPDATE doctor_schedules SET status = 'available' WHERE id = ?");
            $stmt->execute([$schedule_id]);
            
            // Notify doctor
            $stmt = $pdo->prepare("
                INSERT INTO notifications (user_id, message, type, created_at) 
                VALUES (?, ?, 'success', NOW())
            ");
            $stmt->execute([
                $doctor_info['user_id'], 
                'Your schedule has been approved by admin.'
            ]);
            
            $success = "Schedule approved successfully.";
        } elseif ($action === 'reject') {
            $stmt = $pdo->prepare("UPDATE doctor_schedules SET status = 'unavailable' WHERE id = ?");
            $stmt->execute([$schedule_id]);
            
            // Notify doctor
            $stmt = $pdo->prepare("
                INSERT INTO notifications (user_id, message, type, created_at) 
                VALUES (?, ?, 'danger', NOW())
            ");
            $stmt->execute([
                $doctor_info['user_id'], 
                'Your schedule has been rejected by admin.'
            ]);
            
            $success = "Schedule rejected successfully.";
        }
        
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error processing schedule: " . $e->getMessage();
    }
}

// Fetch all pending schedules
$pending_schedules = [];
try {
    $stmt = $pdo->prepare("
        SELECT ds.*, u.name as doctor_name
        FROM doctor_schedules ds
        JOIN doctors d ON ds.doctor_id = d.id
        JOIN users u ON d.user_id = u.id
        WHERE ds.status = 'pending'
        ORDER BY ds.date, ds.start_time
    ");
    $stmt->execute();
    $pending_schedules = $stmt->fetchAll();
} catch (Exception $e) {
    $error = "Error fetching pending schedules: " . $e->getMessage();
}

// Get all users except admins
$stmt = $pdo->prepare("
    SELECT u.*, 
           CASE 
               WHEN u.role = 'doctor' THEN d.license_number
               WHEN u.role = 'patient' THEN p.phone_number
               ELSE NULL 
           END as additional_info
    FROM users u
    LEFT JOIN doctors d ON u.id = d.user_id
    LEFT JOIN patients p ON u.id = p.user_id
    WHERE u.role != 'admin'
    ORDER BY u.name
");
$stmt->execute();
$users = $stmt->fetchAll();

// Get statistics
$stats = [
    'doctors' => $pdo->query("SELECT COUNT(*) FROM doctors")->fetchColumn(),
    'patients' => $pdo->query("SELECT COUNT(*) FROM patients")->fetchColumn(),
    'appointments' => $pdo->query("SELECT COUNT(*) FROM appointments")->fetchColumn(),
    'pending_appointments' => $pdo->query("SELECT COUNT(*) FROM appointments WHERE status = 'pending'")->fetchColumn()
];

// Get recent appointments
$stmt = $pdo->prepare("
    SELECT a.*, 
           p.user_id as patient_user_id,
           d.user_id as doctor_user_id,
           u1.name as patient_name,
           u2.name as doctor_name,
           d.specialization,
           a.appointment_date,
           a.appointment_time
    FROM appointments a
    JOIN patients p ON a.patient_id = p.id
    JOIN doctors d ON a.doctor_id = d.id
    JOIN users u1 ON p.user_id = u1.id
    JOIN users u2 ON d.user_id = u2.id
    ORDER BY a.appointment_date DESC, a.appointment_time DESC
    LIMIT 10
");
$stmt->execute();
$recent_appointments = $stmt->fetchAll();

// Fetch all doctor schedules for admin management
$all_schedules = [];
try {
    $stmt = $pdo->prepare("
        SELECT ds.*, u.name as doctor_name
        FROM doctor_schedules ds
        JOIN doctors d ON ds.doctor_id = d.id
        JOIN users u ON d.user_id = u.id
        ORDER BY ds.date DESC, ds.start_time DESC
    ");
    $stmt->execute();
    $all_schedules = $stmt->fetchAll();
} catch (Exception $e) {}

// Handle reschedule
if (isset($_POST['reschedule']) && isset($_POST['schedule_id'])) {
    $schedule_id = $_POST['schedule_id'];
    $new_date = $_POST['new_date'];
    $new_start = $_POST['new_start_time'];
    $new_end = $_POST['new_end_time'];
    $stmt = $pdo->prepare("UPDATE doctor_schedules SET date = ?, start_time = ?, end_time = ?, status = 'pending' WHERE id = ?");
    $stmt->execute([$new_date, $new_start, $new_end, $schedule_id]);
    $success = "Schedule rescheduled and set to pending for re-approval.";
}
if (isset($_POST['cancel_schedule']) && isset($_POST['schedule_id'])) {
    $schedule_id = $_POST['schedule_id'];
    $stmt = $pdo->prepare("UPDATE doctor_schedules SET status = 'unavailable' WHERE id = ?");
    $stmt->execute([$schedule_id]);
    $success = "Schedule cancelled.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - HealthCare Portal</title>
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

        .stat-card {
            background: #fff;
            border-radius: 1rem;
            padding: 1.5rem;
            text-align: center;
            transition: transform 0.2s;
            box-shadow: 0 2px 12px rgba(14,165,233,0.07);
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: #64748b;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.1rem;
        }

        .stat-card.primary .stat-icon { color: #4e73df; }
        .stat-card.primary .stat-value { color: #4e73df; }
        
        .stat-card.success .stat-icon { color: #1cc88a; }
        .stat-card.success .stat-value { color: #1cc88a; }
        
        .stat-card.info .stat-icon { color: #36b9cc; }
        .stat-card.info .stat-value { color: #36b9cc; }
        
        .stat-card.warning .stat-icon { color: #f6c23e; }
        .stat-card.warning .stat-value { color: #f6c23e; }

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

        .btn-success {
            background: linear-gradient(90deg, #1cc88a 0%, #17a673 100%);
            border: none;
            color: #fff;
            border-radius: 0.5rem;
            font-weight: 600;
            transition: all 0.2s;
        }

        .btn-success:hover {
            background: linear-gradient(90deg, #17a673 0%, #1cc88a 100%);
            color: #fff;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(28, 200, 138, 0.3);
        }

        .btn-danger {
            background: linear-gradient(90deg, #e74a3b 0%, #c0392b 100%);
            border: none;
            color: #fff;
            border-radius: 0.5rem;
            font-weight: 600;
            transition: all 0.2s;
        }

        .btn-danger:hover {
            background: linear-gradient(90deg, #c0392b 0%, #e74a3b 100%);
            color: #fff;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(231, 74, 59, 0.3);
        }

        .badge {
            border-radius: 8px;
            font-weight: 700;
            font-size: 0.95rem;
            padding: 0.4em 1em;
            letter-spacing: 0.03em;
        }
        .bg-success {
            background: #22c55e !important; /* green */
            color: #fff !important;
        }
        .bg-danger {
            background: #ef4444 !important; /* red */
            color: #fff !important;
        }

        .sidebar-footer {
            margin-top: auto;
            border-top: 1px solid rgba(255,255,255,0.1);
            padding-top: 1rem;
        }

        .sidebar-footer h6 {
            font-weight: 600;
            margin: 0;
        }

        .sidebar-footer small {
            opacity: 0.8;
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

        .alert {
            border-radius: 0.75rem;
            margin-bottom: 1.5rem;
            border: none;
        }

        .page-title {
            color: #4e73df;
            font-weight: 700;
            margin-bottom: 2rem;
        }

        .card-body {
            padding: 0;
        }

        .table-responsive {
            border-radius: 0 0 1rem 1rem;
            overflow: hidden;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <h5><i class="bi bi-heart-pulse"></i> HealthCare Admin</h5>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link active" href="dashboard.php">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="manage_doctors.php">
                    <i class="bi bi-person-badge"></i> Manage Doctors
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="manage_patients.php">
                    <i class="bi bi-people"></i> Manage Patients
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="appointments.php">
                    <i class="bi bi-calendar-check"></i> Appointments
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="messages.php">
                    <i class="bi bi-chat-dots"></i> Messages
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
                <h6 class="mb-1">Admin User</h6>
                <small>Administrator</small>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="page-title">Admin Dashboard</h1>
            <button class="btn btn-primary d-md-none" id="sidebarToggle">
                <i class="bi bi-list"></i>
            </button>
        </div>

        <?php if (isset($success)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i>
                <?php echo $success; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-circle-fill me-2"></i>
                <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="stat-card primary">
                    <i class="bi bi-person-badge stat-icon"></i>
                    <div class="stat-value"><?php echo $stats['doctors']; ?></div>
                    <div class="stat-label">Total Doctors</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card success">
                    <i class="bi bi-people stat-icon"></i>
                    <div class="stat-value"><?php echo $stats['patients']; ?></div>
                    <div class="stat-label">Total Patients</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card info">
                    <i class="bi bi-calendar-check stat-icon"></i>
                    <div class="stat-value"><?php echo $stats['appointments']; ?></div>
                    <div class="stat-label">Total Appointments</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card warning">
                    <i class="bi bi-clock stat-icon"></i>
                    <div class="stat-value"><?php echo $stats['pending_appointments']; ?></div>
                    <div class="stat-label">Pending Appointments</div>
                </div>
            </div>
        </div>

        <!-- Recent Appointments -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5>Recent Appointments</h5>
                <a href="appointments.php" class="btn btn-primary btn-sm">
                    <i class="bi bi-arrow-right"></i> View All
                </a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Patient</th>
                                <th>Doctor</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($recent_appointments as $appointment): ?>
                            <tr>
                                <td><?php echo date('M d, Y', strtotime($appointment['appointment_date'])); ?></td>
                                <td><?php echo date('h:i A', strtotime($appointment['appointment_time'])); ?></td>
                                <td><?php echo htmlspecialchars($appointment['patient_name']); ?></td>
                                <td><?php echo htmlspecialchars($appointment['doctor_name']); ?></td>
                                <td>
                                    <?php
                                        $raw_status = $appointment['status'];
                                        $status = strtolower(trim($raw_status));
                                        $badge = 'bg-success text-white';
                                        $label = 'Approved';
                                        
                                        // Debug all statuses thoroughly
                                        echo "<!-- DEBUG Raw status: '" . htmlspecialchars($raw_status) . "', Processed: '" . $status . "' -->";
                                        
                                        if ($status == 'approved' || $status == 'approve' || strpos($status, 'approv') !== false) {
                                            $badge = 'bg-success text-white';
                                            $label = 'Approved';
                                        } elseif ($status == 'completed' || strpos($status, 'complete') !== false) {
                                            $badge = 'bg-info text-white';
                                            $label = 'Completed';
                                        } elseif ($status == 'cancelled' || strpos($status, 'cancel') !== false || $status == 'disapproved' || strpos($status, 'disapprov') !== false) {
                                            $badge = 'bg-danger text-white';
                                            $label = 'Cancelled';
                                        } elseif ($status == 'pending' || strpos($status, 'pending') !== false) {
                                            $badge = 'bg-warning text-dark';
                                            $label = 'Pending';
                                        } // else default is already set to Approved
                                    ?>
                                    <span class="badge <?php echo $badge; ?>">
                                        <?php echo $label; ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Doctor Schedules -->
        <div class="card">
            <div class="card-header">
                <h5>Doctor Schedules</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Doctor</th>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($all_schedules as $schedule): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($schedule['doctor_name']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($schedule['date'])); ?></td>
                                <td>
                                    <?php echo date('h:i A', strtotime($schedule['start_time'])); ?> - 
                                    <?php echo date('h:i A', strtotime($schedule['end_time'])); ?>
                                </td>
                                <td>
                                    <?php
                                        $status = $schedule['status'];
                                        $badge = 'bg-info text-white';
                                        if ($status == 'pending') $badge = 'bg-warning text-dark';
                                        elseif ($status == 'available') $badge = 'bg-success text-white';
                                        elseif ($status == 'unavailable') $badge = 'bg-danger text-white';
                                    ?>
                                    <span class="badge <?php echo $badge; ?>">
                                        <?php echo ucfirst($status); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($status == 'pending'): ?>
                                        <form method="post" style="display:inline;">
                                            <input type="hidden" name="schedule_id" value="<?php echo $schedule['id']; ?>">
                                            <button name="schedule_action" value="approve" class="btn btn-success btn-sm">
                                                <i class="bi bi-check-lg"></i> Approve
                                            </button>
                                        </form>
                                        <form method="post" style="display:inline;">
                                            <input type="hidden" name="schedule_id" value="<?php echo $schedule['id']; ?>">
                                            <button name="schedule_action" value="reject" class="btn btn-danger btn-sm">
                                                <i class="bi bi-x-lg"></i> Reject
                                            </button>
                                        </form>
                                    <?php elseif ($status == 'available'): ?>
                                        <form method="post" style="display:inline;">
                                            <input type="hidden" name="schedule_id" value="<?php echo $schedule['id']; ?>">
                                            <button name="cancel_schedule" value="1" class="btn btn-danger btn-sm">
                                                <i class="bi bi-x-lg"></i> Make Unavailable
                                            </button>
                                        </form>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle sidebar on mobile
        document.getElementById('sidebarToggle').addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('show');
        });
    </script>
</body>
</html> 