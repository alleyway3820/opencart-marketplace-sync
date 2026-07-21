<?php
/**
 * eBay OAuth Re-auth Helper
 *
 * Your current eBay OAuth token only has the "api_scope" (Browse API)
 * which lets you read listings. To sell items (EndItem via Trading API),
 * you need an additional scope: https://api.ebay.com/oauth/api_scope/sell.item
 *
 * This script walks you through the re-authorization flow.
 *
 * Usage:
 *   php scripts/re-auth-ebay-oauth.php --url
 *     → Prints the URL to visit in your browser
 *
 *   php scripts/re-auth-ebay-oauth.php --code "YOUR_AUTH_CODE"
 *     → Exchanges the auth code for tokens, saves to config
 */

declare(strict_types=1);

$baseDir = dirname(__DIR__);
$configFile = $baseDir . '/config.php';

if (!file_exists($configFile)) {
    fwrite(STDERR, "ERROR: config.php not found at {$configFile}\n");
    exit(1);
}

$config = require $configFile;

$api = $config['ebay_api'] ?? [];
$appId     = $api['app_id'] ?? '';
$certId    = $api['cert_id'] ?? '';
$redirect  = $api['redirect_uri'] ?? '';
$env       = $api['environment'] ?? 'PRODUCTION';

if (empty($appId) || empty($certId) || empty($redirect)) {
    fwrite(STDERR, "ERROR: Missing eBay API credentials in config.php. Check app_id, cert_id, redirect_uri.\n");
    exit(1);
}

// ── Scopes needed ────────────────────────────────────────────────────────────
$scopes = implode(' ', [
    'https://api.ebay.com/oauth/api_scope',                   // Browse API (existing)
    'https://api.ebay.com/oauth/api_scope/sell.item',         // Inventory / EndItem
    'https://api.ebay.com/oauth/api_scope/sell.fulfillment.readonly', // Read orders
]);

// ── Parse action ─────────────────────────────────────────────────────────────
$action = $argv[1] ?? '';

if ($action === '--url') {
    // Step 1: Generate the consent URL
    $params = http_build_query([
        'client_id'     => $appId,
        'redirect_uri'  => $redirect,
        'response_type' => 'code',
        'scope'         => $scopes,
    ]);
    $authUrl = ($env === 'SANDBOX' ? 'https://auth.sandbox.ebay.com/oauth2/authorize?' : 'https://auth.ebay.com/oauth2/authorize?') . $params;

    echo "============================================================\n";
    echo " eBay OAuth Re-Authorization\n";
    echo "============================================================\n\n";
    echo "Step 1: Visit this URL in your browser:\n\n";
    echo "  {$authUrl}\n\n";
    echo "Step 2: Log into eBay and grant the requested permissions.\n\n";
    echo "Step 3: After granting, you'll be redirected to your redirect URI\n";
    echo "        with a ?code= parameter in the URL.\n\n";
    echo "Step 4: Run:\n";
    echo "  php scripts/re-auth-ebay-oauth.php --code \"THE_CODE_FROM_THE_URL\"\n\n";
    echo "Scopes requested: {$scopes}\n";
    echo "============================================================\n";
    exit(0);
}

if ($action === '--code') {
    // Step 2: Exchange auth code for tokens
    $authCode = $argv[2] ?? '';

    if (empty($authCode)) {
        fwrite(STDERR, "Usage: php scripts/re-auth-ebay-oauth.php --code \"AUTH_CODE\"\n");
        exit(1);
    }

    echo "Exchanging authorization code for tokens...\n";

    $tokenUrl = $env === 'SANDBOX'
        ? 'https://api.sandbox.ebay.com/identity/v1/oauth2/token'
        : 'https://api.ebay.com/identity/v1/oauth2/token';

    $ch = curl_init($tokenUrl);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERPWD        => $appId . ':' . $certId,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_POSTFIELDS     => http_build_query([
            'grant_type'   => 'authorization_code',
            'code'         => $authCode,
            'redirect_uri' => $redirect,
        ]),
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        fwrite(STDERR, "ERROR: Token exchange failed (HTTP {$httpCode}):\n{$response}\n");
        exit(1);
    }

    $data = json_decode($response, true);

    $accessToken  = $data['access_token'] ?? '';
    $refreshToken = $data['refresh_token'] ?? '';
    $expiresIn    = $data['expires_in'] ?? 7200;
    $tokenExpiry  = time() + (int)$expiresIn;

    if (empty($accessToken) || empty($refreshToken)) {
        fwrite(STDERR, "ERROR: Missing tokens in response:\n{$response}\n");
        exit(1);
    }

    echo "\nSUCCESS! Tokens received.\n\n";
    echo "Access Token:  " . substr($accessToken, 0, 40) . "...\n";
    echo "Refresh Token: " . substr($refreshToken, 0, 40) . "...\n";
    echo "Expires: " . date('Y-m-d H:i:s', $tokenExpiry) . "\n\n";

    // Check if we can read config.php as a file (not just include it)
    $configContent = file_get_contents($configFile);
    if ($configContent === false) {
        echo "Could not read config.php for auto-update.\n";
        echo "Please add these values to your config.php manually:\n\n";
        echo "  'access_token'  => '{$accessToken}',\n";
        echo "  'refresh_token' => '{$refreshToken}',\n";
        echo "  'token_expiry'  => {$tokenExpiry},\n";
        exit(0);
    }

    // Try to update the config file
    $patterns = [
        // Update existing access_token
        ["/'access_token'\s*=>\s*'[^']*'/", "'access_token'  => '{$accessToken}'"],
        // Update existing refresh_token
        ["/'refresh_token'\s*=>\s*'[^']*'/", "'refresh_token' => '{$refreshToken}'"],
        // Update existing token_expiry
        ["/'token_expiry'\s*=>\s*\d+/", "'token_expiry'  => {$tokenExpiry}"],
    ];

    $updated = false;
    foreach ($patterns as [$pattern, $replacement]) {
        if (preg_match($pattern, $configContent)) {
            $configContent = preg_replace($pattern, $replacement, $configContent, 1);
            $updated = true;
        }
    }

    if (!$updated) {
        // Keys don't exist yet — need to add them after 'cert_id'
        echo "Could not find existing token keys in config.php.\n";
        echo "Please add these lines inside the 'ebay_api' array:\n\n";
        echo "  'access_token'  => '{$accessToken}',\n";
        echo "  'refresh_token' => '{$refreshToken}',\n";
        echo "  'token_expiry'  => {$tokenExpiry},\n";
        exit(0);
    }

    if (file_put_contents($configFile, $configContent) !== false) {
        echo "✓ config.php updated successfully!\n";
        echo "  - access_token set\n";
        echo "  - refresh_token set\n";
        echo "  - token_expiry set to " . date('Y-m-d H:i:s', $tokenExpiry) . "\n\n";
        echo "The access token will auto-refresh when needed via EbayInventoryWriter.\n";
    } else {
        echo "✗ Could not write to config.php (permissions?).\n";
        echo "  Please manually add the tokens shown above.\n";
    }

    exit(0);
}

// ── Help ─────────────────────────────────────────────────────────────────────
echo "Usage:\n";
echo "  php scripts/re-auth-ebay-oauth.php --url\n";
echo "    → Prints the eBay authorization URL\n\n";
echo "  php scripts/re-auth-ebay-oauth.php --code \"AUTH_CODE\"\n";
echo "    → Exchanges the code for tokens and updates config.php\n\n";
echo "This re-authorization adds the 'sell.item' scope so we can\n";
echo "end eBay listings when products sell on your OpenCart store.\n";
