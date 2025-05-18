-- Drop specialization_fees table
DROP TABLE IF EXISTS specialization_fees;

-- Remove specialization column from doctors table
ALTER TABLE doctors
DROP COLUMN specialization; 