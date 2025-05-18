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
    SELECT d.*, u.name, u.email, d.profile_picture, d.specialization 
    FROM doctors d 
    JOIN users u ON d.user_id = u.id 
    WHERE d.user_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$doctor = $stmt->fetch();

// Get all appointments for this doctor
$stmt = $pdo->prepare("
    SELECT a.*, u.name as patient_name, p.phone_number as patient_phone
    FROM appointments a
    JOIN patients p ON a.patient_id = p.id
    JOIN users u ON p.user_id = u.id
    WHERE a.doctor_id = ?
    ORDER BY a.appointment_date DESC, a.appointment_time DESC
");
$stmt->execute([$doctor['id']]);
$all_appointments = $stmt->fetchAll();

// Get all completed appointments for this doctor
$stmt = $pdo->prepare("
    SELECT a.*, u.name as patient_name, p.phone_number as patient_phone
    FROM appointments a
    JOIN patients p ON a.patient_id = p.id
    JOIN users u ON p.user_id = u.id
    WHERE a.doctor_id = ? AND a.status = 'completed'
    ORDER BY a.appointment_date DESC, a.appointment_time DESC
");
$stmt->execute([$doctor['id']]);
$completed_appointments = $stmt->fetchAll();

// Get statistics
$stmt = $pdo->prepare("
    SELECT 
        (SELECT COUNT(*) FROM appointments WHERE doctor_id = ?) as total_appointments,
        (SELECT COUNT(*) FROM appointments WHERE doctor_id = ? AND appointment_date = CURDATE()) as today_appointments,
        (SELECT COUNT(*) FROM appointments WHERE doctor_id = ? AND status = 'pending') as pending_appointments,
        (SELECT COUNT(DISTINCT patient_id) FROM appointments WHERE doctor_id = ?) as total_patients
");
$stmt->execute([$doctor['id'], $doctor['id'], $doctor['id'], $doctor['id']]);
$stats = $stmt->fetch();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - HealthCare Portal</title>
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
            margin-bottom: 1.5rem;
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

        .appointment-item {
            padding: 1rem;
            margin-bottom: 1rem;
            background: white;
            border-radius: 0.75rem;
            transition: transform 0.2s ease;
            border-left: 4px solid #4e73df;
        }

        .appointment-item:hover {
            transform: translateX(5px);
            box-shadow: 0 2px 8px rgba(14,165,233,0.1);
        }

        .appointment-item.pending { border-left-color: #f6c23e; }
        .appointment-item.approved { border-left-color: #1cc88a; }
        .appointment-item.cancelled { border-left-color: #e74a3b; }

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

        /* Added table styles from admin dashboard */
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
        .bg-warning {
            background: #facc15 !important; /* yellow */
            color: #000 !important;
        }
        .bg-info {
            background: #3b82f6 !important; /* blue */
            color: #fff !important;
        }
        /* End added table styles */

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
    <div class="sidebar" style="background-color: #002366;">
        <h5><i class="bi bi-heart-pulse"></i> Doctor Portal</h5>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link active" href="dashboard.php" style="color: white;">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="appointments.php" style="color: white;">
                    <i class="bi bi-calendar-check"></i> Appointments
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="schedule.php" style="color: white;">
                    <i class="bi bi-clock"></i> Schedule
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="messages.php" style="color: white;">
                    <i class="bi bi-chat-dots"></i> Messages
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="profile.php" style="color: white;">
                    <i class="bi bi-person"></i> Profile
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="../logout.php" style="color: white;">
                    <i class="bi bi-box-arrow-right"></i> Logout
                </a>
            </li>
        </ul>
        <div class="sidebar-footer">
            <div class="text-center text-white">
                <img src="<?php echo $doctor['profile_picture'] ? '../' . htmlspecialchars($doctor['profile_picture']) : '../assets/images/default-user.jpg'; ?>" 
                     alt="Profile" class="profile-img">
                <h6 class="mb-1"><?php echo htmlspecialchars($doctor['name']); ?></h6>
                <small><?php echo htmlspecialchars($doctor['specialization']); ?></small>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="page-title">Doctor Dashboard</h1>
            <div class="d-flex align-items-center gap-3">
                <?php include '../includes/notifications.php'; ?>
                <button class="btn btn-primary d-md-none" id="sidebarToggle">
                    <i class="bi bi-list"></i>
                </button>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row g-4 mb-4">
            <div class="col-xl-3 col-md-6">
                <div class="stat-card primary">
                    <i class="bi bi-calendar-check stat-icon"></i>
                    <div class="stat-value"><?php echo $stats['total_appointments']; ?></div>
                    <div class="stat-label">Total Appointments</div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="stat-card success">
                    <i class="bi bi-calendar-date stat-icon"></i>
                    <div class="stat-value"><?php echo $stats['today_appointments']; ?></div>
                    <div class="stat-label">Today's Appointments</div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="stat-card warning">
                    <i class="bi bi-clock stat-icon"></i>
                    <div class="stat-value"><?php echo $stats['pending_appointments']; ?></div>
                    <div class="stat-label">Pending Appointments</div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="stat-card info">
                    <i class="bi bi-people stat-icon"></i>
                    <div class="stat-value"><?php echo $stats['total_patients']; ?></div>
                    <div class="stat-label">Total Patients</div>
                </div>
            </div>
        </div>

        <!-- All Appointments Table -->
        <div class="card">
            <div class="card-header">
                <h5>All Appointments</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Patient</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($all_appointments as $appointment): ?>
                            <tr>
                                <td><?php echo date('M d, Y', strtotime($appointment['appointment_date'])); ?></td>
                                <td><?php echo date('h:i A', strtotime($appointment['appointment_time'])); ?></td>
                                <td><?php echo htmlspecialchars($appointment['patient_name']); ?></td>
                                <td>
                                    <?php
                                        $raw_status = $appointment['status'];
                                        $status = strtolower(trim($raw_status));
                                        $badge = 'bg-success text-white';
                                        $label = 'Approved';
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
                                        }
                                    ?>
                                    <span class="badge <?php echo $badge; ?>">
                                        <?php echo $label; ?>
                                    </span>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#viewAppointmentModal<?php echo $appointment['id']; ?>">
                                        <i class="bi bi-eye"></i> View
                                    </button>
                                    <?php if ($status == 'pending'): ?>
                                        <form method="POST" action="update_appointment.php" style="display:inline;">
                                            <input type="hidden" name="appointment_id" value="<?php echo $appointment['id']; ?>">
                                            <input type="hidden" name="status" value="approved">
                                            <button type="submit" class="btn btn-success btn-sm" title="Approve">
                                                <i class="bi bi-check-lg"></i>
                                            </button>
                                        </form>
                                        <form method="POST" action="update_appointment.php" style="display:inline;">
                                            <input type="hidden" name="appointment_id" value="<?php echo $appointment['id']; ?>">
                                            <input type="hidden" name="status" value="disapproved">
                                            <button type="submit" class="btn btn-danger btn-sm" title="Disapprove">
                                                <i class="bi bi-x-lg"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <!-- View Appointment Modal -->
                            <div class="modal fade" id="viewAppointmentModal<?php echo $appointment['id']; ?>" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Appointment Details</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="mb-3">
                                                <label class="form-label fw-bold">Patient:</label>
                                                <p><?php echo htmlspecialchars($appointment['patient_name']); ?></p>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label fw-bold">Date:</label>
                                                <p><?php echo date('M d, Y', strtotime($appointment['appointment_date'])); ?></p>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label fw-bold">Time:</label>
                                                <p><?php echo date('h:i A', strtotime($appointment['appointment_time'])); ?></p>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label fw-bold">Status:</label>
                                                <p><span class="badge <?php echo $badge; ?>"><?php echo $label; ?></span></p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Completed Appointments Table -->
        <div class="card mt-4">
            <div class="card-header">
                <h5>Completed Appointments</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Patient</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($completed_appointments as $appointment): ?>
                            <tr>
                                <td><?php echo date('M d, Y', strtotime($appointment['appointment_date'])); ?></td>
                                <td><?php echo date('h:i A', strtotime($appointment['appointment_time'])); ?></td>
                                <td><?php echo htmlspecialchars($appointment['patient_name']); ?></td>
                                <td><span class="badge bg-info">Completed</span></td>
                                <td>
                                    <form method="POST" action="delete_completed_appointment.php" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this completed appointment?');">
                                        <input type="hidden" name="appointment_id" value="<?php echo $appointment['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-danger" title="Delete">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
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

        // View appointment details
        function viewAppointment(id) {
            // TODO: Implement appointment details view
            alert('View appointment details - Coming soon!');
        }

        // Update appointment status
        function updateStatus(id, status) {
            if (!confirm('Are you sure you want to ' + status + ' this appointment?')) {
                return;
            }

            const formData = new FormData();
            formData.append('appointment_id', id);
            formData.append('status', status);

            fetch('update_appointment.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.message || 'Error updating appointment status');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error updating appointment status');
            });
        }
    </script>
</body>
</html> 