<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

// Get patient information with proper error handling
$stmt = $pdo->prepare("
    SELECT p.*, u.name, u.email, u.role 
    FROM patients p 
    JOIN users u ON p.user_id = u.id 
    WHERE p.user_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$patient = $stmt->fetch(PDO::FETCH_ASSOC);

// Ensure all required fields have default values if not set
$patient = array_merge([
    'name' => '',
    'email' => '',
    'phone_number' => '',
    'address' => '',
    'date_of_birth' => '',
    'gender' => '',
    'medical_history' => '',
    'allergies' => '',
    'blood_type' => '',
    'emergency_contact' => '',
    'emergency_phone' => ''
], $patient ?? []);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $phone = $_POST['phone_number'];
    $address = $_POST['address'];
    $gender = $_POST['gender'];
    $date_of_birth = $_POST['date_of_birth'];
    $medical_history = $_POST['medical_history'];
    $allergies = $_POST['allergies'];
    $blood_type = $_POST['blood_type'];
    $emergency_contact = $_POST['emergency_contact'];
    $emergency_phone = $_POST['emergency_phone'];

    try {
        // Handle profile picture upload
        $profile_picture = $patient['profile_picture']; // Keep existing picture by default
        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../assets/uploads/profile_pictures/';
            $file_extension = strtolower(pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION));
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
            
            if (in_array($file_extension, $allowed_extensions)) {
                $new_filename = 'patient_' . $_SESSION['user_id'] . '_' . time() . '.' . $file_extension;
                $upload_path = $upload_dir . $new_filename;
                
                if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $upload_path)) {
                    // Delete old profile picture if exists
                    if ($profile_picture && file_exists('../' . $profile_picture)) {
                        unlink('../' . $profile_picture);
                    }
                    $profile_picture = 'assets/uploads/profile_pictures/' . $new_filename;
                }
            }
        }

        // Update users table
        $stmt = $pdo->prepare("
            UPDATE users 
            SET name = ?, email = ? 
            WHERE id = ?
        ");
        $stmt->execute([$name, $email, $_SESSION['user_id']]);

        // Update patients table
        $stmt = $pdo->prepare("
            UPDATE patients 
            SET phone_number = ?, address = ?, gender = ?, date_of_birth = ?, 
                medical_history = ?, allergies = ?, blood_type = ?, 
                emergency_contact = ?, emergency_phone = ?, profile_picture = ?
            WHERE user_id = ?
        ");
        $stmt->execute([
            $phone, $address, $gender, $date_of_birth, $medical_history,
            $allergies, $blood_type, $emergency_contact, $emergency_phone,
            $profile_picture, $_SESSION['user_id']
        ]);

        $_SESSION['success'] = "Profile updated successfully!";
        header('Location: profile.php');
        exit();
    } catch (PDOException $e) {
        $error = "Error updating profile: " . $e->getMessage();
    }
}

