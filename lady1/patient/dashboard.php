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
    SELECT p.*, u.name, u.email, p.profile_picture 
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

// Get notifications
$stmt = $pdo->prepare("
    SELECT n.*, 
           CASE 
               WHEN n.type = 'message' THEN (SELECT name FROM users WHERE id = n.related_id)
               ELSE NULL 
           END as sender_name
    FROM notifications n
    WHERE n.user_id = ?
    ORDER BY n.created_at DESC
    LIMIT 10
");
$stmt->execute([$_SESSION['user_id']]);
$notifications = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Dashboard - HealthCare Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; background: #f8fafc; color: #0f172a; }
        .navbar { background: #002366; }
        .navbar-brand, .nav-link { color: #fff !important; font-weight: 600; }
        .nav-link:hover { color: #4e73df !important; }
        .hero-section { background: linear-gradient(120deg, #002366 0%, #0052cc 100%); color: #fff; padding: 12rem 0 8rem 0; position: relative; overflow: hidden; }
        .hero-title { font-size: 3.5rem; font-weight: 800; letter-spacing: -1px; }
        .hero-desc { font-size: 1.5rem; margin-bottom: 2.5rem; font-weight: 500; }
        .search-bar { max-width: 420px; margin: 2rem 0; }
        .stats-circle {
            background: #4e73df;
            color: #fff;
            border-radius: 50%;
            width: 160px; height: 160px;
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            font-size: 2rem; font-weight: 700;
            position: absolute; right: 10%; top: 30%;
            box-shadow: 0 8px 32px rgba(14,165,233,0.15);
        }
        .hero-img { width: 340px; border-radius: 1.5rem; box-shadow: 0 8px 32px rgba(14,165,233,0.15); }
        .services-section { background: #f4f8ff; padding: 4rem 0; }
        .service-card {
            background: #fff; border-radius: 1.5rem; box-shadow: 0 2px 12px rgba(14,165,233,0.07);
            padding: 2rem 1.5rem; text-align: center; margin-bottom: 2rem; height: 100%;
            transition: box-shadow 0.2s;
        }
        .service-card:hover { box-shadow: 0 8px 32px rgba(14,165,233,0.13); }
        .service-img { width: 80px; height: 80px; object-fit: cover; border-radius: 1rem; margin-bottom: 1rem; }
        .service-number { font-size: 2.5rem; color: #4e73df; font-weight: 700; }
        .service-title { font-size: 1.2rem; font-weight: 600; margin-bottom: 0.5rem; }
        .service-desc { color: #64748b; font-size: 1.05rem; }
        .doctors-section { background: #fff; padding: 4rem 0; }
        .doctor-card {
            background: #f4f8ff; border-radius: 1.5rem; box-shadow: 0 2px 12px rgba(14,165,233,0.07);
            padding: 2rem 1.5rem; text-align: center; margin-bottom: 2rem; height: 100%;
        }
        .doctor-img { width: 100px; height: 100px; border-radius: 50%; object-fit: cover; margin-bottom: 1rem; }
        .doctor-name { font-size: 1.1rem; font-weight: 600; }
        .doctor-spec { color: #64748b; font-size: 1rem; margin-bottom: 0.5rem; }
        .doctor-social a { color: #4e73df; margin: 0 0.3rem; font-size: 1.2rem; }
        .about-section { background: #eaf1fb; border-radius: 1.5rem; padding: 3rem 2rem; margin: 3rem 0; }
        .about-title { font-size: 2rem; font-weight: 700; color: #4e73df; }
        .about-desc { font-size: 1.15rem; color: #64748b; margin-bottom: 1.5rem; }
        .about-img { width: 180px; border-radius: 1.5rem; }
        .contact-section { background: #fff; padding: 4rem 0; }
        .contact-form { background: #f4f8ff; border-radius: 1.5rem; padding: 2rem; }
        .location-section { background: #eaf1fb; padding: 3rem 0; }
        .footer { background: #002366; color: #fff; padding: 3rem 0 1.5rem 0; border-radius: 2rem 2rem 0 0; margin-top: 3rem; }
        .footer-link { color: rgba(255,255,255,0.8); text-decoration: none; margin-bottom: 0.75rem; display: block; }
        .footer-link:hover { color: #fff; }
        .social-icon { width: 40px; height: 40px; display: inline-flex; align-items: center; justify-content: center; background: #4e73df; border-radius: 50%; color: #fff; font-size: 1.2rem; margin-right: 0.75rem; }
        .social-icon:hover { background: #22d3ee; }
        @media (max-width: 992px) {
            .hero-img { width: 220px; }
            .hero-title { font-size: 2rem; }
            .about-section { padding: 2rem 1rem; }
            .stats-circle { position: static; margin: 2rem auto; }
        }
        @media (max-width: 768px) {
            .hero-section { text-align: center; }
            .hero-img { margin-bottom: 2rem; }
            .stats-circle { margin: 2rem auto; }
        }
        .btn-outline-primary {
            border-color: #4e73df;
            color: #4e73df;
            background: #fff;
            transition: background 0.2s, color 0.2s;
        }
        .btn-outline-primary:hover, .btn-outline-primary:focus {
            background: #4e73df;
            color: #fff;
        }
        .btn-primary {
            background: linear-gradient(90deg, #4e73df 0%, #1d4ed8 100%);
            border: none;
            color: #fff;
            transition: background 0.2s, box-shadow 0.2s;
        }
        .btn-primary:hover, .btn-primary:focus {
            background: linear-gradient(90deg, #1d4ed8 0%, #4e73df 100%);
            color: #fff;
            box-shadow: 0 4px 16px rgba(30,64,175,0.13);
        }
        .btn-info {
            background: #0ea5e9;
            border: none;
            color: #fff;
            transition: background 0.2s;
        }
        .btn-info:hover, .btn-info:focus {
            background: #0369a1;
            color: #fff;
        }
        .notification-item {
            cursor: pointer;
            padding: 0.75rem 1rem;
            border-bottom: 1px solid #e3e6f0;
        }
        .notification-item:hover {
            background-color: #f8f9fc;
        }
        .notification-item.unread {
            background-color: #f0f7ff;
        }
        .dropdown-menu {
            max-height: 400px;
            overflow-y: auto;
        }
        .btn-book-appointment {
            font-size: 1.35rem;
            font-weight: 700;
            padding: 1rem 2.5rem;
            border-radius: 2rem;
            background: linear-gradient(90deg, #4e73df 0%, #1d4ed8 100%);
            box-shadow: 0 4px 24px rgba(30,64,175,0.18);
            border: none;
            color: #fff;
            transition: background 0.2s, box-shadow 0.2s, transform 0.2s;
        }
        .btn-book-appointment:hover, .btn-book-appointment:focus {
            background: linear-gradient(90deg, #1d4ed8 0%, #4e73df 100%);
            box-shadow: 0 8px 32px rgba(30,64,175,0.25);
            transform: translateY(-2px) scale(1.04);
        }
        .profile-title { font-size: 1.5rem; font-weight: 700; color: #4e73df; }
        .profile-spec { color: #0ea5e9; font-size: 1.1rem; font-weight: 600; margin-bottom: 1rem; }
        .profile-info { font-size: 1.05rem; margin-bottom: 0.7rem; }
        .profile-label { color: #64748b; font-weight: 600; margin-right: 0.5rem; }
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
                    <li class="nav-item"><a class="nav-link active text-white" href="dashboard.php">Homepage</a></li>
                    <li class="nav-item"><a class="nav-link text-white" href="#doctors">Doctors</a></li>
                    <li class="nav-item"><a class="nav-link text-white" href="appointments.php">Appointments</a></li>
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

    <!-- Hero Section -->
    <section class="hero-section position-relative">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6 text-center text-lg-start mb-4 mb-lg-0">
                    <div class="hero-title">Your Health, Our Priority</div>
                    <div class="hero-desc">Book appointments, connect with top healthcare professionals, and manage your wellness journey with HealthCare Portal.</div>
                    <a href="appointments.php" class="btn btn-book-appointment">Book an Appointment</a>
                </div>
                <div class="col-lg-6 text-center position-relative">
                    <i class="bi bi-heart-pulse hero-img" style="font-size: 15rem; color: #4e73df;"></i>
                </div>
            </div>
        </div>
    </section>

    <!-- Services Section -->
    <section class="services-section" id="services">
        <div class="container">
            <h2 class="text-center mb-5" style="font-weight:700; color:#4e73df;">Our <span style="color:#0ea5e9;">Healthcare Services</span></h2>
            <div class="row g-4">
                <!-- Service 1 -->
                <div class="col-md-4">
                    <div class="service-card">
                        <div class="service-number">01</div>
                        <div class="service-emoji" style="font-size: 3rem; margin-bottom: 1rem;"><i class="bi bi-person-badge"></i></div>
                        <div class="service-title">General Consultation</div>
                        <div class="service-desc">Comprehensive health check-ups for all ages by experienced professionals.</div>
                    </div>
                </div>
                <!-- Service 2 -->
                <div class="col-md-4">
                    <div class="service-card">
                        <div class="service-number">02</div>
                        <div class="service-emoji" style="font-size: 3rem; margin-bottom: 1rem;"><i class="bi bi-emoji-smile"></i></div>
                        <div class="service-title">Pediatrics</div>
                        <div class="service-desc">Specialized care for children from birth through adolescence.</div>
                    </div>
                </div>
                <!-- Service 3 -->
                <div class="col-md-4">
                    <div class="service-card">
                        <div class="service-number">03</div>
                        <div class="service-emoji" style="font-size: 3rem; margin-bottom: 1rem;"><i class="bi bi-heart-pulse"></i></div>
                        <div class="service-title">Cardiology</div>
                        <div class="service-desc">Expert care for heart conditions and cardiovascular health.</div>
                    </div>
                </div>
                <!-- Service 4 -->
                <div class="col-md-4">
                    <div class="service-card">
                        <div class="service-number">04</div>
                        <div class="service-emoji" style="font-size: 3rem; margin-bottom: 1rem;"><i class="bi bi-droplet-half"></i></div>
                        <div class="service-title">Dermatology</div>
                        <div class="service-desc">Specialized care for skin conditions and cosmetic treatments.</div>
                    </div>
                </div>
                <!-- Service 5 -->
                <div class="col-md-4">
                    <div class="service-card">
                        <div class="service-number">05</div>
                        <div class="service-emoji" style="font-size: 3rem; margin-bottom: 1rem;"><i class="bi bi-hospital"></i></div>
                        <div class="service-title">Orthopedics</div>
                        <div class="service-desc">Specialized care for bone and joint conditions.</div>
                    </div>
                </div>
                <!-- Service 6 -->
                <div class="col-md-4">
                    <div class="service-card">
                        <div class="service-number">06</div>
                        <div class="service-emoji" style="font-size: 3rem; margin-bottom: 1rem;"><i class="bi bi-cpu"></i></div>
                        <div class="service-title">Neurology</div>
                        <div class="service-desc">Specialized care for nervous system conditions.</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Doctors Section -->
    <section class="doctors-section" id="doctors">
        <div class="container">
            <h2 class="text-center mb-5" style="font-weight:700; color:#4e73df;">Meet <span style="color:#0ea5e9;">Our Doctors</span></h2>
            <div class="row g-4">
                <?php
                $stmt = $pdo->query("SELECT d.*, u.name, u.email FROM doctors d JOIN users u ON d.user_id = u.id ORDER BY RAND() LIMIT 3");
                $doctors = $stmt->fetchAll();
                foreach ($doctors as $doctor):
                ?>
                <div class="col-md-4">
                    <div class="doctor-card">
                        <img src="<?php echo $doctor['profile_picture'] ? '../' . htmlspecialchars($doctor['profile_picture']) : '../assets/images/default-doctor.jpg'; ?>" 
                             alt="Dr. <?php echo htmlspecialchars($doctor['name']); ?>" 
                             class="doctor-img">
                        <div class="doctor-name">Dr. <?php echo htmlspecialchars($doctor['name']); ?></div>
                        <div class="doctor-spec"><?php echo htmlspecialchars($doctor['specialization']); ?></div>
                        <div class="mt-3 d-flex justify-content-center gap-2">
                            <a href="messages.php?doctor=<?php echo $doctor['id']; ?>" class="btn btn-outline-primary btn-sm">
                                <i class="bi bi-chat-dots"></i> Message
                            </a>
                            <a href="appointments.php?doctor=<?php echo $doctor['id']; ?>" class="btn btn-primary btn-sm">
                                <i class="bi bi-calendar-check"></i> Book Appointment
                            </a>
                            <a href="#" class="btn btn-info btn-sm text-white btn-view-profile" data-doctor-id="<?php echo $doctor['id']; ?>">
                                <i class="bi bi-person-lines-fill"></i> View Profile
                            </a>
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
                    <a href="#services" class="footer-link">Services</a>
                    <a href="#doctors" class="footer-link">Doctors</a>
                    <a href="#contact" class="footer-link">Contact</a>
                </div>
                <div class="col-md-4">
                    <h5 class="footer-title">Our Location</h5>
                    <div style="width:100%;border-radius:8px;overflow:hidden;">
                        <iframe src="https://www.google.com/maps?q=Manila,Philippines&output=embed" width="100%" height="150" style="border:0;" allowfullscreen="" loading="lazy"></iframe>
                    </div>
                    <div class="mt-2 text-white-50" style="font-size:0.95rem;">
                        <i class="bi bi-geo-alt me-1"></i> 123 Mainstreet<br>
                        <i class="bi bi-building me-1"></i> Manila, 1004, Philippines<br>
                        <i class="bi bi-telephone me-1"></i> +63 2 8896 5432<br>
                        <i class="bi bi-envelope me-1"></i> info@healthcare.ph
                    </div>
                </div>
            </div>
        </div>
    </footer>

    <!-- Doctor Profile Modal -->
    <div class="modal fade" id="doctorProfileModal" tabindex="-1" aria-labelledby="doctorProfileModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="doctorProfileModalLabel">Doctor Profile</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="doctor-profile-content" class="text-center py-3">
                        <div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Handle notification clicks
        document.querySelectorAll('.notification-item').forEach(item => {
            item.addEventListener('click', function() {
                const notificationId = this.dataset.notificationId;
                if (!notificationId) return;

                fetch('mark_notification_read.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `notification_id=${notificationId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update notification count in the navbar
                        const badge = document.querySelector('.nav-link[href="notifications.php"] .badge');
                        if (badge) {
                            if (data.unread_count > 0) {
                                badge.textContent = data.unread_count;
                            } else {
                                badge.remove();
                            }
                        }
                        // Mark notification as read in UI
                        this.classList.remove('unread');
                        const newBadge = this.querySelector('.badge.bg-primary');
                        if (newBadge) {
                            newBadge.remove();
                        }
                    }
                })
                .catch(error => console.error('Error:', error));
            });
        });

        // Add event listeners for mark as read/unread buttons
        document.querySelectorAll('.mark-read-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                const notificationId = this.dataset.notificationId;
                fetch('mark_notification_read.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `notification_id=${notificationId}`
                })
                .then(response => response.json())
                .then(data => { window.location.reload(); });
            });
        });
        document.querySelectorAll('.mark-unread-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                const notificationId = this.dataset.notificationId;
                fetch('mark_notification_unread.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `notification_id=${notificationId}`
                })
                .then(response => response.json())
                .then(data => { window.location.reload(); });
            });
        });

        document.querySelectorAll('.btn-view-profile').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const doctorId = this.getAttribute('data-doctor-id');
                const modal = new bootstrap.Modal(document.getElementById('doctorProfileModal'));
                const content = document.getElementById('doctor-profile-content');
                content.innerHTML = '<div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div>';
                modal.show();
                fetch('../get_doctor_profile.php?doctor_id=' + doctorId)
                    .then(res => res.json())
                    .then(data => {
                        console.log(data); // Debug: log the response
                        if (data.success) {
                            content.innerHTML = `
                                <img src="${data.profile_picture ? '../' + data.profile_picture : '../assets/images/default-doctor.jpg'}" alt="Dr. ${data.name}" class="profile-img mb-3" style="width:100px;height:100px;object-fit:cover;border-radius:50%;border:3px solid #4e73df;">
                                <div class="profile-title mb-1">Dr. ${data.name}</div>
                                <div class="profile-spec mb-2">${data.specialization || ''}</div>
                                <div class="profile-info"><span class="profile-label">License #:</span> ${data.license_number || ''}</div>
                                <div class="profile-info"><span class="profile-label">Experience:</span> ${data.years_of_experience || ''} years</div>
                                <div class="profile-info"><span class="profile-label">Phone:</span> ${data.phone_number || ''}</div>
                                <div class="profile-info"><span class="profile-label">Email:</span> ${data.email || ''}</div>
                            `;
                        } else {
                            content.innerHTML = '<div class="text-danger">' + (data.error || 'Unable to load profile.') + '</div>';
                        }
                    })
                    .catch(() => {
                        content.innerHTML = '<div class="text-danger">Unable to load profile.</div>';
                    });
            });
        });
    </script>
</body>
</html> 