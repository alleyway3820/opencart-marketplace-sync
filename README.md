# OpenCart Marketplace Sync

Sync product listings from multiple marketplaces (eBay, Amazon, Walmart, Etsy, etc.) into your OpenCart store.

## Architecture

```
sync.php          ← CLI entry point
src/
├── MarketplaceAdapter.php  ← Interface for all marketplace integrations
├── ListingData.php         ← Standardized listing data object
├── OpenCartSyncEngine.php  ← Core sync logic (marketplace → OpenCart)
├── ImageHandler.php        ← Download and resize product images
├── adapters/
│   ├── EbayAdapter.php     ← eBay (Browse API, OAuth 2.0)
│   ├── AmazonAdapter.php   ← Coming soon
│   ├── WalmartAdapter.php  ← Coming soon
│   └── EtsyAdapter.php     ← Coming soon
└── config.example.php
docs/
├── marketplace-research.md ← Full comparison of supported platforms
└── ebay-setup.md           ← eBay-specific OAuth setup
```

## Supported Marketplaces

| Platform | Status | API | Auth | Notes |
|----------|--------|-----|------|-------|
| eBay | ✅ **Live** | Browse API | OAuth 2.0 (auth code + refresh) | Working in production |
| Amazon | 🔜 Next | SP-API | OAuth + IAM | GTIN required |
| Walmart | 🔜 Planned | Marketplace API | OAuth 2.0 Client Credentials | Feed-based |
| Google Shopping | 🔜 Planned | Merchant API v1 | OAuth 2.0 | Content API shutting down Aug 2026 |
| Etsy | 🔜 Planned | Open API v3 | OAuth 2.0 | 10 req/s limit |
| Shopify | 🔜 Planned | GraphQL | OAuth 2.0 | Source migration use case |
| Facebook Marketplace | 🔜 Consider | Commerce API | OAuth 2.0 | Approval-gated |
| Mercari | ❌ Not feasible | None | N/A | No public API |
| Poshmark | ❌ Not feasible | None | N/A | No public API |

Full research: [docs/marketplace-research.md](docs/marketplace-research.md)

## Quick Start

### Prerequisites
- PHP 8.1+ with `curl`, `pdo_mysql`, `mbstring`, `gd` or `imagick`
- MySQL 8.0+ for OpenCart
- OpenCart 4.x installation
- Marketplace developer account (eBay, Amazon, etc.)

### Installation

```bash
# Clone the repo into your OpenCart installation or server
git clone https://github.com/alleyway3820/opencart-marketplace-sync.git
cd opencart-marketplace-sync

# Copy and configure
cp config.example.php config.php
vi config.php   # Fill in DB credentials and marketplace API keys
```

### eBay Setup (Complete)

1. **Get API credentials** from https://developer.ebay.com
   - Create an application → copy App ID, Dev ID, Cert ID
   - Set the OAuth redirect URI in your app settings

2. **Generate OAuth tokens**:
   ```bash
   php sync.php auth:url
   # Open the URL, authorize, copy the code from the callback URL
   php sync.php auth:code YOUR_CODE
   # Copy the refresh_token into config.php
   ```

3. **Sync your listings**:
   ```bash
   # Search by seller username
   php sync.php sync:seller YOUR_EBAY_USERNAME
   
   # Or sync a single item
   php sync.php sync:one v1|123456789|0
   
   # Or search eBay
   php sync.php ebay:search "vintage camera"
   ```

### Commands

```bash
php sync.php                         # Show help
php sync.php status                  # Show OpenCart categories & sync status
php sync.php sync:one ITEM_ID        # Sync a single listing
php sync.php sync:seller USERNAME    # Sync all listings by seller
php sync.php ebay:search QUERY       # Search eBay
php sync.php oc:categories           # List OpenCart categories
php sync.php db:inspect              # Inspect source database
php sync.php auth:url                # Generate OAuth URL
php sync.php auth:code CODE          # Exchange OAuth code for tokens
```

## Adding a New Marketplace

1. Create a new class in `src/adapters/` implementing `MarketplaceAdapter`
2. Implement all required methods
3. Register it in `sync.php` (add to the adapter list)
4. Add config keys to `config.example.php`

See `src/adapters/EbayAdapter.php` for a reference implementation.

## Data Flow

```
Marketplace API
      ↓
Adapter.fetchListings() / getListing()
      ↓
Adapter.extractListingData() → ListingData (standardized)
      ↓
OpenCartSyncEngine.mapToProduct() → OpenCart fields
      ↓
ImageHandler.downloadImage() → local filesystem
      ↓
OpenCartDb.createProduct() / updateProduct()
      ↓
OpenCart store ready
```

## Security

- OAuth tokens stored in `config.php` (keep outside web root)
- eBay refresh tokens auto-renew access tokens
- Database credentials in config only
- Image downloads validate MIME types server-side
- All SQL uses prepared statements

## Development

```bash
# Add a new adapter
cp src/adapters/EbayAdapter.php src/adapters/YourAdapter.php

# Run tests
php -l src/adapters/YourAdapter.php

# Test API connection
php sync.php status
```

## License

MIT - See LICENSE file
