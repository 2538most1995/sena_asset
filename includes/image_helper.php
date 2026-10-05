<?php
// includes/image_helper.php
// Helper to resize and compress uploaded equipment images to keep file sizes tiny (30KB - 80KB)

function processAndSaveEquipmentImage($fileInput, $maxDim = 800, $quality = 75) {
    if (!isset($fileInput) || $fileInput['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'ไม่มีไฟล์รูปภาพหรือเกิดข้อผิดพลาดในการอัปโหลด'];
    }

    $tmpPath = $fileInput['tmp_name'];
    $fileSize = $fileInput['size'];

    // Check image info
    $imageInfo = @getimagesize($tmpPath);
    if (!$imageInfo) {
        return ['success' => false, 'error' => 'ไฟล์ที่อัปโหลดไม่ใช่รูปภาพที่ถูกต้อง'];
    }

    $mime = $imageInfo['mime'];
    $origWidth = $imageInfo[0];
    $origHeight = $imageInfo[1];

    // Create GD image resource based on mime type
    $srcImg = null;
    switch ($mime) {
        case 'image/jpeg':
        case 'image/jpg':
            $srcImg = @imagecreatefromjpeg($tmpPath);
            break;
        case 'image/png':
            $srcImg = @imagecreatefrompng($tmpPath);
            break;
        case 'image/webp':
            $srcImg = @imagecreatefromwebp($tmpPath);
            break;
        case 'image/gif':
            $srcImg = @imagecreatefromgif($tmpPath);
            break;
        default:
            return ['success' => false, 'error' => 'รองรับเฉพาะไฟล์รูปภาพ JPG, PNG, WEBP หรือ GIF'];
    }

    if (!$srcImg) {
        return ['success' => false, 'error' => 'ไม่สามารถประมวลผลรูปภาพได้'];
    }

    // Auto-rotate if EXIF orientation exists (common in smartphone photos)
    if (function_exists('exif_read_data') && ($mime === 'image/jpeg' || $mime === 'image/jpg')) {
        $exif = @exif_read_data($tmpPath);
        if (!empty($exif['Orientation'])) {
            switch ($exif['Orientation']) {
                case 3:
                    $srcImg = imagerotate($srcImg, 180, 0);
                    break;
                case 6:
                    $srcImg = imagerotate($srcImg, -90, 0);
                    $tmp = $origWidth;
                    $origWidth = $origHeight;
                    $origHeight = $tmp;
                    break;
                case 8:
                    $srcImg = imagerotate($srcImg, 90, 0);
                    $tmp = $origWidth;
                    $origWidth = $origHeight;
                    $origHeight = $tmp;
                    break;
            }
        }
    }

    // Calculate new dimensions (max width/height = $maxDim)
    $newWidth = $origWidth;
    $newHeight = $origHeight;

    if ($origWidth > $maxDim || $origHeight > $maxDim) {
        if ($origWidth >= $origHeight) {
            $newWidth = $maxDim;
            $newHeight = (int)round(($origHeight / $origWidth) * $maxDim);
        } else {
            $newHeight = $maxDim;
            $newWidth = (int)round(($origWidth / $origHeight) * $maxDim);
        }
    }

    // Create resized canvas
    $dstImg = imagecreatetruecolor($newWidth, $newHeight);

    // Handle white background for PNG transparency if converted to JPEG
    $white = imagecolorallocate($dstImg, 255, 255, 255);
    imagefill($dstImg, 0, 0, $white);

    // High quality resample
    imagecopyresampled($dstImg, $srcImg, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);

    // Output directory
    $uploadDir = __DIR__ . '/../uploads/equipment/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    // Generate unique lightweight JPEG filename
    $filename = 'eq_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.jpg';
    $targetPath = $uploadDir . $filename;

    // Compress & save as JPEG (quality ~75% gives tiny size 30-70KB while preserving sharp details)
    $saved = imagejpeg($dstImg, $targetPath, $quality);

    // Free memory (PHP 8.0+ automatically manages GdImage memory)
    if (PHP_VERSION_ID < 80000) {
        @imagedestroy($srcImg);
        @imagedestroy($dstImg);
    }

    if ($saved && file_exists($targetPath)) {
        $savedSize = filesize($targetPath);
        return [
            'success' => true,
            'filename' => $filename,
            'url' => 'uploads/equipment/' . $filename,
            'original_size_kb' => round($fileSize / 1024, 1),
            'compressed_size_kb' => round($savedSize / 1024, 1),
            'dimensions' => "{$newWidth}x{$newHeight}"
        ];
    }

    return ['success' => false, 'error' => 'ไม่สามารถบันทึกไฟล์รูปภาพลงเซิร์ฟเวอร์ได้'];
}

/**
 * Process and save official agency logo with transparency support (PNG/WEBP/SVG/JPG)
 */
function processAndSaveLogoImage($fileInput, $maxDim = 400) {
    if (!isset($fileInput) || $fileInput['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'ไม่มีไฟล์รูปภาพหรือเกิดข้อผิดพลาดในการอัปโหลด'];
    }

    $tmpPath = $fileInput['tmp_name'];
    $origName = $fileInput['name'];
    $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

    $uploadDir = __DIR__ . '/../uploads/logo/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    // Support SVG vector logo directly
    if ($ext === 'svg' || (function_exists('mime_content_type') && @mime_content_type($tmpPath) === 'image/svg+xml')) {
        $svgContent = file_get_contents($tmpPath);
        if (stripos($svgContent, '<svg') !== false) {
            $filename = 'logo_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.svg';
            $targetPath = $uploadDir . $filename;
            if (file_put_contents($targetPath, $svgContent)) {
                return [
                    'success' => true,
                    'filename' => $filename,
                    'url' => 'uploads/logo/' . $filename,
                    'size_kb' => round(filesize($targetPath) / 1024, 1),
                    'type' => 'svg'
                ];
            }
        }
        return ['success' => false, 'error' => 'ไฟล์ SVG ไม่ถูกต้องหรือไม่สมบูรณ์'];
    }

    // Raster image: JPEG, PNG, WEBP, GIF
    $imageInfo = @getimagesize($tmpPath);
    if (!$imageInfo) {
        return ['success' => false, 'error' => 'ไฟล์ที่อัปโหลดไม่ใช่รูปภาพที่ถูกต้อง'];
    }

    $mime = $imageInfo['mime'];
    $origWidth = $imageInfo[0];
    $origHeight = $imageInfo[1];

    $srcImg = null;
    switch ($mime) {
        case 'image/jpeg':
        case 'image/jpg':
            $srcImg = @imagecreatefromjpeg($tmpPath);
            break;
        case 'image/png':
            $srcImg = @imagecreatefrompng($tmpPath);
            break;
        case 'image/webp':
            $srcImg = @imagecreatefromwebp($tmpPath);
            break;
        case 'image/gif':
            $srcImg = @imagecreatefromgif($tmpPath);
            break;
        default:
            return ['success' => false, 'error' => 'รองรับเฉพาะไฟล์ PNG, JPG, WEBP, SVG หรือ GIF'];
    }

    if (!$srcImg) {
        return ['success' => false, 'error' => 'ไม่สามารถประมวลผลรูปภาพได้'];
    }

    // Auto-rotate if EXIF orientation exists
    if (function_exists('exif_read_data') && ($mime === 'image/jpeg' || $mime === 'image/jpg')) {
        $exif = @exif_read_data($tmpPath);
        if (!empty($exif['Orientation'])) {
            switch ($exif['Orientation']) {
                case 3:
                    $srcImg = imagerotate($srcImg, 180, 0);
                    break;
                case 6:
                    $srcImg = imagerotate($srcImg, -90, 0);
                    $tmp = $origWidth;
                    $origWidth = $origHeight;
                    $origHeight = $tmp;
                    break;
                case 8:
                    $srcImg = imagerotate($srcImg, 90, 0);
                    $tmp = $origWidth;
                    $origWidth = $origHeight;
                    $origHeight = $tmp;
                    break;
            }
        }
    }

    // Calculate new dimensions (max 400px preserves sharp details for logos)
    $newWidth = $origWidth;
    $newHeight = $origHeight;
    if ($origWidth > $maxDim || $origHeight > $maxDim) {
        if ($origWidth >= $origHeight) {
            $newWidth = $maxDim;
            $newHeight = (int)round(($origHeight / $origWidth) * $maxDim);
        } else {
            $newHeight = $maxDim;
            $newWidth = (int)round(($origWidth / $origHeight) * $maxDim);
        }
    }

    // Create canvas preserving alpha transparency
    $dstImg = imagecreatetruecolor($newWidth, $newHeight);
    imagealphablending($dstImg, false);
    imagesavealpha($dstImg, true);
    $transparent = imagecolorallocatealpha($dstImg, 255, 255, 255, 127);
    imagefilledrectangle($dstImg, 0, 0, $newWidth, $newHeight, $transparent);
    imagealphablending($dstImg, true);

    imagecopyresampled($dstImg, $srcImg, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);

    $filename = 'logo_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.png';
    $targetPath = $uploadDir . $filename;

    $saved = imagepng($dstImg, $targetPath, 8);

    if (PHP_VERSION_ID < 80000) {
        @imagedestroy($srcImg);
        @imagedestroy($dstImg);
    }

    if ($saved && file_exists($targetPath)) {
        return [
            'success' => true,
            'filename' => $filename,
            'url' => 'uploads/logo/' . $filename,
            'size_kb' => round(filesize($targetPath) / 1024, 1),
            'dimensions' => "{$newWidth}x{$newHeight}"
        ];
    }

    return ['success' => false, 'error' => 'ไม่สามารถบันทึกไฟล์โลโก้ลงเซิร์ฟเวอร์ได้'];
}

