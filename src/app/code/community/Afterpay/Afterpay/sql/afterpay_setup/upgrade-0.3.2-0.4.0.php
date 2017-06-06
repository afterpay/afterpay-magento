<?php
/**
 * @package   Afterpay_Afterpay
 * @author    Afterpay <steven.gunarso@touchcorp.com>
 * @copyright Copyright (c) 2016 Afterpay (http://www.afterpay.com.au)
 */

/* @var $installer Mage_Sales_Model_Resource_Setup */
$installer = $this;

$installer->startSetup();


$status = 'afterpay_payment_review';
$state  = Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW;

$installer->run("INSERT INTO `{$this->getTable('sales_order_status')}` (`status`, `label`) VALUES ('{$status}', 'Afterpay Processing');");
$installer->run("INSERT INTO `{$this->getTable('sales/order_status_state')}` (`status`, `state`, `is_default`) VALUES ('{$status}', '{$state}', '0');");


$installer->endSetup();
