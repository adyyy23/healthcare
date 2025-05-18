<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Handle user deletion
if (isset($_POST['delete_patient'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'patient'");
        $stmt->execute([$_POST['patient_id']]);
        $success = "Patient deleted successfully!";
    } catch (Exception $e) {
        $error = "Error deleting patient: " . $e->getMessage();
    }
}

// Get search parameters
$search = isset($_GET['search']) ? $_GET['search'] : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'name';
$order = isset($_GET['order']) ? $_GET['order'] : 'asc';

// Build the query with search and sorting
$query = "
    SELECT p.*, u.name, u.email 
    FROM patients p 
    JOIN users u ON p.user_id = u.id 
    WHERE 1=1
";

if (!empty($search)) {
    $query .= " AND (u.name LIKE ? OR u.email LIKE ? OR p.phone_number LIKE ?)";
}

$query .= " ORDER BY " . ($sort === 'name' ? 'u.name' : ($sort === 'email' ? 'u.email' : 'p.phone_number')) . " " . $order;

$stmt = $pdo->prepare($query);

if (!empty($search)) {
    $searchParam = "%$search%";
    $stmt->execute([$searchParam, $searchParam, $searchParam]);
} else {
    $stmt->execute();
}

$patients = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Patients - HealthCare Portal</title>
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

        .card-body.p-4 {
            padding: 1.5rem !important;
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

        .btn-group .btn {
            margin-right: 0.25rem;
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
                <a class="nav-link active" href="manage_patients.php">
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
            <h1 class="page-title">Manage Patients</h1>
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
                <h5>Search & Filter Patients</h5>
            </div>
            <div class="card-body p-4">
                <form method="GET" class="row g-3">
                    <div class="col-md-6">
                        <div class="search-box">
                            <i class="bi bi-search"></i>
                            <input type="text" class="form-control" name="search" 
                                   placeholder="Search by name, email, or phone number"
                                   value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" name="sort">
                            <option value="name" <?php echo $sort === 'name' ? 'selected' : ''; ?>>Sort by Name</option>
                            <option value="email" <?php echo $sort === 'email' ? 'selected' : ''; ?>>Sort by Email</option>
                            <option value="phone" <?php echo $sort === 'phone' ? 'selected' : ''; ?>>Sort by Phone</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" name="order">
                            <option value="asc" <?php echo $order === 'asc' ? 'selected' : ''; ?>>Ascending</option>
                            <option value="desc" <?php echo $order === 'desc' ? 'selected' : ''; ?>>Descending</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-funnel"></i> Apply Filters
                        </button>
                        <?php if (!empty($search) || $sort !== 'name' || $order !== 'asc'): ?>
                            <a href="manage_patients.php" class="btn btn-secondary">
                                <i class="bi bi-x-circle"></i> Clear Filters
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <!-- Patients List -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5>Registered Patients</h5>
                <span class="badge bg-primary"><?php echo count($patients); ?> Patients</span>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>
                                    <a href="?search=<?php echo urlencode($search); ?>&sort=name&order=<?php echo $sort === 'name' && $order === 'asc' ? 'desc' : 'asc'; ?>" 
                                       class="sort-link">
                                        Name
                                        <?php if ($sort === 'name'): ?>
                                            <i class="bi bi-arrow-<?php echo $order === 'asc' ? 'up' : 'down'; ?>"></i>
                                        <?php endif; ?>
                                    </a>
                                </th>
                                <th>
                                    <a href="?search=<?php echo urlencode($search); ?>&sort=email&order=<?php echo $sort === 'email' && $order === 'asc' ? 'desc' : 'asc'; ?>" 
                                       class="sort-link">
                                        Email
                                        <?php if ($sort === 'email'): ?>
                                            <i class="bi bi-arrow-<?php echo $order === 'asc' ? 'up' : 'down'; ?>"></i>
                                        <?php endif; ?>
                                    </a>
                                </th>
                                <th>
                                    <a href="?search=<?php echo urlencode($search); ?>&sort=phone&order=<?php echo $sort === 'phone' && $order === 'asc' ? 'desc' : 'asc'; ?>" 
                                       class="sort-link">
                                        Phone Number
                                        <?php if ($sort === 'phone'): ?>
                                            <i class="bi bi-arrow-<?php echo $order === 'asc' ? 'up' : 'down'; ?>"></i>
                                        <?php endif; ?>
                                    </a>
                                </th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($patients as $patient): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($patient['name']); ?></td>
                                <td><?php echo htmlspecialchars($patient['email']); ?></td>
                                <td><?php echo htmlspecialchars($patient['phone_number']); ?></td>
                                <td>
                                    <div class="btn-group">
                                        <button type="button" class="btn btn-sm btn-primary" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#viewPatientModal<?php echo $patient['id']; ?>">
                                            <i class="bi bi-eye"></i> View
                                        </button>
                                        <button type="button" class="btn btn-sm btn-danger" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#deletePatientModal<?php echo $patient['id']; ?>">
                                            <i class="bi bi-trash"></i> Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>

                            <!-- View Patient Modal -->
                            <div class="modal fade" id="viewPatientModal<?php echo $patient['id']; ?>" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Patient Details</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="mb-3">
                                                <label class="form-label fw-bold">Name:</label>
                                                <p><?php echo htmlspecialchars($patient['name']); ?></p>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label fw-bold">Email:</label>
                                                <p><?php echo htmlspecialchars($patient['email']); ?></p>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label fw-bold">Phone Number:</label>
                                                <p><?php echo htmlspecialchars($patient['phone_number']); ?></p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Delete Patient Modal -->
                            <div class="modal fade" id="deletePatientModal<?php echo $patient['id']; ?>" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Delete Patient</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <p>Are you sure you want to delete this patient?</p>
                                            <form method="POST" action="">
                                                <input type="hidden" name="patient_id" value="<?php echo $patient['user_id']; ?>">
                                                <button type="submit" name="delete_patient" class="btn btn-danger">Delete</button>
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                            </form>
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