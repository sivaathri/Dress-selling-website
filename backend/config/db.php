<?php
/**
 * Database Configuration and Auto-Migration
 * PondyVastra E-Commerce Admin Backend
 */

header('Content-Type: application/json; charset=UTF-8');

// Database credentials for XAMPP
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'pondyvastra_db');

/**
 * Get a PDO database connection and ensure database & tables exist
 *
 * @return PDO
 */
function getDBConnection() {
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    try {
        // Step 1: Connect to MySQL server without selecting DB
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );

        // Step 2: Auto-create database if it does not exist
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
        $pdo->exec("USE `" . DB_NAME . "`;");

        // Step 3: Auto-create tables if they do not exist
        createTablesIfNotExist($pdo);

        return $pdo;
    } catch (PDOException $e) {
        sendJsonResponse(false, "Database Connection Error: " . $e->getMessage(), [], 500);
        exit;
    }
}

/**
 * Creates necessary tables if they do not exist
 *
 * @param PDO $pdo
 */
function createTablesIfNotExist(PDO $pdo) {
    // 1. Products table
    $sqlProducts = "
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
    ";
    $pdo->exec($sqlProducts);

    // 2. Product Color Variants & Image Angles table
    $sqlVariants = "
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
    ";
    $pdo->exec($sqlVariants);
}

/**
 * Standardized JSON response helper
 *
 * @param bool $success
 * @param string $message
 * @param array $data
 * @param int $statusCode
 */
function sendJsonResponse($success, $message, $data = [], $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode([
        'success'   => $success,
        'message'   => $message,
        'data'      => $data,
        'timestamp' => date('c')
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}
