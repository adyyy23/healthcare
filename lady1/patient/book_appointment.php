<?php
session_start();
require_once '../config/database.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in and is patient
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header('Location: ../login.php');
    exit();
}

// Debug log
error_log("POST data: " . print_r($_POST, true));
error_log("Session user_id: " . $_SESSION['user_id']);

// Handle POST request
$success = false;
$error = '';
$appointment_details = null;
$location = '123 Main Street'; // Default location
$room_number = 'Room ' . rand(1, 10); // Random room number between 1 and 10

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['doctor_id'], $_POST['schedule_id'], $_POST['reason'], $_POST['full_name'], $_POST['appointment_type'], $_POST['service'])) {
    $doctor_id = $_POST['doctor_id'];
    $schedule_id = $_POST['schedule_id'];
    $reason = $_POST['reason'];
    $full_name = $_POST['full_name'];
    $appointment_type = $_POST['appointment_type'];
    $service = $_POST['service'];

    // Service price mapping
    $service_prices = [
        'General Consultation' => 500,
        'Pediatrics' => 600,
        'Cardiology' => 800,
        'Dermatology' => 1000,
        'Orthopedics' => 700,
        'Neurology' => 1500
    ];
    $service_price = $service_prices[$service] ?? 0;

    try {
        $pdo->beginTransaction();

        // Get doctor and schedule details
        $stmt = $pdo->prepare("
            SELECT d.*, u.name as doctor_name, ds.date, ds.start_time, ds.end_time, ds.status, u.id as user_id
            FROM doctors d 
            JOIN users u ON d.user_id = u.id 
            JOIN doctor_schedules ds ON ds.doctor_id = d.id 
            WHERE d.id = ? AND ds.id = ?
        ");
        $stmt->execute([$doctor_id, $schedule_id]);
        $appointment_details = $stmt->fetch();

        if (!$appointment_details) {
            throw new Exception("Invalid doctor or schedule selected.");
        }

        // Check if schedule is available
        if ($appointment_details['status'] !== 'available') {
            throw new Exception("This time slot is no longer available. Please select another time.");
        }

        // Get patient record with user details
        $stmt = $pdo->prepare("
            SELECT p.*, u.name 
            FROM patients p 
            JOIN users u ON p.user_id = u.id 
            WHERE p.user_id = ?
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $patient = $stmt->fetch();

        if (!$patient) {
            throw new Exception("Patient record not found.");
        }

        // Insert appointment
        $stmt = $pdo->prepare("
            INSERT INTO appointments (
                patient_id, doctor_id, appointment_date, appointment_time, 
                reason, status, created_at
            ) VALUES (?, ?, ?, ?, ?, 'pending', NOW())
        ");
        $stmt->execute([
            $patient['id'],
            $doctor_id,
            $appointment_details['date'],
            $appointment_details['start_time'],
            $reason
        ]);

        $appointment_id = $pdo->lastInsertId();

        // Update schedule status
        $stmt = $pdo->prepare("
            UPDATE doctor_schedules 
            SET status = 'booked' 
            WHERE id = ?
        ");
        $stmt->execute([$schedule_id]);

        // Create notification for doctor
        $stmt = $pdo->prepare("
            INSERT INTO notifications (
                user_id, message, type, related_id, created_at
            ) VALUES (?, ?, 'appointment', ?, NOW())
        ");
        $stmt->execute([
            $appointment_details['user_id'],
            "New appointment request from {$full_name} for {$appointment_details['date']} at {$appointment_details['start_time']}",
            $appointment_id
        ]);

        $pdo->commit();
        $success = true;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = $e->getMessage();
        error_log("Appointment booking error: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Appointment - HealthCare Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; background: #f8fafc; color: #0f172a; }
        .card { border-radius: 1.5rem; box-shadow: 0 2px 12px rgba(44,62,80,0.07); }
        .card-header { background: #4e73df; color: #fff; border-radius: 1.5rem 1.5rem 0 0; }
        .btn-primary { background: #4e73df; border: none; }
        .btn-primary:hover { background: #224abe; }
    </style>
</head>
<body>
    <div class="container mt-5 mb-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h3 class="mb-0">Book an Appointment</h3>
                    </div>
                    <div class="card-body">
                        <?php if ($success): ?>
                            <div class="alert alert-success">
                                <h4><i class="bi bi-check-circle-fill"></i> Appointment Request Submitted!</h4>
                                <hr>
                                <h5>Appointment Details:</h5>
                                <p class="card-text">
                                    <strong>Doctor:</strong> <?php echo htmlspecialchars($appointment_details['doctor_name']); ?><br>
                                    <strong>Date:</strong> <?php echo htmlspecialchars($appointment_details['date']); ?><br>
                                    <strong>Time:</strong> <?php echo htmlspecialchars($appointment_details['start_time'] . ' - ' . $appointment_details['end_time']); ?><br>
                                    <strong>Location:</strong> <?php echo htmlspecialchars($location); ?><br>
                                    <strong>Room:</strong> <?php echo htmlspecialchars($room_number); ?><br>
                                    <strong>Service:</strong> <?php echo htmlspecialchars($service); ?><br>
                                    <strong>Amount:</strong> ₱<?php echo number_format($service_price, 2); ?><br>
                                    <strong>Reason:</strong> <?php echo htmlspecialchars($reason); ?>
                                </p>
                                <p class="text-muted">
                                    <i class="bi bi-info-circle"></i>
                                    Please pay the amount on the actual day of your appointment or directly to your doctor in person.<br>
                                    Your appointment request has been sent to the doctor for approval.<br>
                                    You will be notified once the doctor reviews your request.
                                </p>
                                <a href="dashboard.php" class="btn btn-primary">Return to Dashboard</a>
                            </div>
                        <?php elseif ($error): ?>
                            <div class="alert alert-danger">
                                <?php echo $error; ?>
                            </div>
                        <?php endif; ?>
                        <?php if (!$success): ?>
                            <form method="post" action="book_appointment.php">
                                <div class="mb-3">
                                    <label class="form-label">Full Name</label>
                                    <input type="text" class="form-control" name="full_name" value="<?php echo htmlspecialchars($_SESSION['name'] ?? ''); ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Appointment Type</label>
                                    <select class="form-control" name="appointment_type" required>
                                        <option value="new">New Appointment</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Choose Service</label>
                                    <select class="form-control" name="service" required>
                                        <option value="">Select Service</option>
                                        <option value="General Consultation">General Consultation (₱500)</option>
                                        <option value="Pediatrics">Pediatrics (₱600)</option>
                                        <option value="Cardiology">Cardiology (₱800)</option>
                                        <option value="Dermatology">Dermatology (₱1,000)</option>
                                        <option value="Orthopedics">Orthopedics (₱700)</option>
                                        <option value="Neurology">Neurology (₱1,500)</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Doctor</label>
                                    <select class="form-control" name="doctor_id" id="doctor_id" required>
                                        <option value="">Select Doctor</option>
                                        <?php
                                        $stmt = $pdo->prepare("SELECT d.id, u.name FROM doctors d JOIN users u ON d.user_id = u.id WHERE d.id IN (SELECT doctor_id FROM doctor_schedules WHERE status = 'available')");
                                        $stmt->execute();
                                        $doctors = $stmt->fetchAll();
                                        foreach ($doctors as $doctor): ?>
                                            <option value="<?php echo $doctor['id']; ?>"><?php echo htmlspecialchars($doctor['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Appointment Date</label>
                                    <select class="form-control" name="appointment_date" id="appointment_date" required>
                                        <option value="">Select Date</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Appointment Time</label>
                                    <select class="form-control" name="schedule_id" id="appointment_time" required>
                                        <option value="">Select Time</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Reason for Visit</label>
                                    <textarea class="form-control" name="reason" rows="3" required></textarea>
                                </div>
                                <button type="submit" class="btn btn-primary w-100">Book Appointment</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    const doctorSelect = document.getElementById('doctor_id');
    const dateSelect = document.getElementById('appointment_date');
    const timeSelect = document.getElementById('appointment_time');
    let allSchedules = [];

    doctorSelect && doctorSelect.addEventListener('change', function() {
        const doctorId = this.value;
        console.log('Selected doctor ID:', doctorId);
        
        dateSelect.innerHTML = '<option value="">Loading...</option>';
        timeSelect.innerHTML = '<option value="">Select Time</option>';
        
        if (doctorId) {
            console.log('Fetching schedules for doctor:', doctorId);
            fetch('get_doctor_schedules.php?doctor_id=' + doctorId)
                .then(response => {
                    console.log('Response status:', response.status);
                    if (!response.ok) {
                        throw new Error('Network response was not ok: ' + response.status);
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('Received data:', data);
                    if (data.error) {
                        throw new Error(data.error);
                    }
                    allSchedules = data;
                    const dates = [...new Set(data.map(s => s.date))];
                    console.log('Available dates:', dates);
                    
                    let options = '<option value="">Select Date</option>';
                    dates.forEach(date => {
                        options += `<option value="${date}">${date}</option>`;
                    });
                    dateSelect.innerHTML = options;
                })
                .catch(error => {
                    console.error('Error:', error);
                    dateSelect.innerHTML = `<option value="">Error: ${error.message}</option>`;
                });
        } else {
            dateSelect.innerHTML = '<option value="">Select Date</option>';
            timeSelect.innerHTML = '<option value="">Select Time</option>';
        }
    });

    dateSelect && dateSelect.addEventListener('change', function() {
        const selectedDate = this.value;
        console.log('Selected date:', selectedDate);
        
        timeSelect.innerHTML = '<option value="">Loading...</option>';
        
        if (selectedDate) {
            const availableTimes = allSchedules.filter(schedule => schedule.date === selectedDate);
            console.log('Available times for date:', availableTimes);
            
            let options = '<option value="">Select Time</option>';
            availableTimes.forEach(schedule => {
                options += `<option value="${schedule.id}">${schedule.start_time} - ${schedule.end_time}</option>`;
            });
            timeSelect.innerHTML = options;
        } else {
            timeSelect.innerHTML = '<option value="">Select Time</option>';
        }
    });
    </script>
</body>
</html> 