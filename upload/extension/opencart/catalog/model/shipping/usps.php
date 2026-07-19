<?php
/**
 * USPS Shipping Module — Catalog Rate Model
 * Uses USPS Pricing API v3 (replaces deprecated Web Tools)
 */
class ModelExtensionShippingUsps extends Model
{
    // Cache token for up to 8 hours (token expires in 8h)
    private const TOKEN_CACHE_KEY = 'shipping_usps_oauth_token';
    private const TOKEN_CACHE_TTL = 28000; // 8 hours minus margin

    public function getQuote(array $address): array
    {
        $this->load->language('extension/shipping/usps');
        $settings = $this->config->filtered('shipping_usps_');

        $geoZoneId = (int)($settings['shipping_usps_geo_zone_id'] ?? 0);
        if ($geoZoneId && !$this->isInGeoZone($address, $geoZoneId)) {
            return [];
        }

        $weight = $this->cart->getWeight();
        $weightClassId = $this->config->get('config_weight_class_id');
        $weightOz = $this->convertToOunces($weight, $weightClassId);

        if ($weightOz <= 0) {
            $weightOz = (float)($settings['shipping_usps_default_weight'] ?? 4);
        }

        $length = $settings['shipping_usps_default_length'] ?? 7;
        $width = $settings['shipping_usps_default_width'] ?? 4;
        $height = $settings['shipping_usps_default_height'] ?? 1;
        $lengthClassId = $this->config->get('config_length_class_id');
        $lengthIn = $this->convertToInches($length, $lengthClassId);
        $widthIn = $this->convertToInches($width, $lengthClassId);
        $heightIn = $this->convertToInches($height, $lengthClassId);

        $originZip = preg_replace('/[^0-9]/', '', $this->config->get('config_postcode') ?: '77001');
        $destZip = preg_replace('/[^0-9]/', '', $address['postcode'] ?? '10001');

        $token = $this->getAccessToken();
        if (!$token) {
            $this->log->write('USPS: Failed to get OAuth token');
            return [];
        }

        $today = date('Y-m-d');
        $enabledServices = $settings['shipping_usps_services'] ?? ['PARCEL_SELECT'];

        if (!is_array($enabledServices)) {
            $enabledServices = explode(',', $enabledServices);
        }

        $quoteData = [];

        foreach ($enabledServices as $mailClass) {
            $mailClass = trim($mailClass);
            $rate = $this->getRate($token, $originZip, $destZip, $weightOz, $lengthIn, $widthIn, $heightIn, $mailClass, $today);

            if ($rate !== null) {
                $serviceName = $this->getServiceName($mailClass);
                $handlingFee = (float)($settings['shipping_usps_handling_fee'] ?? 0);
                $handlingPercent = (float)($settings['shipping_usps_handling_percent'] ?? 0);
                $total = $rate + $handlingFee + ($rate * $handlingPercent / 100);

                $quoteData[] = [
                    'code'         => 'usps.' . $mailClass,
                    'title'        => $serviceName,
                    'cost'         => round($total, 2),
                    'tax_class_id' => (int)($settings['shipping_usps_tax_class_id'] ?? 0),
                    'text'         => $this->currency->format($this->tax->calculate(round($total, 2), $settings['shipping_usps_tax_class_id'] ?? 0, $this->config->get('config_tax')), $this->session->data['currency']),
                ];
            }
        }

        if (empty($quoteData)) {
            return [];
        }

        $methodData = [
            'code'       => 'usps',
            'title'      => $this->language->get('text_title'),
            'quote'      => $quoteData,
            'sort_order' => (int)($settings['shipping_usps_sort_order'] ?? 1),
            'error'      => false,
        ];

        return $methodData;
    }

    private function getRate(string $token, string $origin, string $dest, float $weightOz, float $length, float $width, float $height, string $mailClass, string $date): ?float
    {
        $cacheKey = 'usps_rate_' . md5("$origin$dest$weightOz$length$width$height$mailClass$date");
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return $cached === false ? null : (float)$cached;
        }

        $clientId = $this->config->get('shipping_usps_client_id');
        $clientSecret = $this->config->get('shipping_usps_client_secret');

        $postData = json_encode([
            'originZIPCode'      => $origin,
            'destinationZIPCode' => $dest,
            'weight'             => $weightOz,
            'length'             => max(0.1, $length),
            'width'              => max(0.1, $width),
            'height'             => max(0.1, $height),
            'mailClass'          => $mailClass,
            'priceType'          => 'COMMERCIAL',
            'mailingDate'        => $date,
        ]);

