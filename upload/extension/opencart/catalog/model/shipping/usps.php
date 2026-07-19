<?php
namespace Opencart\Catalog\Model\Extension\Opencart\Shipping;
/**
 * Class Usps
 *
 * USPS Web Tools shipping module for OpenCart 4.x
 * Communicates with USPS RateV4 API to get real-time shipping rates.
 *
 * @package Opencart\Catalog\Model\Extension\Opencart\Shipping
 */
class Usps extends \Opencart\System\Engine\Model {
	/**
	 * USPS API endpoint
	 */
	const USPS_API_URL = 'https://secure.shippingapis.com/ShippingAPI.dll';
	const USPS_TEST_API_URL = 'https://secure.shippingapis.com/ShippingAPI.dll';

	/**
	 * USPS Service ID to internal code mapping
	 */
	private array $services = [
		'GROUND_ADVANTAGE'    => ['id' => 0,   'name' => 'Ground Advantage',      'first_class' => 'PACKAGE SERVICE'],
		'PRIORITY_MAIL'       => ['id' => 1,   'name' => 'Priority Mail',          'first_class' => null],
		'PRIORITY_EXPRESS'    => ['id' => 3,   'name' => 'Priority Mail Express',  'first_class' => null],
	];

	/**
	 * Cache TTL in seconds (1 hour)
	 */
	const CACHE_TTL = 3600;

	/**
	 * Get shipping quotes from USPS API
	 *
	 * @param array $address
	 * @return array
	 */
	public function getQuote(array $address): array {
		$this->load->language('extension/opencart/shipping/usps');

		$method_data = [];

		// Check if USPS is enabled
		if (!$this->config->get('shipping_usps_status')) {
			return $method_data;
		}

		// Check geo zone
		if (!$this->validateGeoZone($address)) {
			return $method_data;
		}

		// Get enabled services
		$enabled_services = $this->getEnabledServices();

		if (empty($enabled_services)) {
			return $method_data;
		}

		// Only quote domestic US for now
		if ($address['iso_code_2'] !== 'US' && $address['iso_code_3'] !== 'USA') {
			return $method_data;
		}

		// Build package info from cart products
		$package = $this->getPackageInfo($address);

		if ($package === null) {
			return $method_data;
		}

		// Try to get from cache
		$cache_key = 'usps_rates_' . md5(serialize($package) . '_' . implode(',', array_keys($enabled_services)));
		$cached = $this->cache->get($cache_key);

		if ($cached !== null) {
			return $this->formatQuote($cached, $enabled_services);
		}

		// Query USPS API
		$rates = $this->queryUspsApi($package, $enabled_services);

		if ($rates === null || empty($rates)) {
			return $method_data;
		}

		// Cache the result
		$this->cache->set($cache_key, $rates, self::CACHE_TTL);

		return $this->formatQuote($rates, $enabled_services);
	}

	/**
	 * Validate the shipping address is within the configured geo zone
	 *
	 * @param array $address
	 * @return bool
	 */
	private function validateGeoZone(array $address): bool {
		$geo_zone_id = (int)$this->config->get('shipping_usps_geo_zone_id');

		if (!$geo_zone_id) {
			return true;
		}

		$query = $this->db->query(
			"SELECT * FROM `" . DB_PREFIX . "zone_to_geo_zone`
			 WHERE `geo_zone_id` = '" . (int)$geo_zone_id . "'
			 AND `country_id` = '" . (int)$address['country_id'] . "'
			 AND (`zone_id` = '" . (int)$address['zone_id'] . "' OR `zone_id` = '0')"
		);

		return (bool)$query->num_rows;
	}

	/**
	 * Get which services are enabled in admin settings
	 *
	 * @return array
	 */
	private function getEnabledServices(): array {
		$enabled = [];

		if ($this->config->get('shipping_usps_ground_advantage')) {
			$enabled['GROUND_ADVANTAGE'] = $this->services['GROUND_ADVANTAGE'];
		}
		if ($this->config->get('shipping_usps_priority_mail')) {
			$enabled['PRIORITY_MAIL'] = $this->services['PRIORITY_MAIL'];
		}
		if ($this->config->get('shipping_usps_priority_mail_express')) {
			$enabled['PRIORITY_EXPRESS'] = $this->services['PRIORITY_EXPRESS'];
		}

		return $enabled;
	}

