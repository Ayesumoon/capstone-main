-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 19, 2025 at 12:57 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `dbms`
--

-- --------------------------------------------------------

--
-- Table structure for table `adminusers`
--

CREATE TABLE `adminusers` (
  `admin_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `admin_email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role_id` int(11) NOT NULL,
  `status_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `first_name` varchar(50) DEFAULT '',
  `last_name` varchar(50) DEFAULT '',
  `last_logged_in` datetime DEFAULT NULL,
  `last_logged_out` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `adminusers`
--

INSERT INTO `adminusers` (`admin_id`, `username`, `admin_email`, `password_hash`, `role_id`, `status_id`, `created_at`, `first_name`, `last_name`, `last_logged_in`, `last_logged_out`) VALUES
(1, 'Ayesu', 'nicholedeguzman@yahoo.com', '$2y$10$ENseQNg1WhLbfCjBEi3P4ezFAjuxciD8TWR/KoKqSUAKRJAR8HiKu', 2, 1, '2025-03-30 04:35:12', 'Nichole', 'De Guzman', '2025-08-19 18:07:49', NULL),
(2, 'admin1', 'johndoe@email.com', '$2y$10$QJ9ELWmPTRfTDLE7BB2s1eoSioYsT2bvwuprqmQW9tQZlzAq1MNkm', 1, 1, '2025-04-13 22:22:07', 'John', 'Doe', '2025-04-14 18:27:15', '2025-04-14 18:46:37');

-- --------------------------------------------------------

--
-- Table structure for table `carts`
--

CREATE TABLE `carts` (
  `cart_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `cart_status` enum('active','inactive') DEFAULT 'active',
  `product_id` int(11) DEFAULT NULL,
  `quantity` int(11) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `carts`
--

INSERT INTO `carts` (`cart_id`, `customer_id`, `created_at`, `cart_status`, `product_id`, `quantity`) VALUES
(1, 1, '2025-05-05 16:25:46', '', 4, 1),
(2, 1, '2025-05-05 16:56:11', '', 3, 10),
(3, 1, '2025-05-05 17:06:38', '', 2, 13),
(4, 1, '2025-05-06 01:48:01', '', 4, 6),
(5, 1, '2025-05-06 01:57:21', '', 1, 2),
(6, 1, '2025-05-06 01:57:39', 'active', 2, 4),
(7, 1, '2025-05-06 02:02:59', '', 4, 1);

-- --------------------------------------------------------

--
-- Table structure for table `cart_items`
--

CREATE TABLE `cart_items` (
  `cart_item_id` int(11) NOT NULL,
  `cart_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) DEFAULT 1,
  `added_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `category_id` int(11) NOT NULL,
  `category_code` varchar(10) DEFAULT NULL,
  `category_name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`category_id`, `category_code`, `category_name`) VALUES
(1, '001', 'Blouse'),
(2, '002', 'Dress'),
(3, '003', 'Shorts'),
(4, '004', 'Skirt'),
(5, '005', 'Trouser'),
(6, '006', 'Pants'),
(7, '007', 'Coordinates'),
(8, '008', 'Shoes'),
(9, '009', 'Perfume'),
(10, '0010', 'Test1'),
(11, '0011', 'Bags'),
(12, '012', 'Test 2');

-- --------------------------------------------------------

--
-- Table structure for table `colors`
--

