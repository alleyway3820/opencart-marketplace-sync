# Two-Way eBay ↔ OpenCart Sync — Deploy Guide

## Overview

Two independent paths that keep stock in sync:

```
Direction A: eBay sells → OC stock decremented
  eBay email → n8n IMAP poll → PHP webhook → oc_product.quantity -= 1

Direction B: OC sells → eBay listing ended
  OC order status → OC event hook → eBay Trading API (EndItem)
```

---

## Direction A: eBay Sells → OC Stock Decremented

### Step 1: Run the database migration

Connect to your MySQL server (likely via SSH to 198.100.150.120) and run:

```sql
-- /root/opencart-marketplace-sync/db-migrations/001-add-ebay-item-id.sql
-- Adds ebay_item_id column + sold_events idempotency table + migrates existing data
```

This:
- Adds `ebay_item_id VARCHAR(64) NULL` to `oc_product`
- Creates `oc_ebay_sold_events` (idempotency ledger)
- Migrates existing eBay IDs from `sku` → `ebay_item_id`
- Creates an index on the new column

### Step 2: Generate a webhook secret

```bash
php -r "echo bin2hex(random_bytes(32));"
# Example output: a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2
```

Edit `/root/opencart-marketplace-sync/config.php` and set `webhook_secret` to this value.

### Step 3: Deploy the webhook endpoint

Copy `/root/opencart-marketplace-sync/webhook/ebay-sold.php` to the web server:

```
scp /root/opencart-marketplace-sync/webhook/ebay-sold.php \
    user@198.100.150.120:/home/geekygoodygoods.com/public_html/webhook/ebay-sold.php
```

Create the `webhook/` directory on the server first if needed.

Test it:
```bash
curl -X POST https://geekygoodygoods.com/webhook/ebay-sold.php \
  -H "Content-Type: application/json" \
  -H "X-Webhook-Secret: YOUR_SECRET_HERE" \
  -d '{"email_message_id":"test123","ebay_item_id":"335678901234","qty_sold":1}'
```

Expected response (if item doesn't exist):
```json
{"error":"Product not found","ebay_item_id":"335678901234","tried":["335678901234","v1|335678901234|0"]}
```

### Step 4: Set up the n8n workflow

1. Open your n8n dashboard
2. **Import** `/root/opencart-marketplace-sync/n8n-workflow/ebay-sold-webhook.json`
3. **Configure Credentials:**
   - **IMAP node**: your Gmail credentials for `tonyc@ageek4less.com`
   - **HTTP Request node**: create an "Header Auth" credential with:
     - Name: `webhook-secret`
     - Header Name: `X-Webhook-Secret`
     - Header Value: (the secret you generated in Step 2)
4. **Activate** the workflow

The workflow:
- Polls Gmail every 5 minutes
- Filters for emails from `@ebay.com` with "You sold an item" in subject
- Extracts the item ID via regex
- POSTs to your OC webhook
- Logs the result

---

## Direction B: OC Sells → eBay Listing Ended

### Step 5: Re-authorize eBay OAuth for sell scopes

Your current eBay token only has browse (read) scope. You need `sell.item` scope to end listings.

```bash
cd /root/opencart-marketplace-sync

# Step 1: Generate the authorization URL
php scripts/re-auth-ebay-oauth.php --url
```

This prints a URL. Visit it in your browser, log into eBay, and grant the new permission. After granting, you'll be redirected. Copy the `?code=` parameter from the URL bar.

```bash
# Step 2: Exchange code for new tokens
php scripts/re-auth-ebay-oauth.php --code "THE_CODE_FROM_THE_URL"
```

This updates `config.php` with the new access_token and refresh_token that include sell scope.

### Step 6: Test the Trading API call

```bash
php scripts/remove-ebay-listing.php <product_id>
```

Replace `<product_id>` with the ID of an eBay-synced product. It should:
- Confirm the product has ebay_item_id
- Call eBay Trading API EndItem
- Print success/error

⚠️ **This will end the listing on eBay.** Test on a cheap or test listing first.

### Step 7: Set up the event listener

Copy the event controller to your OC server:

```
scp /root/opencart-marketplace-sync/upload/catalog/controller/event/ebay_sync.php \
    user@198.100.150.120:/home/geekygoodygoods.com/public_html/catalog/controller/event/ebay_sync.php
```

Then register the event in MySQL:

```sql
INSERT INTO oc_event (code, trigger, action, `status`, sort_order)
VALUES ('ebay_sync_order_history',
        'catalog/model/checkout/order/addHistory/after',
        'event/ebay_sync/orderHistory',
        1, 0);
```

This activates the listener. Now when an order status changes to Processing (2) or Complete (5), it will:
1. Look up order products
2. Check each product's `ebay_item_id`
3. Call eBay API to end any matching listings

### Step 8: Test end-to-end

1. List an item on eBay
2. Sync it to OC (`php sync.php sync:all`)
3. Verify `ebay_item_id` is populated in `oc_product`
4. Place a test order on OC for that product
5. Check OC event log for: `EbaySync: Ended eBay listing XXXXXXXXXX`
6. Verify eBay listing is ended

---

## Data Flow Diagram

```
eBay                     n8n                    OpenCart              eBay API
 │                       │                       │                     │
 ├─ email (sold) ──────► │                       │                     │
 │                       ├─ filter + parse       │                     │
 │                       ├─ POST ──────────────► │                     │
 │                       │                       ├─ decrement stock    │
 │                       │                       └─ "ok" ───────────►  │
 │                       │                       │                     │
 │                       │                       │ (order status=2)    │
 │                       │                       ├─ event hook fires   │
 │                       │                       ├─ lookup ebay_item_id│
 │                       │                       ├─ EndItem ─────────► │
 │                       │                       │                     ├─ listing ended
 │                       │                       │                     │
```

---

## Monitoring

Check processed events:
```sql
SELECT * FROM oc_ebay_sold_events ORDER BY id DESC LIMIT 20;
```

Check OC error log for `EbaySync:` entries:
```bash
tail -f /home/geekygoodygoods.com/public_html/storage/logs/error.log | grep EbaySync
```

---

## Rollback Plan

If something goes wrong:

1. **Disable the event listener:**
   ```sql
   UPDATE oc_event SET status = 0 WHERE code LIKE 'ebay_sync_%';
   ```

2. **Disable the n8n workflow:** Deactivate it in the n8n dashboard

3. **Restore eBay listing:** If the API incorrectly ended it, you can relist manually from eBay
