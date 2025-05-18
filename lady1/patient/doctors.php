<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is patient
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header('Location: ../login.php');
    exit();
}

// Get search parameters
$search = isset($_GET['search']) ? $_GET['search'] : '';
$specialization = isset($_GET['specialization']) ? $_GET['specialization'] : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'name';

// Get all specializations for filter
$stmt = $pdo->query("SELECT DISTINCT specialization FROM doctors WHERE specialization IS NOT NULL ORDER BY specialization");
$specializations = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Build the query
$query = "
    SELECT 
        d.*,
        u.name,
        u.email,
        COUNT(DISTINCT a.id) as total_appointments
    FROM doctors d
    JOIN users u ON d.user_id = u.id
    LEFT JOIN appointments a ON d.id = a.doctor_id
    WHERE 1=1
";

$params = [];

if ($search) {
    $query .= " AND (u.name LIKE ? OR d.specialization LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($specialization) {
    $query .= " AND d.specialization = ?";
    $params[] = $specialization;
}

$query .= " GROUP BY d.id, u.name, u.email";

// Add sorting
switch ($sort) {
    case 'appointments':
        $query .= " ORDER BY total_appointments DESC";
        break;
    default:
        $query .= " ORDER BY u.name ASC";
}

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$doctors = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Find Doctors - Healthcare Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4e73df;
            --secondary-color: #858796;
            --success-color: #1cc88a;
            --info-color: #36b9cc;
            --warning-color: #f6c23e;
            --danger-color: #e74a3b;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f8f9fc;
        }

        .navbar {
            background: var(--primary-color);
            padding: 1rem 0;
        }

        .navbar-brand {
            color: white !important;
            font-weight: 600;
        }

        .nav-link {
            color: rgba(255,255,255,0.8) !important;
            transition: all 0.3s ease;
        }

        .nav-link:hover {
            color: white !important;
        }

        .page-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, #224abe 100%);
            color: white;
            padding: 3rem 0;
            margin-bottom: 3rem;
        }

        .page-title {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }

        .page-subtitle {
            font-size: 1.1rem;
            opacity: 0.9;
        }

        .search-section {
            background: white;
            border-radius: 1rem;
            padding: 2rem;
            margin-bottom: 3rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        }

        .search-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 1.5rem;
        }

        .form-control {
            border-radius: 0.5rem;
            padding: 0.75rem 1rem;
            border: 1px solid #e3e6f0;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(78, 115, 223, 0.25);
        }

        .btn-search {
            background: var(--primary-color);
            color: white;
            padding: 0.75rem 2rem;
            border-radius: 0.5rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-search:hover {
            background: #2e59d9;
            transform: translateY(-2px);
        }

        .doctor-card {
            background: white;
            border-radius: 1rem;
            overflow: hidden;
            transition: all 0.3s ease;
            height: 100%;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        }

        .doctor-card:hover {
            transform: translateY(-5px);
        }

        .doctor-image {
            width: 100%;
            height: 250px;
            object-fit: cover;
        }

        .doctor-info {
            padding: 1.5rem;
        }

        .doctor-name {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .doctor-specialty {
            color: var(--secondary-color);
            margin-bottom: 1rem;
        }

        .doctor-details {
            display: flex;
            align-items: center;
            margin-bottom: 0.5rem;
            color: var(--secondary-color);
        }

        .doctor-details i {
            color: var(--primary-color);
            margin-right: 0.5rem;
            font-size: 1.1rem;
        }

        .btn-book {
            background: var(--primary-color);
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            font-weight: 500;
            transition: all 0.3s ease;
            width: 100%;
        }

        .btn-book:hover {
            background: #2e59d9;
            transform: translateY(-2px);
        }

        .empty-state {
            text-align: center;
            padding: 3rem;
            background: white;
            border-radius: 1rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        }

        .empty-state i {
            font-size: 4rem;
            color: var(--secondary-color);
            margin-bottom: 1rem;
        }

        .empty-state h3 {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: var(--primary-color);
        }

        .empty-state p {
            color: var(--secondary-color);
            margin-bottom: 2rem;
        }

        .footer {
            background: var(--primary-color);
            color: white;
            padding: 3rem 0;
            margin-top: 4rem;
        }

        .footer-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
        }

        .footer-link {
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .footer-link:hover {
            color: white;
        }

        .social-icon {
            width: 35px;
            height: 35px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
            margin-right: 0.5rem;
            transition: all 0.3s ease;
        }

        .social-icon:hover {
            background: white;
            color: var(--primary-color);
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <i class="bi bi-heart-pulse"></i> HealthCare Portal
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="appointments.php">
                            <i class="bi bi-calendar-check"></i> Appointments
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="doctors.php">
                            <i class="bi bi-person-badge"></i> Find Doctors
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
            </div>
        </div>
    </nav>

    <!-- Page Header -->
    <header class="page-header">
        <div class="container">
            <h1 class="page-title">Find Doctors</h1>
            <p class="page-subtitle">Search and book appointments with our qualified healthcare professionals</p>
        </div>
    </header>

    <!-- Search Section -->
    <section class="search-section">
        <div class="container">
            <h2 class="search-title">Search Doctors</h2>
            <form method="GET" action="" class="row g-3">
                <div class="col-md-4">
                    <input type="text" class="form-control" name="search" placeholder="Search by name or specialization" value="<?php echo htmlspecialchars($search ?? ''); ?>">
                </div>
                <div class="col-md-3">
                    <select class="form-control" name="specialization">
                        <option value="">All Specializations</option>
                        <?php foreach ($specializations as $spec): ?>
                            <option value="<?php echo $spec; ?>" <?php echo ($specialization ?? '') === $spec ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($spec); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <select class="form-control" name="sort">
                        <option value="name" <?php echo ($sort ?? '') === 'name' ? 'selected' : ''; ?>>Sort by Name</option>
                        <option value="appointments" <?php echo ($sort ?? '') === 'appointments' ? 'selected' : ''; ?>>Sort by Appointments</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-search w-100">
                        <i class="bi bi-search"></i> Search
                    </button>
                </div>
            </form>
        </div>
    </section>

    <!-- Doctors List -->
    <section class="container mb-5">
        <?php if (empty($doctors)): ?>
            <div class="empty-state">
                <i class="bi bi-search"></i>
                <h3>No Doctors Found</h3>
                <p>Try adjusting your search criteria to find more doctors.</p>
            </div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($doctors as $doctor): ?>
                    <div class="col-md-4">
                        <div class="doctor-card">
                            <img src="../assets/images/default-doctor.jpg" alt="Dr. <?php echo htmlspecialchars($doctor['name']); ?>" class="doctor-image">
                            <div class="doctor-info">
                                <h4 class="doctor-name">Dr. <?php echo htmlspecialchars($doctor['name']); ?></h4>
                                <p class="doctor-specialty"><?php echo htmlspecialchars($doctor['specialization']); ?></p>
                                <div class="doctor-details">
                                    <i class="bi bi-envelope"></i>
                                    <span><?php echo htmlspecialchars($doctor['email']); ?></span>
                                </div>
                                <div class="doctor-details">
                                    <i class="bi bi-calendar-check"></i>
                                    <span><?php echo $doctor['total_appointments']; ?> Appointments</span>
                                </div>
                                <a href="book-appointment.php?doctor=<?php echo $doctor['id']; ?>" class="btn btn-book mt-3">
                                    <i class="bi bi-calendar-plus"></i> Book Appointment
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="row">
                <div class="col-md-4">
                    <h5 class="footer-title">HealthCare Portal</h5>
                    <p class="text-white-50">Your trusted partner in healthcare management.</p>
                    <div class="mt-3">
                        <a href="#" class="social-icon"><i class="bi bi-facebook"></i></a>
                        <a href="#" class="social-icon"><i class="bi bi-twitter"></i></a>
                        <a href="#" class="social-icon"><i class="bi bi-linkedin"></i></a>
                        <a href="#" class="social-icon"><i class="bi bi-instagram"></i></a>
                    </div>
                </div>
                <div class="col-md-4">
                    <h5 class="footer-title">Quick Links</h5>
                    <ul class="list-unstyled">
                        <li><a href="#" class="footer-link">About Us</a></li>
                        <li><a href="#" class="footer-link">Services</a></li>
                        <li><a href="#" class="footer-link">Doctors</a></li>
                        <li><a href="#" class="footer-link">Contact</a></li>
                    </ul>
                </div>
                <div class="col-md-4">
                    <h5 class="footer-title">Contact Us</h5>
                    <ul class="list-unstyled text-white-50">
                        <li><i class="bi bi-envelope"></i> support@healthcare.com</li>
                        <li><i class="bi bi-phone"></i> +1 234 567 890</li>
                        <li><i class="bi bi-geo-alt"></i> 123 Healthcare St, Medical City</li>
                    </ul>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 