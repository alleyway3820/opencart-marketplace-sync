<?php
/**
 * eBay Browse API Client
 * 
 * Handles OAuth token management and item data retrieval from eBay's Browse API.
 */
class EbayApi
{
    private array $config;
    private ?string $accessToken = null;

    // Sandbox vs Production endpoints
    private const API_BASE = [
        'SANDBOX'    => 'https://api.sandbox.ebay.com',
        'PRODUCTION' => 'https://api.ebay.com',
    ];
    private const AUTH_BASE = [
        'SANDBOX'    => 'https://api.sandbox.ebay.com',
        'PRODUCTION' => 'https://api.ebay.com',
    ];

    public function __construct(array $config)
    {
        $this->config = $config;
        if (!empty($config['access_token'])) {
            $this->accessToken = $config['access_token'];
        }
    }

    /**
     * Get the API base URL for the configured environment.
     */
    private function apiBase(): string
    {
        return self::API_BASE[$this->config['environment']] ?? self::API_BASE['PRODUCTION'];
    }

    /**
     * Ensure we have a valid access token.
     * Uses stored token if available and not expired.
     * @throws RuntimeException if token refresh fails
     */
    public function authenticate(): string
    {
        // If we have a stored token and it's not expired, use it
        if ($this->accessToken && $this->config['token_expiry'] > time() + 60) {
            return $this->accessToken;
        }

        // Otherwise, refresh using the refresh_token
        if (empty($this->config['refresh_token'])) {
            throw new RuntimeException(
                'No refresh token configured. ' .
                'Run: php sync.php auth:login to generate one.'
            );
        }

        return $this->refreshAccessToken();
    }

    /**
     * Refresh the access token using the refresh token.
     */
    private function refreshAccessToken(): string
    {
        $authBase = self::AUTH_BASE[$this->config['environment']] ?? self::AUTH_BASE['PRODUCTION'];
        $credentials = base64_encode($this->config['app_id'] . ':' . $this->config['cert_id']);

        $ch = curl_init($authBase . '/identity/v1/oauth2/token');
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
        $error = curl_error($ch);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new RuntimeException(
                'Token refresh failed (HTTP ' . $httpCode . '): ' . ($response ?: $error)
            );
        }

        $data = json_decode($response, true);
        if (!$data || empty($data['access_token'])) {
            throw new RuntimeException('Invalid token refresh response: ' . $response);
        }

        $this->accessToken = $data['access_token'];
        $this->config['token_expiry'] = time() + ($data['expires_in'] ?? 7200);

        // Log so we can save updated token
        error_log('EBAY_ACCESS_TOKEN=' . $this->accessToken);
        error_log('EBAY_TOKEN_EXPIRY=' . $this->config['token_expiry']);