	/**
	 * Calculate total weight and dimensions from cart products
	 *
	 * @param array $address
	 * @return array|null
	 */
	private function getPackageInfo(array $address): ?array {
		// Get the default values from config
		$default_weight = (float)($this->config->get('shipping_usps_default_weight') ?: 4); // oz
		$default_length = (float)($this->config->get('shipping_usps_default_length') ?: 7); // in
		$default_width  = (float)($this->config->get('shipping_usps_default_width') ?: 4);  // in
		$default_height = (float)($this->config->get('shipping_usps_default_height') ?: 1); // in

		$this->load->model('checkout/cart');
		$this->load->model('catalog/product');

		$total_weight_oz = 0;
		$max_length = 0;
		$max_width = 0;
		$total_height = 0;
		$max_single_dimension = 0;
		$has_products = false;

		// Get products from cart
		$products = $this->model_checkout_cart->getProducts();

		if (empty($products)) {
			return null;
		}

		// Get weight class info for conversion
		$weight_class_id = $this->config->get('config_weight_class_id');
		$weight_classes = $this->model_catalog_product->getWeightClasses();

		// Get length class info for conversion
		$length_class_id = $this->config->get('config_length_class_id');

		// Convert config weight class to grams first, then to ounces
		$weight_in_grams_per_unit = 1;
		// weight_class_id 1 = kg (1000g), 2 = g (1g), 5 = lb (453.592g), 6 = oz (28.3495g)
		switch ($weight_class_id) {
			case 1: // kg
				$weight_in_grams_per_unit = 1000;
				break;
			case 2: // g
				$weight_in_grams_per_unit = 1;
				break;
			case 5: // lb
				$weight_in_grams_per_unit = 453.592;
				break;
			case 6: // oz
				$weight_in_grams_per_unit = 28.3495;
				break;
			default:
				// Try weight_class table
				$weight_query = $this->db->query("SELECT `value` FROM `" . DB_PREFIX . "weight_class` WHERE `weight_class_id` = '" . (int)$weight_class_id . "'");
				if ($weight_query->num_rows) {
					$weight_in_grams_per_unit = (float)$weight_query->row['value'];
				}
				break;
		}

		// Convert length class to inches
		$inches_per_unit = 1;
		// length_class_id 1 = cm (0.393701in), 2 = mm (0.0393701in), 3 = in (1in)
		switch ($length_class_id) {
			case 1: // cm
				$inches_per_unit = 0.393701;
				break;
			case 2: // mm
				$inches_per_unit = 0.0393701;
				break;
			case 3: // in
				$inches_per_unit = 1;
				break;
			default:
				$length_query = $this->db->query("SELECT `value` FROM `" . DB_PREFIX . "length_class` WHERE `length_class_id` = '" . (int)$length_class_id . "'");
				if ($length_query->num_rows) {
					$inches_per_unit = (float)$length_query->row['value'];
				}
				break;
		}

		foreach ($products as $product) {
			// Get full product info for dimensions
			$product_info = $this->model_catalog_product->getProduct($product['product_id']);

			if (!$product_info) {
				continue;
			}

			$has_products = true;

			// Weight: convert to ounces
			$weight_oz = 0;
			$product_weight = (float)$product_info['weight'];
			if ($product_weight > 0) {
				// Convert from store's weight unit to grams, then to ounces
				$weight_grams = $product_weight * $weight_in_grams_per_unit;
				$weight_oz = $weight_grams / 28.3495;
			} else {
				$weight_oz = $default_weight;
			}

			$total_weight_oz += $weight_oz * $product['quantity'];

			// Dimensions: convert to inches
			$product_length = (float)$product_info['length'];
			$product_width  = (float)$product_info['width'];
			$product_height = (float)$product_info['height'];

			if ($product_length > 0 && $product_width > 0 && $product_height > 0) {
				$len_in = $product_length * $inches_per_unit;
				$wid_in = $product_width * $inches_per_unit;
				$hei_in = $product_height * $inches_per_unit;

				// Determine which is the longest side
				$dims = [$len_in, $wid_in, $hei_in];
				sort($dims);

				$longest = $dims[2];
				$middle = $dims[1];
				$shortest = $dims[0];

				$max_single_dimension = max($max_single_dimension, $longest);
				$max_length = max($max_length, $len_in);
				$max_width = max($max_width, $wid_in);
				$total_height += $hei_in * $product['quantity'];
			} else {
				$max_length = max($max_length, (float)($max_length ?: $default_length));
				$max_width = max($max_width, (float)($max_width ?: $default_width));
				$total_height += (float)($default_height) * $product['quantity'];
			}
		}

		if (!$has_products) {
			return null;
		}

		// Ensure minimum values
		$total_weight_oz = max($total_weight_oz, 1);
		$max_length = max($max_length, $default_length);
		$max_width = max($max_width, $default_width);
		$total_height = max($total_height, $default_height);

		// USPS limits: max length + girth = 108", max length = 60" for most services
		// For small packages, use dimensions
		if ($max_length > 60) {
			$max_length = 60;
		}

		return [
			'weight_oz'       => round($total_weight_oz, 2),
			'length'          => round($max_length, 2),
			'width'           => round($max_width, 2),
			'height'          => round($total_height, 2),
			'weight_lb'       => max(round($total_weight_oz / 16, 4), 0.1),
			'zip_origin'      => '', // Will be determined
			'zip_destination' => $address['postcode'] ?? '',
		];
	}

