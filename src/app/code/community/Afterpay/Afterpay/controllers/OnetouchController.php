<?php
/**
 * @package   Afterpay_Afterpay
 * @author    Afterpay
 * @copyright 2016-2018 Afterpay https://www.afterpay.com
 */

/**
 * Class Afterpay_Afterpay_OnetouchController
 *
 * Set the default payment method for the order to be Afterpay, then load the checkout.
*/
class Afterpay_Afterpay_OnetouchController extends Mage_Core_Controller_Front_Action
{
    public function indexAction()
    {
        $quote = Mage::getSingleton('checkout/session')->getQuote();

        $quote->getPayment()->setMethod('afterpaypayovertime')
            ->save();

        $helper = Mage::helper('checkout/url');
        $this->_redirectUrl($helper->getCheckoutUrl());
    }
}