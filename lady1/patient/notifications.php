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

// Handle mark as read action
if (isset($_POST['mark_read'])) {
    $notification_id = $_POST['notification_id'];
    $stmt = $pdo->prepare("
        UPDATE notifications 
        SET is_read = TRUE 
        WHERE id = ? AND user_id = ?
    ");
    $stmt->execute([$notification_id, $_SESSION['user_id']]);
    header('Location: notifications.php');
    exit();
}

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
    SELECT n.* 
    FROM notifications n 
    WHERE n.user_id = ? 
    ORDER BY n.created_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$notifications = $stmt->fetchAll();

// Debug output
echo "<!-- Debug: ";
print_r($notifications);
echo " -->";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - HealthCare Portal</title>
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
        .notifications-container {
            background: white;
            border-radius: 1rem;
            box-shadow: 0 4px 16px rgba(0,0,0,0.08);
            padding: 2rem;
            margin-top: -3rem;
            position: relative;
            z-index: 10;
        }
        .notification-card {
            background: #fff;
            border-radius: 1rem;
            box-shadow: 0 2px 12px rgba(14,165,233,0.07);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            transition: all 0.3s;
            border-left: 4px solid #4e73df;
            position: relative;
        }
        .notification-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(14,165,233,0.12);
        }
        .notification-card.unread {
            background-color: #f0f7ff;
            border-left-color: #ef4444;
        }
        .notification-icon {
            width: 50px;
            height: 50px;
            border-radius: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 1.5rem;
            background: linear-gradient(135deg, #4e73df, #224abe);
        }
        .notification-time {
            color: #64748b;
            font-size: 0.9rem;
        }
        .notification-title {
            color: #0f172a;
            font-weight: 700;
            font-size: 1.1rem;
            margin-bottom: 0.25rem;
        }
        .notification-message {
            color: #334155;
            margin: 0.5rem 0;
        }
        .notification-badge {
            position: absolute;
            top: 1rem;
            right: 1rem;
            padding: 0.25rem 0.75rem;
            border-radius: 1rem;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .badge-unread { 
            background: #fee2e2; 
            color: #991b1b;
        }
        .badge-read { 
            background: #dcfce7; 
            color: #166534;
        }
        .btn-mark-read {
            padding: 0.4rem 0.75rem;
            border-radius: 0.5rem;
            font-size: 0.85rem;
            font-weight: 600;
            background: linear-gradient(90deg, #4e73df 0%, #1d4ed8 100%);
            border: none;
            color: #fff;
            transition: all 0.2s;
        }
        .btn-mark-read:hover {
            background: linear-gradient(90deg, #1d4ed8 0%, #4e73df 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(30,64,175,0.2);
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
                    <li class="nav-item"><a class="nav-link text-white" href="appointments.php">Appointments</a></li>
                    <li class="nav-item"><a class="nav-link text-white" href="messages.php">Messages</a></li>
                    <li class="nav-item">
                        <a class="nav-link active position-relative text-white" href="notifications.php">
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
                    <h1 class="display-5 fw-bold mb-3">Notifications</h1>
                    <p class="lead mb-0">Stay updated with your healthcare information</p>
                </div>
                <div class="col-md-4 d-none d-md-block text-end">
                    <i class="bi bi-bell" style="font-size: 8rem; opacity: 0.2;"></i>
                </div>
            </div>
        </div>
    </section>

    <!-- Notifications List -->
    <section class="py-5">
        <div class="container">
            <div class="notifications-container">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="m-0" style="color: #4e73df; font-weight: 700;">All Notifications</h5>
                    <?php if ($notification_count > 0): ?>
                    <span class="badge bg-danger rounded-pill px-3 py-2">
                        <?php echo $notification_count; ?> Unread
                    </span>
                    <?php endif; ?>
                </div>

                <?php if (empty($notifications)): ?>
                    <div class="empty-state">
                        <i class="bi bi-bell-slash empty-icon"></i>
                        <h3>No Notifications</h3>
                        <p class="text-muted">You don't have any notifications at the moment.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($notifications as $notification): ?>
                        <div class="notification-card <?php echo !$notification['is_read'] ? 'unread' : ''; ?>">
                            <div class="d-flex">
                                <div class="notification-icon me-3">
                                    <i class="bi bi-bell"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="notification-title"><?php echo htmlspecialchars($notification['type']); ?></div>
                                    <div class="notification-message">
                                        <?php echo htmlspecialchars($notification['message']); ?>
                                    </div>
                                    <div class="notification-time">
                                        <i class="bi bi-clock me-1"></i>
                                        <?php echo date('M d, Y h:i A', strtotime($notification['created_at'])); ?>
                                    </div>
                                    
                                    <?php if (!$notification['is_read']): ?>
                                    <div class="mt-3">
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                                            <button type="submit" name="mark_read" class="btn-mark-read">
                                                <i class="bi bi-check-circle me-1"></i> Mark as Read
                                            </button>
                                        </form>
                                    </div>
                                    <?php endif; ?>

                                    <?php if ($notification['type'] === 'action' && $notification['related_id']): ?>
                                        <form method="POST" action="respond_reschedule.php" class="d-inline">
                                            <input type="hidden" name="appointment_id" value="<?php echo $notification['related_id']; ?>">
                                            <button name="action" value="agree" class="btn btn-success btn-sm" <?php echo $notification['is_read'] ? 'disabled' : ''; ?>>Agree</button>
                                            <button name="action" value="cancel" class="btn btn-danger btn-sm" <?php echo $notification['is_read'] ? 'disabled' : ''; ?>>Cancel Appointment</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <span class="notification-badge <?php echo !$notification['is_read'] ? 'badge-unread' : 'badge-read'; ?>">
                                <?php echo !$notification['is_read'] ? 'Unread' : 'Read'; ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 