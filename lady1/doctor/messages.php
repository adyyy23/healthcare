<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is doctor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    header('Location: ../login.php');
    exit();
}

// Get doctor information
$stmt = $pdo->prepare("
    SELECT d.*, u.name, u.email 
    FROM doctors d 
    JOIN users u ON d.user_id = u.id 
    WHERE d.user_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$doctor = $stmt->fetch();

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
            INSERT INTO notifications (user_id, type, message, related_id, created_at) 
            VALUES (?, 'message', 'You have a new message', ?, NOW())
        ");
        $stmt->execute([$receiver_id, $pdo->lastInsertId()]);
        
        header('Location: messages.php?chat=' . $receiver_id);
        exit();
    }
}

// Get selected chat's messages if any
$selected_chat = $_GET['chat'] ?? null;
$chat_user = null;
$messages = [];

if ($selected_chat) {
    // Get user information (either admin or patient)
    $stmt = $pdo->prepare("
        SELECT u.*, 
               CASE 
                   WHEN u.role = 'admin' THEN 'Admin'
                   ELSE (SELECT phone_number FROM patients WHERE user_id = u.id)
               END as additional_info
        FROM users u 
        WHERE u.id = ?
    ");
    $stmt->execute([$selected_chat]);
    $chat_user = $stmt->fetch();

    if ($chat_user) {
        // Mark messages as read
        $stmt = $pdo->prepare("
            UPDATE messages 
            SET is_read = TRUE 
            WHERE sender_id = ? AND receiver_id = ? AND is_read = FALSE
        ");
        $stmt->execute([$chat_user['id'], $doctor['user_id']]);

        // Get messages between doctor and user
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
            $doctor['user_id'],
            $doctor['user_id'], $chat_user['id'],
            $chat_user['id'], $doctor['user_id']
        ]);
        $messages = $stmt->fetchAll();
    }
}

