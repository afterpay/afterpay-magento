<?php
/**
 * @package   Afterpay_Afterpay
 * @author    Afterpay
 * @copyright 2016-2018 Afterpay https://www.afterpay.com
 */

/* @var $installer Mage_Sales_Model_Resource_Setup */

$installer = $this;

$installer->startSetup();

$blockData = array(
    'identifier' => 'afterpay-order-declined',
    'title'      => 'Afterpay Order Declined',
    'content'    => '<p>Your order was not approved on this occasion due to an issue with your card.</p>
<p>Please contact your card issuer to ensure your card details are valid and the card provides sufficient funds to cover the payment amounts.</p>',
    'is_active'  => 1,
    'stores'     => array(0)
);

/** @var $block Mage_Cms_Model_Block */
$block = Mage::getModel('cms/block');
$block->load($blockData['identifier'], 'identifier');
if (!$block->isObjectNew()) {
    unset($blockData['identifier']);
}
$block->addData($blockData);
$block->save();

$installer->endSetup();
