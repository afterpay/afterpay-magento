<?php

/**
 * Afterpay payment redirect block
 *
 * @package   Afterpay_Afterpay
 * @author    Afterpay <steven.gunarso@touchcorp.com>
 * @copyright Copyright (c) 2016 Afterpay (http://www.afterpay.com.au/)
 */

/**
 * Class Afterpay_Afterpay_Block_Form_Abstract
 *
 * @method Afterpay_Afterpay_Block_Redirect setOrderToken(string $token);
 * @method string getOrderToken();
 * @method Afterpay_Afterpay_Block_Redirect setReturnUrl(string $url);
 * @method Afterpay_Afterpay_Block_Redirect setRedirectJsUrl(string $url)
 */
class Afterpay_Afterpay_Block_Redirect extends Mage_Core_Block_Template
{
    protected function _construct()
    {
        parent::_construct();

        $this->setTemplate('afterpay/redirect.phtml');
    }

    /**
     * @return string
     */
    public function getCancelOrderUrl()
    {
        return $this->getUrl('afterpay/payment/cancel', array('_secure' => true));
    }

    /**
     * Get the return URL of the Afterpay, will return false if using API V1
     * @return string | null
     */
    public function getReturnUrl()
    {
        $result = (string)$this->getData('return_url');

        if (!$result) {
            $urlString = $this->getUrl('afterpay/payment/return', array('_secure' => true));
            $parsedUrl = Mage::getModel('core/url')->parseUrl($urlString);
            $result    = $parsedUrl->getData('path');

            if ($parsedUrl->getData('query')) {
                $result .= '?' . $parsedUrl->getData('query');
            }
        }

        if( Mage::getModel('afterpay/method_payovertime')->getConfigPaymentAction() 
            == Afterpay_Afterpay_Model_Method_Payovertime::ACTION_AUTHORIZE_CAPTURE ) {

            return false;
        }
        else {
            return $result;
        }
    }

    /**
     * @return string
     */
    public function getRedirectJsUrl()
    {
        $apiMode      = Mage::getStoreConfig('payment/afterpaypayovertime/' . Afterpay_Afterpay_Model_Method_Base::API_MODE_CONFIG_FIELD);
        $settings     = Afterpay_Afterpay_Model_System_Config_Source_ApiMode::getEnvironmentSettings($apiMode);

        return $settings[Afterpay_Afterpay_Model_System_Config_Source_ApiMode::KEY_WEB_URL] . 'afterpay.js';
    }
}
