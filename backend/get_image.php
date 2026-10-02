<?php
/**
 * Image Retrieval & Preview API
 * Serves images directly from the MySQL database (LONGBLOB)
 * Can be used directly in <img src="backend/get_image.php?id=..."> or queried via JSON.
 */

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/config/db.php';

try {
    $pdo = getDBConnection();

    $id        = isset($_GET['id']) ? intval($_GET['id']) : null;
    $productId = isset($_GET['product_id']) ? intval($_GET['product_id']) : null;
    $variantId = isset($_GET['variant_id']) ? intval($_GET['variant_id']) : null;
    $angle     = isset($_GET['angle']) ? trim($_GET['angle']) : null;
    $format    = isset($_GET['format']) ? strtolower(trim($_GET['format'])) : 'binary';

    $row = null;

    if ($id) {
        $stmt = $pdo->prepare("SELECT * FROM `product_images` WHERE `id` = ? LIMIT 1");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
    } elseif ($variantId && $angle) {
        $stmt = $pdo->prepare("SELECT * FROM `product_images` WHERE `variant_id` = ? AND `angle` = ? LIMIT 1");
        $stmt->execute([$variantId, $angle]);
        $row = $stmt->fetch();
    } elseif ($productId) {
        if ($angle) {
            $stmt = $pdo->prepare("SELECT * FROM `product_images` WHERE `product_id` = ? AND `angle` = ? ORDER BY `id` ASC LIMIT 1");
            $stmt->execute([$productId, $angle]);
            $row = $stmt->fetch();
        } else {
            // First primary or front image for this product
            $stmt = $pdo->prepare("SELECT * FROM `product_images` WHERE `product_id` = ? ORDER BY CASE WHEN `angle` = 'primary' THEN 1 WHEN `angle` = 'front' THEN 2 ELSE 3 END, `id` ASC LIMIT 1");
            $stmt->execute([$productId]);
            $row = $stmt->fetch();
        }
    }

    // If image not found in DB
    if (!$row || empty($row['image_data'])) {
        if ($format === 'json') {
            sendJsonResponse(false, 'Image not found in database.', [], 404);
        }
        
        // Serve fallback placeholder image
        $fallbackFile = dirname(__DIR__) . '/assets/images/prod-1-anarkali.png';
        if (file_exists($fallbackFile)) {
            header('Content-Type: image/png');
            header('Content-Length: ' . filesize($fallbackFile));
            readfile($fallbackFile);
            exit;
        }

        // SVG Placeholder fallback
        header('Content-Type: image/svg+xml');
        echo '<svg xmlns="http://www.w3.org/2000/svg" width="300" height="300" viewBox="0 0 300 300"><rect fill="#f1f5f9" width="300" height="300"/><text fill="#94a3b8" font-family="sans-serif" font-size="14" dy="10.5" font-weight="bold" x="50%" y="50%" text-anchor="middle">Image Not Found</text></svg>';
        exit;
    }

    $mimeType  = $row['mime_type'] ?: 'image/jpeg';
    $fileName  = $row['file_name'] ?: ('image_' . $row['id'] . '.jpg');
    $imageData = $row['image_data'];
    $fileSize  = strlen($imageData);

    // Return as JSON preview (base64 data URI)
    if ($format === 'json') {
        $base64 = 'data:' . $mimeType . ';base64,' . base64_encode($imageData);
        sendJsonResponse(true, 'Image retrieved successfully from database.', [
            'id'         => (int)$row['id'],
            'product_id' => (int)$row['product_id'],
            'variant_id' => $row['variant_id'] ? (int)$row['variant_id'] : null,
            'color_name' => $row['color_name'],
            'angle'      => $row['angle'],
            'file_name'  => $fileName,
            'mime_type'  => $mimeType,
            'file_size'  => $fileSize,
            'preview_url'=> "backend/get_image.php?id=" . $row['id'],
            'data_uri'   => $base64,
            'created_at' => $row['created_at']
        ]);
        exit;
    }

    // Direct binary image output (suitable for <img src="..."> preview)
    header("Content-Type: " . $mimeType);
    header("Content-Length: " . $fileSize);
    header("Content-Disposition: inline; filename=\"" . addslashes($fileName) . "\"");
    header("Cache-Control: public, max-age=86400"); // Cache in browser for 24h
    header("ETag: \"" . md5($row['id'] . '_' . $fileSize) . "\"");

    // Output binary BLOB
    echo $imageData;
    exit;

} catch (PDOException $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    exit;
}
