<?php
/**
 * @package   Afterpay_Afterpay
 * @author    Afterpay
 * @copyright 2016-2018 Afterpay https://www.afterpay.com
 */
$installer = $this;

$installer->startSetup();

/**
 * Setup script to create new column on sales_flat_quote_payment for:
 * - afterpay_token
 * - afterpay_order_id
 */

	$table = $installer->getTable('sales/quote_payment');
	$installer->getConnection()->addColumn($table, 'afterpay_token', "varchar(255) DEFAULT NULL COMMENT 'Afterpay Order Token'");
	$installer->getConnection()->addColumn($table, 'afterpay_order_id', "varchar(255) DEFAULT NULL COMMENT 'Afterpay Order ID'");	

$installer->endSetup();
?>