// Get list of all patients and admin
$stmt = $pdo->prepare("
    SELECT DISTINCT u.*, 
           CASE 
               WHEN u.role = 'admin' THEN 'Admin'
               ELSE (SELECT phone_number FROM patients WHERE user_id = u.id)
           END as additional_info,
           (SELECT COUNT(*) FROM messages 
            WHERE sender_id = u.id 
            AND receiver_id = ? 
            AND is_read = FALSE) as unread_count
    FROM users u
    LEFT JOIN messages m ON (m.sender_id = u.id AND m.receiver_id = ?)
                   OR (m.sender_id = ? AND m.receiver_id = u.id)
    WHERE u.role IN ('patient', 'admin')
    ORDER BY unread_count DESC, u.name ASC
");
$stmt->execute([$doctor['user_id'], $doctor['user_id'], $doctor['user_id']]);
$chat_users = $stmt->fetchAll();

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
            height: calc(100vh - 200px);
            display: flex;
            background: white;
            border-radius: 1rem;
            box-shadow: 0 2px 12px rgba(14,165,233,0.07);
            overflow: hidden;
        }

        .chat-list {
            width: 300px;
            border-right: 1px solid #dee2e6;
            overflow-y: auto;
        }

        .chat-area {
            flex: 1;
            display: flex;
            flex-direction: column;
            padding: 1rem;
        }

        .chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
        }

        .message {
            margin-bottom: 1rem;
            max-width: 70%;
        }

        .message.sent {
            margin-left: auto;
        }

        .message.received {
            margin-right: auto;
        }

        .message-content {
            padding: 0.75rem;
            border-radius: 1rem;
            box-shadow: 0 1px 2px rgba(0,0,0,0.1);
            position: relative;
        }

        .message.sent .message-content {
            background: linear-gradient(90deg, #4e73df 0%, #224abe 100%);
            color: #fff;
        }

        .message.received .message-content {
            background: #f8f9fa;
            color: #212529;
            border: 1px solid #e9ecef;
        }

        .message-time {
            font-size: 0.75rem;
            color: #6c757d;
            margin-top: 0.25rem;
            display: flex;
            align-items: center;
        }

        .message-time small {
            opacity: 0.8;
        }

        .chat-user-item {
            padding: 1rem;
            border-bottom: 1px solid #dee2e6;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .chat-user-item:hover {
            background-color: #f8f9fa;
        }

        .chat-user-item.active {
            background-color: #e9ecef;
            border-left: 4px solid #4e73df;
        }

        .chat-user-item .role-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
            border-radius: 1rem;
            font-weight: 600;
        }

        .chat-user-item .role-badge.admin {
            background-color: #e6f0fa;
            color: #2563eb;
        }

        .chat-user-item .role-badge.patient {
            background-color: #e6f4ea;
            color: #219150;
        }

        .unread-badge {
            background-color: #e74a3b;
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 1rem;
            font-size: 0.75rem;
            font-weight: 600;
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

        .form-control {
            border-radius: 0.5rem;
            border: 1px solid #d1d3e2;
            padding: 0.75rem 1rem;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .form-control:focus {
            border-color: #4e73df;
            box-shadow: 0 0 0 0.25rem rgba(78, 115, 223, 0.25);
        }

        .profile-img {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid rgba(255,255,255,0.2);
            margin-bottom: 1rem;
        }

        .sidebar-footer {
            margin-top: auto;
            border-top: 1px solid rgba(255,255,255,0.1);
            padding-top: 1rem;
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
            .chat-container {
                flex-direction: column;
                height: calc(100vh - 150px);
            }
            .chat-list {
                width: 100%;
                height: 200px;
                border-right: none;
                border-bottom: 1px solid #dee2e6;
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
                <a class="nav-link" href="appointments.php">
                    <i class="bi bi-calendar-check"></i> Appointments
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="schedule.php">
                    <i class="bi bi-clock"></i> Schedule
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link active" href="messages.php">
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
            <h1 class="page-title">Messages</h1>
            <button class="btn btn-primary d-md-none" id="sidebarToggle">
                <i class="bi bi-list"></i>
            </button>
        </div>

        <div class="chat-container">
            <div class="chat-list">
                <h5 class="p-3 border-bottom">Chats</h5>
                <?php foreach ($chat_users as $user): ?>
                <div class="chat-user-item <?php echo $selected_chat == $user['id'] ? 'active' : ''; ?>"
                     onclick="window.location.href='?chat=<?php echo $user['id']; ?>'">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-0"><?php echo htmlspecialchars($user['name']); ?></h6>
                            <div class="d-flex align-items-center gap-2">
                                <span class="role-badge <?php echo $user['role']; ?>">
                                    <?php echo $user['role'] === 'admin' ? 'Admin' : 'Patient'; ?>
                                </span>
                                <?php if ($user['additional_info'] && $user['role'] !== 'admin'): ?>
                                <small class="text-muted"><?php echo htmlspecialchars($user['additional_info']); ?></small>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if ($user['unread_count'] > 0): ?>
                        <span class="unread-badge"><?php echo $user['unread_count']; ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="chat-area">
                <?php if ($selected_chat && $chat_user): ?>
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h5 class="mb-0"><?php echo htmlspecialchars($chat_user['name']); ?></h5>
                        <small class="text-muted">
                            <?php echo $chat_user['role'] === 'admin' ? 'Administrator' : 'Patient'; ?>
                            <?php if ($chat_user['role'] !== 'admin' && $chat_user['additional_info']): ?>
                            - <?php echo htmlspecialchars($chat_user['additional_info']); ?>
                            <?php endif; ?>
                        </small>
                    </div>
                </div>

                <div class="chat-messages">
                    <?php foreach ($messages as $message): ?>
                    <div class="message <?php echo $message['message_type']; ?>">
                        <div class="message-content">
                            <?php echo htmlspecialchars($message['message']); ?>
                        </div>
                        <div class="message-time">
                            <?php echo date('M j, g:i a', strtotime($message['created_at'])); ?>
                            <small class="text-muted ms-2">
                                <?php echo $message['message_type'] === 'sent' ? 'You' : ($chat_user['role'] === 'admin' ? 'Admin' : 'Patient'); ?>
                            </small>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <form method="POST" class="message-input">
                    <input type="hidden" name="receiver_id" value="<?php echo $chat_user['id']; ?>">
                    <div class="input-group">
                        <input type="text" name="message" class="form-control" placeholder="Type your message..." required>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-send"></i> Send
                        </button>
                    </div>
                </form>
                <?php else: ?>
                <div class="text-center text-muted mt-5">
                    <i class="bi bi-chat-dots" style="font-size: 3rem;"></i>
                    <h5 class="mt-3">Select a chat to start messaging</h5>
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
        });

        // Scroll to bottom of messages
        const chatMessages = document.querySelector('.chat-messages');
        if (chatMessages) {
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }
    </script>
</body>
</html> 