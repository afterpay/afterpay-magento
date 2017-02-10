<?php

/**
 * @package   Afterpay_Afterpay
 * @author    Afterpay <steven.gunarso@touchcorp.com>
 * @copyright Copyright (c) 2016 Afterpay (http://www.afterpay.com.au)
 */

/* @var $installer Mage_Sales_Model_Resource_Setup */
$installer = $this;

$installer->startSetup();


$table = $installer->getTable('sales_flat_order_payment');
$installer->getConnection()->addColumn($table, "afterpay_token", "varchar(255) DEFAULT NULL COMMENT 'Afterpay Order Token'");
$installer->getConnection()->addIndex($table, "IDX_SALES_FLAT_ORDER_PAYMENT_AFTERPAY_TOKEN", "afterpay_token", Varien_Db_Adapter_Interface::INDEX_TYPE_UNIQUE);


$installer->endSetup();
