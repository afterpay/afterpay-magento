<?php

/**
 * @package   Afterpay_Afterpay
 * @author    VEN Development Team <info@ven.com>
 * @copyright Copyright (c) 2014 VEN Commerce Ltd (http://www.ven.com)
 */

/* @var $installer Mage_Sales_Model_Resource_Setup */
$installer = $this;

$installer->startSetup();


$table = $installer->getTable('sales_flat_order_payment');
$installer->getConnection()->addColumn($table, "afterpay_order_id", "varchar(255) DEFAULT NULL COMMENT 'Afterpay Order ID'");
$installer->getConnection()->dropIndex($table, "IDX_SALES_FLAT_ORDER_PAYMENT_AFTERPAY_TOKEN");


$installer->endSetup();
