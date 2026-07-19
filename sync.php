#!/usr/bin/env php
<?php
/**
 * eBay → OpenCart Sync Tool
 *
 * Usage:
 *   php sync.php sync:all           Sync all eBay listings to OpenCart (from ebayflip DB)
 *   php sync.php sync:one ITEM_ID   Sync a single eBay item by full ID (e.g. v1|12345|6789)
 *   php sync.php sync:seller NAME   Search eBay by seller username and sync all their listings
 *   php sync.php status             Show sync status summary
 *   php sync.php ebay:search QUERY  Search eBay for items
 *   php sync.php oc:categories      List OpenCart categories
 *   php sync.php db:inspect         Inspect eBay flip database structure
 *   php sync.php auth:url           Generate OAuth authorization URL
 *   php sync.php auth:code CODE     Exchange authorization code for tokens
 */

require_once __DIR__ . '/src/EbayApi.php';
require_once __DIR__ . '/src/OpenCartDb.php';
require_once __DIR__ . '/src/EbayFlipDb.php';
require_once __DIR__ . '/src/ImageHandler.php';
require_once __DIR__ . '/src/DataMapper.php';

// ─── Load Config ───
$configFile = __DIR__ . '/config.php';
if (!file_exists($configFile)) {
    echo "ERROR: config.php not found. Copy config.example.php to config.php and fill in your credentials.\n";
    exit(1);
}
$config = require $configFile;

// ─── Parse Command ───
$command = $argv[1] ?? 'help';

