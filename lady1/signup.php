<?php
session_start();
require_once 'config/database.php';

// Check if admin already exists
$stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'");
$adminCount = $stmt->fetchColumn();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    
    // If admin exists, force role to be patient
    $role = ($adminCount > 0) ? 'patient' : $_POST['role'];
    
    try {
        $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
        $stmt->execute([$name, $email, $password, $role]);
        
        // If user is a doctor, create doctor record
        if ($role == 'doctor') {
            $userId = $pdo->lastInsertId();
            $stmt = $pdo->prepare("INSERT INTO doctors (user_id, specialization, license_number) VALUES (?, ?, ?)");
            $stmt->execute([$userId, $_POST['specialization'], $_POST['license_number']]);
        }
        
        header('Location: login.php');
        exit();
    } catch(PDOException $e) {
        $error = "Registration failed: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html><html lang="en"><head>    <meta charset="UTF-8">    <meta name="viewport" content="width=device-width, initial-scale=1.0">    <title>Sign Up - HealthCare Portal</title>    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">    <style>        body {            font-family: 'Poppins', sans-serif;            background: linear-gradient(135deg, #002366, #4e73df);            min-height: 100vh;            display: flex;            align-items: center;            justify-content: center;            padding: 2rem 0;            color: #0f172a;        }        .signup-container {            width: 100%;            max-width: 500px;            padding: 2rem;        }        .signup-card {            background: white;            border-radius: 15px;            box-shadow: 0 10px 30px rgba(0,0,0,0.15);            overflow: hidden;        }        .signup-header {            background: linear-gradient(90deg, #4e73df 0%, #224abe 100%);            color: white;            padding: 2rem;            text-align: center;        }        .signup-header h1 {            font-size: 1.8rem;            font-weight: 700;            margin-bottom: 0.5rem;        }        .signup-header p {            opacity: 0.9;            margin-bottom: 0;        }        .signup-body {            padding: 2rem;        }        .form-label {            font-weight: 600;            color: #5a5c69;            margin-bottom: 0.5rem;        }        .form-control {            border: 1px solid #d1d3e2;            border-radius: 0.5rem;            padding: 0.75rem 1rem;            font-size: 0.9rem;            transition: all 0.3s ease;        }        .form-control:focus {            border-color: #4e73df;            box-shadow: 0 0 0 0.25rem rgba(78,115,223,0.25);        }        .form-select {            border: 1px solid #d1d3e2;            border-radius: 0.5rem;            padding: 0.75rem 1rem;            font-size: 0.9rem;            transition: all 0.3s ease;        }        .form-select:focus {            border-color: #4e73df;            box-shadow: 0 0 0 0.25rem rgba(78,115,223,0.25);        }        .input-group-text {            background-color: #4e73df;            border: none;            color: white;        }        .btn-primary {            background: linear-gradient(90deg, #4e73df 0%, #1d4ed8 100%);            border: none;            padding: 0.75rem 1.5rem;            font-weight: 600;            border-radius: 0.5rem;            transition: all 0.3s ease;        }        .btn-primary:hover {            background: linear-gradient(90deg, #1d4ed8 0%, #4e73df 100%);            transform: translateY(-2px);            box-shadow: 0 4px 12px rgba(78, 115, 223, 0.3);        }        .alert {            border: none;            border-radius: 0.5rem;            padding: 1rem;            margin-bottom: 1.5rem;        }        .alert-danger {            background: #fdeaea;            color: #d32f2f;        }        .signup-footer {            text-align: center;            padding: 1.5rem 2rem;            background: #f8f9fc;            border-top: 1px solid #e3e6f0;        }        .signup-footer p {            margin-bottom: 0;            color: #5a5c69;        }        .signup-footer a {            color: #4e73df;            text-decoration: none;            font-weight: 600;            transition: all 0.3s ease;        }        .signup-footer a:hover {            color: #224abe;            text-decoration: underline;        }        .logo {            display: flex;            align-items: center;            justify-content: center;            margin-bottom: 1rem;        }        .logo i {            font-size: 2rem;            color: white;            margin-right: 0.5rem;        }        .logo h2 {            margin: 0;            font-weight: 700;            color: white;        }    </style></head><body>    <div class="signup-container">        <div class="signup-card">            <div class="signup-header">                <div class="logo">                    <i class="bi bi-heart-pulse"></i>                    <h2>HealthCare</h2>                </div>                <h1>Create Account</h1>                <p>Join our healthcare community</p>            </div>
            <div class="signup-body">
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-circle-fill me-2"></i>
                        <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="mb-3">
                        <label class="form-label">Full Name</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="bi bi-person"></i>
                            </span>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email Address</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="bi bi-envelope"></i>
                            </span>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="bi bi-lock"></i>
                            </span>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                    </div>
                    
                    <?php if ($adminCount == 0): ?>
                    <div class="mb-3">
                        <label class="form-label">Role</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="bi bi-person-badge"></i>
                            </span>
                            <select class="form-select" id="role" name="role" required>
                                <option value="admin">Admin</option>
                                <option value="doctor">Doctor</option>
                                <option value="patient">Patient</option>
                            </select>
                        </div>
                    </div>
                    <?php else: ?>
                    <input type="hidden" name="role" value="patient">
                    <?php endif; ?>
                    
                    <div id="doctorFields" style="display: none;">
                        <div class="mb-3">
                            <label class="form-label">Specialization</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="bi bi-briefcase"></i>
                                </span>
                                <input type="text" class="form-control" id="specialization" name="specialization">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">License Number</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="bi bi-card-text"></i>
                                </span>
                                <input type="text" class="form-control" id="license_number" name="license_number">
                            </div>
                        </div>
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-person-plus me-1"></i> Sign Up
                        </button>
                    </div>
                </form>
            </div>
            <div class="signup-footer">
                <p>Already have an account? <a href="login.php">Login</a></p>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('role').addEventListener('change', function() {
            const doctorFields = document.getElementById('doctorFields');
            doctorFields.style.display = this.value === 'doctor' ? 'block' : 'none';
            
            const specialization = document.getElementById('specialization');
            const licenseNumber = document.getElementById('license_number');
            
            if (this.value === 'doctor') {
                specialization.required = true;
                licenseNumber.required = true;
            } else {
                specialization.required = false;
                licenseNumber.required = false;
            }
        });
    </script>
</body>
</html> 