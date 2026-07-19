<?php
/**
 * Marketplace Sync Adapter Interface
 *
 * All marketplace integrations must implement this interface.
 * This allows adding new platforms (Amazon, Walmart, Etsy, etc.)
 * without changing the core OpenCart sync logic.
 *
 * @package OpencartMarketplaceSync
 */
interface MarketplaceAdapter
{
    /**
     * Get the display name of this marketplace.
     */
    public function getName(): string;

    /**
     * Authenticate with the marketplace API.
     * Should handle token refresh internally.
     *
     * @throws RuntimeException on auth failure
     */
    public function authenticate(): void;

    /**
     * Test if authentication is valid.
     */
    public function isAuthenticated(): bool;

    /**
     * Fetch all active listings from this marketplace.
     *
     * @return array Array of ListingData objects or arrays
     */
    public function fetchListings(): array;

    /**
     * Get full details for a single listing.
     *
     * @param string $listingId The marketplace's listing ID
     * @return array|null Listing data, or null if not found
     */
    public function getListing(string $listingId): ?array;

    /**
     * Extract standardized listing data from marketplace-specific format.
     *
     * @param array $rawData Raw data from the marketplace API
     * @return array Standardized data with keys:
     *   - item_id: string
     *   - title: string
     *   - price: string
     *   - currency: string
     *   - description: string
     *   - images: string[]
     *   - quantity: int
     *   - category_id: string
     *   - category_name: string
     *   - condition: string
     *   - attributes: array
     *   - listing_url: string
     *   - shipping_cost: string
     */
    public function extractListingData(array $rawData): array;

    /**
     * Search listings on this marketplace.
     *
     * @param string $query Search keywords
     * @param int $limit Max results
     * @return array ['items' => [...], 'total' => int]
     */
    public function search(string $query, int $limit = 20): array;

    /**
     * Get the adapter's configuration requirements.
     *
     * @return array Config keys needed with descriptions
     */
    public static function getConfigKeys(): array;
}
