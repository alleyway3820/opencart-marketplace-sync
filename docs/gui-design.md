# GUI Design: OpenCart Marketplace Sync Admin Panel

## Overview
A web-based admin interface within OpenCart for previewing and importing marketplace listings.

## Architecture

```
OpenCart Admin (/shopadmin/)
├── route=extension/sync/dashboard   ← Main sync dashboard
├── route=extension/sync/preview    ← Preview available items
├── route=extension/sync/import     ← Import items
└── route=extension/sync/history    ← Sync history log
```

## Tech Stack
- **PHP 8.0** — OpenCart controller pattern
- **MySQL** — Existing `geek_shop` database
- **Twig** — OpenCart template engine (OC4)
- **JavaScript** — jQuery for AJAX actions
- **Admin Theme** — OpenCart default admin theme

## Pages

### 1. Dashboard (`dashboard.twig`)
- Summary cards: total synced, pending, errors
- Recent sync activity feed
- Quick import button
- Connection status (eBay API health check)

### 2. Preview (`preview.twig`)
- Search form: seller username + keyword
- Results table with columns:
  - [ ] Checkbox (multi-select)
  - Image thumbnail
  - Title (linked to eBay listing)
  - Price
  - Condition
  - Status badge (Available / Imported)
- Bulk import button
- Pagination

### 3. Import (`import.twig`)
- Progress bar with real-time updates via AJAX polling
- Current item being imported
- Success/error count
- Log output

### 4. History (`history.twig`)
- Table of all sync actions with:
  - Date/time
  - eBay Item ID
  - Title
  - Action (Created/Updated/Skipped/Error)
  - OpenCart Product ID (linked)
- Filter by date range and status

## Files to Create
```
upload/
├── admin/
│   ├── controller/
│   │   └── extension/
│   │       └── sync.php              ← Main controller
│   └── view/
│       └── template/
│           └── extension/
│               ├── sync_dashboard.twig
│               ├── sync_preview.twig
│               └── sync_history.twig
├── catalog/
│   └── controller/
│       └── extension/
│           └── sync.php              ← Public API endpoints for AJAX
```

## Data Flow
```
User clicks "Import" on preview page
        ↓
JS POST to /shopadmin/route=extension/sync/import
        ↓
PHP controller runs syncListing() from OpenCartSyncEngine
        ↓
AJAX polls /route=extension/sync/import_status every 2s
        ↓
Progress bar updates (25%, 50%, 75%, 100%)
        ↓
Redirect to history page on completion
```

## Database Tables (new)
```sql
CREATE TABLE oc_sync_log (
    sync_log_id INT AUTO_INCREMENT PRIMARY KEY,
    marketplace VARCHAR(50) NOT NULL DEFAULT 'ebay',
    listing_id VARCHAR(255) NOT NULL,
    product_id INT,
    action VARCHAR(20) NOT NULL,  -- created/updated/skipped/error
    message TEXT,
    synced_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

## Route: OpenCart Admin Integration
- Uses OpenCart's extension system
- Controllers extend `Controller` base class
- Views use Twig templates with OC4 admin theme
- Accessible under **Extensions > Marketplace Sync**

## Development Steps
1. Create controller file
2. Create Twig templates  
3. Create sync_log table
4. Wire up AJAX endpoints
5. Test in admin panel
6. Claude Code security review
