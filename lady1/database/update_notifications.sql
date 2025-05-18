-- Create notifications table if it doesn't exist
CREATE TABLE IF NOT EXISTS notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    message TEXT NOT NULL,
    type VARCHAR(50) NOT NULL,
    related_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- If table exists, add missing columns
ALTER TABLE notifications
ADD COLUMN IF NOT EXISTS type VARCHAR(50) NOT NULL AFTER message,
ADD COLUMN IF NOT EXISTS related_id INT AFTER type,
MODIFY COLUMN message TEXT NOT NULL,
MODIFY COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP; 