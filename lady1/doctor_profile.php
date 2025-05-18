<?php
require_once 'config/database.php';

$doctor_id = isset($_GET['doctor_id']) ? intval($_GET['doctor_id']) : 0;
if (!$doctor_id) {
    echo '<div style="padding:2rem;text-align:center;">Invalid doctor ID.</div>';
    exit();
}

$stmt = $pdo->prepare("
    SELECT d.*, u.name, u.email
    FROM doctors d
    JOIN users u ON d.user_id = u.id
    WHERE d.id = ?
");
$stmt->execute([$doctor_id]);
$doctor = $stmt->fetch();

if (!$doctor) {
    echo '<div style="padding:2rem;text-align:center;">Doctor profile not found.</div>';
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Profile - <?php echo htmlspecialchars($doctor['name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: #f4f8ff; font-family: 'Poppins', sans-serif; }
        .profile-card {
            max-width: 500px;
            margin: 3rem auto;
            background: #fff;
            border-radius: 1.5rem;
            box-shadow: 0 2px 16px rgba(30,64,175,0.08);
            padding: 2.5rem 2rem;
        }
        .profile-img {
            width: 120px;
            height: 120px;
            object-fit: cover;
            border-radius: 50%;
            margin-bottom: 1.5rem;
            border: 4px solid #4e73df;
        }
        .profile-title { font-size: 2rem; font-weight: 700; color: #4e73df; }
        .profile-spec { color: #0ea5e9; font-size: 1.1rem; font-weight: 600; margin-bottom: 1rem; }
        .profile-info { font-size: 1.05rem; margin-bottom: 0.7rem; }
        .profile-label { color: #64748b; font-weight: 600; margin-right: 0.5rem; }
        .back-link { color: #4e73df; text-decoration: none; font-weight: 600; }
        .back-link:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="container">
        <a href="patient/dashboard.php#doctors" class="back-link d-block mt-4 mb-3"><i class="bi bi-arrow-left"></i> Back to Doctors</a>
        <div class="profile-card text-center">
            <img src="<?php echo $doctor['profile_picture'] ? htmlspecialchars($doctor['profile_picture']) : 'assets/images/default-doctor.jpg'; ?>" alt="Dr. <?php echo htmlspecialchars($doctor['name']); ?>" class="profile-img">
            <div class="profile-title">Dr. <?php echo htmlspecialchars($doctor['name']); ?></div>
            <div class="profile-spec"><?php echo htmlspecialchars($doctor['specialization']); ?></div>
            <div class="profile-info"><span class="profile-label">License #:</span> <?php echo htmlspecialchars($doctor['license_number']); ?></div>
            <div class="profile-info"><span class="profile-label">Experience:</span> <?php echo htmlspecialchars($doctor['years_of_experience']); ?> years</div>
            <div class="profile-info"><span class="profile-label">Phone:</span> <?php echo htmlspecialchars($doctor['phone_number']); ?></div>
            <div class="profile-info"><span class="profile-label">Email:</span> <?php echo htmlspecialchars($doctor['email']); ?></div>
        </div>
    </div>
</body>
</html> 