CREATE TABLE `colors` (
  `color_id` int(11) NOT NULL,
  `color` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `colors`
--

INSERT INTO `colors` (`color_id`, `color`) VALUES
(1, 'Pink'),
(2, 'Red'),
(3, 'White'),
(4, 'Blue'),
(5, 'Black'),
(6, 'Green'),
(7, 'Yellow');

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `customer_id` int(11) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `address` text DEFAULT NULL,
  `status_id` int(11) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `profile_picture` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `customers`
--

INSERT INTO `customers` (`customer_id`, `first_name`, `last_name`, `email`, `phone`, `password_hash`, `address`, `status_id`, `created_at`, `profile_picture`) VALUES
(1, 'Jane', 'Smith', 'janesmith@email.com', '0987654321', '$2y$10$wwJN5JbK5dyz5VJH7jcgkuaII8ytWuWRJ2YpVgiIoR6Px6qd9IIBK', 'Manila', 1, '2025-04-28 13:15:37', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `order_id` int(11) NOT NULL,
  `admin_id` int(10) UNSIGNED DEFAULT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `cash_given` decimal(10,2) DEFAULT NULL,
  `changes` decimal(10,2) NOT NULL,
  `order_status_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `product_id` int(11) NOT NULL,
  `payment_method_id` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`order_id`, `admin_id`, `customer_id`, `total_amount`, `cash_given`, `changes`, `order_status_id`, `created_at`, `product_id`, `payment_method_id`) VALUES
(5, 1, NULL, 350.00, 500.00, 150.00, 0, '2025-08-19 10:42:32', 3, 3),
(6, 1, NULL, 350.00, 500.00, 150.00, 0, '2025-08-19 10:43:48', 3, 3),
(7, 1, NULL, 350.00, 500.00, 150.00, 0, '2025-08-19 10:45:37', 3, 3);

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `stock_id` int(11) NOT NULL,
  `qty` int(11) NOT NULL,
  `price` double NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `stock_id`, `qty`, `price`) VALUES
(2, 5, 1, 1, 350),
(3, 6, 1, 1, 350),
(4, 7, 1, 1, 350);

-- --------------------------------------------------------

--
-- Table structure for table `order_status`
--

CREATE TABLE `order_status` (
  `order_status_id` int(11) NOT NULL,
  `order_status_name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_status`
--

INSERT INTO `order_status` (`order_status_id`, `order_status_name`) VALUES
(3, 'Cancelled'),
(0, 'Completed'),
(1, 'Pending'),
(4, 'Refunded'),
(2, 'Shipped');

-- --------------------------------------------------------

--
-- Table structure for table `payment_methods`
--

CREATE TABLE `payment_methods` (
  `payment_method_id` int(10) UNSIGNED NOT NULL,
  `payment_method_name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payment_methods`
--

INSERT INTO `payment_methods` (`payment_method_id`, `payment_method_name`) VALUES
(2, 'Card'),
(3, 'Cash'),
(1, 'Gcash'),
(4, 'PayMaya'),
(5, 'PayPal');

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `product_id` int(11) NOT NULL,
  `product_name` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `price_id` int(11) DEFAULT NULL,
  `stocks` int(11) DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `image_url` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `supplier_id` int(11) DEFAULT NULL,
  `supplier_price` decimal(10,2) NOT NULL,
  `revenue` decimal(10,2) GENERATED ALWAYS AS (`price_id` - `supplier_price`) STORED,
  `sizes` varchar(255) DEFAULT NULL,
  `colors` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`product_id`, `product_name`, `description`, `price_id`, `stocks`, `category_id`, `image_url`, `created_at`, `supplier_id`, `supplier_price`, `sizes`, `colors`) VALUES
(1, '001', 'Sizes: S, M | Colors: Pink', 380, 20, 2, 'uploads/dress1.jpg', '2025-04-30 14:14:42', 1, 240.00, NULL, NULL),
(2, '002', 'Sizes: S, L | Colors: Red, White, Blue', 500, 20, 1, 'uploads/products/686e07e6b1848_blouse3.jpg,uploads/products/686e07e6b1b3b_blouse2.jpg,uploads/products/686e07e6b1d2c_blouse1.jpg', '2025-05-01 13:25:54', 2, 250.00, NULL, NULL),
(3, '6776', 'Sizes: XS, S, M, L | Colors: Black, White, Pink, Green, Yellow', 350, 17, 7, 'uploads/products/686ca8149794f_6776.jpg', '2025-05-01 13:50:35', 2, 3000.00, NULL, NULL),
(4, '013', 'Kate Spade', 550, 40, 11, 'uploads/katespade.jpg', '2025-05-05 14:11:33', 1, 450.00, NULL, NULL),
(5, '005', 'Sizes: XS, S, M | Colors: Black, White', 200, 30, 3, 'uploads/products/686ca7d4f0ecc_short1.jpg', '2025-07-08 05:04:36', 1, 150.00, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `role_id` int(11) NOT NULL,
  `role_name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`role_id`, `role_name`) VALUES
(3, 'Manager'),
(1, 'Owner'),
(2, 'Staff');

-- --------------------------------------------------------

--
-- Table structure for table `sizes`
--

CREATE TABLE `sizes` (
  `size_id` int(11) NOT NULL,
  `size` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sizes`
--

INSERT INTO `sizes` (`size_id`, `size`) VALUES
(1, 'S'),
(2, 'M'),
(3, 'L'),
(4, 'XS');

-- --------------------------------------------------------

--
-- Table structure for table `status`
--

CREATE TABLE `status` (
  `status_id` int(11) NOT NULL,
  `status_name` enum('Active','Inactive','Suspended') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `status`
--

INSERT INTO `status` (`status_id`, `status_name`) VALUES
(1, 'Active'),
(2, 'Inactive'),
(3, 'Suspended');

-- --------------------------------------------------------

--
-- Table structure for table `stock`
--

CREATE TABLE `stock` (
  `stock_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `current_qty` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `stock`
--

INSERT INTO `stock` (`stock_id`, `product_id`, `current_qty`) VALUES
(1, 3, 127),
(7, 4, 10),
(8, 2, 20),
(9, 5, 30),
(10, 1, 20);

-- --------------------------------------------------------

--
-- Table structure for table `stock_in`
--

CREATE TABLE `stock_in` (
  `stock_in_id` int(11) NOT NULL,
  `stock_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `date_added` date NOT NULL,
  `supplier_id` int(11) DEFAULT NULL,
  `purchase_price` double DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `stock_in`
--

INSERT INTO `stock_in` (`stock_in_id`, `stock_id`, `quantity`, `date_added`, `supplier_id`, `purchase_price`) VALUES
(1, 1, 30, '2025-08-11', 1, NULL),
(2, 1, 40, '2025-08-11', 1, NULL),
(3, 1, 40, '2025-08-11', 1, NULL),
(4, 7, 10, '2025-08-11', 2, NULL),
(5, 8, 20, '2025-08-11', 2, NULL),
(6, 1, 20, '2025-08-11', 1, NULL),
(7, 9, 30, '2025-08-17', 2, NULL),
(8, 10, 20, '2025-08-17', 2, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `store_settings`
--

CREATE TABLE `store_settings` (
  `id` int(11) NOT NULL,
  `store_name` varchar(255) NOT NULL,
  `store_description` text DEFAULT NULL,
  `store_email` varchar(255) NOT NULL,
  `contact` varchar(100) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `timezone_locale` varchar(100) DEFAULT NULL,
  `theme` varchar(100) DEFAULT NULL,
  `homepage_layout` text DEFAULT NULL,
  `custom_css_html` text DEFAULT NULL,
  `shipping_method` varchar(100) DEFAULT NULL,
  `flat_rate_shipping` decimal(10,2) DEFAULT NULL,
  `delivery_time` varchar(100) DEFAULT NULL,
  `two_factor_auth` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `store_settings`
--

INSERT INTO `store_settings` (`id`, `store_name`, `store_description`, `store_email`, `contact`, `address`, `timezone_locale`, `theme`, `homepage_layout`, `custom_css_html`, `shipping_method`, `flat_rate_shipping`, `delivery_time`, `two_factor_auth`) VALUES
(1, 'Seven Dwarfs Boutique', 'This is a sample store description.', 'sevendwarfsboutique123@email.com', '123-456-7890', 'Bayambang, Pangasinan', 'Philippines', 'dark', '{\"featured_products\": true, \"categories\": true}', '', 'Standard', 5.99, '3-5 business days', 0);

-- --------------------------------------------------------

--
-- Table structure for table `suppliers`
--

CREATE TABLE `suppliers` (
  `supplier_id` int(11) NOT NULL,
  `supplier_name` varchar(255) NOT NULL,
  `supplier_email` varchar(100) DEFAULT NULL,
  `supplier_phone` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `category_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `suppliers`
--

INSERT INTO `suppliers` (`supplier_id`, `supplier_name`, `supplier_email`, `supplier_phone`, `created_at`, `category_id`) VALUES
(1, 'Supplier 1', 'supplier@email.com', '0987654321', '2025-04-30 13:50:11', 5),
(2, 'Supplier 2', 'supplier2@email.com', '0912345678', '2025-05-01 13:25:54', 6);

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

CREATE TABLE `transactions` (
  `transaction_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `payment_method_id` int(10) UNSIGNED DEFAULT NULL,
  `total` decimal(10,2) NOT NULL,
  `order_status_id` int(10) UNSIGNED NOT NULL,
  `date_time` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `adminusers`
--
ALTER TABLE `adminusers`
  ADD PRIMARY KEY (`admin_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `admin_email` (`admin_email`),
  ADD KEY `role_id` (`role_id`),
  ADD KEY `status_id` (`status_id`);

--
-- Indexes for table `carts`
--
ALTER TABLE `carts`
  ADD PRIMARY KEY (`cart_id`),
  ADD KEY `fk_carts_customer_id` (`customer_id`),
  ADD KEY `fk_carts_product_id` (`product_id`);

--
-- Indexes for table `cart_items`
--
ALTER TABLE `cart_items`
  ADD PRIMARY KEY (`cart_item_id`),
  ADD KEY `fk_cart_items_cart_id` (`cart_id`),
  ADD KEY `fk_cart_items_product_id` (`product_id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`category_id`);

--
-- Indexes for table `colors`
--
ALTER TABLE `colors`
  ADD PRIMARY KEY (`color_id`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`customer_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `status_id` (`status_id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`order_id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `fk_orders_status` (`order_status_id`),
  ADD KEY `fk_orders_products` (`product_id`),
  ADD KEY `fk_orders_payment_method` (`payment_method_id`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `stock_id` (`stock_id`);

--
-- Indexes for table `order_status`
--
ALTER TABLE `order_status`
  ADD PRIMARY KEY (`order_status_id`),
  ADD UNIQUE KEY `order_status_name` (`order_status_name`);

--
-- Indexes for table `payment_methods`
--
ALTER TABLE `payment_methods`
  ADD PRIMARY KEY (`payment_method_id`),
  ADD UNIQUE KEY `payment_method_name` (`payment_method_name`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`product_id`),
  ADD KEY `fk_supplier` (`supplier_id`),
  ADD KEY `fk_products_category` (`category_id`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`role_id`),
  ADD UNIQUE KEY `role_name` (`role_name`);

--
-- Indexes for table `sizes`
--
ALTER TABLE `sizes`
  ADD PRIMARY KEY (`size_id`);

--
-- Indexes for table `status`
--
ALTER TABLE `status`
  ADD PRIMARY KEY (`status_id`);

--
-- Indexes for table `stock`
--
ALTER TABLE `stock`
  ADD PRIMARY KEY (`stock_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `stock_in`
--
ALTER TABLE `stock_in`
  ADD PRIMARY KEY (`stock_in_id`),
  ADD KEY `stock_id` (`stock_id`),
  ADD KEY `supplier_id` (`supplier_id`);

--
-- Indexes for table `store_settings`
--
ALTER TABLE `store_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `suppliers`
--
ALTER TABLE `suppliers`
  ADD PRIMARY KEY (`supplier_id`);

--
-- Indexes for table `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`transaction_id`),
  ADD KEY `fk_transactions_orders` (`order_id`),
  ADD KEY `fk_transactions_customers` (`customer_id`),
  ADD KEY `fk_transactions_payment_method` (`payment_method_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `adminusers`
--
ALTER TABLE `adminusers`
  MODIFY `admin_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `carts`
--
ALTER TABLE `carts`
  MODIFY `cart_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `cart_items`
--
ALTER TABLE `cart_items`
  MODIFY `cart_item_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `category_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `colors`
--
ALTER TABLE `colors`
  MODIFY `color_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `customer_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `order_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `payment_methods`
--
ALTER TABLE `payment_methods`
  MODIFY `payment_method_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `product_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `sizes`
--
ALTER TABLE `sizes`
  MODIFY `size_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `status`
--
ALTER TABLE `status`
  MODIFY `status_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `stock`
--
ALTER TABLE `stock`
  MODIFY `stock_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `stock_in`
--
ALTER TABLE `stock_in`
  MODIFY `stock_in_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `store_settings`
--
ALTER TABLE `store_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `supplier_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `transaction_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `adminusers`
--
ALTER TABLE `adminusers`
  ADD CONSTRAINT `adminusers_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`role_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `adminusers_ibfk_2` FOREIGN KEY (`status_id`) REFERENCES `status` (`status_id`) ON DELETE CASCADE;

--
-- Constraints for table `carts`
--
ALTER TABLE `carts`
  ADD CONSTRAINT `fk_carts_customer_id` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`customer_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_carts_product_id` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `cart_items`
--
ALTER TABLE `cart_items`
  ADD CONSTRAINT `fk_cart_items_cart_id` FOREIGN KEY (`cart_id`) REFERENCES `carts` (`cart_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_cart_items_product_id` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `customers`
--
ALTER TABLE `customers`
  ADD CONSTRAINT `customers_ibfk_1` FOREIGN KEY (`status_id`) REFERENCES `status` (`status_id`);

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `fk_orders_payment_method` FOREIGN KEY (`payment_method_id`) REFERENCES `payment_methods` (`payment_method_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_orders_products` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_orders_status` FOREIGN KEY (`order_status_id`) REFERENCES `order_status` (`order_status_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`customer_id`);

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`stock_id`) REFERENCES `stock` (`stock_id`);

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `fk_products_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`supplier_id`);

--
-- Constraints for table `stock`
--
ALTER TABLE `stock`
  ADD CONSTRAINT `stock_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE;

--
-- Constraints for table `stock_in`
--
ALTER TABLE `stock_in`
  ADD CONSTRAINT `stock_in_ibfk_1` FOREIGN KEY (`stock_id`) REFERENCES `stock` (`stock_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `stock_in_ibfk_2` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`supplier_id`);

--
-- Constraints for table `transactions`
--
ALTER TABLE `transactions`
  ADD CONSTRAINT `fk_transactions_customers` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`customer_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_transactions_orders` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_transactions_payment_method` FOREIGN KEY (`payment_method_id`) REFERENCES `payment_methods` (`payment_method_id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
