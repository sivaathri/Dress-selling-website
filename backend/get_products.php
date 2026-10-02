<?php
/**
 * Get Products Endpoint
 * Fetches products list or single product with color variants from MySQL database.
 */

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/config/db.php';

try {
    $pdo = getDBConnection();

    // Single product fetch by ID or SKU
    $id  = isset($_GET['id']) ? intval($_GET['id']) : null;
    $sku = isset($_GET['sku']) ? trim($_GET['sku']) : null;

    if ($id || $sku) {
        $sql = "SELECT * FROM `products` WHERE ";
        $params = [];
        if ($id) {
            $sql .= "`id` = ?";
            $params[] = $id;
        } else {
            $sql .= "`sku` = ?";
            $params[] = $sku;
        }
        $sql .= " LIMIT 1";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $product = $stmt->fetch();

        if (!$product) {
            sendJsonResponse(false, "Product not found.", [], 404);
        }

        // Decode sizes JSON
        $product['sizes'] = json_decode($product['sizes'] ?? '[]', true) ?: [];

        // Fetch variants
        $stmtVar = $pdo->prepare("SELECT * FROM `product_color_variants` WHERE `product_id` = ? ORDER BY `id` ASC");
        $stmtVar->execute([$product['id']]);
        $product['variants'] = $stmtVar->fetchAll();

        // Fetch images metadata (excluding heavy BLOB bytes)
        $stmtImgs = $pdo->prepare("SELECT `id`, `product_id`, `variant_id`, `color_name`, `angle`, `file_name`, `mime_type`, `file_size`, CONCAT('backend/get_image.php?id=', `id`) as `preview_url` FROM `product_images` WHERE `product_id` = ? ORDER BY `id` ASC");
        $stmtImgs->execute([$product['id']]);
        $product['images'] = $stmtImgs->fetchAll();

        sendJsonResponse(true, "Product retrieved successfully.", $product);
    }

    // List products with optional filters
    $category = isset($_GET['category']) && $_GET['category'] !== 'All' ? trim($_GET['category']) : null;
    $status   = isset($_GET['status']) && $_GET['status'] !== 'All' ? trim($_GET['status']) : null;
    $search   = isset($_GET['q']) ? trim($_GET['q']) : null;
    $limit    = isset($_GET['limit']) ? max(1, min(100, intval($_GET['limit']))) : 50;
    $offset   = isset($_GET['offset']) ? max(0, intval($_GET['offset'])) : 0;

    $where = [];
    $params = [];

    if ($category) {
        $where[] = "`category` = ?";
        $params[] = $category;
    }
    if ($status) {
        $where[] = "`status` = ?";
        $params[] = $status;
    }
    if ($search) {
        $where[] = "(`name` LIKE ? OR `sku` LIKE ? OR `tags` LIKE ? OR `category` LIKE ?)";
        $wildcard = "%{$search}%";
        $params[] = $wildcard;
        $params[] = $wildcard;
        $params[] = $wildcard;
        $params[] = $wildcard;
    }

    $whereSql = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

    // Count total products
    $countSql = "SELECT COUNT(*) as total FROM `products` {$whereSql}";
    $stmtCount = $pdo->prepare($countSql);
    $stmtCount->execute($params);
    $totalCount = (int)$stmtCount->fetchColumn();

    // Fetch products
    $listSql = "SELECT * FROM `products` {$whereSql} ORDER BY `id` DESC LIMIT {$limit} OFFSET {$offset}";
    $stmtList = $pdo->prepare($listSql);
    $stmtList->execute($params);
    $products = $stmtList->fetchAll();

    // Attach variants for each product
    if (!empty($products)) {
        $productIds = array_column($products, 'id');
        $inQuery = implode(',', array_fill(0, count($productIds), '?'));
        
        $varSql = "SELECT * FROM `product_color_variants` WHERE `product_id` IN ($inQuery) ORDER BY `id` ASC";
        $stmtVars = $pdo->prepare($varSql);
        $stmtVars->execute($productIds);
        $allVariants = $stmtVars->fetchAll();

        // Group variants by product_id
        $variantsByProduct = [];
        foreach ($allVariants as $var) {
            $variantsByProduct[$var['product_id']][] = $var;
        }

        // Fetch image metadata (excluding heavy BLOB data)
        $imgSql = "SELECT `id`, `product_id`, `variant_id`, `color_name`, `angle`, `file_name`, `mime_type`, `file_size`, CONCAT('backend/get_image.php?id=', `id`) as `preview_url` FROM `product_images` WHERE `product_id` IN ($inQuery) ORDER BY `id` ASC";
        $stmtImgs = $pdo->prepare($imgSql);
        $stmtImgs->execute($productIds);
        $allImages = $stmtImgs->fetchAll();

        $imagesByProduct = [];
        foreach ($allImages as $im) {
            $imagesByProduct[$im['product_id']][] = $im;
        }

        foreach ($products as &$prod) {
            $prod['sizes'] = json_decode($prod['sizes'] ?? '[]', true) ?: [];
            $prod['variants'] = $variantsByProduct[$prod['id']] ?? [];
            $prod['images'] = $imagesByProduct[$prod['id']] ?? [];
        }
        unset($prod);
    }

    sendJsonResponse(true, "Products fetched successfully.", [
        'total'    => $totalCount,
        'count'    => count($products),
        'products' => $products
    ]);

} catch (PDOException $e) {
    sendJsonResponse(false, "Database Query Error: " . $e->getMessage(), [], 500);
} catch (Exception $e) {
    sendJsonResponse(false, "Server Error: " . $e->getMessage(), [], 500);
}
