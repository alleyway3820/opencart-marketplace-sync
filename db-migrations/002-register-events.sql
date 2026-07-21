-- ===================================================================
-- Event registration for OC→eBay sync
-- Run this on geek_shop after copying upload/catalog/controller/event/ebay_sync.php
-- to <oc_root>/catalog/controller/event/ebay_sync.php
-- ===================================================================

INSERT INTO oc_event (code, trigger, action, `status`, sort_order)
VALUES ('ebay_sync_order_history',
        'catalog/model/checkout/order/addHistory/after',
        'event/ebay_sync/orderHistory',
        1, 0);

-- Alternative: also hook admin-side order status changes
INSERT INTO oc_event (code, trigger, action, `status`, sort_order)
VALUES ('ebay_sync_order_history_admin',
        'admin/model/sale/order/addHistory/after',
        'event/ebay_sync/orderHistory',
        1, 0);

-- To remove:
-- DELETE FROM oc_event WHERE code LIKE 'ebay_sync_%';
