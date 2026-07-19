<?php
namespace Opencart\Catalog\Model\Extension\Opencart\Shipping;

class Usps extends \Opencart\System\Engine\Model
{
    public function getQuote(array $address): array
    {
        $this->load->language('extension/opencart/shipping/usps');

        if (!$this->config->get('shipping_usps_status')) {
            return [];
        }

        $geoZoneId = (int)$this->config->get('shipping_usps_geo_zone_id');
        if ($geoZoneId && !$this->isInGeoZone($address, $geoZoneId)) {
            return [];
        }

        $weight = $this->cart->getWeight();
        $weightOz = $this->toOz($weight, (int)$this->config->get('config_weight_class_id'));
        if ($weightOz <= 0) {
            $weightOz = (float)($this->config->get('shipping_usps_default_weight') ?: 4);
        }

        $lClass = (int)$this->config->get('config_length_class_id');
        $len = $this->toIn((float)($this->config->get('shipping_usps_default_length') ?: 7), $lClass);
        $wid = $this->toIn((float)($this->config->get('shipping_usps_default_width') ?: 4), $lClass);
        $hei = $this->toIn((float)($this->config->get('shipping_usps_default_height') ?: 1), $lClass);

        $origin = preg_replace('/[^0-9]/', '', $this->config->get('config_postcode') ?: '77001');
        $dest = preg_replace('/[^0-9]/', '', $address['postcode'] ?? '10001');

        $token = $this->getToken();
        if (!$token) {
            return [];
        }

        $today = date('Y-m-d');
        $svcs = $this->config->get('shipping_usps_services') ?: 'PARCEL_SELECT';
        if (!is_array($svcs)) {
            $svcs = explode(',', $svcs);
        }

        $quotes = [];
        foreach ($svcs as $mc) {
            $mc = trim($mc);
            $rate = $this->fetchRate($token, $origin, $dest, $weightOz, $len, $wid, $hei, $mc, $today);
            if ($rate !== null) {
                $fee = (float)($this->config->get('shipping_usps_handling_fee') ?: 0);
                $pct = (float)($this->config->get('shipping_usps_handling_percent') ?: 0);
                $total = $rate + $fee + ($rate * $pct / 100);
                $name = match ($mc) {
                    'PARCEL_SELECT' => 'Ground Advantage',
                    'PRIORITY_MAIL' => 'Priority Mail',
                    default => $mc,
                };
                $quotes[] = [
                    'code'         => 'usps.' . $mc,
                    'title'        => $name,
                    'cost'         => round($total, 2),
                    'tax_class_id' => (int)($this->config->get('shipping_usps_tax_class_id') ?: 0),
                    'text'         => $this->currency->format(
                        $this->tax->calculate(round($total, 2), (int)($this->config->get('shipping_usps_tax_class_id') ?: 0), $this->config->get('config_tax')),
                        $this->session->data['currency']
                    ),
                ];
            }
        }

        if (empty($quotes)) {
            return [];
        }

        return [
            'code'       => 'usps',
            'title'      => $this->language->get('text_title'),
            'quote'      => $quotes,
            'sort_order' => (int)($this->config->get('shipping_usps_sort_order') ?: 1),
            'error'      => false,
        ];
    }

    private function fetchRate(string $token, string $origin, string $dest, float $w, float $l, float $wi, float $h, string $mc, string $date): ?float
    {
        $ck = 'usps_rate_' . md5("$origin$dest$w$l$wi$h$mc$date");
        $cached = $this->cache->get($ck);
        if ($cached !== null) {
            return $cached === false ? null : (float)$cached;
        }

        $body = json_encode([
            'originZIPCode' => $origin, 'destinationZIPCode' => $dest,
            'weight' => max(0.1, $w), 'length' => max(0.1, $l),
            'width' => max(0.1, $wi), 'height' => max(0.1, $h),
            'mailClass' => $mc, 'priceType' => 'COMMERCIAL', 'mailingDate' => $date,
        ]);

        $ch = curl_init('https://apis.usps.com/prices/v3/total-rates/search');
        $auth = 'Authorization: Bearer ' . $token;
        curl_setopt_array($ch, [
            CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', $auth],
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($code !== 200) {
            $this->log->write("USPS rate error ($mc): HTTP $code");
            $this->cache->set($ck, false, 300);
            return null;
        }

        $data = json_decode($resp, true);
        foreach ($data['rateOptions'] ?? [] as $opt) {
            $t = $opt['totalBasePrice'] ?? null;
            if ($t && $t > 0) {
                $d = $opt['rates'][0]['description'] ?? '';
                if (preg_match('/Tray|Tub|Box$|DDU|DSCF|ADC|PMOD|Full|Half|Extended/i', $d)) continue;
                $this->cache->set($ck, $t, 3600);
                return (float)$t;
            }
        }
        $this->cache->set($ck, false, 300);
        return null;
    }

    private function getToken(): ?string
    {
        $ck = 'usps_oauth_token';
        $cached = $this->cache->get($ck);
        if ($cached) return $cached;

        $cid = $this->config->get('shipping_usps_client_id');
        $sec = $this->config->get('shipping_usps_client_secret');
        if (empty($cid) || empty($sec)) return null;

        $body = json_encode(['client_id' => $cid, 'client_secret' => $sec, 'grant_type' => 'client_credentials']);
        $ch = curl_init('https://apis.usps.com/oauth2/v3/token');
        curl_setopt_array($ch, [
            CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code !== 200) {
            $this->log->write("USPS OAuth error: HTTP $code");
            return null;
        }

        $d = json_decode($resp, true);
        if ($token = $d['access_token'] ?? null) {
            $this->cache->set($ck, $token, 28000);
        }
        return $token;
    }

    private function isInGeoZone(array $address, int $gzid): bool
    {
        $q = $this->db->query("SELECT gz.geo_zone_id FROM " . DB_PREFIX . "geo_zone gz JOIN " . DB_PREFIX . "zone_to_geo_zone z2gz ON gz.geo_zone_id = z2gz.geo_zone_id WHERE gz.geo_zone_id = " . (int)$gzid . " AND z2gz.country_id = " . (int)($address['country_id'] ?? 0) . " AND (z2gz.zone_id = 0 OR z2gz.zone_id = " . (int)($address['zone_id'] ?? 0) . ")");
        return (bool)$q->num_rows;
    }

    private function toOz(float $w, int $c): float
    {
        return match ($c) { 1 => $w * 35.274, 2 => $w * 0.035274, 3 => $w * 16, 4, 6 => $w, default => $w * 35.274 };
    }

    private function toIn(float $l, int $c): float
    {
        return match ($c) { 1 => $l * 0.393701, 2 => $l * 0.03937, 3 => $l, 4 => $l * 12, default => $l * 0.393701 };
    }
}