// Get unread notifications count
$stmt = $pdo->prepare("
    SELECT COUNT(*) as unread_count 
    FROM notifications 
    WHERE user_id = ? AND is_read = FALSE
");
$stmt->execute([$_SESSION['user_id']]);
$notification_count = $stmt->fetch()['unread_count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Healthcare Portal</title>
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
        
        .profile-container {
            background: white;
            border-radius: 1rem;
            box-shadow: 0 4px 16px rgba(0,0,0,0.08);
            padding: 2rem;
            margin-top: -3rem;
            position: relative;
            z-index: 10;
        }
        
        .profile-sidebar {
            background: #f9fafc;
            border-radius: 1rem;
            padding: 2rem;
            text-align: center;
        }
        
        .profile-image {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 5px solid #fff;
            box-shadow: 0 4px 16px rgba(78,115,223,0.1);
            margin-bottom: 1.5rem;
        }
        
        .profile-upload {
            position: relative;
            display: inline-block;
            margin-bottom: 1rem;
        }
        
        .upload-icon {
            position: absolute;
            bottom: 0;
            right: 0;
            background: linear-gradient(90deg, #4e73df 0%, #224abe 100%);
            color: white;
            width: 35px;
            height: 35px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 4px 8px rgba(34, 74, 190, 0.2);
            transition: all 0.2s;
        }
        
        .upload-icon:hover {
            transform: scale(1.1);
        }
        
        .profile-name {
            font-size: 1.5rem;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 0.5rem;
        }
        
        .profile-email {
            color: #64748b;
            margin-bottom: 1.5rem;
        }
        
        .info-section {
            background: #fff;
            border-radius: 1rem;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 12px rgba(14,165,233,0.07);
        }
        
        .info-header {
            display: flex;
            align-items: center;
            margin-bottom: 1.5rem;
            color: #4e73df;
            font-weight: 700;
        }
        
        .info-icon {
            background: #e0e7ff;
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            color: #4e73df;
            font-size: 1.25rem;
        }
        
        .form-label {
            font-weight: 600;
            color: #475569;
            margin-bottom: 0.5rem;
        }
        
        .form-control, .form-select {
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            border: 1px solid #e2e8f0;
            font-size: 0.95rem;
            box-shadow: none;
            transition: all 0.2s;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #4e73df;
            box-shadow: 0 0 0 3px rgba(78,115,223,0.1);
        }
        
        .btn-primary {
            background: linear-gradient(90deg, #4e73df 0%, #1d4ed8 100%);
            border: none;
            color: #fff;
            border-radius: 0.5rem;
            transition: all 0.2s;
            font-weight: 600;
            padding: 0.75rem 1.5rem;
        }
        
        .btn-primary:hover, .btn-primary:focus {
            background: linear-gradient(90deg, #1d4ed8 0%, #4e73df 100%);
            box-shadow: 0 4px 16px rgba(30,64,175,0.2);
            transform: translateY(-2px);
        }
        
        .btn-outline-primary {
            border-color: #4e73df;
            color: #4e73df;
            background: #fff;
            border-radius: 0.5rem;
            transition: all 0.2s;
            font-weight: 600;
            padding: 0.75rem 1.5rem;
        }
        
        .btn-outline-primary:hover, .btn-outline-primary:focus {
            background: #4e73df;
            color: #fff;
            box-shadow: 0 4px 12px rgba(78,115,223,0.18);
            transform: translateY(-2px);
        }
        
        .alert {
            border-radius: 0.5rem;
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
            border: none;
        }
        
        .alert-success {
            background-color: #dcfce7;
            color: #166534;
        }
        
        .alert-danger {
            background-color: #fee2e2;
            color: #991b1b;
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
            .profile-sidebar {
                margin-bottom: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-0 align-items-center">
                    <li class="nav-item"><a class="nav-link" href="dashboard.php">Homepage</a></li>
                    <li class="nav-item"><a class="nav-link" href="dashboard.php#doctors">Doctors</a></li>
                    <li class="nav-item"><a class="nav-link" href="appointments.php">Appointments</a></li>
                    <li class="nav-item"><a class="nav-link" href="messages.php">Messages</a></li>
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
                        <a class="nav-link dropdown-toggle active" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-circle me-1"></i> Profile
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                            <li><a class="dropdown-item active" href="profile.php"><i class="bi bi-person me-2"></i>My Profile</a></li>
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
                    <h1 class="display-5 fw-bold mb-3">My Profile</h1>
                    <p class="lead mb-0">Manage your personal information and healthcare preferences</p>
                </div>
                <div class="col-md-4 d-none d-md-block text-end">
                    <i class="bi bi-person-lines-fill" style="font-size: 8rem; opacity: 0.2;"></i>
                </div>
            </div>
        </div>
    </section>

    <!-- Profile Content -->
    <section class="py-5">
        <div class="container">
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i>
                    <?php 
                    echo $_SESSION['success'];
                    unset($_SESSION['success']);
                    ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
                    <i class="bi bi-exclamation-circle-fill me-2"></i>
                    <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <div class="profile-container">
                <form method="POST" action="profile.php" enctype="multipart/form-data">
                    <div class="row">
                        <div class="col-lg-4 mb-4 mb-lg-0">
                            <div class="profile-sidebar">
                                <div class="profile-upload">
                                    <img src="<?php echo $patient['profile_picture'] ? '../' . htmlspecialchars($patient['profile_picture']) : '../assets/images/default-user.jpg'; ?>" 
                                         alt="Profile" class="profile-image">
                                    <label for="profile_picture" class="upload-icon">
                                        <i class="bi bi-camera"></i>
                                    </label>
                                    <input type="file" id="profile_picture" name="profile_picture" class="d-none" accept="image/*">
                                </div>
                                <div class="profile-name"><?php echo htmlspecialchars($patient['name']); ?></div>
                                <div class="profile-email mb-4"><?php echo htmlspecialchars($patient['email']); ?></div>
                                
                                <div class="d-grid gap-2">
                                    <a href="change-password.php" class="btn btn-outline-primary">
                                        <i class="bi bi-key"></i> Change Password
                                    </a>
                                    <a href="appointments.php" class="btn btn-outline-primary">
                                        <i class="bi bi-calendar-check"></i> My Appointments
                                    </a>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-lg-8">
                            <!-- Personal Information -->
                            <div class="info-section mb-4">
                                <div class="info-header">
                                    <div class="info-icon">
                                        <i class="bi bi-person"></i>
                                    </div>
                                    <h5 class="mb-0">Personal Information</h5>
                                </div>
                                
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="name" class="form-label">Full Name</label>
                                        <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($patient['name']); ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="email" class="form-label">Email Address</label>
                                        <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($patient['email']); ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="phone_number" class="form-label">Phone Number</label>
                                        <input type="tel" class="form-control" id="phone_number" name="phone_number" value="<?php echo htmlspecialchars($patient['phone_number']); ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="date_of_birth" class="form-label">Date of Birth</label>
                                        <input type="date" class="form-control" id="date_of_birth" name="date_of_birth" value="<?php echo htmlspecialchars($patient['date_of_birth']); ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="gender" class="form-label">Gender</label>
                                        <select class="form-select" id="gender" name="gender">
                                            <option value="">Select Gender</option>
                                            <option value="male" <?php echo $patient['gender'] === 'male' ? 'selected' : ''; ?>>Male</option>
                                            <option value="female" <?php echo $patient['gender'] === 'female' ? 'selected' : ''; ?>>Female</option>
                                            <option value="other" <?php echo $patient['gender'] === 'other' ? 'selected' : ''; ?>>Other</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="blood_type" class="form-label">Blood Type</label>
                                        <select class="form-select" id="blood_type" name="blood_type">
                                            <option value="">Select Blood Type</option>
                                            <option value="A+" <?php echo $patient['blood_type'] === 'A+' ? 'selected' : ''; ?>>A+</option>
                                            <option value="A-" <?php echo $patient['blood_type'] === 'A-' ? 'selected' : ''; ?>>A-</option>
                                            <option value="B+" <?php echo $patient['blood_type'] === 'B+' ? 'selected' : ''; ?>>B+</option>
                                            <option value="B-" <?php echo $patient['blood_type'] === 'B-' ? 'selected' : ''; ?>>B-</option>
                                            <option value="AB+" <?php echo $patient['blood_type'] === 'AB+' ? 'selected' : ''; ?>>AB+</option>
                                            <option value="AB-" <?php echo $patient['blood_type'] === 'AB-' ? 'selected' : ''; ?>>AB-</option>
                                            <option value="O+" <?php echo $patient['blood_type'] === 'O+' ? 'selected' : ''; ?>>O+</option>
                                            <option value="O-" <?php echo $patient['blood_type'] === 'O-' ? 'selected' : ''; ?>>O-</option>
                                        </select>
                                    </div>
                                    <div class="col-12">
                                        <label for="address" class="form-label">Address</label>
                                        <textarea class="form-control" id="address" name="address" rows="2"><?php echo htmlspecialchars($patient['address']); ?></textarea>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Medical Information -->
                            <div class="info-section mb-4">
                                <div class="info-header">
                                    <div class="info-icon">
                                        <i class="bi bi-heart-pulse"></i>
                                    </div>
                                    <h5 class="mb-0">Medical Information</h5>
                                </div>
                                
                                <div class="row g-3">
                                    <div class="col-12">
                                        <label for="medical_history" class="form-label">Medical History</label>
                                        <textarea class="form-control" id="medical_history" name="medical_history" rows="3" placeholder="List any previous medical conditions or surgeries"><?php echo htmlspecialchars($patient['medical_history']); ?></textarea>
                                    </div>
                                    <div class="col-12">
                                        <label for="allergies" class="form-label">Allergies</label>
                                        <textarea class="form-control" id="allergies" name="allergies" rows="2" placeholder="List any allergies to medications, food, or environmental factors"><?php echo htmlspecialchars($patient['allergies']); ?></textarea>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Emergency Contact -->
                            <div class="info-section mb-4">
                                <div class="info-header">
                                    <div class="info-icon">
                                        <i class="bi bi-telephone"></i>
                                    </div>
                                    <h5 class="mb-0">Emergency Contact</h5>
                                </div>
                                
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="emergency_contact" class="form-label">Contact Name</label>
                                        <input type="text" class="form-control" id="emergency_contact" name="emergency_contact" value="<?php echo htmlspecialchars($patient['emergency_contact']); ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="emergency_phone" class="form-label">Contact Phone</label>
                                        <input type="tel" class="form-control" id="emergency_phone" name="emergency_phone" value="<?php echo htmlspecialchars($patient['emergency_phone']); ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="d-grid d-md-flex justify-content-md-end">
                                <button type="submit" class="btn btn-primary px-4">
                                    <i class="bi bi-check-circle me-2"></i> Save Changes
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
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