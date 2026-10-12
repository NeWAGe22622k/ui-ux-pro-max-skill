<?php
declare(strict_types=1);

const UPLOAD_MAX_BYTES = 3 * 1024 * 1024;

/**
 * Validate an uploaded image, resize it and re-encode it (which strips EXIF data
 * and anything hidden inside the file). Returns the web path, e.g. "uploads/products/ab12.webp".
 *
 * $mode 'photo' → max 800px, WebP (falls back to JPEG).
 * $mode 'qr'    → max 600px, lossless PNG so QR codes stay scannable.
 *
 * Throws RuntimeException with a staff-friendly message.
 */
function save_uploaded_image(array $file, string $folder, string $mode = 'photo'): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $msg = match ($file['error'] ?? null) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'That photo is too big. Please use one under 3MB.',
            UPLOAD_ERR_NO_FILE => 'No photo was chosen.',
            default => 'The photo did not upload. Please try again.',
        };
        throw new RuntimeException($msg);
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('The photo did not upload. Please try again.');
    }
    if ($file['size'] > UPLOAD_MAX_BYTES) {
        throw new RuntimeException('That photo is too big. Please use one under 3MB.');
    }

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $loaders = [
        'image/jpeg' => 'imagecreatefromjpeg',
        'image/png'  => 'imagecreatefrompng',
        'image/webp' => 'imagecreatefromwebp',
        'image/gif'  => 'imagecreatefromgif',
    ];
    if (!isset($loaders[$mime])) {
        throw new RuntimeException('Please upload a JPG, PNG or WebP photo.');
    }
    $info = @getimagesize($file['tmp_name']);
    if (!$info || $info[0] < 1 || $info[1] < 1 || $info[0] * $info[1] > 40_000_000) {
        throw new RuntimeException('That file does not look like a normal photo.');
    }

    $src = @$loaders[$mime]($file['tmp_name']);
    if (!$src) {
        throw new RuntimeException('That photo could not be read. Please try another one.');
    }
    if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
        $src = auto_rotate($src, $file['tmp_name']);
    }

    $max = $mode === 'qr' ? 600 : 800;
    $w = imagesx($src);
    $h = imagesy($src);
    $scale = min(1, $max / max($w, $h));
    $nw = max(1, (int)round($w * $scale));
    $nh = max(1, (int)round($h * $scale));

    $dst = imagecreatetruecolor($nw, $nh);
    imagealphablending($dst, false);
    imagesavealpha($dst, true);
    imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 255, 255, 255, 127));
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
    imagedestroy($src);

    $dir = PUBLIC_DIR . '/uploads/' . $folder;
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        throw new RuntimeException('The uploads folder is not writable. Please check folder permissions.');
    }

    $name = bin2hex(random_bytes(12));
    if ($mode === 'qr') {
        $name .= '.png';
        $ok = imagepng($dst, "$dir/$name", 6);
    } elseif (function_exists('imagewebp')) {
        $name .= '.webp';
        $ok = imagewebp($dst, "$dir/$name", 80);
    } else {
        $name .= '.jpg';
        $ok = imagejpeg($dst, "$dir/$name", 82);
    }
    imagedestroy($dst);
    if (!$ok) {
        throw new RuntimeException('The photo could not be saved. Please try again.');
    }
    return "uploads/$folder/$name";
}

function auto_rotate(GdImage $img, string $path): GdImage
{
    $exif = @exif_read_data($path);
    $angle = match ((int)($exif['Orientation'] ?? 1)) {
        3 => 180,
        6 => -90,
        8 => 90,
        default => 0,
    };
    if ($angle !== 0) {
        $rotated = imagerotate($img, $angle, 0);
        if ($rotated) {
            imagedestroy($img);
            return $rotated;
        }
    }
    return $img;
}

/** Remove a previously uploaded file (only inside uploads/). */
function delete_upload(?string $webPath): void
{
    if (!$webPath || !str_starts_with($webPath, 'uploads/') || str_contains($webPath, '..')) {
        return;
    }
    $file = PUBLIC_DIR . '/' . $webPath;
    if (is_file($file)) {
        @unlink($file);
    }
}

function has_upload(string $field): bool
{
    return isset($_FILES[$field]) && ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
}
