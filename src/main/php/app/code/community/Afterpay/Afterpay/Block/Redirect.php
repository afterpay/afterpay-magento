<?php

/**
 * Afterpay payment redirect block
 *
 * @package   Afterpay_Afterpay
 * @author    VEN Development Team <info@ven.com>
 * @copyright Copyright (c) 2014 VEN Commerce Ltd (http://www.ven.com)
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
     * @return string
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

        return $result;
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
