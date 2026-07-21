<?php
/**
 * OpenCart Sync Engine
 *
 * Core engine that takes standardized ListingData from any marketplace
 * adapter and syncs it to OpenCart's database.
 *
 * @package OpencartMarketplaceSync
 */
require_once __DIR__ . '/../src/OpenCartDb.php';
require_once __DIR__ . '/../src/ImageHandler.php';

class OpenCartSyncEngine
{
    private OpenCartDb $ocDb;
    private ImageHandler $images;
    private array $syncConfig;
    private int $defaultCategoryId;

    public function __construct(PDO $ocPdo, array $config)
    {
        $this->ocDb = new OpenCartDb($ocPdo, $config['opencart']['prefix'] ?? 'oc_');
        $this->images = new ImageHandler($config['images'] ?? []);
        $this->syncConfig = $config['sync'] ?? [];
        $this->defaultCategoryId = (int)($config['sync']['default_category_id'] ?? 20);
    }

    /**
     * Sync a single listing to OpenCart.
     *
     * @param array $listing Standardized listing data (from ListingData::toArray())
     * @return array Result with 'action' (created/updated/skipped/error) and 'product_id'
     */
    public function syncListing(array $listing): array
    {
        $itemId = $listing['item_id'] ?? '';
        $title = $listing['title'] ?? 'Unknown';

        try {
            // Check if already synced (by ebay_item_id column)
            $existingId = $this->ocDb->getProductByEbayItemId($itemId);

            // Map to OpenCart fields
            $productData = $this->mapToProduct($listing);

            // Download primary image
            $images = $listing['images'] ?? [];
            if (!empty($images)) {
                $localImage = $this->images->downloadImage($images[0], $itemId, 0);
                if ($localImage) {
                    $productData['image'] = $localImage;
                }
            }

            if ($existingId) {
                $this->ocDb->updateProduct($existingId, $productData);
                // Download additional images
                if (count($images) > 1) {
                    $downloaded = $this->images->downloadImages(array_slice($images, 1), $itemId);
                    foreach ($downloaded as $i => $path) {
                        $this->ocDb->createProductImage($existingId, $path, $i + 1);
                    }
                }
                return ['action' => 'updated', 'product_id' => $existingId, 'title' => $title];
            } else {
                $newId = $this->ocDb->createProduct($productData);
                if ($newId) {
                    if (count($images) > 1) {
                        $downloaded = $this->images->downloadImages(array_slice($images, 1), $itemId);
                        foreach ($downloaded as $i => $path) {
                            $this->ocDb->createProductImage($newId, $path, $i + 1);
                        }
                    }
                    return ['action' => 'created', 'product_id' => $newId, 'title' => $title];
                }
                return ['action' => 'error', 'product_id' => null, 'title' => $title, 'error' => 'Create failed'];
            }
        } catch (\Exception $e) {
            return ['action' => 'error', 'product_id' => null, 'title' => $title, 'error' => $e->getMessage()];
        }
    }

    /**
     * Sync multiple listings.
     *
     * @param array $listings Array of standardized listing data arrays
     * @return array Sync results with counts
     */
    public function syncListings(array $listings): array
    {
        $results = ['created' => 0, 'updated' => 0, 'errors' => 0, 'skipped' => 0, 'details' => []];

        foreach ($listings as $listing) {
            $result = $this->syncListing($listing);
            $results['details'][] = $result;

            match ($result['action']) {
                'created' => $results['created']++,
                'updated' => $results['updated']++,
                'error'   => $results['errors']++,
                default   => $results['skipped']++,
            };
        }

        $results['images_downloaded'] = $this->images->getDownloadCount();
        return $results;
    }

    /**
     * Map standardized listing data to OpenCart product fields.
     */
    private function mapToProduct(array $listing): array
    {
        $name = $this->cleanTitle($listing['title'] ?? '');
        $categoryId = (int)($listing['category_id'] ?: $this->defaultCategoryId);

        return [
            'model'          => $this->truncate($name, 64),
            'sku'            => '',
            'ebay_item_id'   => $listing['item_id'] ?? '',
            'quantity'       => max(0, (int)($listing['quantity'] ?? 1)),
            'price'          => $this->formatPrice($listing['price'] ?? '0.00'),
            'image'          => '',
            'status'         => $this->getStatus($listing),
            'name'           => $this->truncate($name, 255),
            'description'    => $this->buildDescription($listing),
            'meta_title'     => $this->truncate($name, 255),
            'category_id'    => $categoryId,
            'manufacturer_id'=> 0,
            'store_id'       => 0,
        ];
    }

    private function cleanTitle(string $title): string
    {
        $title = preg_replace('/\s+/', ' ', trim($title));
        return preg_replace('/[^\x{0020}-\x{007E}\x{00A0}-\x{FFFF}]/u', '', $title);
    }

    private function buildDescription(array $listing): string
    {
        $parts = [];
        $parts[] = '<p>' . htmlspecialchars($listing['title'] ?? '') . '</p>';
        if (!empty($listing['condition'])) {
            $parts[] = '<p><strong>Condition:</strong> ' . htmlspecialchars($listing['condition']) . '</p>';
        }
        if (!empty($listing['brand'])) {
            $parts[] = '<p><strong>Brand:</strong> ' . htmlspecialchars($listing['brand']) . '</p>';
        }
        if (!empty($listing['description'])) {
            $parts[] = '<hr><div class="marketplace-description">' . $listing['description'] . '</div>';
        }
        if (!empty($listing['attributes'])) {
            $parts[] = '<hr><h3>Specifications</h3><ul>';
            foreach ($listing['attributes'] as $name => $value) {
                $parts[] = '<li><strong>' . htmlspecialchars($name) . ':</strong> '
                    . htmlspecialchars(is_array($value) ? implode(', ', $value) : $value) . '</li>';
            }
            $parts[] = '</ul>';
        }
        if (!empty($listing['source'])) {
            $parts[] = '<p><small>Listed on ' . htmlspecialchars($listing['source'])
                . ' (ID: ' . htmlspecialchars($listing['item_id'] ?? '') . ')</small></p>';
        }
        return implode("\n", $parts);
    }

    private function formatPrice(string $price): string
    {
        return number_format((float)str_replace(['$', ',', ' '], '', $price), 4, '.', '');
    }

    private function getStatus(array $listing): int
    {
        return ((int)($listing['quantity'] ?? 1) > 0)
            ? ($this->syncConfig['status_active'] ?? 1)
            : 0;
    }

    private function truncate(string $str, int $max): string
    {
        return mb_strlen($str) > $max ? mb_substr($str, 0, $max - 3) . '...' : $str;
    }
}
