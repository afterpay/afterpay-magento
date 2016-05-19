# add custom columns
ALTER TABLE sales_flat_order_payment
    ADD COLUMN afterpay_token varchar(255) DEFAULT NULL COMMENT 'Afterpay Order Token',
    ADD COLUMN afterpay_order_id varchar(255) DEFAULT NULL COMMENT 'Afterpay Order ID',
    ADD COLUMN afterpay_fetched_at TIMESTAMP NULL;

# add custom order status
INSERT INTO sales_order_status (`status`, `label`) VALUES ('afterpay_payment_review', 'Afterpay Processing');
INSERT INTO sales_order_status_state (`status`, `state`, `is_default`) VALUES ('afterpay_payment_review', 'payment_review', '0');