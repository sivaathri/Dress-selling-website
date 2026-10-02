<?php
/**
 * Database Status & Health Check
 */

require_once __DIR__ . '/config/db.php';

try {
    $pdo = getDBConnection();
    
    $stmtProducts = $pdo->query("SELECT COUNT(*) FROM `products`");
    $productCount = $stmtProducts->fetchColumn();

    $stmtVariants = $pdo->query("SELECT COUNT(*) FROM `product_color_variants`");
    $variantCount = $stmtVariants->fetchColumn();

    $stmtImages = $pdo->query("SELECT COUNT(*) as img_count, COALESCE(SUM(file_size), 0) as total_blob_bytes FROM `product_images`");
    $imgStats = $stmtImages->fetch();

    sendJsonResponse(true, "Database is connected and healthy.", [
        'database'         => DB_NAME,
        'host'             => DB_HOST,
        'user'             => DB_USER,
        'products_count'   => (int)$productCount,
        'variants_count'   => (int)$variantCount,
        'blob_images_count'=> (int)($imgStats['img_count'] ?? 0),
        'total_blob_bytes' => (int)($imgStats['total_blob_bytes'] ?? 0),
        'tables'           => ['products', 'product_color_variants', 'product_images']
    ]);
} catch (Exception $e) {
    sendJsonResponse(false, "Database check failed: " . $e->getMessage(), [], 500);
}
