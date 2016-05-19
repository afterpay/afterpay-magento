<?php

class Afterpay_Afterpay_Block_Config extends Mage_Core_Block_Template
{
    public function getMode()
    {
        return Mage::getStoreConfig('afterpay/payovertime_checkout/checkout_mode');
    }
}