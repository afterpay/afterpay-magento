<?php

/**
 * @package   Afterpay_Afterpay
 * @author    Aferpay <steven.gunarso@touchcorp.com>
 * @copyright Copyright (c) 2016 Afterpay (http://www.afterpay.com.au)
 */

/**
 * Class Afterpay_Afterpay_Model_Api_Routers_Router
 *
 * Building API URL.
 */
class Afterpay_Afterpay_Model_Api_Routers_Router
{
    /**
     * @return string
     */
    public function getCancelOrderUrl()
    {
        return Mage::getUrl('afterpay/payment/cancel', array('_secure' => true));
    }

    /**
     * Only used for Version 1 of the API
     * @return string
     */
    public function getConfirmOrderUrl()
    {
        return Mage::getUrl('afterpay/payment/return', array('_secure' => true));
    }

    /**
     * Get the URL for valid payment types
     *
     * @param string $method Which payment method to get the URL for
     * @return string
     */
    public function getPaymentUrl($method)
    {
        $apiMode      = Mage::getStoreConfig('payment/' . $method . '/' . Afterpay_Afterpay_Model_Method_Base::API_MODE_CONFIG_FIELD);
        $settings     = Afterpay_Afterpay_Model_System_Config_Source_ApiMode::getEnvironmentSettings($apiMode);

        return $settings[Afterpay_Afterpay_Model_System_Config_Source_ApiMode::KEY_API_URL] . 'merchants/valid-payment-types';
    }

    /**
     * Function for gateway URL for payment method with token
     *
     * @return string|null
     */
    public function getOrdersApiUrl($search_target = NULL, $type = NULL)
    {
        $apiMode      = Mage::getStoreConfig('payment/afterpaypayovertime/' . Afterpay_Afterpay_Model_Method_Base::API_MODE_CONFIG_FIELD);
        $settings     = Afterpay_Afterpay_Model_System_Config_Source_ApiMode::getEnvironmentSettings($apiMode);

        //make sure we are using the same version of API for consistency purpose
        $gatewayUrl = $settings[Afterpay_Afterpay_Model_System_Config_Source_ApiMode::KEY_API_URL] . 'merchants/orders/';   

        if( !empty($type) && $type == 'token' ) {
            $url = (substr($gatewayUrl, -1) == '/' ? $gatewayUrl : $gatewayUrl . '/') . '?token=' . urlencode($search_target);
        }
        else if( !empty($type) && $type == 'id' ) {
            $url = (substr($gatewayUrl, -1) == '/' ? $gatewayUrl : $gatewayUrl . '/') . $search_target;
        }
        else {
            $url = $settings[Afterpay_Afterpay_Model_System_Config_Source_ApiMode::KEY_API_URL] . 'merchants/orders/';
        }
        
        return $url;
    }

    /**
     * Get configured gateway URL for payment method
     *
     * @return string
     */
    public function getRefundUrl($id)
    {
        $apiMode      = Mage::getStoreConfig('payment/afterpaypayovertime/' . Afterpay_Afterpay_Model_Method_Base::API_MODE_CONFIG_FIELD);
        $settings     = Afterpay_Afterpay_Model_System_Config_Source_ApiMode::getEnvironmentSettings($apiMode);

        return $settings[Afterpay_Afterpay_Model_System_Config_Source_ApiMode::KEY_API_URL] . 'merchants/orders/' . $id . '/refunds';
    }

    /**
     * Redirect URL
     *
     * @return string
     */
    public function getOrderPlaceRedirectUrl()
    {
        return Mage::getUrl('afterpay/payment/redirect', array('_secure' => true));
    }

    /**
     * Get configured gateway URL for payment method
     *
     * @return string|null
     */
    public function getWebRedirectJsUrl()
    {
        $apiMode      = Mage::getStoreConfig('payment/afterpaypayovertime/' . Afterpay_Afterpay_Model_Method_Base::API_MODE_CONFIG_FIELD);
        $settings     = Afterpay_Afterpay_Model_System_Config_Source_ApiMode::getEnvironmentSettings($apiMode);

        return $settings[Afterpay_Afterpay_Model_System_Config_Source_ApiMode::KEY_WEB_URL] . 'afterpay.js';
    }
}