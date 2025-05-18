-- Add specialization column to doctors table
ALTER TABLE doctors
ADD COLUMN specialization VARCHAR(100) DEFAULT 'General Medicine' AFTER license_number;

-- Add new columns to appointments table
ALTER TABLE appointments
ADD COLUMN location VARCHAR(255) DEFAULT '123 Main Street',
ADD COLUMN room_number VARCHAR(10) DEFAULT 'Room 1';

-- Add notes column to appointments table
ALTER TABLE appointments
ADD COLUMN notes TEXT DEFAULT NULL AFTER status;

-- Add updated_at column if it doesn't exist
ALTER TABLE appointments
ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- Add title column to notifications table
ALTER TABLE notifications
ADD COLUMN title VARCHAR(255) DEFAULT NULL AFTER user_id;

-- Add specialization fees table
CREATE TABLE IF NOT EXISTS specialization_fees (
    id INT PRIMARY KEY AUTO_INCREMENT,
    specialization VARCHAR(100) NOT NULL,
    fee DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert default specialization fees
INSERT INTO specialization_fees (specialization, fee) VALUES
('ENT', 700.00),
('Cardiology', 1000.00),
('Dermatology', 800.00),
('Pediatrics', 600.00),
('General Medicine', 500.00)
ON DUPLICATE KEY UPDATE fee = VALUES(fee);

-- Add reschedule_request column to appointments
ALTER TABLE appointments
ADD COLUMN reschedule_request TEXT DEFAULT NULL AFTER notes,
ADD COLUMN reschedule_status ENUM('none', 'pending', 'approved', 'rejected') DEFAULT 'none' AFTER reschedule_request; 