<?php
/**
 * Save Product Endpoint
 * Handles creating / updating products, sizes, prices, inventory, and color variants with images.
 */

// Enable CORS if requested from another port/domain in dev
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/config/db.php';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(false, 'Invalid request method. POST required.', [], 405);
}

try {
    $pdo = getDBConnection();

    // Determine payload source: $_POST (for FormData with files) or php://input (for JSON)
    $inputData = [];
    $contentType = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '';

    if (stripos($contentType, 'application/json') !== false) {
        $raw = file_get_contents('php://input');
        $inputData = json_decode($raw, true) ?: [];
    } else {
        $inputData = $_POST;
    }

    // Extract core fields
    $productId   = !empty($inputData['product_id']) ? intval($inputData['product_id']) : null;
    $name        = trim($inputData['name'] ?? '');
    $sku         = strtoupper(trim($inputData['sku'] ?? ''));
    $shortDesc   = trim($inputData['short_description'] ?? '');
    $description = trim($inputData['description'] ?? '');
    $category    = trim($inputData['category'] ?? '');
    $subcategory = trim($inputData['subcategory'] ?? '');
    $brand       = trim($inputData['brand'] ?? 'PondyVastra');
    $productType = trim($inputData['product_type'] ?? 'variants');
    $tags        = trim($inputData['tags'] ?? '');
    
    $price        = floatval($inputData['price'] ?? 0);
    $comparePrice = !empty($inputData['compare_price']) ? floatval($inputData['compare_price']) : null;
    $discountType = trim($inputData['discount_type'] ?? 'percent');
    $discountVal  = floatval($inputData['discount_val'] ?? 0);
    $taxRate      = trim($inputData['tax_rate'] ?? '0%');
    $stockQty     = intval($inputData['stock_quantity'] ?? 0);
    $lowStock     = !empty($inputData['low_stock_alert']) ? intval($inputData['low_stock_alert']) : 5;
    $status       = trim($inputData['status'] ?? 'Active');

    // Validation
    if (empty($name)) {
        sendJsonResponse(false, 'Product name is required.', [], 422);
    }
    if (empty($sku)) {
        sendJsonResponse(false, 'Product SKU is required.', [], 422);
    }
    if (empty($category)) {
        sendJsonResponse(false, 'Category is required.', [], 422);
    }
    if ($price < 0) {
        sendJsonResponse(false, 'Price must be a valid positive amount.', [], 422);
    }

    // Sizes handling (JSON string or array)
    $sizesRaw = $inputData['sizes'] ?? '[]';
    if (is_array($sizesRaw)) {
        $sizesJson = json_encode(array_values($sizesRaw), JSON_UNESCAPED_UNICODE);
    } else if (is_string($sizesRaw)) {
        $decoded = json_decode($sizesRaw, true);
        if (!is_array($decoded)) {
            $decoded = json_decode(stripslashes($sizesRaw), true);
        }
        if (is_array($decoded)) {
            $sizesJson = json_encode(array_values($decoded), JSON_UNESCAPED_UNICODE);
        } else {
            $parts = array_filter(array_map('trim', explode(',', $sizesRaw)));
            $sizesJson = json_encode(array_values($parts), JSON_UNESCAPED_UNICODE);
        }
    } else {
        $sizesJson = json_encode([], JSON_UNESCAPED_UNICODE);
    }

    // Status auto-correction if stock is zero
    if ($status === 'Active' && $stockQty <= 0) {
        $status = 'Out of Stock';
    } elseif ($status === 'Active' && $stockQty <= $lowStock && $stockQty > 0) {
        $status = 'Low Stock';
    }

    // Prepare upload directory
    $uploadDir = __DIR__ . '/uploads/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // Helper function to process uploaded files or existing path
    $processUploadedFile = function($fileKey, $fallbackPath = null) use ($uploadDir, $sku) {
        if (isset($_FILES[$fileKey]) && $_FILES[$fileKey]['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES[$fileKey];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
            if (!in_array($ext, $allowedExtensions)) {
                return $fallbackPath;
            }

            $cleanSku = preg_replace('/[^A-Za-z0-9_-]/', '', $sku);
            $cleanKey = preg_replace('/[^A-Za-z0-9_-]/', '', $fileKey);
            $uniqueFilename = 'prod_' . $cleanSku . '_' . $cleanKey . '_' . uniqid() . '.' . $ext;
            $destPath = $uploadDir . $uniqueFilename;

            if (move_uploaded_file($file['tmp_name'], $destPath)) {
                // Return path relative to project root
                return 'backend/uploads/' . $uniqueFilename;
            }
        }

        // Clean fallback path if provided
        if (!empty($fallbackPath)) {
            // Strip leading ../ if present
            $cleaned = preg_replace('/^\.\.\//', '', trim($fallbackPath));
            return $cleaned;
        }

        return null;
    };

    // Decode variants metadata
    // In FormData, variants can be passed as JSON string in 'variants_data' or as array in JSON body
    $variantsList = [];
    if (!empty($inputData['variants_data'])) {
        $rawVar = $inputData['variants_data'];
        $variantsList = json_decode($rawVar, true);
        if (!is_array($variantsList)) {
            $variantsList = json_decode(stripslashes($rawVar), true) ?: [];
        }
    } elseif (!empty($inputData['variants']) && is_array($inputData['variants'])) {
        $variantsList = $inputData['variants'];
    }

    // Process each variant and its 4 image slots
    $processedVariants = [];
    $primaryImage = null;

    if (!empty($variantsList) && is_array($variantsList)) {
        foreach ($variantsList as $idx => $v) {
            $colorName = trim($v['color_name'] ?? ('Variant ' . ($idx + 1)));
            $colorHex  = trim($v['color_hex'] ?? '#000000');

            // Check slots for this variant
            $frontKey   = "variant_{$idx}_front";
            $sideKey    = "variant_{$idx}_side";
            $backKey    = "variant_{$idx}_back";
            $closeupKey = "variant_{$idx}_closeup";

            $frontImg   = $processUploadedFile($frontKey, $v['front_image'] ?? null);
            $sideImg    = $processUploadedFile($sideKey, $v['side_image'] ?? null);
            $backImg    = $processUploadedFile($backKey, $v['back_image'] ?? null);
            $closeupImg = $processUploadedFile($closeupKey, $v['closeup_image'] ?? null);

            $processedVariants[] = [
                'color_name'    => $colorName,
                'color_hex'     => $colorHex,
                'front_image'   => $frontImg,
                'side_image'    => $sideImg,
                'back_image'    => $backImg,
                'closeup_image' => $closeupImg
            ];

            // Set primary image from first available front image
            if (!$primaryImage && $frontImg) {
                $primaryImage = $frontImg;
            }
        }
    }

    // Fallback primary image
    if (!$primaryImage && !empty($inputData['primary_image'])) {
        $primaryImage = preg_replace('/^\.\.\//', '', trim($inputData['primary_image']));
    }
    if (!$primaryImage && !empty($processedVariants)) {
        // Use any available image from the first variant
        $first = $processedVariants[0];
        $primaryImage = $first['front_image'] ?: ($first['side_image'] ?: ($first['back_image'] ?: $first['closeup_image']));
    }
    if (!$primaryImage) {
        $primaryImage = 'assets/images/prod-1-anarkali.png';
    }

    // Start database transaction
    $pdo->beginTransaction();

    // Check if product with this SKU already exists
    $stmtCheck = $pdo->prepare("SELECT `id` FROM `products` WHERE `sku` = ? LIMIT 1");
    $stmtCheck->execute([$sku]);
    $existingProduct = $stmtCheck->fetch();

    if ($existingProduct) {
        // Update existing product
        $productId = $existingProduct['id'];
        $sqlUpdate = "
            UPDATE `products` SET
                `name` = ?,
                `short_description` = ?,
                `description` = ?,
                `category` = ?,
                `subcategory` = ?,
                `brand` = ?,
                `product_type` = ?,
                `tags` = ?,
                `price` = ?,
                `compare_price` = ?,
                `discount_type` = ?,
                `discount_value` = ?,
                `tax_rate` = ?,
                `stock_quantity` = ?,
                `low_stock_alert` = ?,
                `sizes` = ?,
                `status` = ?,
                `primary_image` = ?
            WHERE `id` = ?
        ";
        $stmtUpdate = $pdo->prepare($sqlUpdate);
        $stmtUpdate->execute([
            $name,
            $shortDesc,
            $description,
            $category,
            $subcategory,
            $brand,
            $productType,
            $tags,
            $price,
            $comparePrice,
            $discountType,
            $discountVal,
            $taxRate,
            $stockQty,
            $lowStock,
            $sizesJson,
            $status,
            $primaryImage,
            $productId
        ]);

        // Delete old variants to re-insert fresh list
        $stmtDelVariants = $pdo->prepare("DELETE FROM `product_color_variants` WHERE `product_id` = ?");
        $stmtDelVariants->execute([$productId]);
    } else {
        // Insert new product
        $sqlInsert = "
            INSERT INTO `products` (
                `name`, `sku`, `short_description`, `description`,
                `category`, `subcategory`, `brand`, `product_type`, `tags`,
                `price`, `compare_price`, `discount_type`, `discount_value`,
                `tax_rate`, `stock_quantity`, `low_stock_alert`, `sizes`,
                `status`, `primary_image`
            ) VALUES (
                ?, ?, ?, ?,
                ?, ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?
            )
        ";
        $stmtInsert = $pdo->prepare($sqlInsert);
        $stmtInsert->execute([
            $name,
            $sku,
            $shortDesc,
            $description,
            $category,
            $subcategory,
            $brand,
            $productType,
            $tags,
            $price,
            $comparePrice,
            $discountType,
            $discountVal,
            $taxRate,
            $stockQty,
            $lowStock,
            $sizesJson,
            $status,
            $primaryImage
        ]);
        $productId = (int)$pdo->lastInsertId();
    }

    // Insert color variants
    if (!empty($processedVariants)) {
        $sqlVariantInsert = "
            INSERT INTO `product_color_variants` (
                `product_id`, `color_name`, `color_hex`,
                `front_image`, `side_image`, `back_image`, `closeup_image`
            ) VALUES (?, ?, ?, ?, ?, ?, ?)
        ";
        $stmtVar = $pdo->prepare($sqlVariantInsert);

        foreach ($processedVariants as $var) {
            $stmtVar->execute([
                $productId,
                $var['color_name'],
                $var['color_hex'],
                $var['front_image'],
                $var['side_image'],
                $var['back_image'],
                $var['closeup_image']
            ]);
        }
    }

    // Commit all changes
    $pdo->commit();

    sendJsonResponse(true, "Product saved successfully into database!", [
        'product_id'    => $productId,
        'sku'           => $sku,
        'name'          => $name,
        'status'        => $status,
        'primary_image' => $primaryImage,
        'variant_count' => count($processedVariants)
    ], 200);

} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    sendJsonResponse(false, "Database Query Error: " . $e->getMessage(), [], 500);
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    sendJsonResponse(false, "Server Error: " . $e->getMessage(), [], 500);
}
