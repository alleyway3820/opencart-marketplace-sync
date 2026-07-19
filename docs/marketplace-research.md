# Marketplace API Research — OpenCart Marketplace Sync Tool

> **Purpose:** Evaluate which online marketplaces have APIs suitable for integrating with OpenCart to sync product listings (push/pull).
> **Current status:** eBay support exists (Browse API, REST, OAuth 2.0). This document guides which platforms to support next.
> **Last updated:** 2026-07-19

---

## Comparison Table

| # | Platform | API Name | Type | Auth | Product Creation | Images | Category Mapping | PHP Library | Rate Limits | Major Gotchas |
|---|----------|----------|------|------|------------------|--------|-----------------|-------------|-------------|---------------|
| 1 | **Amazon** | Selling Partner API (SP-API) — Listings API + Feeds API | REST (JSON) | OAuth 2.0 + IAM (AWS SigV4) + Selling Partner role | **Yes.** Listings Items API (`PATCH /listings/2021-08-01/items/{sellerId}/{sku}`) — JSON native. Feeds API (`JSON_LISTINGS_FEED`) also available but being deprecated in favor of Listings API. | **Yes.** Via Listings API image sub-resource or separate Images API endpoint. Supports URL-based and binary upload. | **Required.** Use Product Type Definitions API (`v2020-09-01`) to fetch JSON schema per product type. Amazon has ~20,000+ browse nodes. GTIN/UPC/EAN mandatory for most categories. | `jlevers/selling-partner-api` (community, mature, 800+ stars). Also `amzn/amazon-selling-partner-api-sdk-php` (Amazon-published). | Varies by endpoint. Typically **1–5 req/sec** standard usage plans. Feed processing is async (minutes to hours). | SP-API has a steep onboarding process: AWS IAM role → Selling Partner profile → OAuth app registration. JSON_LISTINGS_FEED was the old feed-based approach; Listings Items API is the modern path (JSON, real-time). Category approval needed for gated categories (e.g., grocery, jewelry). SP-API usage/annual fees were **cancelled May 2026** — currently free. New Listings Items API now returns granular rate-limit throttle messages (2025+). |
| 2 | **Walmart** | Walmart Marketplace API (US) | REST (JSON/XML) | OAuth 2.0 (Client Credentials — `clientId` + `clientSecret`) | **Yes.** Via Item Management API and Feeds API. Two paths: (a) **Quick Setup by Match** — match to existing Walmart catalog items. (b) **Full Item Setup** — create new products via `item` feed type (XML or JSON). | **Yes.** Image URLs included in item feed. Supported. | **Required.** Use **Get Spec API** (`/v3/items/spec`) to fetch category-specific attribute requirements. Must map to Walmart's taxonomy (Walmart Category IDs). | `highsidelabs/walmart-api-php` (community, actively maintained). Covers Marketplace, Content Provider, and 1P Supplier APIs. | **Strict:** Item feeds limited to **10/hour**, 25 MB per feed. DSV feeds: **20/hour**. Non-critical: 300 calls/min. | Requires an **approved Walmart seller account** before API access. Onboarding involves Partner Profile creation, API key generation in Seller Center. No sandbox mode as rich as Amazon's — testing must be done carefully. Category-specific specs differ by country (US vs CA). |
| 3 | **Etsy** | Etsy Open API v3 | REST (JSON) | OAuth 2.0 + `x-api-key` header (both required on every request) | **Yes.** `createDraftListing` → upload images → `activateListing`. Full listing lifecycle supported. Variations managed separately via Inventory API. | **Yes.** `uploadListingImage` endpoint. Supports up to 10 images per listing. | **Required.** Uses Etsy seller taxonomy. `getSellerTaxonomy` endpoint returns category tree. Category required at listing creation. | No official PHP SDK. Unofficial: `openapitools/openapi-generator` can generate PHP from Etsy's OpenAPI spec. Community packages exist on Packagist (e.g., `driehle/etsy-api-php`, `cr0nis/etsy-php`). | **10 req/sec burst, 10,000 req/day** per API key per app. | Requires **both** OAuth Bearer token AND `x-api-key` header — missing this is a common error. Open API v3 is the current version; v2 was deprecated. Listing must set `shop_section_id` and `taxonomy_id`. Digital downloads vs physical listings have different flow. Etsy takes a **transaction fee** (6.5% + $0.20 listing fee) — price sync must account for this. Listing creation costs $0.20/listing (Etsy fee), so automated bulk creation gets expensive. |
| 4 | **Shopify** | Shopify Admin API | GraphQL (preferred, REST legacy/deprecated) | OAuth 2.0 (public/private apps) or custom app tokens | **Yes.** `productCreate` mutation (GraphQL). REST endpoint `POST /admin/api/products.json` still works but is **legacy**. | **Yes.** `productCreate` accepts `media` input (base64 images, URLs). Also `productImageCreate` mutation. | **Not required** beyond basic tags/categories. Shopify uses product_type and tags; no mandatory taxonomy mapping. | **Official:** `shopify/shopify-api-php` (maintained by Shopify, supports REST + GraphQL). Also `shopify/shopify-admin-api-php`, `phpclassic/php-shopify`. | **GraphQL:** 1,000 cost points per 60s per app per store. **REST:** 40 req/sec (legacy). | **⚠️ REST Admin API is deprecated** as of Oct 2024. New public apps **must** use GraphQL since April 2025. Shopify is primarily a **source platform** for this tool (OpenCart → Shopify pull) since Shopify is itself a cart platform. However, it could be a destination for migrating sellers. Private app tokens now called "custom apps" — easier OAuth setup. |
| 5 | **Mercari** | None (no public API) | — | — | **Not possible via official API.** | — | — | None. Unofficial scrapers exist (Go, Python) but violate ToS. | N/A | **❌ No public API exists for the US marketplace.** Mercari Japan has a "Master API" (for master/reference data only, not listing management). Cross-listing platforms (Vendoo, ListPerfectly, Crosslist) use **browser automation / web scraping**, which: (a) violates Mercari ToS, (b) risks account bans, (c) breaks when Mercari updates their UI. Mercari's US operations have been winding down (Mercari US was acquired/sunset). **Not recommended for development.** |
| 6 | **Poshmark** | None (no public API) | — | — | **Not possible via official API.** | — | — | None. Scraping/automation only. | N/A | **❌ No public API exists.** No developer program. All cross-listing tools (Vendoo, ListPerfectly, Closo, Flyp) use **browser automation** (Puppeteer/Playwright) or mobile device farming. Poshmark actively detects and blocks automation with CAPTCHAs and IP bans. ToS explicitly prohibits automated access. **Not recommended for development.** |
| 7 | **Facebook Marketplace** | Meta Commerce API (via Marketing API / Product Catalog API) | REST (GraphQL queries available) | OAuth 2.0 (Facebook Login) + Business Manager permissions | **Yes, for shops/businesses.** Products are uploaded via **Product Catalog** — feed-based (CSV/TSV/XML) or API batch updates. Catalog items automatically appear in Marketplace if eligibility criteria are met. | **Yes.** `image_url` or `additional_image_urls` fields in feed. Also via API. | **Yes.** Uses Google Product Taxonomy by default. Category field required. | **Official:** `facebook/php-graph-sdk` (SDK for Graph API). Covers catalog management. Various community wrappers. | **Standard tier:** ~200 calls/hr per app. **Advanced tier:** higher limits after review. Feed processing is async (minutes). | Requires **Business Manager account** + **Commerce Manager catalog** setup. Marketplace product visibility is **not guaranteed** — Meta decides eligibility algorithmically. Additional "Marketplace Partner" approval needed for full Marketplace integration. Individual sellers (not shops) have limited API access. Anti-bot detection is aggressive. Facebook's API changes frequently — expect maintenance burden. |
| 8 | **Google Shopping** | ~~Content API for Shopping v2.1~~ → **Google Merchant API v1** | REST (JSON) | OAuth 2.0 (Google service account) | **Yes.** `products.insert` endpoint. Full CRUD on products in Merchant Center. | **Yes.** `imageLink` field (URL). Additional images via `additionalImageLinks`. | **Required.** Must use Google Product Taxonomy. Can also use custom `productTypes` for internal categorization. | **Official:** `google/apiclient` (Google API PHP Client) + `googleapis/php-shopping-merchant-products` (dedicated package for Merchant API). | **~250,000 requests/day** per Merchant Center account (aggregate). Batch operations reduce call count. | **⚠️ Content API for Shopping is shutting down August 18, 2026.** Must migrate to Google Merchant API v1. GTIN/MPN/UPC required for most products (exemptions for custom/handmade). Strict data quality standards — products get disapproved for missing attributes, inconsistent pricing, or policy violations. Free listings and Shopping Ads have different eligibility. Account suspension risk if feed quality is poor. |

