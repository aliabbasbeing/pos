-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Jan 06, 2026 at 02:13 PM
-- Server version: 11.4.9-MariaDB
-- PHP Version: 8.4.11

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `beastsmm_pos`
--

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `address` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `customers`
--

INSERT INTO `customers` (`id`, `name`, `phone`, `address`, `created_at`) VALUES
(1, 'RAZA KHAN', '0333333333', 'ISB', '2025-10-28 17:11:26'),
(2, 'Abdullah', '03000612428', 'GUL', '2026-01-03 15:12:45'),
(3, 'mazhar', '1111111111111', 'lhr', '2026-01-03 15:18:36'),
(4, 'mubeen khan', '123000000', 'lhr', '2026-01-03 15:46:59'),
(5, 'yasen bhatti', '03002763092', 'burewal.vehari', '2026-01-04 13:59:34'),
(6, 'Dr saleem Raza', '0300', 'Faslabad', '2026-01-04 16:43:12'),
(7, 'Dr saleem Raza', '03000000', 'Faslabad', '2026-01-04 16:44:35'),
(8, 'khansa', '03294642016', 'lhr', '2026-01-05 09:20:08'),
(9, 'Dr.saleem Raza', '03007672300', 'Fsd zsm all punjab', '2026-01-06 08:08:57');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `invoice_number` varchar(50) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `advance_amount` decimal(10,2) DEFAULT 0.00,
  `remaining_amount` decimal(10,2) DEFAULT 0.00,
  `payment_method` varchar(50) DEFAULT NULL,
  `order_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` varchar(20) DEFAULT 'completed',
  `has_return` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `invoice_number`, `customer_id`, `total_amount`, `advance_amount`, `remaining_amount`, `payment_method`, `order_date`, `status`, `has_return`) VALUES
(1, 'INV-20251028-0001', NULL, 0.00, 0.00, 0.00, 'cash', '2025-10-28 16:21:03', 'completed', 0),
(2, 'INV-20260103-0001', 2, 13000.00, 0.00, 0.00, 'cash', '2026-01-03 15:12:45', 'completed', 0),
(3, 'INV-20260103-0002', 1, 35000.00, 0.00, 0.00, 'cash', '2026-01-03 15:14:39', 'completed', 0),
(4, 'INV-20260103-0003', 3, 95000.00, 0.00, 0.00, 'cash', '2026-01-03 15:18:36', 'completed', 0),
(5, 'INV-20260103-0004', 4, 0.00, 0.00, 0.00, 'bank_transfer', '2026-01-03 15:46:59', 'completed', 0),
(6, 'INV-20260103-0005', 1, 142500.00, 0.00, 0.00, 'card', '2026-01-03 15:52:15', 'completed', 0),
(7, 'INV-20260103-0006', 2, 42500.00, 0.00, 0.00, 'cash', '2026-01-03 15:55:55', 'completed', 0),
(8, 'INV-20260103-0007', 2, 95000.00, 0.00, 0.00, 'bank_transfer', '2026-01-03 16:12:19', 'completed', 0),
(9, 'INV-20260104-0001', 5, 42000.00, 0.00, 0.00, 'cash', '2026-01-04 13:59:34', 'completed', 0),
(10, 'INV-20260104-0002', 3, 54000.00, 0.00, 0.00, 'cash', '2026-01-04 14:17:35', 'completed', 0),
(11, 'INV-20260104-0003', 7, 17500.00, 0.00, 0.00, 'cash', '2026-01-04 16:45:05', 'completed', 0),
(12, 'INV-20260105-0001', 8, 103500.00, 0.00, 0.00, 'bank_transfer', '2026-01-05 09:20:08', 'completed', 0),
(13, 'INV-20260105-0002', 8, 27000.00, 0.00, 0.00, 'bank_transfer', '2026-01-05 09:26:06', 'completed', 0),
(14, 'INV-20260105-0003', 2, 22500.00, 0.00, 0.00, 'cash', '2026-01-05 12:33:47', 'completed', 0),
(15, 'INV-20260105-0004', NULL, 47500.00, 0.00, 0.00, 'bank_transfer', '2026-01-05 12:39:09', 'completed', 0),
(16, 'INV-20260106-0001', 2, 162500.00, 0.00, 0.00, 'cash', '2026-01-06 07:33:53', 'completed', 0),
(17, 'INV-20260106-0002', 1, 27000.00, 0.00, 0.00, 'cash', '2026-01-06 07:41:18', 'completed', 0),
(18, 'INV-20260106-0003', 9, 486000.00, 0.00, 0.00, 'card', '2026-01-06 08:08:57', 'completed', 0),
(19, 'INV-20260106-0004', NULL, 4500.00, 4000.00, 500.00, 'cash', '2026-01-06 09:08:24', 'completed', 0);

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `total_price` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `quantity`, `unit_price`, `total_price`) VALUES
(1, 1, 11, 1, 0.00, 0.00),
(2, 2, 11, 1, 9500.00, 9500.00),
(3, 2, 5, 1, 3500.00, 3500.00),
(4, 3, 5, 10, 3500.00, 35000.00),
(5, 4, 11, 10, 9500.00, 95000.00),
(6, 5, 17, 5, 0.00, 0.00),
(7, 6, 11, 15, 9500.00, 142500.00),
(8, 7, 4, 5, 8500.00, 42500.00),
(9, 8, 11, 10, 9500.00, 95000.00),
(10, 9, 5, 12, 3500.00, 42000.00),
(11, 10, 17, 12, 4500.00, 54000.00),
(12, 11, 5, 5, 3500.00, 17500.00),
(13, 12, 11, 5, 13500.00, 67500.00),
(14, 12, 7, 8, 4500.00, 36000.00),
(15, 13, 11, 2, 13500.00, 27000.00),
(16, 14, 18, 5, 4500.00, 22500.00),
(17, 15, 13, 5, 9500.00, 47500.00),
(18, 16, 11, 5, 13500.00, 67500.00),
(19, 16, 13, 10, 9500.00, 95000.00),
(20, 17, 11, 2, 13500.00, 27000.00),
(21, 18, 4, 12, 11000.00, 132000.00),
(22, 18, 8, 12, 10500.00, 126000.00),
(23, 18, 1, 12, 9500.00, 114000.00),
(24, 18, 9, 12, 9500.00, 114000.00),
(25, 19, 7, 1, 4500.00, 4500.00);

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `category` varchar(100) NOT NULL,
  `composition` text DEFAULT NULL,
  `unit` varchar(50) DEFAULT NULL,
  `form` varchar(100) DEFAULT NULL,
  `buy_price` decimal(10,2) DEFAULT 0.00,
  `sell_price` decimal(10,2) DEFAULT 0.00,
  `stock_quantity` int(11) DEFAULT 0,
  `min_stock` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `name`, `category`, `composition`, `unit`, `form`, `buy_price`, `sell_price`, `stock_quantity`, `min_stock`, `created_at`) VALUES
