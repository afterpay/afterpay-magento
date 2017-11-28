<?php
/**
 * @package   Afterpay_Afterpay
 * @author    Afterpay <steven.gunarso@touchcorp.com>
 * @copyright Copyright (c) 2016 Afterpay (http://www.afterpay.com.au)
 */
$installer = $this;
/**
 * Deleting app/code/community/Afterpay/Afterpay/sql/afterpay_setup/install-0.13.0.php
 * It has same code as this file
 * but it causes other setup script files not to run (because its an install of current version)
 *
 */
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