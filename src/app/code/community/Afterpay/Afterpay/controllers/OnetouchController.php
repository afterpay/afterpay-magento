<?php

class Afterpay_Afterpay_OnetouchController extends Mage_Core_Controller_Front_Action
{
    /**
     * Afterpay Onetouch action
     *
     * Set the default payment method for the order to be Afterpay, then load the checkout.
     */
    public function indexAction()
    {
        $quote = Mage::getSingleton('checkout/session')->getQuote();

        $quote->getPayment()->setMethod('afterpaypayovertime')
            ->save();

        $helper = Mage::helper('checkout/url');
        $this->_redirectUrl($helper->getCheckoutUrl());
    }
}