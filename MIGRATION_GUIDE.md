# Database Migration Guide

## Overview
This document provides instructions for applying the terminology updates to your existing POS database.

## Changes Applied
1. **Company Name**: Updated from "Alfah Tech International" to "Alpah Tech Int SMC PVT LTD"
2. **Category Name**: Changed from "neutralisation" to "neutration" 
3. **Product Form**: Changed from "Oral Solution" to "Liquid Solution"

## Migration Instructions

### For Existing Database

If you have an existing database with data, run the migration script:

```bash
mysql -u root -p alfah_pos < migrate_terminology.sql
```

This will:
- Update all products with category 'neutralisation' to 'neutration'
- Update all products with form 'Oral Solution' to 'Liquid Solution'
- Display a summary of the changes

### For New Database Setup

If you're setting up a fresh database, simply run the init script:

```bash
mysql -u root -p < init_db.sql
```

The init script already includes the updated terminology.

## Verification

After running the migration, verify the changes:

```sql
USE alfah_pos;

-- Check category distribution
SELECT category, COUNT(*) as count 
FROM products 
GROUP BY category;

-- Check form distribution  
SELECT form, COUNT(*) as count 
FROM products 
GROUP BY form;
```

Expected results:
- No products should have category 'neutralisation'
- No products should have form 'Oral Solution'
- Products should now use 'neutration' and 'Liquid Solution'

## Rollback (if needed)

If you need to revert the changes:

```sql
USE alfah_pos;

-- Revert category
UPDATE products 
SET category = 'neutralisation' 
WHERE category = 'neutration';

-- Revert form
UPDATE products 
SET form = 'Oral Solution' 
WHERE form = 'Liquid Solution';
```

## Notes

- The migration script is idempotent - it's safe to run multiple times
- No data is deleted, only terminology is updated
- The company name change is applied in the application code (config.php)
- Invoice layout changes are applied in the invoice.php template
