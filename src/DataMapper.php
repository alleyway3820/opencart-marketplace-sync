<?php
/**
 * Data Mapper
 *
 * Maps eBay item data to OpenCart product fields.
 */
class DataMapper
{
    private array $categoryMap;
    private array $config;

    public function __construct(array $categoryMap, array $config)
    {
        $this->categoryMap = $categoryMap;
        $this->config = $config;
    }

    /**
     * Map eBay item data to OpenCart product fields.
     *
     * @param array $ebayItem eBay item data (from extractItemData or Browse API)
     * @return array OpenCart product data ready for createProduct/updateProduct
     */
    public function mapToProduct(array $ebayItem): array
    {
        $name = $this->cleanTitle($ebayItem['title'] ?? '');
        $description = $this->buildDescription($ebayItem);

        $categoryId = $this->resolveCategoryId($ebayItem['category_id'] ?? '', $ebayItem['category_name'] ?? '');
        $price = $ebayItem['price'] ?? '0.00';
        // Price can be string (search results) or array (getItem response: {value, currency})
        if (is_array($price)) {
            $price = $price['value'] ?? '0.00';
        }
        $price = $this->formatPrice($price);
        $quantity = max(0, (int)($ebayItem['quantity'] ?? 1));

        return [
            'model' => $this->truncate($name, 64),
            'sku' => $ebayItem['item_id'] ?? '',
            'quantity' => $quantity,
            'price' => $price,
            'image' => '', // Set after image download
            'status' => $this->getStatus($ebayItem),
            'name' => $this->truncate($name, 255),
            'description' => $description,
            'meta_title' => $this->truncate($name, 255),
            'category_id' => $categoryId,
            'manufacturer_id' => 0,
            'store_id' => 0,
        ];
    }

    /**
     * Clean a product title for OpenCart.
     */
    private function cleanTitle(string $title): string
    {
        // Remove excessive whitespace
        $title = preg_replace('/\s+/', ' ', trim($title));
        // Remove emoji and special chars that break OpenCart
        $title = preg_replace('/[^\x{0020}-\x{007E}\x{00A0}-\x{FFFF}]/u', '', $title);
        return trim($title);
    }

    /**
     * Build an HTML description from eBay item data.
     */
    private function buildDescription(array $item): string
    {
        $parts = [];

        $parts[] = '<p>' . htmlspecialchars($item['title'] ?? '') . '</p>';

        // Condition
        if (!empty($item['condition'])) {
            $parts[] = '<p><strong>Condition:</strong> ' . htmlspecialchars($item['condition']) . '</p>';
        }

        // Brand
        if (!empty($item['brand'])) {
            $parts[] = '<p><strong>Brand:</strong> ' . htmlspecialchars($item['brand']) . '</p>';
        }

        // Description
        $desc = $item['description'] ?? '';
        if (!empty($desc)) {
            $parts[] = '<hr>';
            $parts[] = '<div class="ebay-description">' . $desc . '</div>';
        }

        // Attributes
        if (!empty($item['attributes'])) {
            $parts[] = '<hr>';
            $parts[] = '<h3>Specifications</h3><ul>';
            foreach ($item['attributes'] as $name => $value) {
                // Why: eBay returns spec names like "Brand", "Model", "Processor"
                // which should be displayed as readable HTML.
                $parts[] = '<li><strong>' . htmlspecialchars($name) . ':</strong> '
                    . htmlspecialchars(is_array($value) ? implode(', ', $value) : $value) . '</li>';
            }
            $parts[] = '</ul>';
        }

        // Location
        if (!empty($item['location'])) {
            $parts[] = '<p><em>Item location: ' . htmlspecialchars($item['location']) . '</em></p>';
        }

        // Listing reference
        if (!empty($item['item_id'])) {
            $url = $item['listing_url'] ?? '';
            if ($url) {
                $parts[] = '<p><small>Listed on eBay: <a href="' . htmlspecialchars($url) . '">'
                    . htmlspecialchars($item['item_id']) . '</a></small></p>';
            } else {
                $parts[] = '<p><small>eBay item ID: ' . htmlspecialchars($item['item_id']) . '</small></p>';
            }
        }

        return implode("\n", $parts);
    }

    // ─── Keyword-based auto-categorization ───
    private array $keywordCategoryMap = [
        // Keywords => OpenCart category name to find/create
        'cassette' => 'Music',
        'cd' => 'Music',
        'record' => 'Music',
        'vinyl' => 'Music',
        'dvd' => 'Movies & TV',
        'blu-ray' => 'Movies & TV',
        'phone' => 'Phones & PDAs',
        'iphone' => 'Phones & PDAs',
        'tablet' => 'Tablets',
        'ipad' => 'Tablets',
        'computer' => 'Desktops',
        'laptop' => 'Laptops & Notebooks',
        'camera' => 'Cameras',
        'printer' => 'Printers',
        'monitor' => 'Monitors',
        'game' => 'Software',
        'nintendo' => 'Software',
        'playstation' => 'Software',
        'xbox' => 'Software',
        'headphone' => 'MP3 Players',
        'speaker' => 'MP3 Players',
        'ipod' => 'MP3 Players',
        'shirt' => 'Apparel',
        'shoes' => 'Apparel',
    ];

    /**
     * Resolve the best OpenCart category for an item based on title/keywords.
     *
     * @param string $title Item title
     * @return int OpenCart category_id (default if no match)
     */
    public function resolveCategory(string $title): int
    {
        $lower = strtolower($title);
        foreach ($this->keywordCategoryMap as $keyword => $catName) {
            if (str_contains($lower, $keyword)) {
                // Find or create the category
                $id = $this->findOrCreateCategory($catName);
                if ($id !== null) {
                    return $id;
                }
            }
        }
        return (int)($this->config['default_category_id'] ?? 20);
    }

    private function findOrCreateCategory(string $name): ?int
    {
        // Check our in-memory map first (passed from OpenCartDb)
        foreach ($this->categoryMap as $id => $catName) {
            if (strcasecmp($catName, $name) === 0) {
                return $id;
            }
        }
        return null; // Category not found in our map
    }

    /**
     * Update category map (called after fetching from DB).
     */
    public function setCategoryMap(array $map): void
    {
        $this->categoryMap = $map;
    }

    /**
     * Resolve eBay category to OpenCart category.
     */
    private function resolveCategoryId(string $ebayCategoryId, string $ebayCategoryName): int
    {
        // Check explicit mapping first
        if (isset($this->categoryMap[$ebayCategoryId])) {
            return (int)$this->categoryMap[$ebayCategoryId];
        }

        return (int)($this->config['default_category_id'] ?? 20);
    }

    /**
     * Format price to OpenCart format (decimal string).
     */
    private function formatPrice(string $price): string
    {
        $price = str_replace(['$', ',', ' '], '', $price);
        return number_format((float)$price, 4, '.', '');
    }

    /**
     * Determine product status based on listing state.
     */
    private function getStatus(array $item): int
    {
        $status = $item['listing_status'] ?? 'ACTIVE';
        $quantity = (int)($item['quantity'] ?? 1);

        if ($status !== 'ACTIVE') {
            return 0; // Disabled
        }
        if ($quantity <= 0) {
            return 0; // Out of stock, disable
        }
        return $this->config['status_active'] ?? 1;
    }

    /**
     * Truncate string to max length.
     */
    private function truncate(string $str, int $max): string
    {
        if (mb_strlen($str) > $max) {
            return mb_substr($str, 0, $max - 3) . '...';
        }
        return $str;
    }
}
