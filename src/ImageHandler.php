<?php
/**
 * Image Handler
 *
 * Downloads product images from eBay and stores them in the OpenCart image directory.
 */
class ImageHandler
{
    private string $basePath;
    private string $cacheDir;
    private int $maxWidth;
    private int $maxHeight;
    private array $downloaded = [];

    public function __construct(array $config)
    {
        $this->basePath = rtrim($config['base_path'] ?? '/home/geekygoodygoods.com/public_html/image/', '/');
        $this->cacheDir = trim($config['cache_dir'] ?? 'catalog/ebay/', '/');
        $this->maxWidth = (int)($config['max_width'] ?? 1000);
        $this->maxHeight = (int)($config['max_height'] ?? 1000);
    }

    /**
     * Download an image from a URL and save to the OpenCart image directory.
     *
     * @param string $url The source image URL
     * @param string $itemId The eBay item ID (used for filename)
     * @param int $index Image index (0 = primary, 1+ = additional)
     * @return string|null Relative path (e.g., "catalog/ebay/ITEMID_0.jpg") or null on failure
     */
    public function downloadImage(string $url, string $itemId, int $index = 0): ?string
    {
        $cacheKey = md5($url);
        if (isset($this->downloaded[$cacheKey])) {
            return $this->downloaded[$cacheKey];
        }

        $dir = $this->basePath . '/' . $this->cacheDir;
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0755, true) && !is_dir($dir)) {
                error_log("ImageHandler: Failed to create directory: $dir");
                return null;
            }
        }

        $ext = $this->getExtension($url);
        $filename = preg_replace('/[^a-zA-Z0-9_-]/', '', $itemId) . "_{$index}.{$ext}";
        $localPath = $dir . '/' . $filename;
        $relativePath = $this->cacheDir . '/' . $filename;

        // Skip if already downloaded
        if (file_exists($localPath) && filesize($localPath) > 0) {
            $this->downloaded[$cacheKey] = $relativePath;
            return $relativePath;
        }

        // Download
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $imageData = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($httpCode !== 200 || empty($imageData) || $errno !== 0) {
            error_log("ImageHandler: Failed to download $url (HTTP $httpCode, errno: $errno, error: $error)");
            return null;
        }

        // Validate MIME type server-side
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $detectedMime = finfo_buffer($finfo, $imageData);
        finfo_close($finfo);
        $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($detectedMime, $allowedMimes, true)) {
            error_log("ImageHandler: Invalid MIME type '$detectedMime' for $url");
            return null;
        }

        // Resize if needed and save
        $result = $this->processImage($imageData, $localPath);
        if (!$result) {
            return null;
        }

        $this->downloaded[$cacheKey] = $relativePath;
        return $relativePath;
    }

    /**
     * Download all images for an item.
     *
     * @param array $imageUrls Array of eBay image URLs
     * @param string $itemId eBay item ID
     * @return array Relative paths of successfully downloaded images
     */
    public function downloadImages(array $imageUrls, string $itemId): array
    {
        $paths = [];
        foreach ($imageUrls as $index => $url) {
            $path = $this->downloadImage($url, $itemId, $index);
            if ($path !== null) {
                $paths[] = $path;
            }
        }
        return $paths;
    }

    /**
     * Process image data: resize if needed and write to disk.
     */
    private function processImage(string $data, string $path): bool
    {
        // Try GD first (most common)
        if (function_exists('imagecreatefromstring')) {
            $src = @imagecreatefromstring($data);
            if ($src === false) {
                // Write raw if GD can't parse it (might be webp or other format)
                return file_put_contents($path, $data) !== false;
            }

            $origW = imagesx($src);
            $origH = imagesy($src);

            // Resize only if exceeds max dimensions
            if ($origW > $this->maxWidth || $origH > $this->maxHeight) {
                $ratio = min($this->maxWidth / $origW, $this->maxHeight / $origH);
                $newW = (int)round($origW * $ratio);
                $newH = (int)round($origH * $ratio);
                $dst = imagecreatetruecolor($newW, $newH);
                imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $origW, $origH);
                imagedestroy($src);
                $src = $dst;
            }

            // Determine output format based on file extension
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            $result = match ($ext) {
                'png' => imagepng($src, $path, 7),
                'gif' => imagegif($src, $path),
                'webp' => imagewebp($src, $path, 85),
                default => imagejpeg($src, $path, 85),
            };

            imagedestroy($src);
            return $result;
        }

        // No GD available, write raw
        return file_put_contents($path, $data) !== false;
    }

    /**
     * Get file extension from URL.
     */
    private function getExtension(string $url): string
    {
        $ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
        return in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp']) ? $ext : 'jpg';
    }

    /**
     * Get count of successfully downloaded images.
     */
    public function getDownloadCount(): int
    {
        return count($this->downloaded);
    }
}
