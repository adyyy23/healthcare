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

// Get unread notifications count
$stmt = $pdo->prepare("
    SELECT COUNT(*) as unread_count 
    FROM notifications 
    WHERE user_id = ? AND is_read = FALSE
");
$stmt->execute([$_SESSION['user_id']]);
$notification_count = $stmt->fetch()['unread_count'];

// Get appointments
$stmt = $pdo->prepare("
    SELECT a.*, 
           d.specialization,
           u.name as doctor_name,
           a.appointment_date as date,
           a.appointment_time as start_time,
           NULL as end_time,
           a.status as appointment_status
    FROM appointments a
    JOIN doctors d ON a.doctor_id = d.id
    JOIN users u ON d.user_id = u.id
    WHERE a.patient_id = ?
    ORDER BY a.appointment_date DESC, a.appointment_time DESC
");
$stmt->execute([$patient['id']]);
$appointments = $stmt->fetchAll();

// Separate appointments by status
$upcoming_appointments = [];
$past_appointments = [];
$today = date('Y-m-d');

foreach ($appointments as $appointment) {
    if ($appointment['date'] >= $today && $appointment['appointment_status'] != 'cancelled') {
        $upcoming_appointments[] = $appointment;
    } else {
        $past_appointments[] = $appointment;
    }
}
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
        .navbar { background: #002366; }
        .navbar-brand, .nav-link { color: #fff !important; font-weight: 600; }
        .nav-link:hover { color: #4e73df !important; }
        .page-header { 
            background: linear-gradient(120deg, #002366 0%, #0052cc 100%); 
            color: #fff; 
            padding: 4rem 0 6rem 0;
            position: relative;
            overflow: hidden;
            border-radius: 0 0 0 100px;
        }
        .page-header::after {
            content: '';
            position: absolute;
            bottom: -50px;
            right: -50px;
            width: 200px;
            height: 200px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
        }
        .page-header::before {
            content: '';
            position: absolute;
            top: -30px;
            left: -30px;
            width: 120px;
            height: 120px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
        }
        .tab-container {
            background: white;
            border-radius: 1rem;
            box-shadow: 0 4px 16px rgba(0,0,0,0.08);
            padding: 1rem;
            margin-top: -3rem;
            position: relative;
            z-index: 10;
        }
        .tab-navigation {
            display: flex;
            gap: 1rem;
            padding: 0.5rem 1rem;
            border-bottom: 1px solid #f1f5f9;
            margin-bottom: 1.5rem;
        }
        .tab-btn {
            padding: 0.75rem 1.5rem;
            border: none;
            background: transparent;
            border-radius: 0.5rem;
            font-weight: 600;
            color: #64748b;
            transition: all 0.2s;
        }
        .tab-btn:hover {
            color: #4e73df;
        }
        .tab-btn.active {
            background: #4e73df;
            color: white;
        }
        .appointment-card {
            background: #fff;
            border-radius: 1rem;
            box-shadow: 0 2px 12px rgba(14,165,233,0.07);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            transition: all 0.3s;
            border-left: 4px solid transparent;
        }
        .appointment-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(14,165,233,0.12);
        }
        .appointment-card.status-booked {
            border-left-color: #10b981;
        }
        .appointment-card.status-completed {
            border-left-color: #6366f1;
        }
        .appointment-card.status-cancelled {
            border-left-color: #ef4444;
        }
        .appointment-card.status-pending {
            border-left-color: #f59e0b;
        }
        .appointment-icon {
            width: 50px;
            height: 50px;
            border-radius: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 1.5rem;
        }
        .icon-booked {
            background: linear-gradient(135deg, #10b981, #059669);
        }
        .icon-completed {
            background: linear-gradient(135deg, #6366f1, #4f46e5);
        }
        .icon-cancelled {
            background: linear-gradient(135deg, #ef4444, #dc2626);
        }
        .icon-pending {
            background: linear-gradient(135deg, #f59e0b, #d97706);
        }
        .appointment-date {
            color: #475569;
            font-weight: 600;
            font-size: 1rem;
        }
        .appointment-time {
            color: #64748b;
            font-size: 0.95rem;
        }
        .appointment-doctor {
            color: #0f172a;
            font-weight: 700;
            font-size: 1.1rem;
            margin-bottom: 0.25rem;
        }
        .appointment-specialization {
            color: #4e73df;
            font-weight: 600;
            font-size: 0.95rem;
            margin-bottom: 0.75rem;
        }
        .appointment-status {
            padding: 0.25rem 0.75rem;
            border-radius: 1rem;
            font-size: 0.875rem;
            font-weight: 600;
            display: inline-block;
        }
        .status-booked { background: #dcfce7; color: #166534; }
        .status-cancelled { background: #fee2e2; color: #991b1b; }
        .status-completed { background: #e0e7ff; color: #4338ca; }
        .status-pending { background: #fef3c7; color: #92400e; }
        
        .btn-outline-primary {
            border-color: #4e73df;
            color: #4e73df;
            background: #fff;
            border-radius: 0.5rem;
            transition: all 0.2s;
            font-weight: 600;
        }
        .btn-outline-primary:hover, .btn-outline-primary:focus {
            background: #4e73df;
            color: #fff;
            box-shadow: 0 4px 12px rgba(78,115,223,0.18);
        }
        .btn-primary {
            background: linear-gradient(90deg, #4e73df 0%, #1d4ed8 100%);
            border: none;
            color: #fff;
            border-radius: 0.5rem;
            transition: all 0.2s;
            font-weight: 600;
        }
        .btn-primary:hover, .btn-primary:focus {
            background: linear-gradient(90deg, #1d4ed8 0%, #4e73df 100%);
            color: #fff;
            box-shadow: 0 4px 16px rgba(30,64,175,0.2);
            transform: translateY(-2px);
        }
        .btn-danger {
            background: linear-gradient(90deg, #ef4444 0%, #dc2626 100%);
            border: none;
            color: #fff;
            border-radius: 0.5rem;
            transition: all 0.2s;
            font-weight: 600;
        }
        .btn-danger:hover, .btn-danger:focus {
            background: linear-gradient(90deg, #dc2626 0%, #ef4444 100%);
            color: #fff;
            box-shadow: 0 4px 16px rgba(220,38,38,0.2);
        }
        .booking-cta {
            background: white;
            border-radius: 1rem;
            box-shadow: 0 4px 16px rgba(0,0,0,0.08);
            padding: 2rem;
            text-align: center;
            transition: all 0.3s;
        }
        .booking-cta:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.12);
        }
        .booking-icon {
            font-size: 4rem;
            color: #4e73df;
            margin-bottom: 1rem;
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
        .empty-state {
            text-align: center;
            padding: 3rem 0;
        }
        .empty-icon {
            font-size: 5rem;
            color: #cbd5e1;
            margin-bottom: 1.5rem;
        }
        .footer {
            background: #002366;
            color: #fff;
            padding: 3rem 0 1.5rem 0;
            border-radius: 2rem 2rem 0 0;
            margin-top: 3rem;
        }
        .footer-link {
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            margin-bottom: 0.75rem;
            display: block;
            transition: all 0.2s;
        }
        .footer-link:hover { 
            color: #fff;
            transform: translateX(5px);
        }
        .footer-title {
            font-weight: 700;
            margin-bottom: 1.5rem;
            position: relative;
            padding-bottom: 0.75rem;
        }
        .footer-title::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 50px;
            height: 2px;
            background: #4e73df;
        }
        .social-icon {
            width: 40px;
            height: 40px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #4e73df;
            border-radius: 50%;
            color: #fff;
            font-size: 1.2rem;
            margin-right: 0.75rem;
            transition: all 0.2s;
        }
        .social-icon:hover { 
            background: #22d3ee;
            transform: translateY(-4px);
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg" style="background-color: #002366;">
        <div class="container">
            <a class="navbar-brand text-white" href="dashboard.php">HealthCare Portal</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item"><a class="nav-link text-white" href="dashboard.php">Homepage</a></li>
                    <li class="nav-item"><a class="nav-link text-white" href="dashboard.php#doctors">Doctors</a></li>
                    <li class="nav-item"><a class="nav-link active text-white" href="appointments.php">Appointments</a></li>
                    <li class="nav-item"><a class="nav-link text-white" href="messages.php">Messages</a></li>
                    <li class="nav-item">
                        <a class="nav-link position-relative text-white" href="notifications.php">
                            Notifications
                            <?php if ($notification_count > 0): ?>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                <?php echo $notification_count; ?>
                            </span>
                            <?php endif; ?>
                        </a>
                    </li>
                </ul>
                <ul class="navbar-nav ms-auto align-items-center">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle text-white" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($patient['name']); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                            <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person me-2"></i>Profile</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Page Header -->
    <section class="page-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="display-5 fw-bold mb-3">Your Appointments</h1>
                    <p class="lead mb-4">Manage and track your healthcare appointments in one place</p>
                    <a href="book_appointment.php" class="btn btn-light px-4 py-2 fw-bold">
                        <i class="bi bi-plus-circle me-2"></i> Book New Appointment
                    </a>
                </div>
                <div class="col-md-4 d-none d-md-block text-end">
                    <i class="bi bi-calendar-check" style="font-size: 8rem; opacity: 0.2;"></i>
                </div>
            </div>
        </div>
    </section>

    <!-- Appointments Content -->
    <section class="py-5">
        <div class="container">
            <div class="tab-container">
                <div class="tab-navigation">
                    <button class="tab-btn active" data-tab="upcoming">Upcoming Appointments</button>
                    <button class="tab-btn" data-tab="past">Past Appointments</button>
                </div>

                <!-- Upcoming Appointments Tab -->
                <div class="tab-content active" id="upcoming-tab">
                    <?php if (empty($upcoming_appointments)): ?>
                        <div class="empty-state">
                            <i class="bi bi-calendar-x empty-icon"></i>
                            <h3>No Upcoming Appointments</h3>
                            <p class="text-muted mb-4">You don't have any appointments scheduled.</p>
                        </div>
                        
                        <div class="booking-cta">
                            <i class="bi bi-calendar-plus booking-icon"></i>
                            <h3 class="mb-3">Ready to book your appointment?</h3>
                            <p class="text-muted mb-4">Connect with our healthcare professionals for quality care.</p>
                            <a href="book_appointment.php" class="btn btn-primary btn-lg px-4">
                                <i class="bi bi-plus-circle me-2"></i> Book Your First Appointment
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="row">
                            <?php foreach ($upcoming_appointments as $appointment): ?>
                                <?php
                                $appointment_date = new DateTime($appointment['date']);
                                $today = new DateTime();
                                $interval = $today->diff($appointment_date);
                                $days_until = (int)$interval->format('%r%a');
                                
                                $icon_class = 'icon-pending';
                                if ($appointment['appointment_status'] === 'booked') {
                                    $icon_class = 'icon-booked';
                                } elseif ($appointment['appointment_status'] === 'completed') {
                                    $icon_class = 'icon-completed';
                                } elseif ($appointment['appointment_status'] === 'cancelled') {
                                    $icon_class = 'icon-cancelled';
                                }
                                ?>
                                <div class="col-md-6 mb-4">
                                    <div class="appointment-card status-<?php echo strtolower($appointment['appointment_status']); ?>">
                                        <div class="d-flex">
                                            <div class="appointment-icon <?php echo $icon_class; ?> me-3">
                                                <i class="bi bi-calendar-check"></i>
                                            </div>
                                            <div class="flex-grow-1">
                                                <div class="appointment-doctor">Dr. <?php echo htmlspecialchars($appointment['doctor_name']); ?></div>
                                                <div class="appointment-specialization"><?php echo htmlspecialchars($appointment['specialization']); ?></div>
                                                
                                                <div class="d-flex justify-content-between align-items-center mb-3">
                                                    <div>
                                                        <div class="appointment-date">
                                                            <i class="bi bi-calendar3 me-1"></i> 
                                                            <?php echo date('F d, Y', strtotime($appointment['date'])); ?>
                                                        </div>
                                                        <div class="appointment-time">
                                                            <i class="bi bi-clock me-1"></i> 
                                                            <?php echo date('h:i A', strtotime($appointment['start_time'])); ?> - 
                                                            <?php echo date('h:i A', strtotime($appointment['end_time'])); ?>
                                                        </div>
                                                    </div>
                                                    <div class="appointment-status status-<?php echo strtolower($appointment['appointment_status']); ?>">
                                                        <?php echo ucfirst($appointment['appointment_status']); ?>
                                                    </div>
                                                </div>
                                                
                                                <div class="d-flex mt-3">
                                                    <?php if ($appointment['appointment_status'] === 'booked'): ?>
                                                        <a href="messages.php?doctor=<?php echo $appointment['doctor_id']; ?>" class="btn btn-outline-primary btn-sm me-2">
                                                            <i class="bi bi-chat-dots me-1"></i> Message
                                                        </a>
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($appointment['appointment_status'] !== 'cancelled'): ?>
                                                        <form method="post" action="cancel_appointment.php" onsubmit="return confirm('Are you sure you want to cancel this appointment?');" class="ms-auto">
                                                            <input type="hidden" name="appointment_id" value="<?php echo $appointment['id']; ?>">
                                                            <button type="submit" class="btn btn-danger btn-sm" <?php echo ($days_until < 3) ? 'disabled title="Can only cancel at least 3 days before appointment"' : ''; ?>>
                                                                <i class="bi bi-x-circle me-1"></i> Cancel
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Past Appointments Tab -->
                <div class="tab-content" id="past-tab">
                    <?php if (empty($past_appointments)): ?>
                        <div class="empty-state">
                            <i class="bi bi-hourglass empty-icon"></i>
                            <h3>No Past Appointments</h3>
                            <p class="text-muted">Your appointment history will appear here.</p>
                        </div>
                    <?php else: ?>
                        <div class="row">
                            <?php foreach ($past_appointments as $appointment): ?>
                                <?php
                                $icon_class = 'icon-pending';
                                if ($appointment['appointment_status'] === 'completed') {
                                    $icon_class = 'icon-completed';
                                } elseif ($appointment['appointment_status'] === 'cancelled') {
                                    $icon_class = 'icon-cancelled';
                                }
                                ?>
                                <div class="col-md-6 mb-4">
                                    <div class="appointment-card status-<?php echo strtolower($appointment['appointment_status']); ?>">
                                        <div class="d-flex">
                                            <div class="appointment-icon <?php echo $icon_class; ?> me-3">
                                                <i class="bi bi-calendar-check"></i>
                                            </div>
                                            <div class="flex-grow-1">
                                                <div class="appointment-doctor">Dr. <?php echo htmlspecialchars($appointment['doctor_name']); ?></div>
                                                <div class="appointment-specialization"><?php echo htmlspecialchars($appointment['specialization']); ?></div>
                                                
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <div class="appointment-date">
                                                            <i class="bi bi-calendar3 me-1"></i> 
                                                            <?php echo date('F d, Y', strtotime($appointment['date'])); ?>
                                                        </div>
                                                        <div class="appointment-time">
                                                            <i class="bi bi-clock me-1"></i> 
                                                            <?php echo date('h:i A', strtotime($appointment['start_time'])); ?> - 
                                                            <?php echo date('h:i A', strtotime($appointment['end_time'])); ?>
                                                        </div>
                                                    </div>
                                                    <div class="appointment-status status-<?php echo strtolower($appointment['appointment_status']); ?>">
                                                        <?php echo ucfirst($appointment['appointment_status']); ?>
                                                    </div>
                                                </div>
                                                
                                                <?php if ($appointment['appointment_status'] === 'completed'): ?>
                                                <div class="mt-3">
                                                    <a href="feedback.php?appointment=<?php echo $appointment['id']; ?>" class="btn btn-outline-primary btn-sm">
                                                        <i class="bi bi-star me-1"></i> Leave Feedback
                                                    </a>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="row">
                <div class="col-md-4 mb-4 mb-md-0">
                    <h5 class="footer-title">HealthCare</h5>
                    <p class="text-white-50">Your trusted partner in healthcare management.</p>
                    <div class="mt-3">
                        <a href="#" class="social-icon"><i class="bi bi-facebook"></i></a>
                        <a href="#" class="social-icon"><i class="bi bi-twitter"></i></a>
                        <a href="#" class="social-icon"><i class="bi bi-linkedin"></i></a>
                        <a href="#" class="social-icon"><i class="bi bi-instagram"></i></a>
                    </div>
                </div>
                <div class="col-md-4 mb-4 mb-md-0">
                    <h5 class="footer-title">Useful Links</h5>
                    <a href="dashboard.php#about" class="footer-link">About</a>
                    <a href="dashboard.php#services" class="footer-link">Services</a>
                    <a href="dashboard.php#doctors" class="footer-link">Doctors</a>
                    <a href="dashboard.php#contact" class="footer-link">Contact</a>
                </div>
                <div class="col-md-4">
                    <h5 class="footer-title">Our Location</h5>
                    <div style="width:100%;border-radius:8px;overflow:hidden;">
                        <iframe src="https://www.google.com/maps?q=Manila,Philippines&output=embed" width="100%" height="150" style="border:0;" allowfullscreen="" loading="lazy"></iframe>
                    </div>
                    <div class="mt-2 text-white-50" style="font-size:0.95rem;">
                        <i class="bi bi-geo-alt me-1"></i> 123 Rizal Avenue, Malate<br>
                        <i class="bi bi-building me-1"></i> Manila, 1004, Philippines<br>
                        <i class="bi bi-telephone me-1"></i> +63 2 8896 5432<br>
                        <i class="bi bi-envelope me-1"></i> info@healthcare.ph
                    </div>
                </div>
            </div>
        </div>
    </footer>

    <?php if (isset($_GET['success']) && isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i>
            <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Tab navigation
        document.querySelectorAll('.tab-btn').forEach(button => {
            button.addEventListener('click', function() {
                // Remove active class from all buttons and content
                document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
                
                // Add active class to clicked button and corresponding content
                this.classList.add('active');
                document.getElementById(this.dataset.tab + '-tab').classList.add('active');
            });
        });
    </script>
</body>
</html> 