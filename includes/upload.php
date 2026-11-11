<?php
/**
 * ShopSphere - Secure Image Upload Handler
 * Phase 4: Product Management CRUD
 */

require_once __DIR__ . '/helpers.php';

/**
 * Process product image upload or fallback URL.
 *
 * @param array|null $file The $_FILES['image'] or $_FILES['product_image'] array
 * @param string|null $fallbackUrl An image URL provided in form
 * @param string|null $currentImage Existing image path/URL for updates
 * @return array ['success' => bool, 'path' => string|null, 'error' => string|null]
 */
function handleProductImageUpload(?array $file, ?string $fallbackUrl = null, ?string $currentImage = null): array {
    $fallbackUrl = sanitizeString($fallbackUrl);

    // If an uploaded file is present with no upload errors
    if (!empty($file) && isset($file['error']) && $file['error'] === UPLOAD_ERR_OK) {
        $maxSizeBytes = 2 * 1024 * 1024; // 2MB limit

        if ($file['size'] > $maxSizeBytes) {
            return [
                'success' => false,
                'path'    => null,
                'error'   => 'Image file size exceeds maximum allowable limit of 2MB.'
            ];
        }

        // Verify genuine MIME type using finfo
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);

        $allowedMimes = [
            'image/jpeg' => '.jpg',
            'image/png'  => '.png',
            'image/webp' => '.webp'
        ];

        if (!array_key_exists($mime, $allowedMimes)) {
            return [
                'success' => false,
                'path'    => null,
                'error'   => 'Invalid image file format. Only JPEG, PNG, and WebP are permitted.'
            ];
        }

        $extension = $allowedMimes[$mime];
        $safeName = 'prod_' . bin2hex(random_bytes(8)) . '_' . time() . $extension;
        $uploadDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'products';

        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0755, true);
        }

        $targetPath = $uploadDir . DIRECTORY_SEPARATOR . $safeName;

        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            return [
                'success' => false,
                'path'    => null,
                'error'   => 'Failed to save uploaded image to destination directory.'
            ];
        }

        $savedRelativePath = 'uploads/products/' . $safeName;

        // If replacing an old locally uploaded file, clean up old file safely
        if ($currentImage && $currentImage !== $savedRelativePath) {
            deleteProductImageFile($currentImage);
        }

        return [
            'success' => true,
            'path'    => $savedRelativePath,
            'error'   => null
        ];
    }

    // Check if upload error occurred (other than no file uploaded)
    if (!empty($file) && isset($file['error']) && $file['error'] !== UPLOAD_ERR_NO_FILE) {
        return [
            'success' => false,
            'path'    => null,
            'error'   => 'File upload failed with error code: ' . $file['error']
        ];
    }

    // Fallback: If image URL provided, use it
    if (!empty($fallbackUrl)) {
        // Disallow dangerous executable script extensions
        $dangerousExtensions = ['php', 'phtml', 'php3', 'php4', 'php5', 'php7', 'php8', 'phps', 'pl', 'py', 'cgi', 'sh', 'bash', 'exe'];
        $parsedPath = parse_url($fallbackUrl, PHP_URL_PATH);
        $ext = strtolower(pathinfo($parsedPath ?? $fallbackUrl, PATHINFO_EXTENSION));

        if (in_array($ext, $dangerousExtensions, true)) {
            return [
                'success' => false,
                'path'    => null,
                'error'   => 'Image URL or path cannot point to executable script files.'
            ];
        }

        // Basic URL validation
        if (filter_var($fallbackUrl, FILTER_VALIDATE_URL) || str_starts_with($fallbackUrl, 'uploads/products/')) {
            return [
                'success' => true,
                'path'    => $fallbackUrl,
                'error'   => null
            ];
        } else {
            return [
                'success' => false,
                'path'    => null,
                'error'   => 'Provided image URL is not a valid URL.'
            ];
        }
    }

    // Retain existing image if updating
    if (!empty($currentImage)) {
        return [
            'success' => true,
            'path'    => $currentImage,
            'error'   => null
        ];
    }

    // No image supplied
    return [
        'success' => true,
        'path'    => null,
        'error'   => null
    ];
}

/**
 * Safely delete an uploaded product image from filesystem.
 */
function deleteProductImageFile(?string $imagePath): void {
    if (empty($imagePath)) return;

    // Only allow deletion within uploads/products/
    if (str_starts_with($imagePath, 'uploads/products/')) {
        // Prevent path traversal
        if (str_contains($imagePath, '..')) return;

        $fullPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $imagePath);
        if (is_file($fullPath)) {
            @unlink($fullPath);
        }
    }
}

