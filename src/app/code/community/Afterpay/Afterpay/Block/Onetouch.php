<?php

use Afterpay_Afterpay_Model_Method_Base as Afterpay_Base;
use Afterpay_Afterpay_Model_System_Config_Source_CartMode as CartMode;
use Afterpay_Afterpay_Model_System_Config_Source_ApiMode as ApiMode;

class Afterpay_Afterpay_Block_Onetouch extends Mage_Core_Block_Template
{
    /**
     * Render the block
     *
     * @return string
     */
    protected function _toHtml()
    {
        if ( Mage::getStoreConfig('payment/afterpaypayovertime/' . Afterpay_Base::API_ENABLED_FIELD) &&
            Mage::getStoreConfig('afterpay/payovertime_cart/show_onetouch') != CartMode::NO &&
            Mage::helper('afterpay/checkout')->noConflict() &&
            Mage::getModel('afterpay/method_payovertime')->canUseForCheckoutSession() &&
            $this->isQuoteWithinLimits()
        ) {
            return parent::_toHtml();
        } else {
            return '';
        }
    }

    /**
     * Calculate how much each instalment will cost
     *
     * @return string
     */
    public function getInstalmentAmount()
    {
        return Mage::helper('afterpay')->calculateInstalment();
    }

    /**
     * Calculate the final value of the transaction
     *
     * @return string
     */
    public function getTotalAmount()
    {
        return Mage::helper('afterpay')->calculateTotal();
    }

    public function isQuoteWithinLimits()
    {
        $total = $this->getTotalAmount();
        $min = Mage::getStoreConfig('payment/afterpaypayovertime/' . Afterpay_Base::API_MIN_ORDER_TOTAL_FIELD);
        $max = Mage::getStoreConfig('payment/afterpaypayovertime/' . Afterpay_Base::API_MAX_ORDER_TOTAL_FIELD);

        return ($total > 0 && $total >= $min && $total <= $max);
    }

    public function isExpress()
    {
        return Mage::getStoreConfig('afterpay/payovertime_cart/show_onetouch') == CartMode::EXPRESS;
    }

    public function isShippingRequired()
    {
        $quote = Mage::getSingleton('checkout/session')->getQuote();
        return !$quote->isVirtual();
    }

    public function getCountryCode()
    {
        $countryCode = '';
        $currency = ApiMode::getCurrencyCode();

        if (array_key_exists($currency, Afterpay_Base::CURRENCY_PROPERTIES)){
            $countryCode = Afterpay_Base::CURRENCY_PROPERTIES[$currency]['jsCountry'];
        }

        return $countryCode;
    }

    public function getJsUrl()
    {
        $apiMode = Mage::getStoreConfig('payment/afterpaypayovertime/' . Afterpay_Base::API_MODE_CONFIG_FIELD);
        $settings = ApiMode::getEnvironmentSettings($apiMode);
        $key = urlencode(Mage::getStoreConfig('afterpay/payovertime_cart/express_key'));

        return $settings[ApiMode::KEY_WEB_URL] . 'afterpay.js?merchant_key=' . $key;
    }
}
