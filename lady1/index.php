<?php
session_start();
require_once 'config/database.php';

// Get featured doctors
$stmt = $pdo->prepare("
    SELECT d.*, u.name, u.email, u.profile_image
    FROM doctors d
    JOIN users u ON d.user_id = u.id
    WHERE d.is_featured = 1
    LIMIT 4
");
$stmt->execute();
$featuredDoctors = $stmt->fetchAll();

// Get all services
$stmt = $pdo->prepare("SELECT * FROM services WHERE is_active = 1");
$stmt->execute();
$services = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Healthcare Portal - Your Health, Our Priority</title>
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
            position: relative;
            overflow: hidden;
        }

        .hero-content {
            position: relative;
            z-index: 2;
        }

        .hero-bg {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: url('assets/images/hero-bg.jpg') center/cover;
            opacity: 0.1;
            z-index: 1;
        }

        .ad-banner {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 1rem;
            padding: 1.5rem;
            margin-top: 2rem;
            backdrop-filter: blur(10px);
        }

        .consultation-form {
            background: white;
            border-radius: 1rem;
            padding: 2rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            margin-top: -3rem;
            position: relative;
            z-index: 3;
        }

        .services-section {
            padding: 4rem 0;
            background: white;
        }

        .service-card {
            text-align: center;
            padding: 2rem;
            border-radius: 1rem;
            background: white;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            transition: transform 0.3s ease;
            height: 100%;
        }

        .service-card:hover {
            transform: translateY(-5px);
        }

        .service-icon {
            font-size: 2.5rem;
            color: var(--accent-color);
            margin-bottom: 1rem;
        }

        .doctors-section {
            padding: 4rem 0;
            background: #f8f9fa;
        }

        .doctor-card {
            background: white;
            border-radius: 1rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            transition: transform 0.3s ease;
            height: 100%;
        }

        .doctor-card:hover {
            transform: translateY(-5px);
        }

        .doctor-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
            border-radius: 1rem 1rem 0 0;
        }

        .footer {
            background: var(--primary-color);
            color: white;
            padding: 2rem 0;
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
                        <a class="nav-link" href="#services">Services</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#doctors">Doctors</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#contact">Contact</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="login.php">Login</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link btn btn-primary ms-2" href="register.php">Register</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section with Advertisement -->
    <section class="hero-section">
        <div class="hero-bg"></div>
        <div class="container hero-content">
            <div class="row">
                <div class="col-md-8">
                    <h1 class="display-4 fw-bold">Your Health, Our Priority</h1>
                    <p class="lead">Experience world-class healthcare services with our expert doctors and modern facilities.</p>
                    <div class="ad-banner">
                        <h4><i class="bi bi-star-fill text-warning"></i> Special Offer!</h4>
                        <p class="mb-0">Get 20% off on your first consultation. Book now and take the first step towards better health.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Consultation Form -->
    <div class="container">
        <div class="consultation-form">
            <h3 class="text-center mb-4">Need a Consultation?</h3>
            <form action="book_consultation.php" method="POST">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <input type="text" class="form-control" name="name" placeholder="Your Name" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <input type="email" class="form-control" name="email" placeholder="Your Email" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <input type="tel" class="form-control" name="phone" placeholder="Your Phone" required>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <select class="form-select" name="service" required>
                            <option value="">Select Service</option>
                            <?php foreach ($services as $service): ?>
                                <option value="<?php echo $service['id']; ?>"><?php echo htmlspecialchars($service['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <input type="date" class="form-control" name="preferred_date" required>
                    </div>
                </div>
                <div class="mb-3">
                    <textarea class="form-control" name="message" rows="3" placeholder="Describe your symptoms or concerns" required></textarea>
                </div>
                <div class="text-center">
                    <button type="submit" class="btn btn-primary btn-lg">Request Consultation</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Services Section -->
    <section id="services" class="services-section">
        <div class="container">
            <h2 class="text-center mb-5">Our Services</h2>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="service-card">
                        <i class="bi bi-heart-pulse service-icon"></i>
                        <h4>General Checkup</h4>
                        <p>Comprehensive health assessment and preventive care services.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="service-card">
                        <i class="bi bi-calendar-check service-icon"></i>
                        <h4>Online Consultation</h4>
                        <p>Connect with doctors remotely for medical advice and prescriptions.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="service-card">
                        <i class="bi bi-capsule service-icon"></i>
                        <h4>Pharmacy Services</h4>
                        <p>Easy access to medications with home delivery options.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Featured Doctors Section -->
    <section id="doctors" class="doctors-section">
        <div class="container">
            <h2 class="text-center mb-5">Our Featured Doctors</h2>
            <div class="row g-4">
                <?php foreach ($featuredDoctors as $doctor): ?>
                <div class="col-md-3">
                    <div class="doctor-card">
                        <img src="<?php echo $doctor['profile_image'] ?? 'assets/images/default-doctor.jpg'; ?>" 
                             alt="Dr. <?php echo htmlspecialchars($doctor['name']); ?>" 
                             class="doctor-image">
                        <div class="p-3">
                            <h5 class="mb-1">Dr. <?php echo htmlspecialchars($doctor['name']); ?></h5>
                            <p class="text-muted mb-2"><?php echo htmlspecialchars($doctor['specialization']); ?></p>
                            <div class="d-flex justify-content-between align-items-center">
                                <a href="doctor-profile.php?id=<?php echo $doctor['id']; ?>" class="btn btn-outline-primary btn-sm">View Profile</a>
                                <a href="book-appointment.php?doctor_id=<?php echo $doctor['id']; ?>" class="btn btn-primary btn-sm">Book Now</a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

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