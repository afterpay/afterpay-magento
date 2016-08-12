<?php

class Afterpay_Afterpay_Block_Config extends Mage_Core_Block_Template
{
    /**
     * Get the payment mode
     *
     * @return mixed (redirect | lightbox)
     */
    public function getMode()
    {
        return Mage::getStoreConfig('afterpay/payovertime_checkout/checkout_mode');
    }

    /**
     * Get the payment action
     *
     * @return mixed (order | authorize_capture)
     */
    public function getPaymentAction()
    {
        return Mage::getStoreConfig('payment/afterpaypayovertime/payment_action');
    }
}