        $ch = curl_init('https://apis.usps.com/prices/v3/total-rates/search');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postData,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $token,
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($httpCode !== 200) {
            $this->log->write("USPS API error ($mailClass): HTTP $httpCode - $error");
            $this->cache->set($cacheKey, false);
            return null;
        }

        $data = json_decode($response, true);
        $rateOptions = $data['rateOptions'] ?? [];

        // Find the cheapest non-specialized rate for this mail class
        $cheapest = null;
        foreach ($rateOptions as $option) {
            $total = $option['totalBasePrice'] ?? null;
            if ($total !== null && $total > 0) {
                $desc = $option['rates'][0]['description'] ?? '';
                // Skip tray/box/tub rates - they're for bulk mailers
                if (preg_match('/Tray|Tub|Box|DDU|DSCF|ADC|ASF|DRPDC|PMOD|Full|Half|Extended|Flat Tub/i', $desc)) {
                    continue;
                }
                $cheapest = $total;
                break;
            }
        }

        if ($cheapest === null && !empty($rateOptions)) {
            $cheapest = $rateOptions[0]['totalBasePrice'] ?? null;
        }

        if ($cheapest !== null) {
            $this->cache->set($cacheKey, $cheapest);
            return (float)$cheapest;
        }

        $this->cache->set($cacheKey, false);
        return null;
    }

    private function getAccessToken(): ?string
    {
        $cached = $this->cache->get(self::TOKEN_CACHE_KEY);
        if ($cached) {
            return $cached;
        }

        $clientId = $this->config->get('shipping_usps_client_id');
        $clientSecret = $this->config->get('shipping_usps_client_secret');

        if (empty($clientId) || empty($clientSecret)) {
            return null;
        }

        $postData = json_encode([
            'client_id'     => $clientId,
            'client_secret' => $clientSecret,
            'grant_type'    => 'client_credentials',
        ]);

        $ch = curl_init('https://apis.usps.com/oauth2/v3/token');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postData,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            $this->log->write('USPS OAuth error: HTTP ' . $httpCode);
            return null;
        }

        $data = json_decode($response, true);
        $token = $data['access_token'] ?? null;

        if ($token) {
            $this->cache->set(self::TOKEN_CACHE_KEY, $token, self::TOKEN_CACHE_TTL);
        }

        return $token;
    }

    private function isInGeoZone(array $address, int $geoZoneId): bool
    {
        // Use OpenCart's built-in geo zone validation
        $query = $this->db->query("
            SELECT DISTINCT gz.geo_zone_id
            FROM " . DB_PREFIX . "geo_zone gz
            JOIN " . DB_PREFIX . "zone_to_geo_zone z2gz ON gz.geo_zone_id = z2gz.geo_zone_id
            WHERE gz.geo_zone_id = " . (int)$geoZoneId . "
            AND z2gz.country_id = " . (int)($address['country_id'] ?? 0) . "
            AND (z2gz.zone_id = 0 OR z2gz.zone_id = " . (int)($address['zone_id'] ?? 0) . ")
        ");

        return $query->num_rows > 0;
    }

    private function convertToOunces(float $weight, int $classId): float
    {
        // OC4 default weight classes: 1=kg, 2=g, 3=lb, 4=oz
        return match ($classId) {
            1 => $weight * 35.274,  // kg -> oz
            2 => $weight * 0.035274, // g -> oz
            3 => $weight * 16,       // lb -> oz
            4 => $weight,            // already oz
            default => $weight * 35.274,
        };
    }

    private function convertToInches(float $length, int $classId): float
    {
        // OC4 default length classes: 1=cm, 2=mm, 3=in, 4=ft
        return match ($classId) {
            1 => $length * 0.393701, // cm -> in
            2 => $length * 0.0393701, // mm -> in
            3 => $length,             // already inches
            4 => $length * 12,        // ft -> in
            default => $length * 0.393701,
        };
    }

    private function getServiceName(string $mailClass): string
    {
        return match ($mailClass) {
            'PARCEL_SELECT' => 'Ground Advantage',
            'PRIORITY_MAIL' => 'Priority Mail',
            'PRIORITY_MAIL_EXPRESS' => 'Priority Mail Express',
            default => $mailClass,
        };
    }
}
