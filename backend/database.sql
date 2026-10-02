-- =======================================================
-- PondyVastra E-Commerce Database Schema
-- Database Name: pondyvastra_db
-- =======================================================

CREATE DATABASE IF NOT EXISTS `pondyvastra_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `pondyvastra_db`;

-- -------------------------------------------------------
-- 1. Table structure for table `products`
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS `products` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `sku` VARCHAR(100) NOT NULL UNIQUE,
    `short_description` VARCHAR(500) DEFAULT NULL,
    `description` LONGTEXT DEFAULT NULL,
    `category` VARCHAR(100) NOT NULL,
    `subcategory` VARCHAR(100) DEFAULT NULL,
    `brand` VARCHAR(100) DEFAULT 'PondyVastra',
    `product_type` ENUM('single', 'variants') DEFAULT 'variants',
    `tags` VARCHAR(255) DEFAULT NULL,
    `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `compare_price` DECIMAL(10,2) DEFAULT NULL,
    `discount_type` VARCHAR(20) DEFAULT 'percent',
    `discount_value` DECIMAL(10,2) DEFAULT 0.00,
    `tax_rate` VARCHAR(20) DEFAULT '0%',
    `stock_quantity` INT NOT NULL DEFAULT 0,
    `low_stock_alert` INT DEFAULT 5,
    `sizes` TEXT DEFAULT NULL,
    `status` ENUM('Active', 'Draft', 'Low Stock', 'Out of Stock') DEFAULT 'Active',
    `primary_image` VARCHAR(500) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_category (`category`),
    INDEX idx_status (`status`),
    INDEX idx_sku (`sku`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- 2. Table structure for table `product_color_variants`
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS `product_color_variants` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `product_id` INT NOT NULL,
    `color_name` VARCHAR(100) NOT NULL,
    `color_hex` VARCHAR(20) DEFAULT '#000000',
    `front_image` VARCHAR(500) DEFAULT NULL,
    `side_image` VARCHAR(500) DEFAULT NULL,
    `back_image` VARCHAR(500) DEFAULT NULL,
    `closeup_image` VARCHAR(500) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_product_id (`product_id`),
    CONSTRAINT `fk_product_variants`
        FOREIGN KEY (`product_id`) 
        REFERENCES `products`(`id`) 
        ON DELETE CASCADE 
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- 3. Table structure for table `product_images` (LONGBLOB)
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS `product_images` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `product_id` INT NOT NULL,
    `variant_id` INT DEFAULT NULL,
    `color_name` VARCHAR(100) DEFAULT NULL,
    `angle` ENUM('front', 'side', 'back', 'closeup', 'primary', 'general') DEFAULT 'front',
    `file_name` VARCHAR(255) NOT NULL,
    `mime_type` VARCHAR(100) NOT NULL DEFAULT 'image/jpeg',
    `file_size` INT NOT NULL DEFAULT 0,
    `image_data` LONGBLOB NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_prod_angle (`product_id`, `angle`),
    INDEX idx_variant_id (`variant_id`),
    CONSTRAINT `fk_product_images_prod`
        FOREIGN KEY (`product_id`) 
        REFERENCES `products`(`id`) 
        ON DELETE CASCADE 
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- Sample initial product (Optional Reference)
-- -------------------------------------------------------
INSERT INTO `products` 
(`name`, `sku`, `short_description`, `description`, `category`, `subcategory`, `brand`, `product_type`, `tags`, `price`, `compare_price`, `discount_type`, `discount_value`, `tax_rate`, `stock_quantity`, `low_stock_alert`, `sizes`, `status`, `primary_image`)
VALUES
('Floral Anarkali Kurta', 'FAK-PNK-M', 'Graceful flared floral Anarkali kurta crafted with soft breathable cotton fabric.', '<p>Handcrafted ethnic Anarkali kurta with delicate floral print detailing, sweetheart neckline, and 3/4th sleeves. Ideal for festive and casual wear.</p>', 'Kurtis', 'Anarkali Kurta', 'PondyVastra', 'variants', 'cotton, anarkali, floral, festive', 1299.00, 2499.00, 'percent', 48.00, '5%', 25, 5, '["S","M","L"]', 'Active', 'assets/images/prod-1-anarkali.png')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);
