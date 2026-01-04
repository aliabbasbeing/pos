````markdown
# Alfah POS (Core PHP)

Alfah Tech International — Advanced Poultry Health Solutions  
A simple Point of Sale (POS) system built with pure Core PHP, Tailwind CSS, Chart.js, jQuery and FPDF/TCPDF for invoice generation.

This repository is intentionally framework-free and uses PDO (prepared statements), password_hash, CSRF protection and sessions.

## Requirements

- PHP 7.4+
- MySQL / MariaDB
- Composer (optional for TCPDF, or you can install FPDF manually)
- Web server (Apache / Nginx)

## Installation

1. Place the `alfah-pos/` folder inside your web root (or set your DocumentRoot to this folder).
2. Create a database user & database or use root for local testing.

3. Edit `config.php` to update database credentials:
   - DB_HOST, DB_NAME (default `alfah_pos`), DB_USER, DB_PASS

4. Import the database:
   - From terminal:
     mysql -u youruser -p < init_db.sql

   The `init_db.sql` includes sample data and an admin user with username: `admin` and password: `Admin@123` (hashed in the SQL). If you prefer to re-generate the password hash, run:
   ```php
   <?php
   echo password_hash('Admin@123', PASSWORD_DEFAULT);