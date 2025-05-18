<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is patient
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header('Location: ../login.php');
    exit();
}

// Get patient information
$stmt = $pdo->prepare("
    SELECT p.*, u.name, u.email 
    FROM patients p 
    JOIN users u ON p.user_id = u.id 
    WHERE p.user_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$patient = $stmt->fetch();

// Get upcoming appointments
$stmt = $pdo->prepare("
    SELECT a.*, d.specialization, u.name as doctor_name, ds.date, ds.start_time, ds.end_time
    FROM appointments a
    JOIN doctors d ON a.doctor_id = d.id
    JOIN users u ON d.user_id = u.id
    JOIN doctor_schedules ds ON a.schedule_id = ds.id
    WHERE a.patient_id = ? AND ds.date >= CURDATE()
    ORDER BY ds.date ASC, ds.start_time ASC
    LIMIT 3
");
$stmt->execute([$patient['id']]);
$upcoming_appointments = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Healthcare Portal - Patient Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #34495e;
            --accent-color: #3498db;
            --success-color: #2ecc71;
            --warning-color: #f1c40f;
            --danger-color: #e74c3c;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f8f9fa;
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

        .hero-section {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            padding: 4rem 0;
            margin-bottom: 2rem;
        }

        .feature-card {
            background: white;
            border-radius: 1rem;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            transition: transform 0.3s ease;
        }

        .feature-card:hover {
            transform: translateY(-5px);
        }

        .feature-icon {
            font-size: 2.5rem;
            color: var(--accent-color);
            margin-bottom: 1rem;
        }

        .appointment-card {
            background: white;
            border-radius: 1rem;
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        }

        .ad-container {
            background: white;
            border-radius: 1rem;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        }

        .ad-placeholder {
            background: #f8f9fa;
            border: 2px dashed #dee2e6;
            border-radius: 0.5rem;
            padding: 2rem;
            text-align: center;
            color: #6c757d;
        }

        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .action-card {
            background: white;
            border-radius: 1rem;
            padding: 1.5rem;
            text-align: center;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            transition: transform 0.3s ease;
            cursor: pointer;
        }

        .action-card:hover {
            transform: translateY(-5px);
        }

        .action-icon {
            font-size: 2rem;
            color: var(--accent-color);
            margin-bottom: 1rem;
        }

        .footer {
            background: var(--primary-color);
            color: white;
            padding: 2rem 0;
            margin-top: 4rem;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand" href="index.php">
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
                        <a class="nav-link" href="doctors.php">
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

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h1 class="display-4 mb-4">Welcome, <?php echo htmlspecialchars($patient['name']); ?>!</h1>
                    <p class="lead mb-4">Your health is our priority. Book appointments, consult with doctors, and manage your healthcare all in one place.</p>
                    <a href="doctors.php" class="btn btn-light btn-lg">
                        <i class="bi bi-search"></i> Find a Doctor
                    </a>
                </div>
                <div class="col-md-6">
                    <img src="../assets/images/hero-image.svg" alt="Healthcare" class="img-fluid">
                </div>
            </div>
        </div>
    </section>

    <!-- Main Content -->
    <div class="container">
        <!-- Quick Actions -->
        <div class="quick-actions">
            <div class="action-card" onclick="window.location.href='book-appointment.php'">
                <i class="bi bi-calendar-plus action-icon"></i>
                <h5>Book Appointment</h5>
                <p class="text-muted">Schedule a new consultation</p>
            </div>
            <div class="action-card" onclick="window.location.href='doctors.php'">
                <i class="bi bi-search action-icon"></i>
                <h5>Find Doctors</h5>
                <p class="text-muted">Search for specialists</p>
            </div>
            <div class="action-card" onclick="window.location.href='messages.php'">
                <i class="bi bi-chat-dots action-icon"></i>
                <h5>Messages</h5>
                <p class="text-muted">Chat with your doctors</p>
            </div>
            <div class="action-card" onclick="window.location.href='profile.php'">
                <i class="bi bi-person action-icon"></i>
                <h5>My Profile</h5>
                <p class="text-muted">Update your information</p>
            </div>
        </div>

        <div class="row">
            <!-- Main Content -->
            <div class="col-lg-8">
                <!-- Upcoming Appointments -->
                <div class="card mb-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Upcoming Appointments</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($upcoming_appointments)): ?>
                            <div class="text-center py-4">
                                <i class="bi bi-calendar-x text-muted" style="font-size: 3rem;"></i>
                                <h5 class="mt-3">No upcoming appointments</h5>
                                <a href="book-appointment.php" class="btn btn-primary mt-3">
                                    <i class="bi bi-calendar-plus"></i> Book an Appointment
                                </a>
                            </div>
                        <?php else: ?>
                            <?php foreach ($upcoming_appointments as $appointment): ?>
                                <div class="appointment-card">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="mb-1">Dr. <?php echo htmlspecialchars($appointment['doctor_name']); ?></h6>
                                            <p class="mb-1 text-muted"><?php echo htmlspecialchars($appointment['specialization']); ?></p>
                                            <p class="mb-0">
                                                <i class="bi bi-calendar"></i> 
                                                <?php echo date('F d, Y', strtotime($appointment['date'])); ?>
                                            </p>
                                            <p class="mb-0">
                                                <i class="bi bi-clock"></i>
                                                <?php echo date('h:i A', strtotime($appointment['start_time'])); ?> - 
                                                <?php echo date('h:i A', strtotime($appointment['end_time'])); ?>
                                            </p>
                                        </div>
                                        <span class="badge bg-<?php 
                                            echo $appointment['status'] === 'pending' ? 'warning' : 
                                                ($appointment['status'] === 'approved' ? 'success' : 'danger'); 
                                        ?>">
                                            <?php echo ucfirst($appointment['status']); ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Health Tips -->
                <div class="card">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Health Tips</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-4">
                                <div class="feature-card">
                                    <i class="bi bi-droplet feature-icon"></i>
                                    <h5>Stay Hydrated</h5>
                                    <p class="text-muted">Drink at least 8 glasses of water daily to maintain good health.</p>
                                </div>
                            </div>
                            <div class="col-md-6 mb-4">
                                <div class="feature-card">
                                    <i class="bi bi-activity feature-icon"></i>
                                    <h5>Regular Exercise</h5>
                                    <p class="text-muted">30 minutes of daily exercise can significantly improve your health.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="col-lg-4">
                <!-- Ad Space -->
                <div class="ad-container">
                    <h6 class="mb-3">Sponsored</h6>
                    <div class="ad-placeholder">
                        <i class="bi bi-megaphone" style="font-size: 2rem;"></i>
                        <p class="mt-2">Advertisement Space</p>
                    </div>
                </div>

                <!-- Health News -->
                <div class="card">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Health News</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <h6>Latest Medical Breakthroughs</h6>
                            <p class="text-muted small">Stay updated with the latest developments in healthcare.</p>
                            <a href="#" class="btn btn-sm btn-outline-primary">Read More</a>
                        </div>
                        <hr>
                        <div class="mb-3">
                            <h6>COVID-19 Updates</h6>
                            <p class="text-muted small">Get the latest information about COVID-19 and vaccination.</p>
                            <a href="#" class="btn btn-sm btn-outline-primary">Read More</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="row">
                <div class="col-md-4">
                    <h5>HealthCare Portal</h5>
                    <p class="text-white-50">Your trusted partner in healthcare management.</p>
                </div>
                <div class="col-md-4">
                    <h5>Quick Links</h5>
                    <ul class="list-unstyled">
                        <li><a href="#" class="text-white-50">About Us</a></li>
                        <li><a href="#" class="text-white-50">Contact</a></li>
                        <li><a href="#" class="text-white-50">Privacy Policy</a></li>
                        <li><a href="#" class="text-white-50">Terms of Service</a></li>
                    </ul>
                </div>
                <div class="col-md-4">
                    <h5>Contact Us</h5>
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