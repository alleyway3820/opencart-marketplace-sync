<?php
namespace Opencart\Catalog\Controller\Extension\Opencart\Feed;

class GoogleShopping extends \Opencart\System\Engine\Controller {
    
    public function index(): void {
        if (!$this->config->get('feed_google_shopping_status')) {
            $this->response->setOutput('Feed disabled');
            return;
        }
        
        $this->load->model('catalog/product');
        $this->load->model('catalog/category');
        $this->load->model('tool/image');
        
        $products = $this->model_catalog_product->getProducts();
        
        $this->response->addHeader('Content-Type: application/xml; charset=utf-8');
        
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<rss xmlns:g="http://base.google.com/ns/1.0" version="2.0">' . "\n";
        $xml .= '<channel>' . "\n";
        $xml .= '  <title>' . $this->config->get('config_name') . '</title>' . "\n";
        $xml .= '  <link>' . $this->config->get('config_url') . '</link>' . "\n";
        $xml .= '  <description>' . $this->config->get('config_meta_title') . ' Google Shopping Feed</description>' . "\n";
        
        foreach ($products as $product) {
            $productId = $product['product_id'];
            $price = number_format((float)$product['price'], 2, '.', '');
            $link = $this->url->link('product/product', 'product_id=' . $productId);
            $categories = $this->model_catalog_product->getCategories($productId);
            $categoryPath = '';
            foreach ($categories as $cat) {
                $catInfo = $this->model_catalog_category->getCategory($cat['category_id']);
                if ($catInfo) {
                    $categoryPath = $catInfo['name'];
                }
            }
            
            $image = $product['image'] ? $this->model_tool_image->resize($product['image'], 800, 800) : '';
            $description = strip_tags(html_entity_decode($product['description'] ?? '', ENT_QUOTES, 'UTF-8'));
            $description = mb_substr($description, 0, 5000);
            
            $xml .= '  <item>' . "\n";
            $xml .= '    <g:id>' . $productId . '</g:id>' . "\n";
            $xml .= '    <g:title><![CDATA[' . $product['name'] . ']]></g:title>' . "\n";
            $xml .= '    <g:description><![CDATA[' . $description . ']]></g:description>' . "\n";
            $xml .= '    <g:link>' . $link . '</g:link>' . "\n";
            if ($image) $xml .= '    <g:image_link>' . $image . '</g:image_link>' . "\n";
            $xml .= '    <g:condition>used</g:condition>' . "\n";
            $xml .= '    <g:availability>in_stock</g:availability>' . "\n";
            $xml .= '    <g:price>' . $price . ' ' . $this->config->get('config_currency') . '</g:price>' . "\n";
            $xml .= '    <g:brand>Geeky Goody Goods</g:brand>' . "\n";
            if ($categoryPath) $xml .= '    <g:google_product_category><![CDATA[' . $categoryPath . ']]></g:google_product_category>' . "\n";
            $xml .= '    <g:mpn>' . $productId . '</g:mpn>' . "\n";
            $xml .= '  </item>' . "\n";
        }
        
        $xml .= '</channel>' . "\n";
        $xml .= '</rss>' . "\n";
        
        $this->response->setOutput($xml);
    }
}
