-- Add new columns to patients table
ALTER TABLE patients
ADD COLUMN emergency_contact VARCHAR(20) AFTER phone_number,
ADD COLUMN blood_type VARCHAR(5) AFTER emergency_contact,
ADD COLUMN current_medications TEXT AFTER medical_history,
ADD COLUMN allergies TEXT AFTER current_medications,
ADD COLUMN surgical_history TEXT AFTER allergies,
ADD COLUMN reason_for_visit TEXT AFTER surgical_history,
ADD COLUMN symptoms TEXT AFTER reason_for_visit; 