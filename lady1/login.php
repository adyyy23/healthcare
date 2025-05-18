<?php
session_start();
require_once 'config/database.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_POST['email'];
    $password = $_POST['password'];
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];
        
        switch($user['role']) {
            case 'admin':
                header('Location: admin/dashboard.php');
                break;
            case 'doctor':
                header('Location: doctor/dashboard.php');
                break;
            case 'patient':
                header('Location: patient/dashboard.php');
                break;
        }
        exit();
    } else {
        $error = "Invalid email or password";
    }
}
?>

<!DOCTYPE html><html lang="en"><head>    <meta charset="UTF-8">    <meta name="viewport" content="width=device-width, initial-scale=1.0">    <title>Login - HealthCare Portal</title>    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">    <style>        body {            font-family: 'Poppins', sans-serif;            background: linear-gradient(135deg, #002366, #4e73df);            min-height: 100vh;            display: flex;            align-items: center;            justify-content: center;            padding: 2rem 0;            color: #0f172a;        }        .login-container {            width: 100%;            max-width: 400px;            padding: 2rem;        }        .login-card {            background: white;            border-radius: 15px;            box-shadow: 0 10px 30px rgba(0,0,0,0.15);            overflow: hidden;        }        .login-header {            background: linear-gradient(90deg, #4e73df 0%, #224abe 100%);            color: white;            padding: 2rem;            text-align: center;        }        .login-header h1 {            font-size: 1.8rem;            font-weight: 700;            margin-bottom: 0.5rem;        }        .login-header p {            opacity: 0.9;            margin-bottom: 0;        }        .login-body {            padding: 2rem;        }        .form-label {            font-weight: 600;            color: #5a5c69;            margin-bottom: 0.5rem;        }        .form-control {            border: 1px solid #d1d3e2;            border-radius: 0.5rem;            padding: 0.75rem 1rem;            font-size: 0.9rem;            transition: all 0.3s ease;        }        .form-control:focus {            border-color: #4e73df;            box-shadow: 0 0 0 0.25rem rgba(78,115,223,0.25);        }        .input-group-text {            background-color: #4e73df;            border: none;            color: white;        }        .btn-primary {            background: linear-gradient(90deg, #4e73df 0%, #1d4ed8 100%);            border: none;            padding: 0.75rem 1.5rem;            font-weight: 600;            border-radius: 0.5rem;            transition: all 0.3s ease;        }        .btn-primary:hover {            background: linear-gradient(90deg, #1d4ed8 0%, #4e73df 100%);            transform: translateY(-2px);            box-shadow: 0 4px 12px rgba(78, 115, 223, 0.3);        }        .alert {            border: none;            border-radius: 0.5rem;            padding: 1rem;            margin-bottom: 1.5rem;        }        .alert-danger {            background: #fdeaea;            color: #d32f2f;        }        .login-footer {            text-align: center;            padding: 1.5rem 2rem;            background: #f8f9fc;            border-top: 1px solid #e3e6f0;        }        .login-footer p {            margin-bottom: 0;            color: #5a5c69;        }        .login-footer a {            color: #4e73df;            text-decoration: none;            font-weight: 600;            transition: all 0.3s ease;        }        .login-footer a:hover {            color: #224abe;            text-decoration: underline;        }        .logo {            display: flex;            align-items: center;            justify-content: center;            margin-bottom: 1rem;        }        .logo i {            font-size: 2rem;            color: white;            margin-right: 0.5rem;        }        .logo h2 {            margin: 0;            font-weight: 700;            color: white;        }    </style></head><body>    <div class="login-container">        <div class="login-card">            <div class="login-header">                <div class="logo">                    <i class="bi bi-heart-pulse"></i>                    <h2>HealthCare</h2>                </div>                <h1>Welcome Back!</h1>                <p>Please login to your account</p>            </div>
            <div class="login-body">
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-circle-fill me-2"></i>
                        <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="mb-3">
                        <label class="form-label">Email Address</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="bi bi-envelope"></i>
                            </span>
                            <input type="email" class="form-control" name="email" required>
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="form-label">Password</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="bi bi-lock"></i>
                            </span>
                            <input type="password" class="form-control" name="password" required>
                        </div>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-box-arrow-in-right me-1"></i> Login
                        </button>
                    </div>
                </form>
            </div>
            <div class="login-footer">
                <p>Don't have an account? <a href="signup.php">Sign up</a></p>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 