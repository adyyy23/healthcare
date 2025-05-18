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

// Filtering logic
$filter_patient = isset($_GET['patient']) ? trim($_GET['patient']) : '';
$filter_status = isset($_GET['status']) ? trim($_GET['status']) : '';
$filter_date = isset($_GET['date']) ? trim($_GET['date']) : '';

$query = "
    SELECT a.*, p.phone_number as patient_phone, u.name as patient_name, u.id as user_id
    FROM appointments a
    JOIN patients p ON a.patient_id = p.id
    JOIN users u ON p.user_id = u.id
    WHERE a.doctor_id = ?
";
$params = [$doctor['id']];
if ($filter_patient !== '') {
    $query .= " AND u.name LIKE ?";
    $params[] = "%$filter_patient%";
}
if ($filter_status !== '') {
    $query .= " AND a.status = ?";
    $params[] = $filter_status;
}
if ($filter_date !== '') {
    $query .= " AND a.appointment_date = ?";
    $params[] = $filter_date;
}
$query .= " ORDER BY a.appointment_date DESC, a.appointment_time DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
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
        .status-approved    { background: #e6f4ea; color: #219150; }
        .status-pending     { background: #fff7e6; color: #b78103; }
        .status-completed   { background: #e6f0fa; color: #2563eb; }
        .status-cancelled,
        .status-disapproved { background: #fdeaea; color: #d32f2f; }
        .status-other       { background: #ececec; color: #888; }

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

        .patient-info-link {
            color: #4e73df;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.2s;
        }

        .patient-info-link:hover {
            color: #224abe;
            text-decoration: underline;
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
                <a class="nav-link active" href="appointments.php">
                    <i class="bi bi-calendar-check"></i> Appointments
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="schedule.php">
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
            <h1 class="page-title">Appointments</h1>
            <button class="btn btn-primary d-md-none" id="sidebarToggle">
                <i class="bi bi-list"></i>
            </button>
        </div>

        <!-- Filtering Form -->
        <div class="card mb-4">
            <div class="card-header">
                <h5>Filter Appointments</h5>
            </div>
            <div class="card-body p-4">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <input type="text" class="form-control" name="patient" placeholder="Patient Name" value="<?php echo htmlspecialchars($filter_patient); ?>">
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" name="status">
                            <option value="">All Status</option>
                            <option value="pending" <?php if($filter_status=='pending') echo 'selected'; ?>>Pending</option>
                            <option value="approved" <?php if($filter_status=='approved') echo 'selected'; ?>>Approved</option>
                            <option value="completed" <?php if($filter_status=='completed') echo 'selected'; ?>>Completed</option>
                            <option value="cancelled" <?php if($filter_status=='cancelled') echo 'selected'; ?>>Cancelled</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <input type="date" class="form-control" name="date" value="<?php echo htmlspecialchars($filter_date); ?>">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-funnel"></i> Filter
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Appointments List -->
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
                            <?php foreach ($appointments as $appointment): ?>
                            <tr>
                                <td><?php echo date('M d, Y', strtotime($appointment['appointment_date'])); ?></td>
                                <td><?php echo date('h:i A', strtotime($appointment['appointment_time'])); ?></td>
                                <td><?php echo htmlspecialchars($appointment['patient_name']); ?></td>
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
                                        }
                                    ?>
                                    <span class="badge <?php echo $badge; ?>">
                                        <?php echo $label; ?>
                                    </span>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#rescheduleModal<?php echo $appointment['id']; ?>">
                                        <i class="bi bi-pencil"></i> Edit
                                    </button>
                                    <?php if ($status != 'completed' && $status != 'cancelled'): ?>
                                        <form method="POST" action="mark_completed.php" style="display:inline;">
                                            <input type="hidden" name="appointment_id" value="<?php echo $appointment['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-success" title="Mark as Completed">
                                                <i class="bi bi-check2-circle"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>

                            <!-- Reschedule Modal -->
                            <div class="modal fade" id="rescheduleModal<?php echo $appointment['id']; ?>" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Reschedule Appointment</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <form method="POST" action="reschedule_appointment.php">
                                            <div class="modal-body">
                                                <input type="hidden" name="appointment_id" value="<?php echo $appointment['id']; ?>">
                                                <div class="mb-3">
                                                    <label class="form-label">Date</label>
                                                    <input type="date" class="form-control" name="date" value="<?php echo htmlspecialchars($appointment['appointment_date']); ?>" required>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Time</label>
                                                    <input type="time" class="form-control" name="time" value="<?php echo htmlspecialchars($appointment['appointment_time']); ?>" required>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="submit" class="btn btn-success">Save Changes</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Patient Info Modal -->
    <div class="modal fade" id="patientInfoModal" tabindex="-1" aria-labelledby="patientInfoModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="patientInfoModalLabel">Patient Profile</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="patientProfileCard">
                        <div class="text-center py-3" id="patientProfileLoading" style="display:none;">
                            <div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div>
                        </div>
                        <div id="patientProfileContent" style="display:none;">
                            <h5 id="modalPatientName"></h5>
                            <p class="mb-2"><strong>Email:</strong> <span id="modalPatientEmail"></span></p>
                            <p class="mb-2"><strong>Phone:</strong> <span id="modalPatientPhone"></span></p>
                            <p class="mb-2"><strong>Gender:</strong> <span id="modalPatientGender"></span></p>
                            <p class="mb-2"><strong>Date of Birth:</strong> <span id="modalPatientDOB"></span></p>
                            <p class="mb-2"><strong>Address:</strong> <span id="modalPatientAddress"></span></p>
                            <p class="mb-2"><strong>Medical History:</strong> <span id="modalPatientHistory"></span></p>
                        </div>
                        <div id="patientProfileError" class="alert alert-danger" style="display:none;"></div>
                    </div>
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

        document.addEventListener('DOMContentLoaded', function() {
            var patientLinks = document.querySelectorAll('.patient-info-link');
            var loading = document.getElementById('patientProfileLoading');
            var content = document.getElementById('patientProfileContent');
            var error = document.getElementById('patientProfileError');

            patientLinks.forEach(function(link) {
                link.addEventListener('click', function() {
                    var userId = this.getAttribute('data-user-id');
                    loading.style.display = 'block';
                    content.style.display = 'none';
                    error.style.display = 'none';
                    fetch('get_patient_profile.php?user_id=' + encodeURIComponent(userId))
                        .then(response => response.json())
                        .then(function(data) {
                            loading.style.display = 'none';
                            if (data.error) {
                                error.textContent = data.error;
                                error.style.display = 'block';
                            } else {
                                document.getElementById('modalPatientName').textContent = data.name || '';
                                document.getElementById('modalPatientEmail').textContent = data.email || '';
                                document.getElementById('modalPatientPhone').textContent = data.phone_number || '';
                                document.getElementById('modalPatientGender').textContent = data.gender || '';
                                document.getElementById('modalPatientDOB').textContent = data.date_of_birth || '';
                                document.getElementById('modalPatientAddress').textContent = data.address || '';
                                document.getElementById('modalPatientHistory').textContent = data.medical_history || '';
                                content.style.display = 'block';
                            }
                        })
                        .catch(function() {
                            loading.style.display = 'none';
                            error.textContent = 'Failed to load patient profile.';
                            error.style.display = 'block';
                        });
                });
            });
        });
    </script>
</body>
</html> 