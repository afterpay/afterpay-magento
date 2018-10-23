<?php

/**
 * @package   Afterpay_Afterpay
 * @author    Afterpay
 * @copyright 2016-2018 Afterpay https://www.afterpay.com
 */

/* @var $installer Mage_Sales_Model_Resource_Setup */
$installer = $this;

$installer->startSetup();

//--------------------------------------------------
/*
    # add custom columns
    ALTER TABLE sales_flat_order_payment
        ADD COLUMN afterpay_token varchar(255) DEFAULT NULL COMMENT 'Afterpay Order Token',
        ADD COLUMN afterpay_order_id varchar(255) DEFAULT NULL COMMENT 'Afterpay Order ID',
        ADD COLUMN afterpay_fetched_at TIMESTAMP NULL;

    # add custom order status
    INSERT INTO sales_order_status (`status`, `label`) VALUES ('afterpay_payment_review', 'Afterpay Processing');
    INSERT INTO sales_order_status_state (`status`, `state`, `is_default`) VALUES ('afterpay_payment_review', 'payment_review', '0');
*/
//--------------------------------------------------

	// add columns to sales/order_payment
	$table = $installer->getTable('sales_flat_order_payment');
	$installer->getConnection()->addColumn($table, "afterpay_token", "varchar(255) DEFAULT NULL COMMENT 'Afterpay Order Token'");
	$installer->getConnection()->addColumn($table, "afterpay_order_id", "varchar(255) DEFAULT NULL COMMENT 'Afterpay Order ID'");
	$installer->getConnection()->addColumn($table, "afterpay_fetched_at", "TIMESTAMP NULL");

	// add new status and map it to Payment Review state
	$status = 'afterpay_payment_review';
	$state  = Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW;
	$installer->run("INSERT INTO `{$this->getTable('sales_order_status')}` (`status`, `label`) VALUES ('{$status}', 'Afterpay Processing');");
	$installer->run("INSERT INTO `{$this->getTable('sales_order_status_state')}` (`status`, `state`, `is_default`) VALUES ('{$status}', '{$state}', '0');");
//--------------------------------------------------

$installer->endSetup();