        return $this->accessToken;
    }

    /**
     * Set an access token directly (from config or saved state).
     */
    public function setAccessToken(string $token, int $expiry = 0): void
    {
        $this->accessToken = $token;
        if ($expiry > 0) {
            $this->config['token_expiry'] = $expiry;
        }
    }

    /**
     * Fetch item details from eBay Browse API.
     * 
     * @param string $ebayItemId The eBay item ID
     * @return array|null Item data, or null if not found
     * @throws RuntimeException on API error
     */
    public function getItem(string $ebayItemId): ?array
    {
        $token = $this->authenticate();
        $url = $this->apiBase() . '/buy/browse/v1/item/' . urlencode($ebayItemId);

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
            throw new RuntimeException(
                'eBay API connection error for item ' . $ebayItemId . ': ' . $error
            );
        }

        // Auto-refresh on 401 and retry once
        if ($httpCode === 401) {
            error_log("EbayApi: Token expired for item $ebayItemId, refreshing...");
            $this->accessToken = null;
            $token = $this->authenticate();
            return $this->getItem($ebayItemId);
        }

        if ($httpCode === 404) {
            return null; // Item not found
        }

        if ($httpCode !== 200) {
            // Try to get a meaningful error message
            $data = json_decode($response, true);
            $msg = $data['errors'][0]['message'] ?? ($response ?: $error);
            throw new RuntimeException(
                'eBay API error for item ' . $ebayItemId . ' (HTTP ' . $httpCode . '): ' . $msg
            );
        }

        return json_decode($response, true);
    }

    /**
     * Search for items by keyword.
      *
      * @param string $query Search keywords
      * @param int $limit Max results (1-200)
      * @param string $offset Pagination offset
      * @return array Results with 'items' array and 'total'
      */
     public function searchItems(string $query, int $limit = 20, string $offset = ''): array
     {
         $token = $this->authenticate();

         $params = [
             'q' => $query,
             'limit' => min($limit, 200),
         ];
         if ($offset) {
             $params['offset'] = $offset;
         }

         $url = $this->apiBase() . '/buy/browse/v1/item_summary/search?' . http_build_query($params);

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
             throw new RuntimeException('eBay search connection error: ' . $error);
         }

         if ($httpCode !== 200) {
             $data = json_decode($response, true);
             $msg = $data['errors'][0]['message'] ?? ($response ?: $error);
             throw new RuntimeException('eBay search error (HTTP ' . $httpCode . '): ' . $msg);
         }

         $data = json_decode($response, true);
         return [
             'items' => $data['itemSummaries'] ?? [],
             'total' => $data['total'] ?? 0,
             'next'  => $data['next'] ?? '',
         ];
     }

     /**
      * Get active listings by seller username.
      *
      * @param string $sellerUsername eBay seller username
      * @param int $limit Max results per page (1-200)
      * @param string $offset Pagination offset
      * @return array Results with 'items' array and 'total'
      */
    public function getSellerListings(string $sellerUsername, int $limit = 200, string $offset = '', string $keyword = ''): array
    {
        $this->authenticate();
        
        $params = [
            'q' => $keyword ?: $sellerUsername,
            'limit' => min($limit, 200),
            'filter' => 'sellers:{' . $sellerUsername . '}',
        ];
        if ($offset) {
            $params['offset'] = $offset;
        }

        $url = $this->apiBase() . '/buy/browse/v1/item_summary/search?' . http_build_query($params);

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
            throw new RuntimeException(
                'eBay seller search connection error: ' . $error
            );
        }

        if ($httpCode === 401) {
            $this->accessToken = null;
            return $this->getSellerListings($sellerUsername, $limit, $offset);
        }

        if ($httpCode !== 200) {
            $data = json_decode($response, true);
            $msg = $data['errors'][0]['message'] ?? ($response ?: $error);
            throw new RuntimeException('eBay seller search error (HTTP ' . $httpCode . '): ' . $msg);
        }

        $data = json_decode($response, true);
        return [
            'items' => $data['itemSummaries'] ?? [],
            'total' => $data['total'] ?? 0,
            'next'  => $data['next'] ?? '',
        ];
    }

    /**
     * Exchange an authorization code for tokens (first-time auth).
     * 
     * @param string $code The authorization code from the OAuth redirect
     * @return array With 'access_token', 'refresh_token', 'expires_in'
     */
    public function exchangeAuthCode(string $code): array
    {
        $authBase = self::AUTH_BASE[$this->config['environment']] ?? self::AUTH_BASE['PRODUCTION'];
        $credentials = base64_encode($this->config['app_id'] . ':' . $this->config['cert_id']);

        $ch = curl_init($authBase . '/identity/v1/oauth2/token');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded',
                'Authorization: Basic ' . $credentials,
            ],
            CURLOPT_POSTFIELDS => http_build_query([
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $this->config['redirect_uri'],
            ]),
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new RuntimeException('Auth code exchange failed (HTTP ' . $httpCode . '): ' . $response);
        }

        return json_decode($response, true);
    }

    /**
     * Generate the OAuth authorization URL for first-time setup.
     */
    public function getAuthUrl(): string
    {
        $authBase = self::AUTH_BASE[$this->config['environment']] ?? self::AUTH_BASE['PRODUCTION'];
        $params = http_build_query([
            'client_id'     => $this->config['app_id'],
            'redirect_uri'  => $this->config['redirect_uri'],
            'response_type' => 'code',
            'prompt'        => 'login',
            'scope'         => 'https://api.ebay.com/oauth/api_scope',
        ]);
        return $authBase . '/identity/v1/oauth2/authorize?' . $params;
    }

    /**
     * Extract meaningful fields from eBay item data.
     */
    public static function extractItemData(array $item): array
    {
        $images = [];
        if (!empty($item['image']['imageUrl'])) {
            $images[] = $item['image']['imageUrl'];
        }
        foreach ($item['additionalImages'] ?? [] as $img) {
            if (!empty($img['imageUrl'])) {
                $images[] = $img['imageUrl'];
            }
        }

        // Get the listing price
        $price = '0.00';
        $priceData = $item['price'] ?? $item['listingPrice'] ?? [];
        if (!empty($priceData['value'])) {
            $price = $priceData['value'];
        } elseif (!empty($priceData[0]['value'])) {
            $price = $priceData[0]['value'];
        }

        // Get shipping cost
        $shippingCost = '0.00';
        if (!empty($item['shippingOptions'][0]['shippingCost']['value'])) {
            $shippingCost = $item['shippingOptions'][0]['shippingCost']['value'];
        }

        // Get condition
        $conditionText = $item['condition'] ?? $item['conditionDescription'] ?? 'New';
        $conditionId = $item['conditionId'] ?? '1000'; // 1000 = New

        // Strip HTML from description
        $description = $item['description'] ?? '';
        if (!empty($item['shortDescription'])) {
            $description = $item['shortDescription'];
        }

        // Get item specifics (attributes)
        $attributes = [];
        foreach ($item['itemSpecifics'] ?? [] as $spec) {
            $name = $spec['name'] ?? '';
            $values = $spec['values'] ?? [];
            if ($name && $values) {
                // Why: eBay returns values as an array of strings or objects.
                // Normalize to comma-separated string for storage.
                $valStrings = array_map(function ($v) {
                    return is_string($v) ? $v : ($v['value'] ?? '');
                }, $values);
                $attributes[$name] = implode(', ', array_filter($valStrings));
            }
        }

        // Get category
        $categoryPaths = $item['categories'] ?? [];
        $primaryCategory = !empty($categoryPaths[0]['categoryId']) 
            ? $categoryPaths[0]['categoryId'] 
            : '';

        // Item location
        $location = '';
        if (!empty($item['itemLocation']['city'])) {
            $location = $item['itemLocation']['city'];
            if (!empty($item['itemLocation']['stateOrProvince'])) {
                $location .= ', ' . $item['itemLocation']['stateOrProvince'];
            }
        }

        return [
            'item_id'       => $item['itemId'] ?? '',
            'title'         => $item['title'] ?? '',
            'price'         => $price,
            'currency'      => $priceData['currency'] ?? 'USD',
            'shipping_cost' => $shippingCost,
            'condition'     => $conditionText,
            'condition_id'  => $conditionId,
            'quantity'      => $item['quantity'] ?? $item['estimatedAvailabilities'][0]['estimatedAvailableQuantity'] ?? 1,
            'description'   => $description,
            'images'        => $images,
            'category_id'   => $primaryCategory,
            'category_name' => $categoryPaths[0]['categoryName'] ?? '',
            'attributes'    => $attributes,
            'location'      => $location,
            'listing_url'   => $item['itemWebUrl'] ?? '',
            'listing_status' => $item['listingStatus'] ?? 'ACTIVE',
            'returns_accepted' => !empty($item['returnTerms']['returnsAccepted']),
            'return_period' => $item['returnTerms']['returnPeriod']['value'] ?? 0,
            'gtin'          => $item['gtin'] ?? '',
            'brand'         => $item['brand'] ?? '',
            'mpn'           => $item['mpn'] ?? '',
            'epid'          => $item['epid'] ?? '',
        ];
    }
}
