<?php
/**
 * @package   Afterpay_Afterpay
 * @author    Afterpay
 * @copyright 2016-2018 Afterpay https://www.afterpay.com
 */

/* @var $installer Mage_Sales_Model_Resource_Setup */

$installer = $this;

$installer->startSetup();
$installer->run("
    ALTER TABLE `{$installer->getTable('sales_flat_order_payment')}`
    DROP COLUMN afterpay_token,
    DROP COLUMN afterpay_order_id,
    DROP COLUMN afterpay_fetched_at;
");

$installer->run("
    DELETE FROM `{$installer->getTable('sales_order_status_state')}` WHERE status='afterpay_payment_review';
");

$installer->run("
    DELETE FROM `{$installer->getTable('sales_order_status')}` WHERE status='afterpay_payment_review';
");

$installer->run("
    DROP TABLE IF EXISTS `{$installer->getTable('afterpay_shipped_api_queue')}`;
");

$installer->run("
    DELETE FROM `{$installer->getTable('core_resource')}` WHERE code='afterpay_setup';
");

$installer->endSetup();
?>