<?php
/**
 * Delete Product Endpoint
 */

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/config/db.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'POST' && $method !== 'DELETE') {
    sendJsonResponse(false, 'Invalid request method. POST or DELETE required.', [], 405);
}

try {
    $pdo = getDBConnection();

    $input = [];
    $raw = file_get_contents('php://input');
    if (!empty($raw)) {
        $input = json_decode($raw, true) ?: [];
    }
    if (empty($input)) {
        $input = $_POST;
    }

    $id  = isset($input['id']) ? intval($input['id']) : (isset($_GET['id']) ? intval($_GET['id']) : null);
    $sku = isset($input['sku']) ? trim($input['sku']) : (isset($_GET['sku']) ? trim($_GET['sku']) : null);

    if (!$id && !$sku) {
        sendJsonResponse(false, 'Product ID or SKU is required for deletion.', [], 422);
    }

    // Find product to remove uploaded images if stored locally
    $sqlFind = "SELECT `id`, `sku`, `primary_image` FROM `products` WHERE " . ($id ? "`id` = ?" : "`sku` = ?");
    $stmtFind = $pdo->prepare($sqlFind);
    $stmtFind->execute([$id ?: $sku]);
    $prod = $stmtFind->fetch();

    if (!$prod) {
        sendJsonResponse(false, 'Product not found.', [], 404);
    }

    // Delete product (cascades to product_color_variants)
    $stmtDel = $pdo->prepare("DELETE FROM `products` WHERE `id` = ?");
    $stmtDel->execute([$prod['id']]);

    sendJsonResponse(true, "Product (SKU: {$prod['sku']}) deleted successfully.", [
        'id'  => $prod['id'],
        'sku' => $prod['sku']
    ]);

} catch (PDOException $e) {
    sendJsonResponse(false, "Database Query Error: " . $e->getMessage(), [], 500);
} catch (Exception $e) {
    sendJsonResponse(false, "Server Error: " . $e->getMessage(), [], 500);
}
