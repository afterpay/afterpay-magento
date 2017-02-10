<?php
/**
 * @package   Afterpay_Afterpay
 * @author    Afterpay <steven.gunarso@touchcorp.com>
 * @copyright Copyright (c) 2016 Afterpay (http://www.afterpay.com.au)
 */
$installer = $this;

$installer->startSetup();

/**
 * Setup script to create new column on sales_flat_quote_payment for:
 * - afterpay_token
 * - afterpay_order_id
 */

    // add columns to sales/order_payment
    $table = $installer->getTable('sales_flat_order_payment');
    $installer->getConnection()->addColumn($table, "afterpay_token", "varchar(255) DEFAULT NULL COMMENT 'Afterpay Order Token'");
    $installer->getConnection()->addColumn($table, "afterpay_order_id", "varchar(255) DEFAULT NULL COMMENT 'Afterpay Order ID'");
    $installer->getConnection()->addColumn($table, "afterpay_fetched_at", "TIMESTAMP NULL");

    //create shipped API queue table
    $installer->run("
        CREATE TABLE `{$installer->getTable('afterpay/afterpay_shipped_api_queue')}` (
          `shipped_api_queue_id` INT(11) NOT NULL auto_increment,
          `payment_id` INT(11) NOT NULL,
          `tracking_number` VARCHAR(255),
          `courier` VARCHAR(255),
          `errors_count` INT(11),
          PRIMARY KEY  (`shipped_api_queue_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
    ");

    // add new status and map it to Payment Review state
    $status = 'afterpay_payment_review';
    $state  = Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW;
    $installer->run("INSERT INTO `{$this->getTable('sales_order_status')}` (`status`, `label`) VALUES ('{$status}', 'Afterpay Processing');");
    $installer->run("INSERT INTO `{$this->getTable('sales_order_status_state')}` (`status`, `state`, `is_default`) VALUES ('{$status}', '{$state}', '0');");

	$table = $installer->getTable('sales/quote_payment');
	$installer->getConnection()->addColumn($table, 'afterpay_token', "varchar(255) DEFAULT NULL COMMENT 'Afterpay Order Token'");
	$installer->getConnection()->addColumn($table, 'afterpay_order_id', "varchar(255) DEFAULT NULL COMMENT 'Afterpay Order ID'");	

$installer->endSetup();

?>
