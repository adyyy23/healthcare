<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Handle appointment status update
if (isset($_POST['update_status'])) {
    try {
        $stmt = $pdo->prepare("UPDATE appointments SET status = ? WHERE id = ?");
        $stmt->execute([$_POST['status'], $_POST['appointment_id']]);
        $success = "Appointment status updated successfully!";
    } catch (Exception $e) {
        $error = "Error updating appointment: " . $e->getMessage();
    }
}

// Get filter parameters
$search = isset($_GET['search']) ? $_GET['search'] : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'date';
$order = isset($_GET['order']) ? $_GET['order'] : 'desc';

// Get all appointments
$stmt = $pdo->prepare("
    SELECT 
        a.*,
        p.user_id as patient_user_id,
        d.user_id as doctor_user_id,
        u1.name as patient_name,
        u2.name as doctor_name,
        d.specialization
    FROM appointments a
    JOIN patients p ON a.patient_id = p.id
    JOIN doctors d ON a.doctor_id = d.id
    JOIN users u1 ON p.user_id = u1.id
    JOIN users u2 ON d.user_id = u2.id
    ORDER BY a.appointment_date DESC, a.appointment_time DESC
");
$stmt->execute();
$appointments = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointments - HealthCare Portal</title>
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

        .btn-secondary {
            background: linear-gradient(90deg, #858796 0%, #5a5c69 100%);
            border: none;
            color: #fff;
            border-radius: 0.5rem;
            font-weight: 600;
            transition: all 0.2s;
        }

        .btn-secondary:hover {
            background: linear-gradient(90deg, #5a5c69 0%, #858796 100%);
            color: #fff;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(90, 92, 105, 0.3);
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
        
        .bg-warning {
            background: #f6c23e !important; /* yellow */
            color: #000 !important;
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

        .search-box {
            position: relative;
        }

        .search-box i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #858796;
        }

        .search-box input {
            padding-left: 2.5rem;
            border-radius: 0.5rem;
            border: 1px solid #e3e6f0;
        }

        .form-select, .form-control {
            border-radius: 0.5rem;
            border: 1px solid #e3e6f0;
            padding: 0.5rem 1rem;
        }

        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            font-weight: 600;
        }

        .page-title {
            color: #4e73df;
            font-weight: 700;
            margin-bottom: 2rem;
        }

        .alert {
            border-radius: 0.75rem;
            margin-bottom: 1.5rem;
            border: none;
        }

        .card-body {
            padding: 0;
        }

        .table-responsive {
            border-radius: 0 0 1rem 1rem;
            overflow: hidden;
        }

        .sort-link {
            color: inherit;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
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
        <h5><i class="bi bi-heart-pulse"></i> HealthCare Admin</h5>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link" href="dashboard.php">
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
                <a class="nav-link active" href="appointments.php">
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
            <h1 class="page-title">Appointments Management</h1>
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

        <!-- Search and Filter -->
        <div class="card mb-4">
            <div class="card-header">
                <h5>Search & Filter Appointments</h5>
            </div>
            <div class="card-body p-4">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <div class="search-box">
                            <i class="bi bi-search"></i>
                            <input type="text" class="form-control" name="search" 
                                   placeholder="Search by patient or doctor name"
                                   value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <select class="form-select" name="status">
                            <option value="">All Status</option>
                            <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="approved" <?php echo $status === 'approved' ? 'selected' : ''; ?>>Approved</option>
                            <option value="booked" <?php echo $status === 'booked' ? 'selected' : ''; ?>>Booked</option>
                            <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="cancelled" <?php echo $status === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <input type="date" class="form-control" name="date_from" 
                               placeholder="From Date" value="<?php echo $date_from; ?>">
                    </div>
                    <div class="col-md-2">
                        <input type="date" class="form-control" name="date_to" 
                               placeholder="To Date" value="<?php echo $date_to; ?>">
                    </div>
                    <div class="col-md-2">
                        <select class="form-select" name="sort">
                            <option value="date" <?php echo $sort === 'date' ? 'selected' : ''; ?>>Sort by Date</option>
                            <option value="patient" <?php echo $sort === 'patient' ? 'selected' : ''; ?>>Sort by Patient</option>
                            <option value="doctor" <?php echo $sort === 'doctor' ? 'selected' : ''; ?>>Sort by Doctor</option>
                            <option value="status" <?php echo $sort === 'status' ? 'selected' : ''; ?>>Sort by Status</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-funnel"></i> Apply Filters
                        </button>
                        <?php if (!empty($search) || !empty($status) || !empty($date_from) || !empty($date_to) || $sort !== 'date' || $order !== 'desc'): ?>
                            <a href="appointments.php" class="btn btn-secondary">
                                <i class="bi bi-x-circle"></i> Clear Filters
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <!-- Appointments List -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5>All Appointments</h5>
                <span class="badge bg-primary"><?php echo count($appointments); ?> Appointments</span>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>
                                    <a href="?search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>&sort=date&order=<?php echo $sort === 'date' && $order === 'asc' ? 'desc' : 'asc'; ?>" 
                                       class="sort-link">
                                        Date
                                        <?php if ($sort === 'date'): ?>
                                            <i class="bi bi-arrow-<?php echo $order === 'asc' ? 'up' : 'down'; ?>"></i>
                                        <?php endif; ?>
                                    </a>
                                </th>
                                <th>Time</th>
                                <th>
                                    <a href="?search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>&sort=patient&order=<?php echo $sort === 'patient' && $order === 'asc' ? 'desc' : 'asc'; ?>" 
                                       class="sort-link">
                                        Patient
                                        <?php if ($sort === 'patient'): ?>
                                            <i class="bi bi-arrow-<?php echo $order === 'asc' ? 'up' : 'down'; ?>"></i>
                                        <?php endif; ?>
                                    </a>
                                </th>
                                <th>
                                    <a href="?search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>&sort=doctor&order=<?php echo $sort === 'doctor' && $order === 'asc' ? 'desc' : 'asc'; ?>" 
                                       class="sort-link">
                                        Doctor
                                        <?php if ($sort === 'doctor'): ?>
                                            <i class="bi bi-arrow-<?php echo $order === 'asc' ? 'up' : 'down'; ?>"></i>
                                        <?php endif; ?>
                                    </a>
                                </th>
                                <th>
                                    <a href="?search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>&sort=status&order=<?php echo $sort === 'status' && $order === 'asc' ? 'desc' : 'asc'; ?>" 
                                       class="sort-link">
                                        Status
                                        <?php if ($sort === 'status'): ?>
                                            <i class="bi bi-arrow-<?php echo $order === 'asc' ? 'up' : 'down'; ?>"></i>
                                        <?php endif; ?>
                                    </a>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($appointments as $appointment): ?>
                            <tr>
                                <td><?php echo date('M d, Y', strtotime($appointment['appointment_date'])); ?></td>
                                <td><?php echo date('h:i A', strtotime($appointment['appointment_time'])); ?></td>
                                <td><?php echo htmlspecialchars($appointment['patient_name']); ?></td>
                                <td><?php echo htmlspecialchars($appointment['doctor_name']); ?></td>
                                <td>
                                    <?php
                                        $raw_status = $appointment['status'];
                                        $status = strtolower(trim($raw_status));
                                        $badge = 'bg-success';
                                        $label = 'Approved';
                                        
                                        // Debug all statuses thoroughly
                                        echo "<!-- DEBUG Raw status: '" . htmlspecialchars($raw_status) . "', Processed: '" . $status . "' -->";
                                        
                                        if ($status == 'approved' || $status == 'approve' || strpos($status, 'approv') !== false) {
                                            $badge = 'bg-success';
                                            $label = 'Approved';
                                        } elseif ($status == 'completed' || strpos($status, 'complete') !== false) {
                                            $badge = 'bg-info';
                                            $label = 'Completed';
                                        } elseif ($status == 'cancelled' || strpos($status, 'cancel') !== false || $status == 'disapproved' || strpos($status, 'disapprov') !== false) {
                                            $badge = 'bg-danger';
                                            $label = 'Cancelled';
                                        } elseif ($status == 'pending' || strpos($status, 'pending') !== false) {
                                            $badge = 'bg-warning';
                                            $label = 'Pending';
                                        } // else default is already set to Approved
                                    ?>
                                    <span class="status-badge <?php echo $badge; ?>">
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
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle sidebar on mobile
        document.getElementById('sidebarToggle').addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('show');
        });

        // Auto-hide alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(function() {
                const alerts = document.querySelectorAll('.alert');
                alerts.forEach(function(alert) {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                });
            }, 5000);
        });
    </script>
</body>
</html> 