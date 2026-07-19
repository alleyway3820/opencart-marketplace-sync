<?php
/**
 * Standardized Listing Data Object
 *
 * Represents a product listing from any marketplace in a normalized format
 * that the OpenCart sync engine can process.
 *
 * @package OpencartMarketplaceSync
 */
class ListingData
{
    public string $itemId;
    public string $title;
    public string $price;
    public string $currency = 'USD';
    public string $description = '';
    public array $images = [];
    public int $quantity = 1;
    public string $categoryId = '';
    public string $categoryName = '';
    public string $condition = 'New';
    public array $attributes = [];
    public string $listingUrl = '';
    public string $shippingCost = '0.00';
    public string $sourceMarketplace = '';
    public string $gtin = '';
    public string $brand = '';
    public string $mpn = '';

    public function __construct(array $data = [])
    {
        foreach ($data as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
    }

    /**
     * Convert to an array for OpenCart product mapping.
     */
    public function toArray(): array
    {
        return [
            'item_id'      => $this->itemId,
            'title'        => $this->title,
            'price'        => $this->price,
            'currency'     => $this->currency,
            'description'  => $this->description,
            'images'       => $this->images,
            'quantity'     => $this->quantity,
            'category_id'  => $this->categoryId,
            'category_name'=> $this->categoryName,
            'condition'    => $this->condition,
            'attributes'   => $this->attributes,
            'listing_url'  => $this->listingUrl,
            'shipping_cost'=> $this->shippingCost,
            'source'       => $this->sourceMarketplace,
            'gtin'         => $this->gtin,
            'brand'        => $this->brand,
            'mpn'          => $this->mpn,
        ];
    }
}