	/**
	 * Query the USPS RateV4 API
	 *
	 * @param array $package
	 * @param array $enabled_services
	 * @return array|null
	 */
	private function queryUspsApi(array $package, array $enabled_services): ?array {
		$user_id = $this->config->get('shipping_usps_user_id');

		if (empty($user_id)) {
			$this->logDebug('USPS API Error: No User ID configured');
			return null;
		}

		$zip_destination = $package['zip_destination'];

		if (empty($zip_destination)) {
			$this->logDebug('USPS API Error: No destination zip code');
			return null;
		}

		// Get origin zip from store settings
		$zip_origin = $this->config->get('config_postcode');

		if (empty($zip_origin)) {
			// Fallback to a default US zip if store is non-US
			$zip_origin = '10001'; // New York, NY
		}

		$rates = [];

		foreach ($enabled_services as $code => $service) {
			$rate = $this->querySingleService($user_id, $zip_origin, $zip_destination, $package, $service);

			if ($rate !== null) {
				$rates[$code] = $rate;
			}
		}

		return $rates;
	}

	/**
	 * Query USPS API for a single service
	 *
	 * @param string $user_id
	 * @param string $zip_origin
	 * @param string $zip_destination
	 * @param array $package
	 * @param array $service
	 * @return array|null
	 */
	private function querySingleService(string $user_id, string $zip_origin, string $zip_destination, array $package, array $service): ?array {
		$pounds = floor($package['weight_oz'] / 16);
		$ounces = $package['weight_oz'] - ($pounds * 16);

		// USPS requires at least 0.1 oz for Ground Advantage (PACKAGE SERVICE)
		// and at least 0.5 oz for Priority
		if ($ounces < 0.1) {
			$ounces = 0.1;
		}

		// Build the XML request for RateV4
		$xml = new \DOMDocument('1.0', 'UTF-8');

		$rate_request = $xml->createElement('RateV4Request');
		$rate_request->setAttribute('USERID', $user_id);

		$package_node = $xml->createElement('Package');
		$package_node->setAttribute('ID', '0');

		$package_node->appendChild($xml->createElement('Service', $service['id']));

		if ($service['first_class'] !== null) {
			$package_node->appendChild($xml->createElement('FirstClassMailType', $service['first_class']));
		}

		$package_node->appendChild($xml->createElement('ZipOrigination', $zip_origin));
		$package_node->appendChild($xml->createElement('ZipDestination', $zip_destination));
		$package_node->appendChild($xml->createElement('Pounds', (string)$pounds));
		$package_node->appendChild($xml->createElement('Ounces', number_format($ounces, 1, '.', '')));

		// Container: VARIABLE (non-rectangular) or RECTANGULAR
		$package_node->appendChild($xml->createElement('Container', 'VARIABLE'));

		// Dimensions (in inches)
		$size = 'REGULAR';
		$length = $package['length'];
		$width = $package['width'];
		$height = $package['height'];

		// Determine size based on dimensions
		if ($length > 12 || $width > 12 || $height > 12) {
			$size = 'LARGE';
		}

		$package_node->appendChild($xml->createElement('Size', $size));

		if ($size === 'LARGE') {
			$package_node->appendChild($xml->createElement('Width', number_format($width, 2, '.', '')));
			$package_node->appendChild($xml->createElement('Length', number_format($length, 2, '.', '')));
			$package_node->appendChild($xml->createElement('Height', number_format($height, 2, '.', '')));

			// Girth for non-rectangular
			$girth = 2 * ($width + $height);
			$package_node->appendChild($xml->createElement('Girth', number_format($girth, 2, '.', '')));
		}

		// Machinable
		$package_node->appendChild($xml->createElement('Machinable', 'true'));

		$rate_request->appendChild($package_node);
		$xml->appendChild($rate_request);

		$request_xml = $xml->saveXML();

		$this->logDebug("USPS Request XML for {$service['name']}: " . $request_xml);

		// Make the API call
		$api_url = self::USPS_API_URL;
		$query_string = 'API=RateV4&XML=' . urlencode($request_xml);

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $api_url);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $query_string);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_TIMEOUT, 30);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
		curl_setopt($ch, CURLOPT_USERAGENT, 'OpenCart USPS Module/1.0');

		$response = curl_exec($ch);
		$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$curl_error = curl_error($ch);
		curl_close($ch);

		if ($curl_error) {
			$this->logDebug("USPS CURL Error for {$service['name']}: " . $curl_error);
			return null;
		}

		$this->logDebug("USPS Response XML for {$service['name']} (HTTP $http_code): " . $response);

		if ($http_code !== 200) {
			$this->logDebug("USPS HTTP Error for {$service['name']}: HTTP $http_code");
			return null;
		}

		// Parse the response XML
		return $this->parseUspsResponse($response, $service);
	}

	/**
	 * Parse USPS API response
	 *
	 * @param string $response_xml
	 * @param array $service
	 * @return array|null
	 */
	private function parseUspsResponse(string $response_xml, array $service): ?array {
		libxml_use_internal_errors(true);
		$xml = simplexml_load_string($response_xml);

		if ($xml === false) {
			$errors = libxml_get_errors();
			$this->logDebug("USPS XML Parse Error: " . print_r($errors, true));
			libxml_clear_errors();
			return null;
		}

		// Check for USPS API errors
		if (isset($xml->Number) && isset($xml->Description)) {
			$this->logDebug("USPS API Error: #{$xml->Number} - {$xml->Description}");
			return null;
		}

		// Check for package-level errors
		if (isset($xml->Package)) {
			$package = $xml->Package;

			if (isset($package->Error)) {
				$error_num = (string)$package->Error->Number;
				$error_desc = (string)$package->Error->Description;
				$this->logDebug("USPS Package Error #$error_num: $error_desc for {$service['name']}");
				return null;
			}

			if (isset($package->Postage)) {
				$rate = (float)$package->Postage->Rate;

				if ($rate > 0) {
					$service_name = (string)$package->Postage->MailService;

					// Add handling fee
					$handling_fee = (float)$this->config->get('shipping_usps_handling_fee');
					$handling_pct = (float)$this->config->get('shipping_usps_handling_percentage');

					$total_rate = $rate + $handling_fee;
					if ($handling_pct > 0) {
						$total_rate += ($rate * $handling_pct / 100);
					}

					return [
						'code'   => 'usps.' . strtolower($service['name']),
						'name'   => $service_name,
						'cost'   => round($total_rate, 2),
						'base'   => $rate,
						'text'   => '', // Will be filled in by formatQuote
					];
				}
			}
		}

		return null;
	}

	/**
	 * Format the quote data for OpenCart
	 *
	 * @param array $rates
	 * @param array $enabled_services
	 * @return array
	 */
	private function formatQuote(array $rates, array $enabled_services): array {
		$quote_data = [];

		// Sort rates by price (lowest first)
		uasort($rates, function($a, $b) {
			return $a['cost'] - $b['cost'];
		});

		$tax_class_id = (int)$this->config->get('shipping_usps_tax_class_id');
		$sort_order = (int)$this->config->get('shipping_usps_sort_order');

		foreach ($rates as $code => $rate) {
			$quote_data[$code] = [
				'code'         => 'usps.' . $code,
				'name'         => $rate['name'],
				'cost'         => $rate['cost'],
				'tax_class_id' => $tax_class_id,
				'text'         => $this->currency->format(
					$this->tax->calculate($rate['cost'], $tax_class_id, $this->config->get('config_tax')),
					$this->session->data['currency']
				),
			];
		}

		if (empty($quote_data)) {
			return [];
		}

		return [
			'code'       => 'usps',
			'name'       => $this->language->get('heading_title'),
			'quote'      => $quote_data,
			'sort_order' => $sort_order,
			'error'      => false,
		];
	}

	/**
	 * Log debug information
	 *
	 * @param string $message
	 * @return void
	 */
	private function logDebug(string $message): void {
		if ($this->config->get('shipping_usps_debug')) {
			$this->log->write('[USPS] ' . $message);
		}
	}
}
