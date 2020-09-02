<?php

/**
 * @package   Afterpay_Afterpay
 * @author    Afterpay
 * @copyright 2016-2018 Afterpay https://www.afterpay.com
 */

/**
 * Class Afterpay_Afterpay_Model_Api_Routers_Routerv1
 *
 * Building API URL.
 */

use Afterpay_Afterpay_Model_Method_Base as Afterpay_Base;

class Afterpay_Afterpay_Model_Api_Routers_Routerv1
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
     * @param int|null $store_id Which store to get the config from
     * @return string
     */
    public function getPaymentUrl($method, $store_id = null)
    {
        $apiMode      = Mage::getStoreConfig('payment/' . $method . '/' . Afterpay_Base::API_MODE_CONFIG_FIELD, $store_id);
        $settings     = Afterpay_Afterpay_Model_System_Config_Source_ApiMode::getEnvironmentSettings($apiMode);

        //make sure we are using the same version of API for consistency purpose
        return $settings[Afterpay_Afterpay_Model_System_Config_Source_ApiMode::KEY_API_URL] . 'v1/configuration';
    }


    /**
     * Function for gateway URL doing Get Payment Update Version 1
     *
     * @return string|null
     */
    public function getOrdersApiUrl( $search_target = NULL, $type = NULL )
    {
        $apiMode      = Mage::getStoreConfig('payment/afterpaypayovertime/' . Afterpay_Base::API_MODE_CONFIG_FIELD);
        $settings     = Afterpay_Afterpay_Model_System_Config_Source_ApiMode::getEnvironmentSettings($apiMode);

        //make sure we are using the same version of API for consistency purpose
        $gatewayUrl = $settings[Afterpay_Afterpay_Model_System_Config_Source_ApiMode::KEY_API_URL] . 'v1/payments/';

        if( !empty($type) && $type == $search_target ) {
            $url = (substr($gatewayUrl, -1) == '/' ? $gatewayUrl : $gatewayUrl . '/') . 'token:' . urlencode($search_target);
        }
        else if( !empty($type) && $type == 'id') {
            $url = (substr($gatewayUrl, -1) == '/' ? $gatewayUrl : $gatewayUrl . '/') . $search_target;
        }
        else if( !empty($type) && $type == 'courier') {
            $url = (substr($gatewayUrl, -1) == '/' ? $gatewayUrl : $gatewayUrl . '/') . $search_target . "/courier/";
        }
        else if( !empty($type) && $type == 'token' ) {
            $url = $settings[Afterpay_Afterpay_Model_System_Config_Source_ApiMode::KEY_API_URL] . 'v1/orders/' . $search_target;
        }
        else {
            $url = $settings[Afterpay_Afterpay_Model_System_Config_Source_ApiMode::KEY_API_URL] . 'v1/orders/';
        }

        return $url;
    }

    /**
     * Get configured gateway URL for payment method
     *
     * @return string
     */
    public function getRefundUrl($payment)
    {
        $store_id = $payment->getOrder()->getStoreId();
        $order_id = $payment->getData('afterpay_order_id');

        $apiMode      = Mage::getStoreConfig('payment/afterpaypayovertime/' . Afterpay_Base::API_MODE_CONFIG_FIELD, $store_id);
        $settings     = Afterpay_Afterpay_Model_System_Config_Source_ApiMode::getEnvironmentSettings($apiMode);

        //make sure we are using the same version of API for consistency purpose
        return $settings[Afterpay_Afterpay_Model_System_Config_Source_ApiMode::KEY_API_URL] . 'v1/payments/' . $order_id . '/refund';
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
        $apiMode      = Mage::getStoreConfig('payment/afterpaypayovertime/' . Afterpay_Base::API_MODE_CONFIG_FIELD);
        $settings     = Afterpay_Afterpay_Model_System_Config_Source_ApiMode::getEnvironmentSettings($apiMode);

        return $settings[Afterpay_Afterpay_Model_System_Config_Source_ApiMode::KEY_WEB_URL] . 'afterpay.js';
    }


    /* ONLY ON VER 1 */
    /**
     * Get configured gateway URL for API Ver 1 Direct Capture
     *
     * @return string|null
     */
    public function getDirectCaptureApiUrl()
    {
        $apiMode      = Mage::getStoreConfig('payment/afterpaypayovertime/' . Afterpay_Base::API_MODE_CONFIG_FIELD);
        $settings     = Afterpay_Afterpay_Model_System_Config_Source_ApiMode::getEnvironmentSettings($apiMode);

        return $settings[Afterpay_Afterpay_Model_System_Config_Source_ApiMode::KEY_API_URL] . 'v1/payments/capture/';
    }


    /**
     * Function for gateway URL doing Get Payment Update Version 1
     *
     * @return string|null
     */
    public function getGatewayApiUrl( $token = NULL )
    {
        $apiMode      = Mage::getStoreConfig('payment/afterpaypayovertime/' . Afterpay_Base::API_MODE_CONFIG_FIELD);
        $settings     = Afterpay_Afterpay_Model_System_Config_Source_ApiMode::getEnvironmentSettings($apiMode);
        $currency = Mage::app()->getStore()->getCurrentCurrencyCode();

        $countryCode = 'au';
        if (array_key_exists($currency, Afterpay_Base::CURRENCY_PROPERTIES)){
            $countryCode = Afterpay_Base::CURRENCY_PROPERTIES[$currency]['webCountry'];
        }

        //make sure we are using the same version of API for consistency purpose
        $gatewayUrl = $settings[Afterpay_Afterpay_Model_System_Config_Source_ApiMode::KEY_WEB_URL] . $countryCode . '/checkout';

        if( !empty($token) ) {
            $url = (substr($gatewayUrl, -1) == '/' ? $gatewayUrl : $gatewayUrl . '/') . '?token=' . urlencode($token) . '&redirected=1&relativeCallbackUrl=';
        }

        return $url;
    }
}
