# drop all created columns
ALTER TABLE sales_flat_order_payment
    DROP COLUMN afterpay_token,
    DROP COLUMN afterpay_order_id,
    DROP COLUMN afterpay_fetched_at;

# delete custom order status
DELETE FROM sales_order_status_state WHERE status='afterpay_payment_review';
DELETE FROM sales_order_status WHERE status='afterpay_payment_review';

# drop created tables
DROP TABLE IF EXISTS afterpay_shipped_api_queue;

# delete record from core_resource
DELETE FROM core_resource WHERE code='afterpay_setup';
