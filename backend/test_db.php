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

    sendJsonResponse(true, "Database is connected and healthy.", [
        'database'      => DB_NAME,
        'host'          => DB_HOST,
        'user'          => DB_USER,
        'products_count'=> (int)$productCount,
        'variants_count'=> (int)$variantCount,
        'tables'        => ['products', 'product_color_variants']
    ]);
} catch (Exception $e) {
    sendJsonResponse(false, "Database check failed: " . $e->getMessage(), [], 500);
}