// ─── Database Connections ───
function connectOpenCart(array $cfg): PDO
{
    $dsn = "mysql:host={$cfg['host']};port={$cfg['port']};dbname={$cfg['dbname']};charset=utf8mb4";
    $pdo = new PDO($dsn, $cfg['username'], $cfg['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $pdo;
}

function connectEbayFlip(array $cfg): PDO
{
    $dsn = "mysql:host={$cfg['host']};port={$cfg['port']};dbname={$cfg['dbname']};charset=utf8mb4";
    $pdo = new PDO($dsn, $cfg['username'], $cfg['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $pdo;
}

// ─── Command Handlers ───

match ($command) {
    'sync:all' => handleSyncAll($config),
    'sync:one' => handleSyncOne($config, $argv[2] ?? ''),
    'sync:seller' => handleSyncSeller($config, $argv[2] ?? ''),
    'status' => handleStatus($config),
    'ebay:search' => handleEbaySearch($config, array_slice($argv, 2)),
    'oc:categories' => handleOcCategories($config),
    'db:inspect' => handleDbInspect($config),
    'auth:url' => handleAuthUrl($config),
    'auth:code' => handleAuthCode($config, $argv[2] ?? ''),
    default => showHelp(),
};

// ══════════════════════════════════════════
//  Command Implementations
// ══════════════════════════════════════════

/**
 * Sync all active eBay listings to OpenCart.
 */
function handleSyncAll(array $config): void
{
    echo "=== eBay → OpenCart Sync ===\n\n";

    try {
        $ocDb = new OpenCartDb(connectOpenCart($config['opencart']), $config['opencart']['prefix']);
        $flipDb = new EbayFlipDb(connectEbayFlip($config['ebay_flip']));
        $imageHandler = new ImageHandler($config['images']);
        $mapper = new DataMapper($flipDb->getSample('ebay_items') ? [] : [], $config['sync']);
        $ebayApi = new EbayApi($config['ebay_api']);

        // Get items to sync
        $items = $flipDb->getItemsToSync();
        $total = count($items);
        echo "Found $total items to sync.\n\n";

        $synced = 0;
        $created = 0;
        $updated = 0;
        $errors = 0;
        $skipped = 0;

        foreach ($items as $item) {
            $itemId = $item['item_id'] ?? '';
            $title = $item['title'] ?? 'Unknown';

            echo "  [$itemId] $title... ";

            try {
                // Check if already synced
                $existingProductId = $ocDb->getProductByListingId($itemId);

                // Fetch live eBay data via API
                $ebayData = $ebayApi->getItem($itemId);

                if ($ebayData === null) {
                    // Item not found on eBay - mark as ended
                    echo "NOT FOUND on eBay\n";
                    $flipDb->markSynced($itemId, 0, 'ended');
                    $skipped++;
                    continue;
                }

                $itemData = EbayApi::extractItemData($ebayData);

                // Map to OpenCart fields
                $productData = $mapper->mapToProduct($itemData);

                // Download primary image
                $images = $itemData['images'];
                if (!empty($images)) {
                    $localImage = $imageHandler->downloadImage($images[0], $itemId, 0);
                    if ($localImage) {
                        $productData['image'] = $localImage;
                    }
                }

                if ($existingProductId) {
                    // Update existing product
                    $productData['category_id'] = $productData['category_id'] ?: $config['sync']['default_category_id'];
                    $result = $ocDb->updateProduct($existingProductId, $productData);
                    if ($result) {
                        // Download additional images
                        if (count($images) > 1) {
                            $imageHandler->downloadImages(array_slice($images, 1), $itemId);
                        }
                        echo "UPDATED (ID: $existingProductId)\n";
                        $updated++;
                    } else {
                        echo "UPDATE FAILED\n";
                        $errors++;
                    }
                } else {
                    // Create new product
                    $productData['category_id'] = $productData['category_id'] ?: $config['sync']['default_category_id'];
                    $newId = $ocDb->createProduct($productData);
                    if ($newId) {
                        // Download additional images
                        if (count($images) > 1) {
                            $downloaded = $imageHandler->downloadImages(array_slice($images, 1), $itemId);
                            foreach ($downloaded as $i => $imgPath) {
                                $ocDb->createProductImage($newId, $imgPath, $i + 1);
                            }
                        }
                        $flipDb->markSynced($itemId, $newId, 'synced');
                        echo "CREATED (ID: $newId)\n";
                        $created++;
                    } else {
                        echo "CREATE FAILED\n";
                        $errors++;
                    }
                }
                $synced++;
            } catch (Exception $e) {
                echo "ERROR: " . $e->getMessage() . "\n";
                $errors++;
            }
        }

        echo "\n=== Sync Complete ===\n";
        echo "  Total:   $total\n";
        echo "  Created: $created\n";
        echo "  Updated: $updated\n";
        echo "  Errors:  $errors\n";
        echo "  Skipped: $skipped\n";
        echo "  Images:  " . $imageHandler->getDownloadCount() . "\n";

    } catch (Exception $e) {
        echo "FATAL ERROR: " . $e->getMessage() . "\n";
        exit(1);
    }
}

/**
 * Sync a single eBay item.
 */
function handleSyncOne(array $config, string $itemId): void
{
    if (empty($itemId)) {
        echo "Usage: php sync.php sync:one ITEM_ID\n";
        exit(1);
    }

    try {
        $ocDb = new OpenCartDb(connectOpenCart($config['opencart']), $config['opencart']['prefix']);
        $imageHandler = new ImageHandler($config['images']);
        $mapper = new DataMapper([], $config['sync']);
        $ebayApi = new EbayApi($config['ebay_api']);

        echo "Fetching item $itemId from eBay API...\n";
        $ebayData = $ebayApi->getItem($itemId);

        if ($ebayData === null) {
            echo "Item not found on eBay.\n";
            exit(1);
        }

        $itemData = EbayApi::extractItemData($ebayData);
        echo "  Title: " . ($itemData['title'] ?? 'N/A') . "\n";
        echo "  Price: $" . ($itemData['price'] ?? '0.00') . "\n";
        echo "  Condition: " . ($itemData['condition'] ?? 'N/A') . "\n";
        echo "  Images: " . count($itemData['images']) . "\n\n";

        // Map to OpenCart
        $productData = $mapper->mapToProduct($itemData);

        // Check existing
        $existingId = $ocDb->getProductByListingId($itemId);
        if ($existingId) {
            echo "Product already exists (ID: $existingId). Updating...\n";
            // Download images
            if (!empty($itemData['images'])) {
                $localImage = $imageHandler->downloadImage($itemData['images'][0], $itemId, 0);
                if ($localImage) {
                    $productData['image'] = $localImage;
                }
            }
            $ocDb->updateProduct($existingId, $productData);
            echo "Updated.\n";
        } else {
            echo "Creating new product...\n";
            if (!empty($itemData['images'])) {
                $localImage = $imageHandler->downloadImage($itemData['images'][0], $itemId, 0);
                if ($localImage) {
                    $productData['image'] = $localImage;
                }
            }
            $newId = $ocDb->createProduct($productData);
            if ($newId) {
                echo "Created (ID: $newId).\n";
                // Download additional images
                if (count($itemData['images']) > 1) {
                    $downloaded = $imageHandler->downloadImages(array_slice($itemData['images'], 1), $itemId);
                    foreach ($downloaded as $i => $imgPath) {
                        $ocDb->createProductImage($newId, $imgPath, $i + 1);
                    }
                }
            } else {
                echo "Failed to create product.\n";
            }
        }

        echo "\nDone. Images downloaded: " . $imageHandler->getDownloadCount() . "\n";

    } catch (Exception $e) {
        echo "ERROR: " . $e->getMessage() . "\n";
        exit(1);
    }
}

/**
 * Show sync status.
 */
function handleStatus(array $config): void
{
    try {
        $ocDb = new OpenCartDb(connectOpenCart($config['opencart']), $config['opencart']['prefix']);
        $flipDb = new EbayFlipDb(connectEbayFlip($config['ebay_flip']));

        echo "=== Sync Status ===\n\n";

        // eBay items count
        $items = $flipDb->getItemsToSync();
        echo "eBay items to sync: " . count($items) . "\n";

        // OpenCart product count
        echo "OpenCart categories:\n";
        $categories = $ocDb->getCategoryMap();
        foreach ($categories as $id => $name) {
            echo "  [$id] $name\n";
        }

        // Show first few items as preview
        echo "\nFirst 5 items to sync:\n";
        $shown = 0;
        foreach ($items as $item) {
            if ($shown >= 5) break;
            echo "  [{$item['item_id']}] {$item['title']}\n";
            $shown++;
        }

    } catch (Exception $e) {
        echo "ERROR: " . $e->getMessage() . "\n";
        exit(1);
    }
}

/**
 * List eBay items from the flip database.
 */
function handleEbayList(array $config): void
{
    try {
        $flipDb = new EbayFlipDb(connectEbayFlip($config['ebay_flip']));
        $items = $flipDb->getItemsToSync();

        echo "=== eBay Active Listings ===\n\n";
        foreach ($items as $item) {
            $price = $item['price'] ?? 'N/A';
            echo "  [{$item['item_id']}] {$item['title']} - \${$price}\n";
        }
        echo "\nTotal: " . count($items) . "\n";

    } catch (Exception $e) {
        echo "ERROR: " . $e->getMessage() . "\n";
        exit(1);
    }
}

/**
 * List OpenCart categories.
 */
function handleOcCategories(array $config): void
{
    try {
        $ocDb = new OpenCartDb(connectOpenCart($config['opencart']), $config['opencart']['prefix']);
        $cats = $ocDb->getCategoryMap();

        echo "=== OpenCart Categories ===\n\n";
        foreach ($cats as $id => $name) {
            echo "  [$id] $name\n";
        }
        echo "\nTotal: " . count($cats) . "\n";

    } catch (Exception $e) {
        echo "ERROR: " . $e->getMessage() . "\n";
        exit(1);
    }
}

/**
 * Inspect the eBay flip database structure.
 */
function handleDbInspect(array $config): void
{
    try {
        $flipDb = new EbayFlipDb(connectEbayFlip($config['ebay_flip']));
        $tables = $flipDb->getTableInfo();

        echo "=== eBay Flip Database Tables ===\n\n";
        foreach ($tables as $table => $count) {
            echo "  $table ($count rows)\n";
            $sample = $flipDb->getSample($table);
            if (!empty($sample)) {
                echo "    Columns: " . implode(', ', array_keys($sample)) . "\n";
                echo "    Sample:  " . json_encode($sample, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
            }
            echo "\n";
        }

    } catch (Exception $e) {
        echo "ERROR: " . $e->getMessage() . "\n";
        exit(1);
    }
}

/**
 * Generate eBay OAuth authorization URL.
 */
function handleAuthUrl(array $config): void
{
    $api = new EbayApi($config['ebay_api']);
    $url = $api->getAuthUrl();
    echo "Open this URL in your browser to authorize:\n\n";
    echo $url . "\n\n";
    echo "After authorizing, you'll be redirected to your callback URL.\n";
    echo "Copy the 'code' parameter from the URL and run:\n";
    echo "  php sync.php auth:code YOUR_CODE\n";
}

/**
 * Exchange authorization code for tokens.
 */
function handleAuthCode(array $config, string $code): void
{
    if (empty($code)) {
        echo "Usage: php sync.php auth:code AUTHORIZATION_CODE\n";
        exit(1);
    }

    try {
        $api = new EbayApi($config['ebay_api']);
        $tokens = $api->exchangeAuthCode($code);

        echo "=== Tokens Received ===\n\n";
        echo "Access Token:\n" . ($tokens['access_token'] ?? 'N/A') . "\n\n";
        echo "Refresh Token:\n" . ($tokens['refresh_token'] ?? 'N/A') . "\n\n";
        echo "Expires in: " . ($tokens['expires_in'] ?? 'N/A') . " seconds\n\n";
        echo "Update your config.php with:\n";
        echo "  'refresh_token' => '" . ($tokens['refresh_token'] ?? '') . "',\n";
        echo "  'access_token'  => '" . ($tokens['access_token'] ?? '') . "',\n";
        echo "  'token_expiry'  => " . (time() + ($tokens['expires_in'] ?? 7200)) . ",\n";

    } catch (Exception $e) {
        echo "ERROR: " . $e->getMessage() . "\n";
        exit(1);
    }
}

/**
 * Search eBay and sync items by seller username.
 */
function handleSyncSeller(array $config, string $sellerName): void
{
    if (empty($sellerName)) {
        echo "Usage: php sync.php sync:seller SELLER_USERNAME\n";
        exit(1);
    }

    try {
        $ocDb = new OpenCartDb(connectOpenCart($config['opencart']), $config['opencart']['prefix']);
        $imageHandler = new ImageHandler($config['images']);
        $mapper = new DataMapper([], $config['sync']);
        $ebayApi = new EbayApi($config['ebay_api']);

        echo "Searching eBay for seller: $sellerName...\n";
        $results = $ebayApi->getSellerListings($sellerName, 50);
        $items = $results['items'] ?? [];
        echo "Found " . count($items) . " listings.\n\n";

        $created = 0;
        $updated = 0;
        $errors = 0;

        foreach ($items as $summary) {
            $itemId = $summary['itemId'] ?? '';
            $title = $summary['title'] ?? 'Unknown';

            echo "  [$itemId] $title... ";

            try {
                // Get full item details
                $itemData = $ebayApi->getItem($itemId);
                if (!$itemData) {
                    echo "NOT FOUND\n";
                    $errors++;
                    continue;
                }

                $extracted = EbayApi::extractItemData($itemData);
                $productData = $mapper->mapToProduct($extracted);

                // Download primary image
                $images = $extracted['images'];
                if (!empty($images)) {
                    $localImage = $imageHandler->downloadImage($images[0], $extracted['item_id'], 0);
                    if ($localImage) {
                        $productData['image'] = $localImage;
                    }
                }

                // Check if already synced
                $existingId = $ocDb->getProductByListingId($extracted['item_id']);

                if ($existingId) {
                    $ocDb->updateProduct($existingId, $productData);
                    echo "UPDATED (ID: $existingId)\n";
                    $updated++;
                } else {
                    $newId = $ocDb->createProduct($productData);
                    if ($newId) {
                        // Download additional images
                        if (count($images) > 1) {
                            $downloaded = $imageHandler->downloadImages(array_slice($images, 1), $extracted['item_id']);
                            foreach ($downloaded as $i => $imgPath) {
                                $ocDb->createProductImage($newId, $imgPath, $i + 1);
                            }
                        }
                        echo "CREATED (ID: $newId)\n";
                        $created++;
                    } else {
                        echo "CREATE FAILED\n";
                        $errors++;
                    }
                }
            } catch (Exception $e) {
                echo "ERROR: " . $e->getMessage() . "\n";
                $errors++;
            }
        }

        echo "\nDone! Created: $created, Updated: $updated, Errors: $errors\n";

    } catch (Exception $e) {
        echo "FATAL: " . $e->getMessage() . "\n";
        exit(1);
    }
}

/**
 * Search eBay listings.
 */
function handleEbaySearch(array $config, array $queryParts): void
{
    $query = implode(' ', $queryParts);
    if (empty($query)) {
        echo "Usage: php sync.php ebay:search SEARCH_TERMS\n";
        exit(1);
    }

    try {
        $ebayApi = new EbayApi($config['ebay_api']);
        echo "Searching eBay for: $query\n\n";

        $results = $ebayApi->searchItems($query, 20);
        $items = $results['items'] ?? [];

        if (empty($items)) {
            echo "No results found. Try a different search.\n";
            return;
        }

        foreach ($items as $item) {
            $itemId = $item['itemId'] ?? '';
            $title = $item['title'] ?? '?';
            $price = $item['price'] ?? [];
            $amount = $price['value'] ?? $price[0]['value'] ?? '?';
            echo "  [$itemId] $title - \$$amount\n";
        }
        echo "\nTotal: " . count($items) . "\n";
        echo "To sync an item: php sync.php sync:one ITEM_ID\n";

    } catch (Exception $e) {
        echo "ERROR: " . $e->getMessage() . "\n";
        exit(1);
    }
}

/**
 * Show help.
 */
function showHelp(): void
{
    $help = <<<HELP
eBay → OpenCart Sync Tool

Usage:
  php sync.php sync:all           Sync all listings (from ebayflip DB)
  php sync.php sync:one ITEM_ID   Sync a single eBay item by ID (e.g. v1|12345|6789)
  php sync.php sync:seller NAME   Search eBay by seller username and sync all
  php sync.php status             Show sync status summary
  php sync.php ebay:search QUERY  Search eBay listings
  php sync.php oc:categories      List OpenCart categories
  php sync.php db:inspect         Inspect eBay flip database structure
  php sync.php auth:url           Generate OAuth authorization URL
  php sync.php auth:code CODE     Exchange authorization code for tokens

Setup:
  1. Copy config.example.php to config.php
  2. Fill in database credentials and eBay API keys
  3. Run 'php sync.php auth:url' to get OAuth URL
  4. Authorize in browser, then run 'php sync.php auth:code YOUR_CODE'
  5. Update config.php with the tokens
  6. Run 'php sync.php sync:all' or 'php sync.php sync:seller YOUR_USERNAME'

HELP;
    echo $help;
}
