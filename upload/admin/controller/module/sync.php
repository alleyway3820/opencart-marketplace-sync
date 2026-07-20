<?php
namespace Opencart\Admin\Controller\Extension\Opencart\Module;

class Sync extends \Opencart\System\Engine\Controller {

    public function index(): void {
        $this->load->language('extension/opencart/module/sync');
        $this->document->setTitle($this->language->get('heading_title'));
        
        $data['breadcrumbs'] = [];
        $data['breadcrumbs'][] = ['text' => $this->language->get('text_home'), 'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'])];
        $data['breadcrumbs'][] = ['text' => $this->language->get('text_extension'), 'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module')];
        $data['breadcrumbs'][] = ['text' => $this->language->get('heading_title'), 'href' => $this->url->link('extension/opencart/module/sync', 'user_token=' . $this->session->data['user_token'])];
        
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
        
        $data['preview'] = $this->url->link('extension/opencart/module/sync.preview', 'user_token=' . $this->session->data['user_token']);
        
        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');
        
        $this->response->setOutput($this->load->view('extension/opencart/module/sync', $data));
    }

    public function preview(): void {
        $this->load->language('extension/opencart/module/sync');
        $this->document->setTitle($this->language->get('text_preview'));
        
        $data['breadcrumbs'] = [];
        $data['breadcrumbs'][] = ['text' => $this->language->get('text_home'), 'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'])];
        $data['breadcrumbs'][] = ['text' => $this->language->get('heading_title'), 'href' => $this->url->link('extension/opencart/module/sync', 'user_token=' . $this->session->data['user_token'])];
        $data['breadcrumbs'][] = ['text' => $this->language->get('text_preview'), 'href' => $this->url->link('extension/opencart/module/sync.preview', 'user_token=' . $this->session->data['user_token'])];
        
        $data['dashboard'] = $this->url->link('extension/opencart/module/sync', 'user_token=' . $this->session->data['user_token']);
        $data['user_token'] = $this->session->data['user_token'];
        
        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');
        
        $this->response->setOutput($this->load->view('extension/opencart/module/sync_preview', $data));
    }

    public function search(): void {
        ob_start();
        $json = ['error' => '', 'items' => [], 'categories' => [], 'total' => 0];
        
        $seller = trim($this->request->post['seller'] ?? '');
        $keyword = trim($this->request->post['keyword'] ?? '');
        $categoryId = trim($this->request->post['category_id'] ?? '');
        $limit = min(200, max(1, (int)($this->request->post['limit'] ?? 50)));
        $offset = max(0, (int)($this->request->post['offset'] ?? 0));
        
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
            
            if ($seller) {
                if ($keyword) {
                    // Keyword provided: use single search with proper pagination + categories
                    $results = $api->getSellerListings($seller, $limit, (string)$offset, $keyword, $categoryId);
                } else {
                    // No keyword: use multi-term aggregate (no pagination)
                    $results = $api->getAllSellerItems($seller, $limit, '', $categoryId);
                }
            } else {
                $results = $api->searchItems($keyword, $limit, $offset);
            }
            
            $categories = [];
            foreach ($results['items'] ?? [] as $item) {
                foreach ($item['categories'] ?? [] as $cat) {
                    $id = $cat['categoryId'] ?? '';
                    $name = $cat['categoryName'] ?? '';
                    if ($id && $name) $categories[$id] = $name;
                }
            }
            asort($categories);
            $catList = [];
            foreach ($categories as $id => $name) $catList[] = ['id' => $id, 'name' => $name];
            
            $items = [];
            foreach ($results['items'] ?? [] as $item) {
                $itemId = $item['itemId'] ?? '';
                if (!$itemId) continue;
                $ebayItemId = $itemId;
                // eBay Browse API returns itemId with v1|...|0 format in some cases
                if (strpos($itemId, 'v1|') !== 0) {
                    $ebayItemId = 'v1|' . $itemId . '|0';
                }
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
            $json['categories'] = $catList;
            $json['total'] = $results['total'] ?? count($items);
            $json['limit'] = $limit;
            $json['offset'] = $offset;
            
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
            
            $existingId = $ocDb->getProductBySku($productData['sku'] ?? $itemId);
            
            if (!empty($itemData['image']['imageUrl'])) {
                $localImage = $images->downloadImage($itemData['image']['imageUrl'], $itemId, 0);
                if ($localImage) $productData['image'] = $localImage;
            }
            
            if ($existingId) {
                $ocDb->updateProduct($existingId, $productData);
                $productId = $existingId;
            } else {
                $productId = $ocDb->createProduct($productData, $catId);
            }
            
            foreach ($itemData['additionalImages'] ?? [] as $i => $img) {
                if (!empty($img['imageUrl'])) {
                    $localPath = $images->downloadImage($img['imageUrl'], $itemId, $i + 1);
                    if ($localPath) $ocDb->createProductImage($productId, $localPath, $i + 1);
                }
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
