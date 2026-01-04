-- Alfah POS - init_db.sql
-- Run: mysql -u root -p < init_db.sql
CREATE DATABASE IF NOT EXISTS alfah_pos CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE alfah_pos;

-- Drop existing
DROP TABLE IF EXISTS order_items;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS customers;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS users;

-- products
CREATE TABLE products (
  id INT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(255) NOT NULL,
  category VARCHAR(100) NOT NULL,
  composition TEXT,
  unit VARCHAR(50),
  form VARCHAR(100),
  buy_price DECIMAL(10,2) DEFAULT 0,
  sell_price DECIMAL(10,2) DEFAULT 0,
  stock_quantity INT DEFAULT 0,
  min_stock INT DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- customers
CREATE TABLE customers (
  id INT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(255) NOT NULL,
  phone VARCHAR(20) NOT NULL UNIQUE,
  address TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- orders
CREATE TABLE orders (
  id INT PRIMARY KEY AUTO_INCREMENT,
  invoice_number VARCHAR(50) NOT NULL UNIQUE,
  customer_id INT,
  total_amount DECIMAL(10,2) NOT NULL,
  payment_method VARCHAR(50),
  order_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  status VARCHAR(20) DEFAULT 'completed',
  FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- order_items
CREATE TABLE order_items (
  id INT PRIMARY KEY AUTO_INCREMENT,
  order_id INT NOT NULL,
  product_id INT NOT NULL,
  quantity INT NOT NULL,
  unit_price DECIMAL(10,2) NOT NULL,
  total_price DECIMAL(10,2) NOT NULL,
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- users
CREATE TABLE users (
  id INT PRIMARY KEY AUTO_INCREMENT,
  username VARCHAR(100) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  role VARCHAR(50) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Sample products (20 total: 13 antibiotics, 7 neutralisation)
INSERT INTO products (name, category, composition, unit, form, buy_price, sell_price, stock_quantity, min_stock) VALUES
('Oxytetracycline 100g', 'antibiotics', 'Oxytetracycline 200mg/g', '100g', 'Water Soluble Powder', 150.00, 220.00, 120, 20),
('Enrofloxacin 100ml', 'antibiotics', 'Enrofloxacin 50mg/ml', '100ml', 'Oral Solution', 200.00, 280.00, 80, 15),
('Amoxicillin 100g', 'antibiotics', 'Amoxicillin trihydrate 250mg/g', '100g', 'Water Soluble Powder', 120.00, 180.00, 55, 10),
('Tylosin 100g', 'antibiotics', 'Tylosin 100mg/g', '100g', 'Water Soluble Powder', 140.00, 210.00, 40, 10),
('Doxycycline 100g', 'antibiotics', 'Doxycycline 200mg/g', '100g', 'Water Soluble Powder', 160.00, 230.00, 30, 10),
('Neomycin 100g', 'antibiotics', 'Neomycin 250mg/g', '100g', 'Water Soluble Powder', 110.00, 170.00, 60, 10),
('Ciprofloxacin 100ml', 'antibiotics', 'Ciprofloxacin 50mg/ml', '100ml', 'Oral Solution', 220.00, 300.00, 25, 8),
('Lincomycin 100g', 'antibiotics', 'Lincomycin 150mg/g', '100g', 'Water Soluble Powder', 130.00, 195.00, 75, 12),
('Florfenicol 100g', 'antibiotics', 'Florfenicol 300mg/g', '100g', 'Water Soluble Powder', 240.00, 330.00, 18, 10),
('Spectinomycin 100g', 'antibiotics', 'Spectinomycin 200mg/g', '100g', 'Water Soluble Powder', 190.00, 260.00, 22, 8),
('Sulfadimidine 100g', 'antibiotics', 'Sulfadimidine 300mg/g', '100g', 'Water Soluble Powder', 95.00, 150.00, 65, 12),
('Trimethoprim 100g', 'antibiotics', 'Trimethoprim 50mg/g', '100g', 'Water Soluble Powder', 100.00, 155.00, 48, 10),
('Gentamicin 100ml', 'antibiotics', 'Gentamicin 80mg/ml', '100ml', 'Oral Solution', 210.00, 295.00, 35, 8),

('Neutraliser A 100ml', 'neutralisation', 'Neutraliser formula alpha', '100ml', 'Oral Solution', 50.00, 80.00, 130, 20),
('Neutraliser B 100g', 'neutralisation', 'Neutraliser beta powder', '100g', 'Water Soluble Powder', 60.00, 95.00, 90, 20),
('pH Balancer 100ml', 'neutralisation', 'pH balancing agents', '100ml', 'Oral Solution', 70.00, 110.00, 75, 15),
('Detox Mix 100g', 'neutralisation', 'Detoxifying powders', '100g', 'Water Soluble Powder', 85.00, 130.00, 50, 10),
('Cleaner C 100ml', 'neutralisation', 'Sanitizing formula', '100ml', 'Oral Solution', 40.00, 65.00, 160, 30),
('Electrolyte 100g', 'neutralisation', 'Electrolyte powder', '100g', 'Water Soluble Powder', 55.00, 85.00, 140, 25),
('Gut Health 100g', 'neutralisation', 'Probiotic mix', '100g', 'Water Soluble Powder', 120.00, 175.00, 45, 12);

-- Sample admin user
-- NOTE: Replace the password hash below if you regenerate it. The sample password is: Admin@123
INSERT INTO users (username, password, role) VALUES
('admin', '$2y$10$H8mG1xY5s9e7FQ2uV0a9Be6uN8p6wYtGx3k5Lz1Qp9H2R7s4T6u1a', 'admin');

-- Optional: sample customer
INSERT INTO customers (name, phone, address) VALUES
('Walk-in Farmer', '03001234567', 'Lahore');