<?php
/**
 * @package   Afterpay_Afterpay
 * @author    Afterpay
 * @copyright 2016-2018 Afterpay https://www.afterpay.com
 */

/* @var $installer Mage_Sales_Model_Resource_Setup */
$installer = $this;

$installer->startSetup();


$table = $installer->getTable('sales_flat_order_payment');
$installer->getConnection()->addColumn($table, "afterpay_order_id", "varchar(255) DEFAULT NULL COMMENT 'Afterpay Order ID'");
$installer->getConnection()->dropIndex($table, "IDX_SALES_FLAT_ORDER_PAYMENT_AFTERPAY_TOKEN");


$installer->endSetup();
