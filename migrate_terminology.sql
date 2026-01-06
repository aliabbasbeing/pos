-- Migration script to update terminology in existing database
-- This script updates category names from 'neutralisation' to 'neutration'
-- and product form from 'Oral Solution' to 'Liquid Solution'
-- Run this script on your existing database: mysql -u root -p alfah_pos < migrate_terminology.sql

USE alfah_pos;

-- Update category terminology
UPDATE products 
SET category = 'neutration' 
WHERE category = 'neutralisation';

-- Update product form terminology
UPDATE products 
SET form = 'Liquid Solution' 
WHERE form = 'Oral Solution';

-- Verify the changes
SELECT 'Category Update Results:' AS '';
SELECT category, COUNT(*) as count 
FROM products 
GROUP BY category;

SELECT 'Form Update Results:' AS '';
SELECT form, COUNT(*) as count 
FROM products 
GROUP BY form;
