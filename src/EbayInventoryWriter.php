<?php
/**
 * eBay Inventory Writer
 *
 * Handles eBay Trading API calls to end listings.
 * Requires OAuth access token with https://api.ebay.com/oauth/api_scope/sell.item scope.
 *
 * @package OpencartMarketplaceSync
 */

class EbayInventoryWriter
{
    private array $config;
    private ?string $accessToken = null;
    private int $siteId;

    public const ENDPOINT_PRODUCTION = 'https://api.ebay.com/ws/api.dll';
    public const ENDPOINT_SANDBOX    = 'https://api.sandbox.ebay.com/ws/api.dll';
    public const COMPATIBILITY_LEVEL = 1267;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->siteId = $config['site_id'] ?? 0; // 0 = US
    }

    /**
     * Set an access token directly (avoids refreshing if already available).
     */
    public function setAccessToken(string $token): void
    {
        $this->accessToken = $token;
    }

    /**
     * Get a valid access token, refreshing if expired.
     *
     * @return string|null Bearer token or null on failure
     */
    public function getAccessToken(): ?string
    {
        // If we already have a non-expired token, reuse it
        if ($this->accessToken) {
            return $this->accessToken;
        }

        $api = $this->config['ebay_api'] ?? [];

        // Check stored access token expiry
        $expiry = $api['token_expiry'] ?? 0;
        if (time() < $expiry && !empty($api['access_token'])) {
            $this->accessToken = $api['access_token'];
            return $this->accessToken;
        }

        // Refresh using refresh token
        return $this->refreshAccessToken();
    }

    /**
     * Refresh the access token using the refresh token.
     */
    private function refreshAccessToken(): ?string
    {
        $api = $this->config['ebay_api'] ?? [];

        $refreshToken = $api['refresh_token'] ?? '';
        if (empty($refreshToken)) {
            error_log('EbayInventoryWriter: No refresh token available');
            return null;
        }

        $ch = curl_init('https://api.ebay.com/identity/v1/oauth2/token');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERPWD        => $api['app_id'] . ':' . $api['cert_id'],
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_POSTFIELDS     => http_build_query([
                'grant_type'    => 'refresh_token',
                'refresh_token' => $refreshToken,
                'scope'         => $this->getRequiredScopes(),
            ]),
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            error_log('EbayInventoryWriter: Token refresh failed (HTTP ' . $httpCode . '): ' . $response);
            return null;
        }

        $data = json_decode($response, true);
        $this->accessToken = $data['access_token'] ?? null;

        return $this->accessToken;
    }

    /**
     * Get the scopes required for sell operations.
     */
    public function getRequiredScopes(): string
    {
        return 'https://api.ebay.com/oauth/api_scope/sell.item';
    }

    /**
     * Build the OAuth URL for user consent (first-time auth flow).
     */
    public function buildAuthUrl(): string
    {
        $api = $this->config['ebay_api'] ?? [];
        $params = http_build_query([
            'client_id'     => $api['app_id'] ?? '',
            'redirect_uri'  => $api['redirect_uri'] ?? '',
            'response_type' => 'code',
            'scope'         => $this->getRequiredScopes(),
        ]);
        return 'https://auth.ebay.com/oauth2/authorize?' . $params;
    }

    /**
     * Exchange an authorization code for tokens.
     * Returns ['access_token' => '', 'refresh_token' => '', 'expires_in' => N] or null.
     */
    public function exchangeAuthCode(string $code): ?array
    {
        $api = $this->config['ebay_api'] ?? [];

        $ch = curl_init('https://api.ebay.com/identity/v1/oauth2/token');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERPWD        => $api['app_id'] . ':' . $api['cert_id'],
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_POSTFIELDS     => http_build_query([
                'grant_type'   => 'authorization_code',
                'code'         => $code,
                'redirect_uri' => $api['redirect_uri'] ?? '',
            ]),
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            error_log('EbayInventoryWriter: Auth code exchange failed (HTTP ' . $httpCode . '): ' . $response);
            return null;
        }

        return json_decode($response, true);
    }

    /**
     * End an eBay listing via the Trading API.
     *
     * @param string $ebayItemId Numeric eBay item ID (e.g., "335678901234")
     * @param string $reason     Ending reason: NotAvailable, Incorrect, LostOrBroken, etc.
     * @return array ['success' => bool, 'end_time' => string|null, 'error' => string|null]
     */
    public function endItem(string $ebayItemId, string $reason = 'NotAvailable'): array
    {
        $token = $this->getAccessToken();
        if (!$token) {
            return ['success' => false, 'end_time' => null, 'error' => 'No access token available'];
        }

        // Strip any prefix (e.g., "v1|") to get bare numeric ID
        $numericId = preg_replace('/[^0-9]/', '', $ebayItemId);
        if (empty($numericId)) {
            return ['success' => false, 'end_time' => null, 'error' => 'Invalid eBay item ID'];
        }

        $xml = $this->buildEndItemRequest($numericId, $reason);
        $response = $this->callTradingApi('EndItem', $xml);

        return $this->parseEndItemResponse($response);
    }

    /**
     * Build the EndItemRequest XML.
     */
    private function buildEndItemRequest(string $itemId, string $reason): string
    {
        $reasonMap = [
            'NotAvailable'  => 'NotAvailable',
            'Incorrect'     => 'Incorrect',
            'LostOrBroken'  => 'LostOrBroken',
            'OtherListingError' => 'OtherListingError',
        ];
        $reason = $reasonMap[$reason] ?? 'NotAvailable';

        return '<?xml version="1.0" encoding="utf-8"?>
<EndItemRequest xmlns="urn:ebay:apis:eBLBaseComponents">
  <RequesterCredentials>
    <eBayAuthToken>' . htmlspecialchars($this->accessToken, ENT_XML1, 'UTF-8') . '</eBayAuthToken>
  </RequesterCredentials>
  <ItemID>' . htmlspecialchars($itemId, ENT_XML1, 'UTF-8') . '</ItemID>
  <EndingReason>' . $reason . '</EndingReason>
  <Message>This item was sold on our store.</Message>
</EndItemRequest>';
    }

    /**
     * Call the eBay Trading API.
     */
    private function callTradingApi(string $callName, string $xmlBody): string
    {
        $api = $this->config['ebay_api'] ?? [];
        $env = $api['environment'] ?? 'PRODUCTION';
        $endpoint = $env === 'SANDBOX' ? self::ENDPOINT_SANDBOX : self::ENDPOINT_PRODUCTION;

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS     => $xmlBody,
            CURLOPT_HTTPHEADER     => [
                'X-EBAY-API-COMPATIBILITY-LEVEL: ' . self::COMPATIBILITY_LEVEL,
                'X-EBAY-API-CALL-NAME: ' . $callName,
                'X-EBAY-API-SITEID: ' . $this->siteId,
                'Content-Type: text/xml; charset=utf-8',
            ],
            CURLOPT_TIMEOUT        => 15,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            return '<EndItemResponse><Ack>Failure</Ack><Errors><ShortMessage>HTTP ' . $httpCode . '</ShortMessage></Errors></EndItemResponse>';
        }

        return $response;
    }

    /**
     * Parse the EndItemResponse XML.
     */
    private function parseEndItemResponse(string $xml): array
    {
        $result = ['success' => false, 'end_time' => null, 'error' => null];

        libxml_use_internal_errors(true);
        $dom = simplexml_load_string($xml);
        if (!$dom) {
            $result['error'] = 'Failed to parse eBay response XML';
            return $result;
        }

        // Register the namespace
        $ns = 'urn:ebay:apis:eBLBaseComponents';
        $dom->registerXPathNamespace('ebay', $ns);

        $ack = (string)$dom->Ack;

        if ($ack === 'Success' || $ack === 'Warning') {
            $result['success'] = true;
            $result['end_time'] = (string)$dom->EndTime;
        } else {
            $errors = $dom->xpath('//ebay:Errors');
            $errorMsg = [];
            if ($errors) {
                foreach ($errors as $err) {
                    $errorMsg[] = (string)$err->ShortMessage . ': ' . (string)$err->LongMessage;
                }
            }
            $result['error'] = implode('; ', $errorMsg) ?: 'Unknown eBay API error';

            // Log the full response for debugging
            error_log('EbayInventoryWriter::endItem failed. Response: ' . $xml);
        }

        return $result;
    }
}
