<?php
namespace Opencart\Admin\Controller\Extension\Opencart\Shipping;

class Sync extends \Opencart\System\Engine\Controller {

    public function index(): void {
        $this->load->language('extension/opencart/shipping/sync');
        $this->document->setTitle($this->language->get('heading_title'));
        
        $data['breadcrumbs'] = [];
        $data['breadcrumbs'][] = ['text' => $this->language->get('text_home'), 'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'])];
        $data['breadcrumbs'][] = ['text' => $this->language->get('text_extension'), 'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=shipping')];
        $data['breadcrumbs'][] = ['text' => $this->language->get('heading_title'), 'href' => $this->url->link('extension/opencart/shipping/sync', 'user_token=' . $this->session->data['user_token'])];
        
        $data['total_products'] = 0;
        $data['total_categories'] = 0;
        
        try {
            $this->load->model('catalog/product');
            $data['total_products'] = $this->model_catalog_product->getTotalProducts();
        } catch (\Throwable $e) {}
        
        try {
            $this->load->model('catalog/category');
            $data['total_categories'] = $this->model_catalog_category->getTotalCategories();
        } catch (\Throwable $e) {}
        
        $data['preview'] = $this->url->link('extension/opencart/shipping/sync.preview', 'user_token=' . $this->session->data['user_token']);
        
        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');
        
        $this->response->setOutput($this->load->view('extension/opencart/shipping/sync', $data));
    }

    public function preview(): void {
        $this->load->language('extension/opencart/shipping/sync');
        $this->document->setTitle($this->language->get('text_preview'));
        
        $data['breadcrumbs'] = [];
        $data['breadcrumbs'][] = ['text' => $this->language->get('text_home'), 'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'])];
        $data['breadcrumbs'][] = ['text' => $this->language->get('heading_title'), 'href' => $this->url->link('extension/opencart/shipping/sync', 'user_token=' . $this->session->data['user_token'])];
        $data['breadcrumbs'][] = ['text' => $this->language->get('text_preview'), 'href' => $this->url->link('extension/opencart/shipping/sync.preview', 'user_token=' . $this->session->data['user_token'])];
        
        $data['dashboard'] = $this->url->link('extension/opencart/shipping/sync', 'user_token=' . $this->session->data['user_token']);
        $data['user_token'] = $this->session->data['user_token'];
        
        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');
        
        $this->response->setOutput($this->load->view('extension/opencart/shipping/sync_preview', $data));
    }

    public function search(): void {
        $json = ['error' => '', 'items' => []];
        
        $seller = trim($this->request->post['seller'] ?? '');
        $keyword = trim($this->request->post['keyword'] ?? '');
        
        if (empty($seller) && empty($keyword)) {
            $json['error'] = $this->language->get('error_search');
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode($json));
            return;
        }

        try {
            $baseDir = DIR_OPENCART . 'ebay-sync/';
            if (!file_exists($baseDir . 'config.php')) {
                $json['error'] = 'Sync app not found';
                $this->response->addHeader('Content-Type: application/json');
                $this->response->setOutput(json_encode($json));
                return;
            }
            
            $syncConfig = require $baseDir . 'config.php';
            require_once $baseDir . 'src/EbayApi.php';
            require_once $baseDir . 'src/OpenCartDb.php';
            require_once $baseDir . 'src/DataMapper.php';
            
            $pdo = new \PDO("mysql:host=localhost;dbname=geek_shop;charset=utf8mb4", "geek_shop", "jqqeO-C1kna^4Go0");
            $ocDb = new \OpenCartDb($pdo, 'oc_');
            $api = new \EbayApi($syncConfig['ebay_api']);
            $prodCfg = $syncConfig['products'] ?? [];
            $mapper = new \DataMapper($prodCfg, $prodCfg);
            
            // Use seller filter if seller name provided, otherwise plain search
            if ($seller && $keyword) {
                $results = $api->getSellerListings($seller, 50, '', $keyword);
            } elseif ($seller) {
                // No keyword? Let API use its broad default ('a')
                $results = $api->getSellerListings($seller, 200, '', '');
            } else {
                $results = $api->searchItems($keyword, 50);
            }
            
            $items = [];
            foreach ($results['items'] ?? [] as $item) {
                $itemId = $item['itemId'] ?? '';
                if (!$itemId) continue;
                
                $ebayItemId = 'v1|' . $itemId . '|0';
                $imported = $ocDb->getProductBySku($ebayItemId);
                
                $items[] = [
                    'id' => $ebayItemId,
                    'title' => $item['title'] ?? 'Unknown',
                    'price' => '$' . ($item['price']['value'] ?? '0.00'),
                    'image' => $item['image']['imageUrl'] ?? '',
                    'condition' => $item['condition'] ?? 'Unknown',
                    'status' => $imported ? 'Imported' : 'Available',
                    'product_id' => $imported ?: 0,
                ];
            }
            
            $json['items'] = $items;
            $json['total'] = $results['total'] ?? count($items);
            
        } catch (\Throwable $e) {
            $json['error'] = $e->getMessage();
        }
        
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    public function importItem(): void {
        $json = ['error' => '', 'success' => false, 'product_id' => 0];
        
        $itemId = trim($this->request->post['item_id'] ?? '');
        if (empty($itemId)) {
            $json['error'] = 'No item ID provided';
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode($json));
            return;
        }

        try {
            $baseDir = DIR_OPENCART . 'ebay-sync/';
            if (!file_exists($baseDir . 'config.php')) {
                $json['error'] = 'Sync app not found';
                $this->response->addHeader('Content-Type: application/json');
                $this->response->setOutput(json_encode($json));
                return;
            }
            
            $syncConfig = require $baseDir . 'config.php';
            require_once $baseDir . 'src/EbayApi.php';
            require_once $baseDir . 'src/OpenCartDb.php';
            require_once $baseDir . 'src/DataMapper.php';
            require_once $baseDir . 'src/ImageHandler.php';
            
            $pdo = new \PDO("mysql:host=localhost;dbname=geek_shop;charset=utf8mb4", "geek_shop", "jqqeO-C1kna^4Go0");
            $ocDb = new \OpenCartDb($pdo, 'oc_');
            $api = new \EbayApi($syncConfig['ebay_api']);
            $prodCfg = $syncConfig['products'] ?? [];
            $mapper = new \DataMapper($prodCfg, $prodCfg);
            $images = new \ImageHandler($syncConfig['images']);
            
            $itemData = $api->getItem($itemId);
            $productData = $mapper->mapToProduct($itemData);
            $catName = $mapper->resolveCategory($itemData['title'] ?? '');
            $catId = $ocDb->ensureCategory($catName, $prodCfg['default_category_id'] ?? 20);
            $productData['category_id'] = $catId;
            
            $existingId = $ocDb->findBySku($productData['sku'] ?? $itemId);
            
            if ($existingId) {
                $ocDb->updateProduct($existingId, $productData);
                $productId = $existingId;
            } else {
                $productId = $ocDb->createProduct($productData, $catId);
            }
            
            $itemImages = $itemData['images'] ?? [];
            if ($itemImages) {
                $ocDb->updateProductImages($productId, $itemImages, $itemData['image'] ?? '');
            }
            
            $json['success'] = true;
            $json['product_id'] = $productId;
            
        } catch (\Throwable $e) {
            $json['error'] = $e->getMessage();
        }
        
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }
}
