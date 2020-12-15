<?php
/**
 * @package   Afterpay_Afterpay
 * @author    Afterpay
 * @copyright 2016-2018 Afterpay https://www.afterpay.com
 */

/**
 * Class Afterpay_Afterpay_Helper_Checkout
 *
 * Within this class, we are quite intentionally not using class constants for a number of the system configuration
 * settings. This is done as mostly these classes won't exist, as the checkouts won't be installed.
 *
 * Additionally, this class extends Mage_Checkout_Helper_Url, to give easy access to the default getCheckoutUrl() method
 * in that class.
 */
class Afterpay_Afterpay_Helper_Checkout extends Mage_Checkout_Helper_Url
{
    /**
     * Determine if this instance of Magento is using an unsupported checkout extension
     *
     * @return bool
     */
    public function isUsingUnsupportedCheckout()
    {
        // is it one of our properly supported extensions?
        if ($this->isAheadworksCheckout()) {
            return false;
        }

        if ($this->isIwdCheckout()) {
            return false;
        }

        if ($this->isTmFireCheckout()) {
            return false;
        }

        // has an extension we don't recognise changed the checkout URL?
        if ($this->isDifferentUrl()) {
            return true;
        }

        // has an extension rewritten the onepage checkout controller
        if ($this->defaultUrlExtended()) {
            return true;
        }

        // probably we are using the default checkout
        return false;
    }

    /**
     * Determine if the current site checkout URL is different to the default
     *
     * This is achieved by comparing the result of Mage_Checkout_Helper_Url::getCheckoutUrl() with the result of the
     * checkout/url helper's same method. In default Magento, these will be the same method and so will return the same
     * URL. However, almost all checkout extensions that use a custom URL rewrite this class to themselves and change
     * the return value of this method.
     *
     * This class extends Mage_Checkout_Helper_Url specifically so that it can have easy access to this default method.
     *
     * @return bool
     */
    public function isDifferentUrl()
    {
        $defaultUrl = $this->getCheckoutUrl();
        $actualUrl  = Mage::helper('checkout/url')->getCheckoutUrl();

        return $defaultUrl != $actualUrl;
    }

    /**
     * Determine if we are using the Aheadworks one step checkout.
     *
     * This checks that the global setting added by the extension is enabled and set to true
     *
     * @return bool
     */
    public function isAheadworksCheckout()
    {
        return Mage::helper('core')->isModuleEnabled('AW_Onestepcheckout');
    }

    /**
     * Determine if we are using the IWD one step checkout
     *
     * This checks the global IWD checkout setting
     *
     * @return bool
     */
    public function isIwdCheckout()
    {
        return Mage::helper('core')->isModuleEnabled('IWD_Opc');
    }

    /**
     * Determine if we are using the TM FireCheckout
     *
     * This checks the global TM Firecheckout setting
     *
     * @return bool
     */
    public function isTmFireCheckout()
    {
        return Mage::helper('core')->isModuleEnabled('TM_FireCheckout');
    }

    /**
     * Determine if anything is rewriting the default onepage controller
     *
     * This is not a foolproof way of determining that we are using a custom checkout, on the default URL, but it's
     * a reasonably accurate one.
     *
     * @return bool
     */
    public function defaultUrlExtended()
    {
        $config = Mage::getConfig()->getNode('frontend/routers/checkout/args/modules');

        if ($config) {
            foreach ($config->children() as $override) {
                if ($override->getAttribute('before') == 'Mage_Checkout_OnepageController') {
                    return true;
                }
            }
        }

        return false;
    }

    public function noConflict()
    {
        $currency = Mage::app()->getStore()->getCurrentCurrencyCode();
        $base = Mage::app()->getStore()->getBaseCurrencyCode();
        return $currency == $base && in_array($currency, Afterpay_Afterpay_Model_Method_Base::SUPPORTED_CURRENCIES);
    }
}
