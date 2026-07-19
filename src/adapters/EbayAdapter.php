<?php
/**
 * eBay Marketplace Adapter
 *
 * Implements MarketplaceAdapter for eBay's Browse API.
 *
 * @package OpencartMarketplaceSync
 */
require_once __DIR__ . '/MarketplaceAdapter.php';
require_once __DIR__ . '/ListingData.php';

class EbayAdapter implements MarketplaceAdapter
{
    private array $config;
    private bool $authenticated = false;
    private ?string $accessToken = null;

    private const API_BASE = 'https://api.ebay.com';
    private const AUTH_BASE = 'https://api.ebay.com';

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function getName(): string
    {
        return 'eBay';
    }

    public static function getConfigKeys(): array
    {
        return [
            'app_id'       => 'eBay App ID (Client ID) from developer.ebay.com',
            'cert_id'      => 'eBay Cert ID (Client Secret)',
            'dev_id'       => 'eBay Dev ID',
            'redirect_uri' => 'OAuth redirect URI (must match eBay app settings)',
            'refresh_token'=> 'OAuth refresh token (generated via auth flow)',
            'access_token' => 'Current access token (auto-refreshed)',
            'token_expiry' => 'Access token expiry timestamp',
        ];
    }

    public function authenticate(): void
    {
        if ($this->accessToken && isset($this->config['token_expiry'])
            && $this->config['token_expiry'] > time() + 60) {
            $this->authenticated = true;
            return;
        }

        if (empty($this->config['refresh_token'])) {
            throw new RuntimeException(
                'No refresh token. Run the OAuth flow first.'
            );
        }

        $this->refreshAccessToken();
        $this->authenticated = true;
    }

    public function isAuthenticated(): bool
    {
        return $this->authenticated;
    }

    public function fetchListings(): array
    {
        $this->authenticate();
        // For eBay, we search by seller username from config
        $seller = $this->config['seller_username'] ?? '';
        if (empty($seller)) {
            throw new RuntimeException('No seller_username configured for eBay');
        }
        return $this->search('seller:' . $seller, 200)['items'] ?? [];
    }

    public function getListing(string $listingId): ?array
    {
        $this->authenticate();
        $url = self::API_BASE . '/buy/browse/v1/item/' . urlencode($listingId);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->accessToken,
                'Content-Type: application/json',
                'X-EBAY-C-MARKETPLACE-ID: EBAY_US',
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        $errno = curl_errno($ch);
        curl_close($ch);

        if ($errno !== 0) {
            throw new RuntimeException('eBay connection error: ' . $error);
        }
        if ($httpCode === 401) {
            $this->refreshAccessToken();
            return $this->getListing($listingId);
        }
        if ($httpCode === 404) {
            return null;
        }
        if ($httpCode !== 200) {
            $data = json_decode($response, true);
            throw new RuntimeException(
                'eBay API error: ' . ($data['errors'][0]['message'] ?? $response)
            );
        }

        $raw = json_decode($response, true);
        return $this->extractListingData($raw);
    }

    public function extractListingData(array $rawData): array
    {
        $images = [];
        if (!empty($rawData['image']['imageUrl'])) {
            $images[] = $rawData['image']['imageUrl'];
        }
        foreach ($rawData['additionalImages'] ?? [] as $img) {
            if (!empty($img['imageUrl'])) {
                $images[] = $img['imageUrl'];
            }
        }

        $price = $rawData['price']['value']
            ?? $rawData['listingPrice'][0]['value'] ?? '0.00';

        $attributes = [];
        foreach ($rawData['itemSpecifics'] ?? [] as $spec) {
            $name = $spec['name'] ?? '';
            $values = $spec['values'] ?? [];
            if ($name && $values) {
                $vals = array_map(fn($v) => is_string($v) ? $v : ($v['value'] ?? ''), $values);
                $attributes[$name] = implode(', ', array_filter($vals));
            }
        }

        $categoryPaths = $rawData['categories'] ?? [];

        return (new ListingData([
            'itemId'      => $rawData['itemId'] ?? '',
            'title'       => $rawData['title'] ?? '',
            'price'       => $price,
            'currency'    => $rawData['price']['currency'] ?? 'USD',
            'description' => $rawData['shortDescription'] ?? $rawData['description'] ?? '',
            'images'      => $images,
            'quantity'    => $rawData['quantity']
                ?? $rawData['estimatedAvailabilities'][0]['estimatedAvailableQuantity']
                ?? 1,
            'categoryId'  => $categoryPaths[0]['categoryId'] ?? '',
            'categoryName'=> $categoryPaths[0]['categoryName'] ?? '',
            'condition'   => $rawData['condition'] ?? 'New',
            'attributes'  => $attributes,
            'listingUrl'  => $rawData['itemWebUrl'] ?? '',
            'shippingCost'=> $rawData['shippingOptions'][0]['shippingCost']['value'] ?? '0.00',
            'sourceMarketplace' => 'eBay',
            'gtin'        => $rawData['gtin'] ?? '',
            'brand'       => $rawData['brand'] ?? '',
            'mpn'         => $rawData['mpn'] ?? '',
        ]))->toArray();
    }

    public function search(string $query, int $limit = 20): array
    {
        $this->authenticate();
        $params = ['q' => $query, 'limit' => min($limit, 200)];

        $url = self::API_BASE . '/buy/browse/v1/item_summary/search?' . http_build_query($params);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->accessToken,
                'Content-Type: application/json',
                'X-EBAY-C-MARKETPLACE-ID: EBAY_US',
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($errno !== 0) {
            throw new RuntimeException('eBay search error: ' . $error);
        }
        if ($httpCode === 401) {
            $this->refreshAccessToken();
            return $this->search($query, $limit);
        }
        if ($httpCode !== 200) {
            $data = json_decode($response, true);
            throw new RuntimeException(
                'eBay search error: ' . ($data['errors'][0]['message'] ?? $response)
            );
        }

        $data = json_decode($response, true);
        return [
            'items' => array_map(
                fn($item) => $this->getListing($item['itemId'] ?? ''),
                $data['itemSummaries'] ?? []
            ),
            'total' => $data['total'] ?? 0,
        ];
    }

    private function refreshAccessToken(): void
    {
        $credentials = base64_encode(
            $this->config['app_id'] . ':' . $this->config['cert_id']
        );

        $ch = curl_init(self::AUTH_BASE . '/identity/v1/oauth2/token');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded',
                'Authorization: Basic ' . $credentials,
            ],
            CURLOPT_POSTFIELDS => http_build_query([
                'grant_type' => 'refresh_token',
                'refresh_token' => $this->config['refresh_token'],
                'scope' => 'https://api.ebay.com/oauth/api_scope',
            ]),
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new RuntimeException('eBay token refresh failed (HTTP ' . $httpCode . ')');
        }

        $data = json_decode($response, true);
        $this->accessToken = $data['access_token'];
        $this->config['token_expiry'] = time() + ($data['expires_in'] ?? 7200);
    }
}
