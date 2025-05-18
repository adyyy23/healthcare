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

// Handle message sending
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $receiver_id = $_POST['receiver_id'];
    $message = $_POST['message'];
    
    $stmt = $pdo->prepare("
        INSERT INTO messages (sender_id, receiver_id, message, created_at) 
        VALUES (?, ?, ?, NOW())
    ");
    
    if ($stmt->execute([$_SESSION['user_id'], $receiver_id, $message])) {
        // Create notification for the receiver
        $stmt = $pdo->prepare("
            INSERT INTO notifications (user_id, type, message, related_id) 
            VALUES (?, 'message', 'You have a new message', ?)
        ");
        $stmt->execute([$receiver_id, $pdo->lastInsertId()]);
        
        header('Location: messages.php?doctor=' . $receiver_id);
        exit();
    }
}

// Get selected doctor's messages if any
$selected_doctor = $_GET['doctor'] ?? null;
if ($selected_doctor) {
    // Get doctor information
    $stmt = $pdo->prepare("
        SELECT u.*, d.specialization 
        FROM users u 
        JOIN doctors d ON u.id = d.user_id 
        WHERE u.id = ? AND u.role = 'doctor'
    ");
    $stmt->execute([$selected_doctor]);
    $doctor = $stmt->fetch();

    if ($doctor) {
        // Mark messages as read
        $stmt = $pdo->prepare("
            UPDATE messages 
            SET is_read = TRUE 
            WHERE sender_id = ? AND receiver_id = ? AND is_read = FALSE
        ");
        $stmt->execute([$doctor['id'], $patient['user_id']]);

        // Get messages between patient and doctor
        $stmt = $pdo->prepare("
            SELECT m.*, 
                   CASE 
                       WHEN m.sender_id = ? THEN 'sent'
                       ELSE 'received'
                   END as message_type
            FROM messages m
            WHERE (m.sender_id = ? AND m.receiver_id = ?)
               OR (m.sender_id = ? AND m.receiver_id = ?)
            ORDER BY m.created_at ASC
        ");
        $stmt->execute([
            $patient['user_id'],
            $patient['user_id'], $doctor['id'],
            $doctor['id'], $patient['user_id']
        ]);
        $messages = $stmt->fetchAll();
    }
}

// Get list of all doctors
$stmt = $pdo->prepare("
    SELECT DISTINCT u.*, d.specialization,
           (SELECT COUNT(*) FROM messages 
            WHERE sender_id = u.id 
            AND receiver_id = ? 
            AND is_read = FALSE) as unread_count
    FROM users u
    JOIN doctors d ON u.id = d.user_id
    LEFT JOIN messages m ON (m.sender_id = u.id AND m.receiver_id = ?)
                   OR (m.sender_id = ? AND m.receiver_id = u.id)
    WHERE u.role = 'doctor'
    ORDER BY unread_count DESC, u.name ASC
");
$stmt->execute([$patient['user_id'], $patient['user_id'], $patient['user_id']]);
$doctors = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - HealthCare Portal</title>
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
        
        .chat-container {
            height: calc(100vh - 250px);
            background: #fff;
            border-radius: 1rem;
            box-shadow: 0 4px 16px rgba(0,0,0,0.08);
            overflow: hidden;
            margin-top: -3rem;
            position: relative;
            z-index: 10;
            display: flex;
        }

        .doctors-list {
            width: 300px;
            border-right: 1px solid #e5e7eb;
            overflow-y: auto;
            background: #f9fafc;
        }

        .chat-area {
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .chat-header {
            padding: 1.25rem 1.5rem;
            background: #fff;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .doctor-info {
            display: flex;
            align-items: center;
        }
        
        .doctor-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e0e7ff;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #4338ca;
            font-weight: 700;
            margin-right: 1rem;
        }

        .chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 1.5rem;
            background: #f8fafc;
        }

        .message {
            margin-bottom: 1.5rem;
            max-width: 70%;
            display: flex;
            flex-direction: column;
        }

        .message.sent {
            margin-left: auto;
            align-items: flex-end;
        }

        .message.received {
            margin-right: auto;
            align-items: flex-start;
        }

        .message-content {
            padding: 0.75rem 1rem;
            border-radius: 1.25rem;
            position: relative;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .message.sent .message-content {
            background: linear-gradient(90deg, #4e73df 0%, #224abe 100%);
            color: #fff;
            border-bottom-right-radius: 0;
        }
        
        .message.received .message-content {
            background: #fff;
            color: #0f172a;
            border-bottom-left-radius: 0;
            border: 1px solid #e5e7eb;
        }

        .message-time {
            font-size: 0.75rem;
            color: #94a3b8;
            margin-top: 0.5rem;
        }
        
        .message-input-container {
            padding: 1rem 1.5rem;
            background: #fff;
            border-top: 1px solid #e5e7eb;
        }

        .message-input {
            display: flex;
            align-items: center;
            background: #f1f5f9;
            border-radius: 2rem;
            padding: 0.5rem 0.75rem;
        }
        
        .message-input input {
            flex: 1;
            border: none;
            background: transparent;
            padding: 0.5rem;
            outline: none;
            font-size: 0.95rem;
        }
        
        .message-input button {
            background: linear-gradient(90deg, #4e73df 0%, #224abe 100%);
            color: white;
            border: none;
            border-radius: 50%;
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .message-input button:hover {
            transform: scale(1.05);
            box-shadow: 0 2px 8px rgba(78,115,223,0.25);
        }

        .doctor-item {
            padding: 1rem 1.25rem;
            border-bottom: 1px solid #e5e7eb;
            cursor: pointer;
            transition: all 0.2s;
        }

        .doctor-item:hover {
            background-color: #f1f5f9;
        }

        .doctor-item.active {
            background: #e0e7ff;
            border-left: 4px solid #4e73df;
        }
        
        .doctor-item-header {
            display: flex;
            align-items: center;
            margin-bottom: 0.5rem;
        }
        
        .doctor-list-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #4e73df;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-weight: 700;
            margin-right: 0.75rem;
        }
        
        .doctor-item.active .doctor-list-avatar {
            background: #4338ca;
        }

        .unread-badge {
            background: #ef4444;
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 1rem;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .doctors-header {
            padding: 1.25rem;
            border-bottom: 1px solid #e5e7eb;
            font-weight: 700;
            color: #4e73df;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .doctors-search {
            position: relative;
            margin: 1rem;
        }
        
        .doctors-search input {
            width: 100%;
            padding: 0.65rem 1rem 0.65rem 2.5rem;
            border: 1px solid #e2e8f0;
            border-radius: 0.5rem;
            background: #f8fafc;
            outline: none;
            transition: all 0.2s;
        }
        
        .doctors-search input:focus {
            border-color: #4e73df;
            box-shadow: 0 0 0 2px rgba(78,115,223,0.2);
        }
        
        .doctors-search i {
            position: absolute;
            left: 0.85rem;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
        }
        
        .empty-chat-placeholder {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            padding: 2rem;
            color: #94a3b8;
            text-align: center;
        }
        
        .empty-chat-icon {
            font-size: 5rem;
            margin-bottom: 1.5rem;
            color: #cbd5e1;
        }
        
        .footer {
            background: #002366;
            color: #fff;
            padding: 3rem 0 1.5rem 0;
            border-radius: 2rem 2rem 0 0;
            margin-top: 3rem;
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
        
        @media (max-width: 768px) {
            .chat-container {
                flex-direction: column;
                height: auto;
            }
            .doctors-list {
                width: 100%;
                height: 300px;
                border-right: none;
                border-bottom: 1px solid #e5e7eb;
            }
            .chat-area {
                height: calc(100vh - 550px);
            }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark" style="background-color: #002366;">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">HealthCare Portal</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item"><a class="nav-link" href="dashboard.php">Homepage</a></li>
                    <li class="nav-item"><a class="nav-link" href="dashboard.php#doctors">Doctors</a></li>
                    <li class="nav-item"><a class="nav-link" href="appointments.php">Appointments</a></li>
                    <li class="nav-item"><a class="nav-link active" href="messages.php">Messages</a></li>
                    <li class="nav-item">
                        <a class="nav-link position-relative" href="notifications.php">
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
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
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
                    <h1 class="display-5 fw-bold mb-3">Messages</h1>
                    <p class="lead mb-0">Communicate securely with your healthcare providers</p>
                </div>
                <div class="col-md-4 d-none d-md-block text-end">
                    <i class="bi bi-chat-dots" style="font-size: 8rem; opacity: 0.2;"></i>
                </div>
            </div>
        </div>
    </section>

    <!-- Messages Section -->
    <section class="py-4 pb-5">
        <div class="container">
            <div class="chat-container">
                <div class="doctors-list">
                    <div class="doctors-header">
                        <span><i class="bi bi-people-fill me-2"></i> Healthcare Providers</span>
                        <span class="badge bg-primary rounded-pill"><?php echo count($doctors); ?></span>
                    </div>
                    
                    <div class="doctors-search">
                        <i class="bi bi-search"></i>
                        <input type="text" id="doctorSearch" placeholder="Search doctors..." class="form-control">
                    </div>
                    
                    <div id="doctorsList">
                        <?php foreach ($doctors as $d): ?>
                        <div class="doctor-item <?php echo $selected_doctor == $d['id'] ? 'active' : ''; ?>"
                             onclick="window.location.href='?doctor=<?php echo $d['id']; ?>'" 
                             data-doctor-name="<?php echo htmlspecialchars(strtolower($d['name'])); ?>"
                             data-specialization="<?php echo htmlspecialchars(strtolower($d['specialization'])); ?>">
                            <div class="doctor-item-header">
                                <div class="doctor-list-avatar">
                                    <?php echo strtoupper(substr($d['name'], 0, 1)); ?>
                                </div>
                                <div class="flex-grow-1">
                                    <h6 class="mb-0">Dr. <?php echo htmlspecialchars($d['name']); ?></h6>
                                    <small class="text-muted"><?php echo htmlspecialchars($d['specialization']); ?></small>
                                </div>
                                <?php if ($d['unread_count'] > 0): ?>
                                <span class="unread-badge ms-2"><?php echo $d['unread_count']; ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="doctor-item-footer d-flex justify-content-end">
                                <span class="badge rounded-pill bg-light text-dark">
                                    <i class="bi bi-chat-text me-1"></i> Chat
                                </span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="chat-area">
                    <?php if ($selected_doctor && $doctor): ?>
                    <div class="chat-header">
                        <div class="doctor-info">
                            <div class="doctor-avatar">
                                <?php echo strtoupper(substr($doctor['name'], 0, 1)); ?>
                            </div>
                            <div>
                                <h5 class="mb-0">Dr. <?php echo htmlspecialchars($doctor['name']); ?></h5>
                                <small class="text-muted"><?php echo htmlspecialchars($doctor['specialization']); ?></small>
                            </div>
                        </div>
                        <div>
                            <a href="appointments.php?doctor=<?php echo $doctor['id']; ?>" class="btn btn-sm btn-outline-primary rounded-pill">
                                <i class="bi bi-calendar-plus me-1"></i> Book Appointment
                            </a>
                        </div>
                    </div>

                    <div class="chat-messages">
                        <?php foreach ($messages as $message): ?>
                        <div class="message <?php echo $message['message_type']; ?>">
                            <div class="message-content">
                                <?php echo htmlspecialchars($message['message']); ?>
                            </div>
                            <div class="message-time">
                                <?php echo date('g:i A', strtotime($message['created_at'])); ?> · <?php echo date('M j', strtotime($message['created_at'])); ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="message-input-container">
                        <form method="POST" class="message-input">
                            <input type="hidden" name="receiver_id" value="<?php echo $doctor['id']; ?>">
                            <input type="text" name="message" placeholder="Type your message..." required>
                            <button type="submit">
                                <i class="bi bi-send-fill"></i>
                            </button>
                        </form>
                    </div>
                    <?php else: ?>
                    <div class="empty-chat-placeholder">
                        <i class="bi bi-chat-square-text empty-chat-icon"></i>
                        <h4>No conversation selected</h4>
                        <p class="mb-4">Select a healthcare provider from the list to start messaging</p>
                        <small class="text-muted">Your messages are private and secure</small>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Scroll to bottom of chat messages
        document.addEventListener('DOMContentLoaded', function() {
            const chatMessages = document.querySelector('.chat-messages');
            if (chatMessages) {
                chatMessages.scrollTop = chatMessages.scrollHeight;
            }
            
            // Doctor search functionality
            const searchInput = document.getElementById('doctorSearch');
            const doctorItems = document.querySelectorAll('.doctor-item');
            
            searchInput.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase().trim();
                
                doctorItems.forEach(item => {
                    const doctorName = item.getAttribute('data-doctor-name');
                    const specialization = item.getAttribute('data-specialization');
                    
                    if (doctorName.includes(searchTerm) || specialization.includes(searchTerm)) {
                        item.style.display = 'block';
                    } else {
                        item.style.display = 'none';
                    }
                });
            });
        });
    </script>
</body>
</html> 