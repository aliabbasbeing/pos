-- Migration script to add advance payment functionality
-- Run this script on existing databases: mysql -u root -p alfah_pos < migrate_advance_payments.sql

USE alfah_pos;

-- Add advance_amount and remaining_amount columns to orders table
ALTER TABLE orders 
ADD COLUMN advance_amount DECIMAL(10,2) DEFAULT 0 AFTER total_amount,
ADD COLUMN remaining_amount DECIMAL(10,2) DEFAULT 0 AFTER advance_amount;

-- For existing orders, set remaining_amount to 0 (assuming they were fully paid)
UPDATE orders 
SET advance_amount = 0, remaining_amount = 0 
WHERE advance_amount IS NULL OR remaining_amount IS NULL;

-- Verify the changes
SELECT 'Migration completed successfully!' AS status;
SELECT 'Sample orders with new columns:' AS '';
SELECT id, invoice_number, total_amount, advance_amount, remaining_amount 
FROM orders 
LIMIT 5;
