<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Get all doctors for the chat list
$stmt = $pdo->query("
    SELECT d.id, u.name, u.email, d.profile_picture, d.specialization
    FROM doctors d 
    JOIN users u ON d.user_id = u.id
    ORDER BY u.name
");
$doctors = $stmt->fetchAll();

// Get selected doctor's messages if any
$selected_doctor = null;
$messages = [];
if (isset($_GET['doctor_id'])) {
    $doctor_id = $_GET['doctor_id'];
    
    // Get doctor details
    $stmt = $pdo->prepare("
        SELECT d.id, u.name, d.profile_picture, d.specialization, u.id as user_id
        FROM doctors d
        JOIN users u ON d.user_id = u.id
        WHERE d.id = ?
    ");
    $stmt->execute([$doctor_id]);
    $selected_doctor = $stmt->fetch();
    
    if ($selected_doctor) {
        // Get messages
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
            $_SESSION['user_id'],
            $_SESSION['user_id'], $selected_doctor['user_id'],
            $selected_doctor['user_id'], $_SESSION['user_id']
        ]);
        $messages = $stmt->fetchAll();
    }
}
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

        .page-title {
            color: #4e73df;
            font-weight: 700;
            margin-bottom: 2rem;
        }

        .chat-container {
            display: flex;
            height: calc(100vh - 150px);
            background: white;
            border-radius: 1rem;
            box-shadow: 0 2px 12px rgba(14,165,233,0.07);
            overflow: hidden;
        }

        .chat-sidebar {
            width: 300px;
            border-right: 1px solid #e3e6f0;
            overflow-y: auto;
        }

        .chat-main {
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .chat-header {
            padding: 1.5rem;
            border-bottom: 1px solid #e3e6f0;
            display: flex;
            align-items: center;
            gap: 1rem;
            background: #fff;
        }

        .chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 1.5rem;
            background: #f8fafc;
        }

        .chat-input {
            padding: 1rem;
            border-top: 1px solid #e3e6f0;
            background: #fff;
        }

        .user-item {
            padding: 1rem;
            border-bottom: 1px solid #f1f3f8;
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .user-item:hover {
            background-color: #f8fafc;
        }

        .user-item.active {
            background-color: #eaecf4;
            border-left: 4px solid #4e73df;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }

        .message {
            max-width: 70%;
            margin-bottom: 1rem;
            padding: 0.75rem 1rem;
            border-radius: 1rem;
        }

        .message.sent {
            background: linear-gradient(90deg, #4e73df 0%, #224abe 100%);
            color: white;
            margin-left: auto;
            border-bottom-right-radius: 0.25rem;
        }

        .message.received {
            background-color: #fff;
            color: #0f172a;
            border-bottom-left-radius: 0.25rem;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }

        .message-time {
            font-size: 0.75rem;
            opacity: 0.7;
            margin-top: 0.25rem;
            text-align: right;
        }

        .message.sent .message-time {
            color: rgba(255, 255, 255, 0.8);
        }

        .message.received .message-time {
            color: #858796;
        }

        .form-control {
            border-radius: 0.5rem;
            border: 1px solid #e3e6f0;
            padding: 0.75rem 1rem;
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
            .chat-sidebar {
                width: 100%;
                position: absolute;
                left: 0;
                top: 0;
                bottom: 0;
                z-index: 1000;
                background: white;
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }
            .chat-sidebar.show {
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
                <a class="nav-link" href="manage_patients.php">
                    <i class="bi bi-people"></i> Manage Patients
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="appointments.php">
                    <i class="bi bi-calendar-check"></i> Appointments
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link active" href="messages.php">
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
            <h1 class="page-title">Messages</h1>
            <button class="btn btn-primary d-md-none" id="sidebarToggle">
                <i class="bi bi-list"></i>
            </button>
        </div>

        <div class="chat-container">
            <!-- Chat Sidebar -->
            <div class="chat-sidebar" id="chatSidebar">
                <?php foreach ($doctors as $doctor): ?>
                <div class="user-item <?php echo ($selected_doctor && $selected_doctor['id'] == $doctor['id']) ? 'active' : ''; ?>"
                     onclick="window.location.href='messages.php?doctor_id=<?php echo $doctor['id']; ?>'">
                    <div class="d-flex align-items-center gap-3">
                        <img src="<?php echo $doctor['profile_picture'] ? '../' . htmlspecialchars($doctor['profile_picture']) : '../assets/images/default-user.jpg'; ?>" 
                             alt="<?php echo htmlspecialchars($doctor['name']); ?>" 
                             class="user-avatar">
                        <div>
                            <div class="fw-bold"><?php echo htmlspecialchars($doctor['name']); ?></div>
                            <small class="text-muted">
                                <?php if ($doctor['specialization']): ?>
                                <?php echo htmlspecialchars($doctor['specialization']); ?>
                                <?php endif; ?>
                            </small>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Chat Main -->
            <div class="chat-main">
                <?php if ($selected_doctor): ?>
                <!-- Chat Header -->
                <div class="chat-header">
                    <img src="<?php echo $selected_doctor['profile_picture'] ? '../' . htmlspecialchars($selected_doctor['profile_picture']) : '../assets/images/default-user.jpg'; ?>" 
                         alt="<?php echo htmlspecialchars($selected_doctor['name']); ?>" 
                         class="user-avatar">
                    <div>
                        <div class="fw-bold"><?php echo htmlspecialchars($selected_doctor['name']); ?></div>
                        <small class="text-muted">
                            <?php if ($selected_doctor['specialization']): ?>
                            <?php echo htmlspecialchars($selected_doctor['specialization']); ?>
                            <?php endif; ?>
                        </small>
                    </div>
                </div>

                <!-- Chat Messages -->
                <div class="chat-messages" id="chatMessages">
                    <?php foreach ($messages as $message): ?>
                    <div class="message <?php echo $message['message_type'] === 'sent' ? 'sent' : 'received'; ?>">
                        <div class="message-content">
                            <?php echo htmlspecialchars($message['message']); ?>
                        </div>
                        <div class="message-time">
                            <?php echo date('g:i A', strtotime($message['created_at'])); ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Chat Input -->
                <div class="chat-input">
                    <form id="messageForm" class="d-flex gap-2">
                        <input type="hidden" name="receiver_id" value="<?php echo $selected_doctor['user_id']; ?>">
                        <input type="text" class="form-control" name="message" placeholder="Type your message..." required>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-send"></i>
                        </button>
                    </form>
                </div>
                <?php else: ?>
                <div class="d-flex align-items-center justify-content-center h-100">
                    <div class="text-center text-muted">
                        <i class="bi bi-chat-dots" style="font-size: 3rem; color: #4e73df;"></i>
                        <p class="mt-3">Select a doctor to start chatting</p>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle sidebar on mobile
        document.getElementById('sidebarToggle').addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('show');
            document.getElementById('chatSidebar').classList.toggle('show');
        });

        // Handle message form submission
        document.getElementById('messageForm')?.addEventListener('submit', function(e) {
            e.preventDefault();
            const form = this;
            const formData = new FormData(form);

            fetch('send_message.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Reload the page to show the new message
                    window.location.reload();
                } else {
                    alert('Error sending message: ' + (data.error || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error sending message. Please try again.');
            });
        });

        // Scroll to bottom of messages
        const messagesContainer = document.getElementById('chatMessages');
        if (messagesContainer) {
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }
    </script>
</body>
</html> 