(1, 'DOXIN AVI 30%', 'antibiotics', 'Each 100g contains: Doxycycline Hyclate .....20g, Tylosin Tartrate ........10g', '1kg', 'Water Soluble Powder', 6500.00, 9500.00, 8, 5, '2025-10-27 14:38:57'),
(2, 'DOXIN AVI 50%', 'antibiotics', 'Each 100g contains: Doxycycline Hyclate ......50g', '1kg', 'Water Soluble Powder', 11300.00, 15500.00, 50, 5, '2025-10-27 14:38:57'),
(3, 'DOXIN AVI 60%', 'antibiotics', 'Each 100g contains: Doxycycline Hyclate .....40g, Tylosin Tartrate ........20g', '1kg', 'Water Soluble Powder', 13000.00, 18500.00, 50, 5, '2025-10-27 14:38:57'),
(4, 'AMOXIN AVI 50%', 'antibiotics', 'Each 100g contains: Amoxicillin Trihydrate ........50g', '1kg', 'Water Soluble Powder', 7500.00, 11000.00, 8, 5, '2025-10-27 14:38:57'),
(5, 'AMOXIN AVI 20%', 'antibiotics', 'Each 100g contains: Amoxicillin as Trihydrate .....20g', '1kg', 'Water Soluble Powder', 2500.00, 3500.00, 3, 5, '2025-10-27 14:38:57'),
(6, 'AMPROXIN AVI 70%', 'antibiotics', 'Each 100g contains: Amprolium HCl (BP) ......70g', '1kg', 'Water Soluble Powder', 9500.00, 13500.00, 20, 5, '2025-10-27 14:38:57'),
(7, 'AMPROXIN AVI 20%', 'antibiotics', 'Each 100g contains: Amprolium HCl (BP) ........20g', '1kg', 'Water Soluble Powder', 3500.00, 4500.00, 11, 5, '2025-10-27 14:38:57'),
(8, 'OXSIN AVI 50%', 'antibiotics', 'Each 100g contains: Oxytetracycline HCL .......50g', '100g', 'Water Soluble Powder', 7500.00, 10500.00, 38, 5, '2025-10-27 14:38:57'),
(9, 'COLIS AVI 50%', 'antibiotics', 'Each 100g contains: Colistin Sulphate (BP) ....50g', '1.Liter', 'Water Soluble Powder', 6500.00, 9500.00, 8, 5, '2025-10-27 14:38:57'),
(10, 'AVI NEO 72%', 'antibiotics', 'Each 100g contains: Neomycin Sulphate (B.P) ......72g', '1kg', 'Water Soluble Powder', 5300.00, 7500.00, 20, 5, '2025-10-27 14:38:57'),
(11, 'AMENTAN AVI 80%', 'antibiotics', 'Each 100g contains: Amantadine HCI .........80g', '1kg', 'Water Soluble Powder', 9500.00, 13500.00, 6, 5, '2025-10-27 14:38:57'),
(12, 'LYSO AVI', 'neutration', 'Each 100g contains: Lysozyme ........2,500,000 mg, Vitamin E ........40,000 mg, Zinc .............20,000 mg', '1kg', 'Water Soluble Powder', 6800.00, 9700.00, 50, 5, '2025-10-27 14:38:57'),
(13, 'AVI FLOR 20%', 'antibiotics', 'Each 100ml contains: Florenicol .........20ml', '1.Liter', 'Liquid Solution', 6500.00, 9500.00, 5, 5, '2025-10-27 14:38:57'),
(14, 'FRUSA AVI', 'neutration', 'Each 100ml contains: Frusemide ...........4 mg, Methenamin .........20 mg, Cal-D-Pantothenate ...15 mg, Ammonium Chloride ....5 mg, Vitamin B1 .........10 mg, Vitamin B2 ..........5 mg, Vitamin K3 ..........5 mg', '1kg', 'Water Soluble Powder', 3200.00, 4500.00, 100, 5, '2025-10-27 14:38:57'),
(15, 'AVI SELL 30%', 'neutration', 'Each 100ml contains: Vitamin E ..........300 mg, Selenium .........0.95 mg', '1.Liter', 'Liquid Solution', 4500.00, 650.00, 20, 5, '2025-10-27 14:38:57'),
(16, 'AVI FLOX 20%', 'neutration', 'Each 100ml contains: Enrofloxacin HCI ......20ml', '1.Liter', 'Liquid Solution', 4100.00, 5500.00, 20, 5, '2025-10-27 14:38:57'),
(17, 'AVI DECK C', 'neutration', 'Each 100g contains: Vitamin A ...........50,000,000 IU, Vitamin D3 .........20,000,000 IU, Vitamin E .............50,000 mg, Vitamin K ............50,000 mg, Vitamin C ...........10,000 mg', '1kg', 'Water Soluble Powder', 3500.00, 4500.00, 8, 5, '2025-10-27 14:38:57'),
(18, 'AVI LIVON LIQUID', 'neutration', 'Each 100ml contains: Silymarin .........400,000 mg, Sorbitol ..........300,000 mg, Sodium Chloride ...120,000 mg, Sodium Bicarbonate ...50,000 mg, Potassium Chloride ...40,000 mg, Vitamin C ..........5,000 mg, Lysine ............5,000 mg, Methionine ........9,000 mg, Glycine ...........8,000 mg', '1.Liter', 'Liquid Solution', 3500.00, 4500.00, 15, 5, '2025-10-27 14:38:57'),
(19, 'BRONCO AVI', 'neutration', 'Each 100ml contains: Menthol ................40,000 mg, Oil Allium Satira ......15,000 mg, Oil Emlira Officinali ..10,000 mg, Extract Sedavin ........20,000 mg, Ammonium Chloride ......10,000 mg, Sorbitol ...............25,000 mg', '1.Liter', 'Liquid Solution', 4000.00, 5500.00, 20, 5, '2025-10-27 14:38:57'),
(20, 'LINCO AVI 4.4%', 'antibiotics', 'Each 100g contains: Lincomycin Hydrochloride ......4.4g', '25kg', 'Liquid Solution', 21000.00, 28500.00, 100, 5, '2025-10-27 14:38:57'),
(21, 'Avi tox', 'neutration', '', '25kg', 'Liquid Solution', 11500.00, 16500.00, 200, 5, '2026-01-04 16:38:28');

