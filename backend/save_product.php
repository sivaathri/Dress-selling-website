<?php
/**
 * Save Product Endpoint
 * Handles creating / updating products, sizes, prices, inventory, and color variants with images.
 * Stores raw image bytes directly into the `product_images` table as LONGBLOB.
 */

// Enable CORS if requested from another port/domain in dev
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/config/db.php';

// Only accept POST requests
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
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

    // Helper function to extract raw binary bytes from $_FILES, base64 dataURI, or local disk path
    $extractImageBinary = function($fileKey, $fallbackVal = null, $defaultFileName = 'image.jpg') {
        // 1. Check real file upload from FormData
        if (isset($_FILES[$fileKey]) && $_FILES[$fileKey]['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES[$fileKey];
            $binary = file_get_contents($file['tmp_name']);
            if (!empty($binary)) {
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime  = $finfo->buffer($binary) ?: ($file['type'] ?: 'image/jpeg');
                $name  = basename($file['name']);
                return [
                    'binary'    => $binary,
                    'mime_type' => $mime,
                    'file_name' => $name,
                    'file_size' => strlen($binary)
                ];
            }
        }

        // 2. Check fallback (dataURI or server file path)
        if (!empty($fallbackVal) && is_string($fallbackVal)) {
            // Check base64 dataURI
            if (preg_match('#^data:(image/[\w+-]+);base64,(.*)$#is', $fallbackVal, $m)) {
                $mime = $m[1];
                $binary = base64_decode($m[2]);
                if ($binary !== false && strlen($binary) > 0) {
                    $ext = explode('/', $mime)[1] ?? 'jpg';
                    return [
                        'binary'    => $binary,
                        'mime_type' => $mime,
                        'file_name' => $defaultFileName . '.' . $ext,
                        'file_size' => strlen($binary)
                    ];
                }
            }

            // Check if it's already an existing get_image.php URL (e.g. backend/get_image.php?id=...)
            if (strpos($fallbackVal, 'get_image.php?id=') !== false) {
                // Keep the reference URL as string
                return [
                    'is_existing_url' => true,
                    'url'             => $fallbackVal
                ];
            }

            // Check local file on disk (e.g. assets/images/...)
            $cleaned = preg_replace('/^\.\.\//', '', trim($fallbackVal));
            $fullPath = dirname(__DIR__) . '/' . $cleaned;
            if (file_exists($fullPath) && is_file($fullPath)) {
                $binary = file_get_contents($fullPath);
                if (!empty($binary)) {
                    $finfo = new finfo(FILEINFO_MIME_TYPE);
                    $mime  = $finfo->buffer($binary) ?: 'image/jpeg';
                    return [
                        'binary'    => $binary,
                        'mime_type' => $mime,
                        'file_name' => basename($fullPath),
                        'file_size' => strlen($binary)
                    ];
                }
            }
        }

        return null;
    };

    // Decode variants metadata
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

    // Start database transaction
    $pdo->beginTransaction();

    // Check if product with this SKU already exists
    $stmtCheck = $pdo->prepare("SELECT `id` FROM `products` WHERE `sku` = ? LIMIT 1");
    $stmtCheck->execute([$sku]);
    $existingProduct = $stmtCheck->fetch();

    $primaryImage = null;

    if ($existingProduct) {
        $productId = (int)$existingProduct['id'];
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
                `status` = ?
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
            $productId
        ]);

        // Delete old variants & images to cleanly replace with current set
        $pdo->prepare("DELETE FROM `product_color_variants` WHERE `product_id` = ?")->execute([$productId]);
        $pdo->prepare("DELETE FROM `product_images` WHERE `product_id` = ?")->execute([$productId]);
    } else {
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
            'assets/images/prod-1-anarkali.png' // temporary placeholder until images processed below
        ]);
        $productId = (int)$pdo->lastInsertId();
    }

    // Prepared statements for Variants & BLOB Image saving
    $stmtInsertVariant = $pdo->prepare("
        INSERT INTO `product_color_variants` (
            `product_id`, `color_name`, `color_hex`,
            `front_image`, `side_image`, `back_image`, `closeup_image`
        ) VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    $stmtInsertImage = $pdo->prepare("
        INSERT INTO `product_images` (
            `product_id`, `variant_id`, `color_name`, `angle`,
            `file_name`, `mime_type`, `file_size`, `image_data`
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $processedVariantsCount = 0;
    $totalBlobImagesSaved   = 0;

    if (!empty($variantsList) && is_array($variantsList)) {
        foreach ($variantsList as $idx => $v) {
            $colorName = trim($v['color_name'] ?? ('Variant ' . ($idx + 1)));
            $colorHex  = trim($v['color_hex'] ?? '#000000');

            // 1. Insert placeholder variant to acquire variant_id
            $stmtInsertVariant->execute([
                $productId,
                $colorName,
                $colorHex,
                null, null, null, null
            ]);
            $variantId = (int)$pdo->lastInsertId();

            // 2. Process each of the 4 angles: Front, Side, Back, Close-up
            $slotKeys = ['front', 'side', 'back', 'closeup'];
            $slotUrls = [];

            foreach ($slotKeys as $slotKey) {
                $fileKey = "variant_{$idx}_{$slotKey}";
                $fallback = $v["{$slotKey}_image"] ?? null;
                $defaultName = "prod_{$sku}_{$colorName}_{$slotKey}";

                $imgData = $extractImageBinary($fileKey, $fallback, $defaultName);

                if ($imgData) {
                    if (!empty($imgData['is_existing_url'])) {
                        $slotUrls[$slotKey] = $imgData['url'];
                    } else {
                        // Insert raw binary into MySQL LONGBLOB
                        $stmtInsertImage->execute([
                            $productId,
                            $variantId,
                            $colorName,
                            $slotKey,
                            $imgData['file_name'],
                            $imgData['mime_type'],
                            $imgData['file_size'],
                            $imgData['binary']
                        ]);
                        $newImgId = (int)$pdo->lastInsertId();
                        $totalBlobImagesSaved++;

                        // The preview URL dynamically renders the BLOB from MySQL
                        $slotUrls[$slotKey] = "backend/get_image.php?id=" . $newImgId;

                        if (!$primaryImage && $slotKey === 'front') {
                            $primaryImage = $slotUrls[$slotKey];
                        }
                    }
                } else {
                    $slotUrls[$slotKey] = null;
                }
            }

            // Fallback primary image to any variant slot if front was empty
            if (!$primaryImage) {
                foreach ($slotUrls as $url) {
                    if ($url) {
                        $primaryImage = $url;
                        break;
                    }
                }
            }

            // 3. Update the variant with the BLOB preview URLs
            $stmtUpdateVar = $pdo->prepare("
                UPDATE `product_color_variants` SET
                    `front_image` = ?,
                    `side_image` = ?,
                    `back_image` = ?,
                    `closeup_image` = ?
                WHERE `id` = ?
            ");
            $stmtUpdateVar->execute([
                $slotUrls['front'] ?? null,
                $slotUrls['side'] ?? null,
                $slotUrls['back'] ?? null,
                $slotUrls['closeup'] ?? null,
                $variantId
            ]);

            $processedVariantsCount++;
        }
    }

    // Process explicit primary image if provided and no variant image was selected
    if (!$primaryImage && !empty($inputData['primary_image'])) {
        $primData = $extractImageBinary('primary_image', $inputData['primary_image'], "prod_{$sku}_primary");
        if ($primData && empty($primData['is_existing_url'])) {
            $stmtInsertImage->execute([
                $productId,
                null,
                null,
                'primary',
                $primData['file_name'],
                $primData['mime_type'],
                $primData['file_size'],
                $primData['binary']
            ]);
            $primaryImage = "backend/get_image.php?id=" . $pdo->lastInsertId();
            $totalBlobImagesSaved++;
        } elseif ($primData && !empty($primData['is_existing_url'])) {
            $primaryImage = $primData['url'];
        }
    }

    // Default primary image fallback if none was uploaded
    if (!$primaryImage) {
        $primaryImage = 'assets/images/prod-1-anarkali.png';
    }

    // Update primary image in products table
    $stmtUpdateProdImg = $pdo->prepare("UPDATE `products` SET `primary_image` = ? WHERE `id` = ?");
    $stmtUpdateProdImg->execute([$primaryImage, $productId]);

    // Commit all changes
    $pdo->commit();

    sendJsonResponse(true, "Product and BLOB images saved successfully into database!", [
        'product_id'       => $productId,
        'sku'              => $sku,
        'name'             => $name,
        'status'           => $status,
        'primary_image'    => $primaryImage,
        'blob_images_count'=> $totalBlobImagesSaved,
        'variant_count'    => $processedVariantsCount
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
