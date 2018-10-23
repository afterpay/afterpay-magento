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
$installer->getConnection()->addColumn($table, "afterpay_fetched_at", "TIMESTAMP NULL");


$installer->endSetup();