-- --------------------------------------------------------

--
-- Table structure for table `returns`
--

CREATE TABLE `returns` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `return_number` varchar(50) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `return_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `total_refund` decimal(10,2) NOT NULL,
  `refund_method` varchar(50) NOT NULL,
  `reason` text DEFAULT NULL,
  `status` varchar(20) DEFAULT 'pending',
  `processed_by` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `return_items`
--

CREATE TABLE `return_items` (
  `id` int(11) NOT NULL,
  `return_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `total_price` decimal(10,2) NOT NULL,
  `condition_status` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `role`, `created_at`) VALUES
(1, 'admin', '$2y$10$uoHEdg8/zGAOqU25FVwgqeRkWNXm9rz7cVWIcw6NAgdGRy6dxxkzy', 'admin', '2025-10-27 15:53:44');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `phone` (`phone`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `invoice_number` (`invoice_number`),
  ADD KEY `customer_id` (`customer_id`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `returns`
--
ALTER TABLE `returns`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `return_number` (`return_number`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `processed_by` (`processed_by`),
  ADD KEY `idx_return_date` (`return_date`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `return_items`
--
ALTER TABLE `return_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `return_id` (`return_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `returns`
--
ALTER TABLE `returns`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `return_items`
--
ALTER TABLE `return_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`);

--
-- Constraints for table `returns`
--
ALTER TABLE `returns`
  ADD CONSTRAINT `returns_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`),
  ADD CONSTRAINT `returns_ibfk_2` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `returns_ibfk_3` FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `return_items`
--
ALTER TABLE `return_items`
  ADD CONSTRAINT `return_items_ibfk_1` FOREIGN KEY (`return_id`) REFERENCES `returns` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `return_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
