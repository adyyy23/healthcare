CREATE TABLE IF NOT EXISTS services (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    icon VARCHAR(50),
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert some sample services
INSERT INTO services (name, description, icon, is_active) VALUES
('General Consultation', 'Basic medical consultation with a general practitioner', 'bi-clipboard-pulse', 1),
('Specialist Consultation', 'Consultation with specialized medical professionals', 'bi-person-badge', 1),
('Laboratory Tests', 'Various diagnostic and blood tests', 'bi-vial', 1),
('Medical Imaging', 'X-ray, MRI, and other imaging services', 'bi-camera', 1),
('Vaccination', 'Immunization and vaccination services', 'bi-shield-check', 1),
('Emergency Care', '24/7 emergency medical services', 'bi-ambulance', 1); 