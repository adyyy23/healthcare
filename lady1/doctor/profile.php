<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is doctor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    header('Location: ../login.php');
    exit();
}

// Get doctor's information
$stmt = $pdo->prepare("
    SELECT d.*, u.name, u.email 
    FROM doctors d 
    JOIN users u ON d.user_id = u.id 
    WHERE d.user_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$doctor = $stmt->fetch(PDO::FETCH_ASSOC);

// If doctor profile doesn't exist, create it
if (!$doctor) {
    try {
        // Create doctor profile with default values
        $stmt = $pdo->prepare("
            INSERT INTO doctors (
                user_id, 
                specialization, 
                license_number, 
                years_of_experience,
                phone_number
            ) VALUES (?, 'General Medicine', 'PENDING', 0, '')
        ");
        $stmt->execute([$_SESSION['user_id']]);
        
        // Fetch the newly created profile
        $stmt = $pdo->prepare("
            SELECT d.*, u.name, u.email 
            FROM doctors d 
            JOIN users u ON d.user_id = u.id 
            WHERE d.user_id = ?
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $doctor = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$doctor) {
            throw new Exception("Failed to create doctor profile");
        }
    } catch (Exception $e) {
        $error_message = "Error creating doctor profile: " . $e->getMessage();
    }
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $specialization = $_POST['specialization'] ?? '';
    $license_number = $_POST['license_number'] ?? '';
    $years_of_experience = $_POST['years_of_experience'] ?? 0;
    $phone_number = $_POST['phone_number'] ?? '';

    // Handle profile picture upload
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../assets/uploads/profile_pictures/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $file_extension = strtolower(pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];

        if (in_array($file_extension, $allowed_extensions)) {
            $new_filename = 'doctor_' . $_SESSION['user_id'] . '_' . time() . '.' . $file_extension;
            $upload_path = $upload_dir . $new_filename;

            if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $upload_path)) {
                // Delete old profile picture if exists
                if (!empty($doctor['profile_picture']) && file_exists('../' . $doctor['profile_picture'])) {
                    unlink('../' . $doctor['profile_picture']);
                }
                $profile_picture = 'assets/uploads/profile_pictures/' . $new_filename;
            }
        }
    }

    try {
        // Update user information
        $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ? WHERE id = ?");
        $stmt->execute([$name, $email, $_SESSION['user_id']]);

        // Update doctor information
        $stmt = $pdo->prepare("
            UPDATE doctors 
            SET specialization = ?, 
                license_number = ?, 
                years_of_experience = ?,
                phone_number = ?" . 
                (isset($profile_picture) ? ", profile_picture = ?" : "") . "
            WHERE user_id = ?
        ");

        $params = [
            $specialization,
            $license_number,
            $years_of_experience,
            $phone_number
        ];

        if (isset($profile_picture)) {
            $params[] = $profile_picture;
        }
        $params[] = $_SESSION['user_id'];

        $stmt->execute($params);

        // Refresh doctor data
        $stmt = $pdo->prepare("
            SELECT d.*, u.name, u.email 
            FROM doctors d 
            JOIN users u ON d.user_id = u.id 
            WHERE d.user_id = ?
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $doctor = $stmt->fetch(PDO::FETCH_ASSOC);

        $success_message = "Profile updated successfully!";
    } catch (PDOException $e) {
        $error_message = "Error updating profile: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - HealthCare Portal</title>
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

        .page-title {
            color: #4e73df;
            font-weight: 700;
            margin-bottom: 2rem;
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

        .form-label {
            color: #5a5c69;
            font-weight: 600;
            margin-bottom: 0.5rem;
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

        .sidebar-footer {
            margin-top: auto;
            border-top: 1px solid rgba(255,255,255,0.1);
            padding-top: 1rem;
        }

        .profile-img {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid rgba(255,255,255,0.2);
            margin-bottom: 1rem;
        }

        .profile-image {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 5px solid #fff;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }

        .profile-image-container {
            position: relative;
            display: inline-block;
            margin-bottom: 1.5rem;
        }

        .profile-image-upload {
            position: absolute;
            bottom: 0;
            right: 0;
            background: #4e73df;
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .profile-image-upload:hover {
            background: #1d4ed8;
            transform: scale(1.1);
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
                <a class="nav-link" href="messages.php">
                    <i class="bi bi-chat-dots"></i> Messages
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link active" href="profile.php">
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
            <h1 class="page-title">My Profile</h1>
            <button class="btn btn-primary d-md-none" id="sidebarToggle">
                <i class="bi bi-list"></i>
            </button>
        </div>

        <?php if (isset($success_message)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php 
            echo $success_message;
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php 
            echo $error_message;
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Professional Information</h5>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="profile.php" class="needs-validation" novalidate enctype="multipart/form-data">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Full Name</label>
                            <input type="text" class="form-control" name="name" value="<?php echo htmlspecialchars($doctor['name'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($doctor['email'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">License Number</label>
                            <input type="text" class="form-control" name="license_number" value="<?php echo htmlspecialchars($doctor['license_number'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Specialization</label>
                            <input type="text" class="form-control" name="specialization" value="<?php echo htmlspecialchars($doctor['specialization'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Phone Number</label>
                            <input type="tel" class="form-control" name="phone_number" value="<?php echo htmlspecialchars($doctor['phone_number'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Years of Experience</label>
                            <input type="number" class="form-control" name="years_of_experience" value="<?php echo htmlspecialchars($doctor['years_of_experience'] ?? ''); ?>" required>
                        </div>
                        <div class="col-12 mb-4">
                            <label class="form-label">Profile Picture</label>
                            <div class="profile-image-container">
                                <img src="<?php echo $doctor['profile_picture'] ? '../' . htmlspecialchars($doctor['profile_picture']) : '../assets/images/default-user.jpg'; ?>" 
                                     alt="Profile" class="profile-image">
                                <label for="profile_picture" class="profile-image-upload">
                                    <i class="bi bi-camera"></i>
                                </label>
                                <input type="file" id="profile_picture" name="profile_picture" class="d-none" accept="image/*">
                            </div>
                            <small class="text-muted">Click the camera icon to change your profile picture</small>
                        </div>
                    </div>
                    <div class="text-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Update Profile
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle sidebar on mobile
        document.getElementById('sidebarToggle').addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('show');
        });

        document.getElementById('profile_picture').addEventListener('change', function(e) {
            if (e.target.files && e.target.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.querySelector('.profile-image').src = e.target.result;
                }
                reader.readAsDataURL(e.target.files[0]);
            }
        });
    </script>
</body>
</html> 