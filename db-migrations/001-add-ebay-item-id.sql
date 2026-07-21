-- ===================================================================
-- Migration 001: Add ebay_item_id column and sold_events idempotency table
-- Run this once on the geek_shop database
-- ===================================================================

-- 1. Add ebay_item_id column to oc_product (nullable, after sku)
ALTER TABLE oc_product
  ADD COLUMN ebay_item_id VARCHAR(64) NULL DEFAULT NULL AFTER sku,
  ADD INDEX idx_ebay_item_id (ebay_item_id);

-- 2. Migrate existing eBay IDs from sku into ebay_item_id
--    Current format: "v1|335678901234|0" or bare numeric
UPDATE oc_product
SET ebay_item_id = sku
WHERE sku IS NOT NULL AND sku != ''
  AND (
    sku LIKE 'v1|%|0'
    OR sku REGEXP '^[0-9]{10,13}$'
  );

-- 3. Clear sku where we migrated (the sku was never a real merchant SKU)
UPDATE oc_product
SET sku = ''
WHERE ebay_item_id IS NOT NULL
  AND (sku LIKE 'v1|%|0' OR sku REGEXP '^[0-9]{10,13}$');

-- 4. Idempotency ledger for eBay sold events (via n8n email webhook)
CREATE TABLE IF NOT EXISTS oc_ebay_sold_events (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    email_message_id VARCHAR(255) NOT NULL COMMENT 'MIME Message-ID — unique per email, idempotency key',
    ebay_order_id    VARCHAR(64)  NOT NULL DEFAULT '' COMMENT 'eBay order number (25-12345-67890)',
    ebay_item_id     VARCHAR(64)  NOT NULL COMMENT 'eBay item ID (numeric part)',
    product_id       INT          NOT NULL COMMENT 'OpenCart product_id that was decremented',
    qty_sold         INT          NOT NULL DEFAULT 1,
    new_quantity     INT          NOT NULL DEFAULT 0 COMMENT 'Quantity after decrement',
    processed_at     DATETIME     NOT NULL,
    UNIQUE KEY uk_email_msg (email_message_id(128)),
    INDEX idx_ebay_item (ebay_item_id),
    INDEX idx_processed (processed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