---

## Detailed Platform Notes

### 1. Amazon — Selling Partner API (SP-API)

**Priority: HIGH** (largest marketplace, strong OpenCart user demand)

**Architecture:**
- Uses AWS IAM roles + OAuth 2.0 — the most complex auth setup of any platform
- Two paths for product creation:
  - **Modern path:** Listings Items API (`PATCH /listings/2021-08-01/items/{sellerId}/{sku}`) — JSON payload, real-time response
  - **Legacy path:** Feeds API with `JSON_LISTINGS_FEED` or `POST_FLAT_FILE_LISTINGS_DATA` — async, being phased out
- Product Type Definitions API provides JSON Schema for every product type

**Implementation effort: HIGH** (complex auth, category mapping, GTIN management)

**Key considerations for your tool:**
- Must implement IAM credential management (AWS SDK required for signing)
- Category mapping requires fetching schemas per product type
- GTIN/UPC validation — OpenCart products without GTINs will need workarounds
- Bulk operations via feed vs. individual via Listings Items API

---

### 2. Walmart — Walmart Marketplace API

**Priority: HIGH** (second largest US marketplace, growing rapidly)

**Architecture:**
- Simplest auth among major players: OAuth 2.0 Client Credentials (clientId + clientSecret)
- Two product setup paths:
  - **Quick Setup by Match:** Match to existing Walmart catalog (faster, but not always possible)
  - **Full Item Setup:** Create new products via item feed
