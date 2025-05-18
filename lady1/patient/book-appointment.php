<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is patient
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header('Location: ../login.php');
    exit();
}

// Get patient's information
$stmt = $pdo->prepare("
    SELECT p.*, u.name, u.email 
    FROM patients p 
    JOIN users u ON p.user_id = u.id 
    WHERE p.user_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$patient = $stmt->fetch();

// Get all doctors
$stmt = $pdo->prepare("
    SELECT d.*, u.name, u.email
    FROM doctors d
    JOIN users u ON d.user_id = u.id
    ORDER BY u.name
");
$stmt->execute();
$doctors = $stmt->fetchAll();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate input
        if (empty($_POST['doctor_id']) || empty($_POST['appointment_type']) || 
            empty($_POST['appointment_date']) || empty($_POST['appointment_time']) || 
            empty($_POST['reason'])) {
            throw new Exception("All fields are required.");
        }

        // Check if the selected time slot is available
        $stmt = $pdo->prepare("
            SELECT * FROM doctor_schedules 
            WHERE doctor_id = ? 
            AND date = ? 
            AND start_time = ?
            AND status = 'available'
        ");
        $stmt->execute([
            $_POST['doctor_id'],
            $_POST['appointment_date'],
            $_POST['appointment_time']
        ]);
        
        if (!$stmt->fetch()) {
            throw new Exception("Selected time slot is no longer available.");
        }

        // Store appointment details in session
        $_SESSION['temp_appointment'] = [
            'doctor_id' => $_POST['doctor_id'],
            'appointment_type' => $_POST['appointment_type'],
            'appointment_date' => $_POST['appointment_date'],
            'appointment_time' => $_POST['appointment_time'],
            'reason' => $_POST['reason']
        ];

        // Redirect to confirmation page
        header('Location: confirm-appointment.php');
        exit();

    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Get available dates for the selected doctor
$available_dates = [];
if (isset($_GET['doctor_id'])) {
    $stmt = $pdo->prepare("
        SELECT DISTINCT date 
        FROM doctor_schedules 
        WHERE doctor_id = ? 
        AND date >= CURDATE()
        AND status = 'available'
        ORDER BY date
    ");
    $stmt->execute([$_GET['doctor_id']]);
    $available_dates = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// Get available times for the selected date
$available_times = [];
if (isset($_GET['doctor_id']) && isset($_GET['date'])) {
    $stmt = $pdo->prepare("
        SELECT start_time, end_time 
        FROM doctor_schedules 
        WHERE doctor_id = ? 
        AND date = ?
        AND status = 'available'
        ORDER BY start_time
    ");
    $stmt->execute([$_GET['doctor_id'], $_GET['date']]);
    $available_times = $stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Appointment - Healthcare System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2563eb;
            --secondary-color: #64748b;
            --success-color: #10b981;
            --info-color: #0ea5e9;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --background-color: #f1f5f9;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--background-color);
            color: #334155;
        }

        .navbar {
            background: white;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            padding: 1rem 2rem;
        }

        .navbar-brand {
            color: var(--primary-color) !important;
            font-weight: 600;
            font-size: 1.5rem;
        }

        .booking-card {
            background: white;
            border-radius: 1rem;
            padding: 2rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-top: 2rem;
        }

        .form-label {
            font-weight: 600;
            color: var(--secondary-color);
            margin-bottom: 0.75rem;
        }

        .form-control {
            border-radius: 0.5rem;
            border: 1px solid #e2e8f0;
            padding: 0.75rem 1rem;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(37, 99, 235, 0.25);
        }

        .btn-primary {
            background: var(--primary-color);
            border: none;
            padding: 0.8rem 1.5rem;
            font-weight: 500;
            border-radius: 0.5rem;
            min-width: 200px;
        }

        .btn-primary:hover {
            background: #1d4ed8;
        }

        .time-slot {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            margin: 0.25rem;
            border: 1px solid #e2e8f0;
            border-radius: 0.5rem;
            cursor: pointer;
            transition: all 0.2s;
            background: white;
            min-width: 150px;
            text-align: center;
        }

        .time-slot:hover {
            background: var(--background-color);
            border-color: var(--primary-color);
        }

        .time-slot.selected {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }

        .date-slot {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            margin: 0.25rem;
            border: 1px solid #e2e8f0;
            border-radius: 0.5rem;
            cursor: pointer;
            transition: all 0.2s;
            background: white;
        }

        .date-slot:hover {
            background: var(--background-color);
            border-color: var(--primary-color);
        }

        .date-slot.selected {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }

        #available_dates, #available_times {
            margin-top: 0.5rem;
        }

        .booking-card h2 {
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 1.5rem;
        }

        .form-select {
            border-radius: 0.5rem;
            border: 1px solid #e2e8f0;
            padding: 0.75rem 1rem;
            color: var(--secondary-color);
        }

        .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(37, 99, 235, 0.25);
        }

        textarea.form-control {
            min-height: 100px;
            resize: vertical;
        }

        .btn-outline-secondary {
            border-color: var(--secondary-color);
            color: var(--secondary-color);
            padding: 0.8rem 1.5rem;
            font-weight: 500;
            border-radius: 0.5rem;
            min-width: 200px;
        }

        .btn-outline-secondary:hover {
            background: var(--secondary-color);
            color: white;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand" href="#">HealthCare Portal</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="appointments.php">Appointments</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="doctors.php">Doctors</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="messages.php">Messages</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="profile.php">Profile</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../logout.php">Logout</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <div class="booking-card">
            <h2 class="mb-4">Book an Appointment</h2>
            
            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger">
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="" id="bookingForm">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="doctor_id" class="form-label">Select Doctor</label>
                        <select class="form-select" id="doctor_id" name="doctor_id" required>
                            <option value="">Choose a doctor...</option>
                            <?php foreach ($doctors as $doctor): ?>
                                <option value="<?php echo $doctor['id']; ?>">
                                    Dr. <?php echo htmlspecialchars($doctor['name']); ?> 
                                    (<?php echo htmlspecialchars($doctor['specialization']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="appointment_type" class="form-label">Appointment Type</label>
                        <select class="form-select" id="appointment_type" name="appointment_type" required>
                            <option value="">Select type...</option>
                            <option value="new">New Consultation</option>
                            <option value="follow_up">Follow-up Visit</option>
                        </select>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Select Date</label>
                    <div id="available_dates" class="d-flex flex-wrap gap-2">
                        <?php foreach ($available_dates as $date): ?>
                            <div class="date-slot" data-date="<?php echo $date; ?>">
                                <?php echo date('F j, Y', strtotime($date)); ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <input type="hidden" name="appointment_date" id="appointment_date" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Select Time</label>
                    <div id="available_times" class="d-flex flex-wrap gap-2">
                        <?php foreach ($available_times as $time): ?>
                            <div class="time-slot" data-time="<?php echo $time['start_time']; ?>">
                                <?php 
                                echo date('g:i A', strtotime($time['start_time']));
                                echo ' - ';
                                echo date('g:i A', strtotime($time['end_time']));
                                ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <input type="hidden" name="appointment_time" id="appointment_time" required>
                </div>

                <div class="mb-3">
                    <label for="reason" class="form-label">Reason for Visit</label>
                    <textarea class="form-control" id="reason" name="reason" rows="4" required></textarea>
                </div>

                <div class="d-flex gap-3">
                    <button type="submit" class="btn btn-primary">Proceed to Confirmation</button>
                    <a href="dashboard.php" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const doctorSelect = document.getElementById('doctor_id');
            const dateContainer = document.getElementById('available_dates');
            const timeContainer = document.getElementById('available_times');
            const appointmentDateInput = document.getElementById('appointment_date');
            const appointmentTimeInput = document.getElementById('appointment_time');

            // Set initial values from URL parameters
            const urlParams = new URLSearchParams(window.location.search);
            const doctorId = urlParams.get('doctor_id');
            const date = urlParams.get('date');

            if (doctorId) {
                doctorSelect.value = doctorId;
            }
            if (date) {
                appointmentDateInput.value = date;
                // Add selected class to the current date
                document.querySelectorAll('.date-slot').forEach(slot => {
                    if (slot.dataset.date === date) {
                        slot.classList.add('selected');
                    }
                });
            }

            // Handle doctor selection
            doctorSelect.addEventListener('change', function() {
                const doctorId = this.value;
                if (doctorId) {
                    // Clear previous selections
                    appointmentDateInput.value = '';
                    appointmentTimeInput.value = '';
                    document.querySelectorAll('.date-slot').forEach(slot => slot.classList.remove('selected'));
                    document.querySelectorAll('.time-slot').forEach(slot => slot.classList.remove('selected'));
                    
                    // Load available dates
                    fetch(`book-appointment.php?doctor_id=${doctorId}`)
                        .then(response => response.text())
                        .then(html => {
                            const parser = new DOMParser();
                            const doc = parser.parseFromString(html, 'text/html');
                            const newDateContainer = doc.getElementById('available_dates');
                            dateContainer.innerHTML = newDateContainer.innerHTML;
                        });
                }
            });

            // Handle date selection
            dateContainer.addEventListener('click', function(e) {
                if (e.target.classList.contains('date-slot')) {
                    // Remove selected class from all dates
                    document.querySelectorAll('.date-slot').forEach(slot => {
                        slot.classList.remove('selected');
                    });
                    
                    // Add selected class to clicked date
                    e.target.classList.add('selected');
                    
                    // Update hidden input
                    const selectedDate = e.target.dataset.date;
                    appointmentDateInput.value = selectedDate;
                    
                    // Clear previous time selection
                    appointmentTimeInput.value = '';
                    document.querySelectorAll('.time-slot').forEach(slot => slot.classList.remove('selected'));
                    
                    // Load available times for selected date
                    const doctorId = doctorSelect.value;
                    fetch(`book-appointment.php?doctor_id=${doctorId}&date=${selectedDate}`)
                        .then(response => response.text())
                        .then(html => {
                            const parser = new DOMParser();
                            const doc = parser.parseFromString(html, 'text/html');
                            const newTimeContainer = doc.getElementById('available_times');
                            timeContainer.innerHTML = newTimeContainer.innerHTML;
                        });
                }
            });

            // Handle time selection
            timeContainer.addEventListener('click', function(e) {
                if (e.target.classList.contains('time-slot')) {
                    // Remove selected class from all times
                    document.querySelectorAll('.time-slot').forEach(slot => {
                        slot.classList.remove('selected');
                    });
                    
                    // Add selected class to clicked time
                    e.target.classList.add('selected');
                    
                    // Update hidden input
                    appointmentTimeInput.value = e.target.dataset.time;
                }
            });

            // Form validation
            document.getElementById('bookingForm').addEventListener('submit', function(e) {
                if (!appointmentDateInput.value || !appointmentTimeInput.value) {
                    e.preventDefault();
                    alert('Please select both date and time for your appointment.');
                }
            });
        });
    </script>
</body>
</html> 