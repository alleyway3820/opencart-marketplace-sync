<?php
namespace Opencart\Catalog\Controller\Extension\Opencart\Feed;

class GoogleSitemap extends \Opencart\System\Engine\Controller {
    
    public function index(): void {
        if (!$this->config->get('feed_google_sitemap_status')) {
            $this->response->setOutput('Sitemap disabled');
            return;
        }
        
        $this->load->model('catalog/product');
        $this->load->model('catalog/category');
        
        $this->response->addHeader('Content-Type: application/xml; charset=utf-8');
        
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        
        // Home page
        $xml .= '  <url>' . "\n";
        $xml .= '    <loc>' . $this->config->get('config_url') . '</loc>' . "\n";
        $xml .= '    <priority>1.0</priority>' . "\n";
        $xml .= '  </url>' . "\n";
        
        // Categories
        $categories = $this->model_catalog_category->getCategories();
        foreach ($categories as $category) {
            $link = $this->url->link('product/category', 'path=' . $category['category_id']);
            $xml .= '  <url>' . "\n";
            $xml .= '    <loc>' . $link . '</loc>' . "\n";
            $xml .= '    <priority>0.7</priority>' . "\n";
            $xml .= '  </url>' . "\n";
        }
        
        // Products
        $products = $this->model_catalog_product->getProducts();
        foreach ($products as $product) {
            $link = $this->url->link('product/product', 'product_id=' . $product['product_id']);
            $xml .= '  <url>' . "\n";
            $xml .= '    <loc>' . $link . '</loc>' . "\n";
            $xml .= '    <priority>0.5</priority>' . "\n";
            $xml .= '  </url>' . "\n";
        }
        
        $xml .= '</urlset>' . "\n";
        
        $this->response->setOutput($xml);
    }
}
