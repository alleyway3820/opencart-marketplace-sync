<?php
namespace Opencart\Admin\Controller\Extension\Opencart\Feed;

class GoogleShopping extends \Opencart\System\Engine\Controller {
    
    public function index(): void {
        $this->load->language('extension/opencart/feed/google_shopping');
        $this->document->setTitle($this->language->get('heading_title'));
        
        $data['breadcrumbs'] = [];
        $data['breadcrumbs'][] = ['text' => $this->language->get('text_home'), 'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'])];
        $data['breadcrumbs'][] = ['text' => $this->language->get('text_extension'), 'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=feed')];
        $data['breadcrumbs'][] = ['text' => $this->language->get('heading_title'), 'href' => $this->url->link('extension/opencart/feed/google_shopping', 'user_token=' . $this->session->data['user_token'])];
        
        $data['save'] = $this->url->link('extension/opencart/feed/google_shopping.save', 'user_token=' . $this->session->data['user_token']);
        $data['back'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=feed');
        
        $data['feed_google_shopping_status'] = $this->config->get('feed_google_shopping_status');
        
        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');
        
        $this->response->setOutput($this->load->view('extension/opencart/feed/google_shopping', $data));
    }
    
    public function save(): void {
        $this->load->language('extension/opencart/feed/google_shopping');
        
        if (!$this->user->hasPermission('modify', 'extension/opencart/feed/google_shopping')) {
            $this->session->data['error'] = $this->language->get('error_permission');
        } else {
            $this->load->model('setting/setting');
            $this->model_setting_setting->editSetting('feed_google_shopping', $this->request->post);
            $this->session->data['success'] = $this->language->get('text_success');
        }
        
        $this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=feed'));
    }
}