- Feeds-based system for most operations (async processing)

**Implementation effort: MEDIUM** (feed-based, good documentation)

**Key considerations:**
- Feed size limits (25 MB) and rate limits (10 item feeds/hour) mean you must batch thoughtfully
- Tax partner certification needed eventually
- Category specs differ between US and CA — must support both
- PHP SDK (`highsidelabs/walmart-api-php`) is well-maintained

---

### 3. Etsy — Open API v3

**Priority: HIGH** (handmade/vintage niche, overlaps with OpenCart's small merchant base)

**Architecture:**
- REST API with full listing lifecycle
- Unique: requires both OAuth Bearer token AND `x-api-key` header
- Listing creation is a multi-step process (draft → images → activate)
- Separate Inventory API for variations

**Implementation effort: MEDIUM**

**Key considerations:**
- **Listing fees cost $0.20 each** — bulk creation must track billing
- Requires taxonomy mapping to Etsy's seller categories
- No official PHP SDK — you'll either write a client or use openapi-generator
- 10,000 requests/day limit is relatively low for a sync tool
- Etsy's transaction fee (6.5%) should be noted for price syncing

---

### 4. Shopify — Admin API

**Priority: MEDIUM** (more of a source than destination; useful for import/migration)

**Architecture:**
- **GraphQL is now the primary API** — REST is legacy/deprecated
- Products created via `productCreate` mutation
- Excellent PHP SDK maintained by Shopify themselves

**Implementation effort: LOW** (best SDK, straightforward data model)

**Key considerations:**
- Shopify is primarily a **source** for this tool (sellers moving FROM Shopify TO OpenCart, or cross-listing from OpenCart TO Shopify)
- No category mapping needed — Shopify's product model is simple
- Good test environment (Shopify Partners get development stores)
- GraphQL cost-based rate limiting requires query cost estimation logic

---

### 5. Mercari — No Public API

**Priority: NONE** (not feasible)

**Status:** Mercari does not offer any public API for listing management in either the US or Japanese market. The "Master API" mentioned in some engineering blogs is for internal/reference data only.

**Verdict:** Do not invest development time. Cross-listing tools that claim "Mercari support" use browser automation (Selenium/Puppeteer) which violates ToS and is unreliable.

---

### 6. Poshmark — No Public API

**Priority: NONE** (not feasible)

**Status:** Poshmark has never offered a public API. No developer program exists.

**Verdict:** Do not invest development time. All current integration relies on browser automation. Poshmark actively blocks automation with CAPTCHAs and rate-limiting.

---

### 7. Facebook Marketplace — Commerce API

**Priority: MEDIUM** (high user reach, but complex access requirements)

**Architecture:**
- Products managed through **Product Catalogs** within Meta Business Manager
- Feed-based upload (CSV/TSV/XML scheduled fetch or API batch updates)
- Products appear in Marketplace algorithmically — not guaranteed
- Uses Google Product Taxonomy

**Implementation effort: HIGH** (approval gates, feed management)

**Key considerations:**
- Requires **Business Manager account** + **Commerce Manager setup**
- **Marketplace Partner approval** may be needed for full integration
- Marketplace visibility is at Meta's discretion — products may only appear in Instagram/Facebook Shops
- API permissions require app review with detailed use-case documentation
- Advertising component is deeply integrated (Catalog Sales objective)
- High maintenance — Meta changes APIs frequently

---

### 8. Google Shopping — Merchant API

**Priority: HIGH** (massive product discovery channel, essential for merchants)

**Architecture:**
- **Content API for Shopping is being decommissioned (Aug 18, 2026)** — must use **Merchant API v1**
- Product insertion via `products.insert`
- Feed-based or individual API calls
- Google Product Taxonomy required
- Free listings and Shopping Ads both use the same product data

**Implementation effort: MEDIUM** (good PHP libraries, clear data spec)

**Key considerations:**
- **You must target the Merchant API, not Content API** — Content API shuts down August 2026
- Google's official PHP client (`google/apiclient`) + dedicated `googleapis/php-shopping-merchant-products` package
- GTIN/MPN are strictly enforced — products without valid identifiers will be disapproved
- Google has strict data quality requirements — price discrepancies, missing attributes, misleading descriptions all cause disapprovals
- Account suspension risk for low-quality feeds

---

## Recommended Implementation Priority

| Priority | Platform | Rationale |
|----------|----------|-----------|
| **1** | **Amazon** | Largest marketplace, highest user demand, SP-API is now free. Complex but essential. |
| **2** | **Walmart** | Fast-growing #2 in US. Simple auth, good PHP SDK, clear feeds system. |
| **3** | **Google Shopping** | Massive product discovery. Merchant API is modern. High value for merchants. |
| **4** | **Etsy** | Strong niche overlap with OpenCart merchants. Multi-step listing flow is well-documented. |
| **5** | **Shopify** | Useful as a source for data migration. Excellent SDK. Low effort. |
| **6** | **Facebook Marketplace** | High reach but approval gates and algorithmic visibility reduce reliability. |
| **7** | **Mercari** | **Not feasible** — no public API. |
| **8** | **Poshmark** | **Not feasible** — no public API. |

---

## Architecture Recommendations

**For your PHP/MySQL OpenCart tool:**

1. **Adapter pattern** — Each marketplace gets a dedicated adapter class implementing a common interface (createProduct, updateProduct, deleteProduct, syncInventory). Currently you have `eBayAdapter.php`?

2. **OAuth token storage** — Most platforms use OAuth 2.0. Store tokens with refresh tokens in a dedicated `api_tokens` table. Handle token refresh transparently.

3. **Category mapping system** — Build a database table `category_mappings` that maps an OpenCart category ID to marketplace-specific category IDs. Each marketplace has its own taxonomy.

4. **Image handling** — Download images from OpenCart, store them temporarily, upload to marketplace. Handle image size limits (Amazon: 10MB, Etsy: varies).

5. **Queue/worker pattern** — Marketplace API rate limits mean you should queue sync operations and process them asynchronously (e.g., MySQL queue table + cron worker).

6. **Error handling** — Each marketplace has unique error responses. Build a unified error taxonomy: retryable (rate limit, server error) vs. non-retryable (auth failure, invalid data, category not found).

---

## References

| Platform | Documentation URL |
|----------|-------------------|
| Amazon SP-API | https://developer.amazonservices.com/ |
| Walmart Marketplace API | https://developer.walmart.com/us-marketplace/docs/introduction-to-marketplace-apis |
| Etsy Open API v3 | https://developers.etsy.com/ |
| Shopify Admin API (GraphQL) | https://shopify.dev/docs/api/admin-graphql |
| Google Merchant API | https://developers.google.com/merchant/api |
| Meta Commerce API | https://developers.facebook.com/docs/commerce-platform/ |
| Amazon SP-API PHP Client | https://github.com/jlevers/selling-partner-api |
| Walmart PHP SDK | https://github.com/highsidelabs/walmart-api-php |
| Shopify PHP SDK | https://github.com/Shopify/shopify-api-php |
| Google PHP Client | https://github.com/googleapis/google-api-php-client |
| Google Shopping Merchant Products PHP | https://github.com/googleapis/php-shopping-merchant-products |
| Facebook PHP SDK | https://github.com/facebook/php-graph-sdk |
