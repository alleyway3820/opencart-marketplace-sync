<?php
namespace Opencart\Admin\Controller\Extension\Opencart\Shipping;
/**
 * Class Usps
 *
 * USPS Web Tools shipping module for OpenCart 4.x
 * Supports Ground Advantage, Priority Mail, Priority Mail Express
 *
 * @package Opencart\Admin\Controller\Extension\Opencart\Shipping
 */
class Usps extends \Opencart\System\Engine\Controller {
	/**
	 * index
	 *
	 * @return void
	 */
	public function index(): void {
		$this->load->language('extension/opencart/shipping/usps');

		$this->document->setTitle($this->language->get('heading_title'));

		$data['breadcrumbs'] = [];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'])
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_extension'),
			'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=shipping')
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/opencart/shipping/usps', 'user_token=' . $this->session->data['user_token'])
		];

		$data['save'] = $this->url->link('extension/opencart/shipping/usps.save', 'user_token=' . $this->session->data['user_token']);
		$data['back'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=shipping');

		// USPS User ID
		$data['shipping_usps_user_id'] = $this->config->get('shipping_usps_user_id');

		// Mode (test/live)
		$data['shipping_usps_mode'] = $this->config->get('shipping_usps_mode');

		// Debug logging
		$data['shipping_usps_debug'] = $this->config->get('shipping_usps_debug');

		// Service selection
		$data['shipping_usps_ground_advantage'] = $this->config->get('shipping_usps_ground_advantage');
		$data['shipping_usps_priority_mail'] = $this->config->get('shipping_usps_priority_mail');
		$data['shipping_usps_priority_mail_express'] = $this->config->get('shipping_usps_priority_mail_express');

		// Handling fee
		$data['shipping_usps_handling_fee'] = $this->config->get('shipping_usps_handling_fee');
		$data['shipping_usps_handling_percentage'] = $this->config->get('shipping_usps_handling_percentage');

		// Tax class
		$data['shipping_usps_tax_class_id'] = $this->config->get('shipping_usps_tax_class_id');

		$this->load->model('localisation/tax_class');
		$data['tax_classes'] = $this->model_localisation_tax_class->getTaxClasses();

		// Geo zone
		$data['shipping_usps_geo_zone_id'] = $this->config->get('shipping_usps_geo_zone_id');

		$this->load->model('localisation/geo_zone');
		$data['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();

		// Default weight (oz) and dimensions (in)
		$data['shipping_usps_default_weight'] = $this->config->get('shipping_usps_default_weight');
		$data['shipping_usps_default_length'] = $this->config->get('shipping_usps_default_length');
		$data['shipping_usps_default_width'] = $this->config->get('shipping_usps_default_width');
		$data['shipping_usps_default_height'] = $this->config->get('shipping_usps_default_height');

		// Status and sort order
		$data['shipping_usps_status'] = $this->config->get('shipping_usps_status');
		$data['shipping_usps_sort_order'] = $this->config->get('shipping_usps_sort_order');

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/opencart/shipping/usps', $data));
	}

	/**
	 * Save settings
	 *
	 * @return void
	 */
	public function save(): void {
		$this->load->language('extension/opencart/shipping/usps');

		$json = [];

		if (!$this->user->hasPermission('modify', 'extension/opencart/shipping/usps')) {
			$json['error'] = $this->language->get('error_permission');
		}

		if (!$json) {
			$this->load->model('setting/setting');

			$this->model_setting_setting->editSetting('shipping_usps', $this->request->post);

			$json['success'] = $this->language->get('text_success');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
}
